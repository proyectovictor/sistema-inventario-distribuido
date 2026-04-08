<?php
/**
 * conexion.php
 * Clase InventarioCRUD con:
 * - Conexión a Supabase A, Supabase B y PostgreSQL local
 * - Fallback automático (A → B → local)
 * - Métodos CRUD y operaciones de inventario
 * - Soporte para cola_pendientes
 */

class InventarioCRUD {
    private $config;
    private $activeConnection = null;
    private $activeServer = null;
    private $lastError = null;
    
    public function __construct() {
        // Configuración de los tres servidores
        $this->config = [
            'A' => [
                'type' => 'supabase',
                'host'     => 'aws-1-us-west-2.pooler.supabase.com',   // Ejemplo: host del pooler
                'port'     => '6543',                                   // Puerto de PostgreSQL (pooler)
                'dbname'   => 'postgres',                               // Base de datos por defecto
                'user'     => 'postgres.tnahdwifbiegvmcioljx',                        // Usuario (con anon/key? mejor usar rol)
                'password' => 'Co6r4f457?13',
                'sslmode'  => 'require'
            ],
            'B' => [
                'type' => 'supabase',
                'host'     => 'aws-1-us-east-2.pooler.supabase.com',
                'port'     => '6543',
                'dbname'   => 'postgres',
                'user'     => 'postgres.wgqjicrtrcrykwpsfuzt',
                'password' => 'C06r4f457?13',
                'sslmode'  => 'require'
            ],
            'local' => [
                'type' => 'postgres',
                'host' => 'localhost',
                'port' => '5432',
                'dbname' => 'inventario_db',
                'user' => 'postgres',
                'password' => 'C06r4f457?',
                'sslmode' => 'disable'
            ]
        ];
    }
    
    // ==================== DETECCIÓN DE SERVIDOR ACTIVO ====================
    public function detectActiveServer() {
        $servers = ['A', 'B', 'local'];
        foreach ($servers as $server) {
            if ($this->testConnection($server)) {
                $this->activeServer = $server;
                return $server;
            }
        }
        return null;
    }
    
    private function testConnection($server) {
        try {
            $cfg = $this->config[$server];
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['sslmode']
            );
            $pdo = new PDO($dsn, $cfg['user'], $cfg['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    public function getConnection() {
        if ($this->activeConnection !== null && $this->activeServer !== null) {
            return $this->activeConnection;
        }
        $servers = ['A', 'B', 'local'];
        foreach ($servers as $server) {
            try {
                $cfg = $this->config[$server];
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                    $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['sslmode']
                );
                $pdo = new PDO($dsn, $cfg['user'], $cfg['password']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->activeConnection = $pdo;
                $this->activeServer = $server;
                return $pdo;
            } catch (PDOException $e) {
                continue;
            }
        }
        return null;
    }
    
    public function getActiveServer() {
        return $this->activeServer;
    }
    
    public function getLastError() {
        return $this->lastError;
    }
    
    // ==================== OPERACIONES CRUD ====================
    public function leerProductos($idProducto = null) {
        $pdo = $this->getConnection();
        if (!$pdo) return [];
        if ($idProducto) {
            $sql = "SELECT p.*, c.nombre as categoria_nombre 
                    FROM producto p 
                    LEFT JOIN categoria c ON p.idcategoria = c.idcategoria
                    WHERE p.idproducto = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idProducto]);
        } else {
            $sql = "SELECT p.*, c.nombre as categoria_nombre 
                    FROM producto p 
                    LEFT JOIN categoria c ON p.idcategoria = c.idcategoria
                    ORDER BY p.idproducto";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerStock($idProducto) {
        $pdo = $this->getConnection();
        if (!$pdo) return 0;
        $stmt = $pdo->prepare("SELECT stockactual FROM existencia WHERE idproducto = ?");
        $stmt->execute([$idProducto]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['stockactual'] : 0;
    }
    
    public function obtenerAlertasStockBajo() {
        $pdo = $this->getConnection();
        if (!$pdo) return [];
        $stmt = $pdo->query("SELECT * FROM vista_alertas_stock_bajo ORDER BY faltante DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function obtenerMovimientos($limite = 50) {
        $pdo = $this->getConnection();
        if (!$pdo) return [];
        $sql = "SELECT m.idmovimiento, m.fecha, a.nombre as almacen, u.nombre as usuario, 
                       tm.nombre as tipo, m.observaciones
                FROM movimiento m
                JOIN almacen a ON m.idalmacen = a.idalmacen
                JOIN usuario u ON m.idusuario = u.idusuario
                JOIN tipomovimiento tm ON m.idtipomovimiento = tm.idtipomovimiento
                ORDER BY m.fecha DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limite]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ==================== OPERACIONES DE INVENTARIO ====================
    public function registrarEntrada($idAlmacen, $idUsuario, $idProducto, $cantidad, $lote, $fechaIngreso, $ubicacionEstante, $observaciones) {
        $pdo = $this->getConnection();
        if (!$pdo) return false;
        $sql = "SELECT sp_registrar_entrada(?, ?, ?, ?, ?, ?, ?, ?) as idmov";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idAlmacen, $idUsuario, $idProducto, $cantidad, $lote, $fechaIngreso, $ubicacionEstante, $observaciones]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['idmov'];
    }
    
    public function registrarSalida($idAlmacen, $idUsuario, $idProducto, $cantidad, $observaciones) {
        $pdo = $this->getConnection();
        if (!$pdo) return false;
        $sql = "SELECT sp_registrar_salida_fifo(?, ?, ?, ?, ?) as idmov";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idAlmacen, $idUsuario, $idProducto, $cantidad, $observaciones]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['idmov'];
    }
    
    // ==================== COLA DE PENDIENTES ====================
    public function guardarEnCola($sucursalOrigen, $operacion, $datos) {
        $pdo = $this->getConnection();
        if (!$pdo) return false;
        $sql = "INSERT INTO cola_pendientes (sucursal_origen, operacion, datos) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$sucursalOrigen, $operacion, json_encode($datos)]);
    }
    
    public function reprocesarCola() {
        $pdo = $this->getConnection();
        if (!$pdo) return 0;
        // Obtener pendientes
        $stmt = $pdo->query("SELECT id, operacion, datos FROM cola_pendientes WHERE estado = 'pendiente' ORDER BY id");
        $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $procesados = 0;
        foreach ($pendientes as $p) {
            $datos = json_decode($p['datos'], true);
            $exito = false;
            if ($p['operacion'] == 'entrada') {
                $exito = $this->registrarEntrada(
                    $datos['idalmacen'], $datos['idusuario'], $datos['idproducto'],
                    $datos['cantidad'], $datos['lote'], $datos['fechaingreso'],
                    $datos['ubicacion'], $datos['observaciones']
                );
            } elseif ($p['operacion'] == 'salida') {
                $exito = $this->registrarSalida(
                    $datos['idalmacen'], $datos['idusuario'], $datos['idproducto'],
                    $datos['cantidad'], $datos['observaciones']
                );
            }
            if ($exito) {
                $upd = $pdo->prepare("UPDATE cola_pendientes SET estado = 'procesado' WHERE id = ?");
                $upd->execute([$p['id']]);
                $procesados++;
            }
        }
        return $procesados;
    }
}
?>