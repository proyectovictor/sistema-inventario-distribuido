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
            error_log("ColaHandler Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function obtenerPendientes(): array {
        $conn = $this->db->getConnection('local');
        if (!$conn) return [];
        
        $stmt = $conn->query("SELECT * FROM cola_pendientes WHERE estado = 'pendiente' ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function marcarComoProcesado(int $id): bool {
        $conn = $this->db->getConnection('local');
        if (!$conn) return false;
        
        $stmt = $conn->prepare("UPDATE cola_pendientes SET estado = 'procesado', fecha_intento = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    public function marcarComoError(int $id, string $razon): bool {
        $conn = $this->db->getConnection('local');
        if (!$conn) return false;
        
        $stmt = $conn->prepare("UPDATE cola_pendientes SET estado = 'error', fecha_intento = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    
    public function contarPendientes(): int {
        $conn = $this->db->getConnection('local');
        if (!$conn) return 0;
        
        $stmt = $conn->query("SELECT COUNT(*) FROM cola_pendientes WHERE estado = 'pendiente'");
        return (int)$stmt->fetchColumn();
    }
}