<?php
/**
 * cola_handler.php
 * Manejo de la tabla cola_pendientes
 */

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

class ColaHandler {
    private DatabaseManager $db;
    
    public function __construct() {
        $this->db = new DatabaseManager();
    }
    
    // ========== MÉTODOS ORIGINALES ==========
    
    public function guardarEnCola(string $sucursalOrigen, string $operacion, array $datos): bool {
        $conn = $this->db->getConnection('local');
        if (!$conn) {
            error_log("ColaHandler: No se pudo conectar a LOCAL");
            return false;
        }
        
        try {
            $sql = "INSERT INTO cola_pendientes (sucursal_origen, operacion, datos, estado, fecha_intento) 
                    VALUES (:origen, :operacion, :datos, 'pendiente', NOW())";
            $stmt = $conn->prepare($sql);
            return $stmt->execute([
                ':origen' => $sucursalOrigen,
                ':operacion' => $operacion,
                ':datos' => json_encode($datos)
            ]);
        } catch (PDOException $e) {
            error_log("ColaHandler Error guardar: " . $e->getMessage());
            return false;
        }
    }
    
    public function obtenerPendientes(): array {
        $conn = $this->db->getConnection('local');
        if (!$conn) return [];
        
        try {
            $stmt = $conn->query("SELECT * FROM cola_pendientes WHERE estado = 'pendiente' ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ColaHandler Error obtenerPendientes: " . $e->getMessage());
            return [];
        }
    }
    
    public function marcarComoProcesado(int $id): bool {
        $conn = $this->db->getConnection('local');
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare("UPDATE cola_pendientes SET estado = 'procesado', fecha_intento = NOW() WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("ColaHandler Error marcarProcesado: " . $e->getMessage());
            return false;
        }
    }
    
    public function marcarComoError(int $id, string $razon): bool {
        $conn = $this->db->getConnection('local');
        if (!$conn) return false;
        
        try {
            $stmt = $conn->prepare("UPDATE cola_pendientes SET estado = 'error', fecha_intento = NOW() WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("ColaHandler Error marcarError: " . $e->getMessage());
            return false;
        }
    }
    
    public function contarPendientes(): int {
        $conn = $this->db->getConnection('local');
        if (!$conn) return 0;
        
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM cola_pendientes WHERE estado = 'pendiente'");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    // ========== NUEVOS MÉTODOS ==========
    
    public function obtenerPendientesPorOrigen(string $origen): array {
        $conn = $this->db->getConnection('local');
        if (!$conn) return [];
        
        try {
            $stmt = $conn->prepare("SELECT * FROM cola_pendientes 
                                    WHERE estado = 'pendiente' AND sucursal_origen = :origen 
                                    ORDER BY id ASC");
            $stmt->execute([':origen' => $origen]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ColaHandler Error obtenerPendientesPorOrigen: " . $e->getMessage());
            return [];
        }
    }
    
    public function contarPendientesPorOrigen(string $origen): int {
        $conn = $this->db->getConnection('local');
        if (!$conn) return 0;
        
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM cola_pendientes 
                                    WHERE estado = 'pendiente' AND sucursal_origen = :origen");
            $stmt->execute([':origen' => $origen]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    public function limpiarProcesados(int $dias = 30): int {
        $conn = $this->db->getConnection('local');
        if (!$conn) return 0;
        
        try {
            $stmt = $conn->prepare("DELETE FROM cola_pendientes 
                                    WHERE estado = 'procesado' 
                                    AND fecha_intento < NOW() - INTERVAL ':dias days'");
            $stmt->execute([':dias' => $dias]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("ColaHandler Error limpiar: " . $e->getMessage());
            return 0;
        }
    }
}