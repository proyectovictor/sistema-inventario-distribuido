<?php
/**
 * sync_manager.php
 * Sincroniza datos desde LOCAL hacia servidores principales (A o B)
 * Respeta el IdAlmacen al sincronizar
 */

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/cola_handler.php';

class SyncManager {
    private DatabaseManager $db;
    private ColaHandler $cola;
    
    public function __construct() {
        $this->db = new DatabaseManager();
        $this->cola = new ColaHandler();
    }
    
    public function ejecutarSincronizacion(?string $targetServer = null): array {
        $resultado = [
            'timestamp' => date('Y-m-d H:i:s'),
            'servidor_objetivo' => null,
            'pendientes' => 0,
            'exitosos' => 0,
            'errores' => 0
        ];
        
        // Determinar servidor objetivo
        $servidor = $targetServer ?? $this->detectarServidorPrincipal();
        $resultado['servidor_objetivo'] = $servidor;
        
        if (!$servidor || $servidor === 'local') {
            $resultado['error'] = 'No hay servidor principal disponible';
            return $resultado;
        }
        
        // Verificar que el servidor objetivo está disponible
        if (!$this->db->testConnection($servidor)) {
            $resultado['error'] = "Servidor {$servidor} no disponible";
            return $resultado;
        }
        
        // Obtener pendientes filtrados por servidor de origen
        $pendientes = $this->cola->obtenerPendientesPorOrigen($servidor);
        $resultado['pendientes'] = count($pendientes);
        
        if (empty($pendientes)) {
            return $resultado;
        }
        
        // Conectar al servidor objetivo
        $conn = $this->db->getConnection($servidor);
        if (!$conn) {
            $resultado['error'] = "No se pudo conectar a {$servidor}";
            return $resultado;
        }
        
        // Procesar cada operación
        foreach ($pendientes as $item) {
            $exito = $this->reproducirOperacion($conn, $item);
            
            if ($exito) {
                $this->cola->marcarComoProcesado($item['id']);
                $resultado['exitosos']++;
            } else {
                $this->cola->marcarComoError($item['id'], "Error en sync con {$servidor}");
                $resultado['errores']++;
            }
        }
        
        return $resultado;
    }
    
    private function detectarServidorPrincipal(): ?string {
        if ($this->db->testConnection('A')) return 'A';
        if ($this->db->testConnection('B')) return 'B';
        return null;
    }
    
    private function reproducirOperacion(PDO $conn, array $item): bool {
        $datos = json_decode($item['datos'], true);
        $operacion = $item['operacion'];
        
        // Verificar que el almacén corresponde al servidor
        $idAlmacen = $datos['idalmacen'] ?? null;
        $servidorOrigen = $item['sucursal_origen'];
        
        // Validación: Si el servidor es A, solo debe sincronizar almacén Central (Id=1)
        if ($servidorOrigen === 'A' && $idAlmacen != 1) {
            $this->log("⚠️ Operación {$item['id']}: Servidor A solo acepta almacén Central (Id=1). Actual: {$idAlmacen}");
            return false;
        }
        
        // Validación: Si el servidor es B, solo debe sincronizar almacén Norte (Id=2)
        if ($servidorOrigen === 'B' && $idAlmacen != 2) {
            $this->log("⚠️ Operación {$item['id']}: Servidor B solo acepta almacén Norte (Id=2). Actual: {$idAlmacen}");
            return false;
        }
        
        try {
            if ($operacion === 'entrada') {
                $sql = "SELECT sp_registrar_entrada(:almacen, :usuario, :producto, :cantidad, :lote, :fecha, :ubicacion, :obs) as id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':almacen' => $idAlmacen,
                    ':usuario' => $datos['idusuario'] ?? 1,
                    ':producto' => $datos['idproducto'],
                    ':cantidad' => $datos['cantidad'],
                    ':lote' => $datos['lote'] ?? 'SYNC-' . date('YmdHis'),
                    ':fecha' => $datos['fechaingreso'] ?? date('Y-m-d'),
                    ':ubicacion' => $datos['ubicacion'] ?? 'SYNC',
                    ':obs' => '[SINCRONIZADO] ' . ($datos['observaciones'] ?? '')
                ]);
                return $stmt->fetch() !== false;
                
            } elseif ($operacion === 'salida') {
                $sql = "SELECT sp_registrar_salida_fifo(:almacen, :usuario, :producto, :cantidad, :obs) as id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':almacen' => $idAlmacen,
                    ':usuario' => $datos['idusuario'] ?? 1,
                    ':producto' => $datos['idproducto'],
                    ':cantidad' => $datos['cantidad'],
                    ':obs' => '[SINCRONIZADO] ' . ($datos['observaciones'] ?? '')
                ]);
                return $stmt->fetch() !== false;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Sync error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getEstado(): array {
        return [
            'servidor_principal' => $this->detectarServidorPrincipal(),
            'pendientes_local_A' => $this->cola->contarPendientesPorOrigen('A'),
            'pendientes_local_B' => $this->cola->contarPendientesPorOrigen('B')
        ];
    }
    
    private function log(string $message): void {
        $logFile = __DIR__ . '/../sync_log.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
    }
}