<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../conexion.php';

$sucursal = $_SERVER['HTTP_X_SUCURSAL'] ?? $_GET['sucursal'] ?? 'A';

if (!in_array($sucursal, ['A', 'B', 'local'])) {
    // error_log("Sucursal inválida: $sucursal");
    echo json_encode(['error' => 'Sucursal invalida']);
    exit;
}

$db = new DatabaseManager();
$conn = $db->getConnection($sucursal);

if (!$conn) {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos', 'servidor' => $sucursal]);
    exit;
}

$accion = $_GET['accion'] ?? '';
$id = $_GET['id'] ?? null;

try {
    switch ($accion) {
        // ==================== PRODUCTOS ====================
        case 'productos' :
            if ($id) {
                $stmt = $conn->prepare("
                    SELECT p.*, c.nombre as categoria_nombre
                    FROM producto p
                    LEFT JOIN categoria c ON p.IdCategoria = c.IdCategoria
                    WHERE p.IdProducto = ?
                ");
                $stmt->execute([$id]);

            } else {
                $stmt = $conn->query("
                    SELECT p.*, c.nombre as categoria_nombre
                    FROM producto p
                    LEFT JOIN categoria c ON p.IdCategoria = c.IdCategoria
                    ORDER BY p.IdProducto
                ");
            }
            $resultado = $stmt->fetchAll();
            break;

        // ==================== CATEGORÍAS ====================
        case 'categorias':
            if ($id) {
                $stmt = $conn->prepare("SELECT * FROM categoria WHERE IdCategoria = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $conn->query("SELECT * FROM categoria ORDER BY Nombre");
            }
            $resultado = $stmt->fetchAll();
            break;

        // ==================== ALMACENES ====================
        case 'almacenes':
            if ($id) {
                $stmt = $conn->prepare("SELECT * FROM almacen WHERE IdAlmacen = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $conn->query("SELECT * FROM almacen WHERE Activo = true ORDER BY Nombre");
            }
            $resultado = $stmt->fetchAll();
            break;
        
        // ==================== USUARIOS ====================
        case 'usuarios':
            if ($id) {
                $stmt = $conn->prepare("SELECT IdUsuario, Nombre, UsuarioLogin, Rol FROM usuario WHERE IdUsuario = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $conn->query("SELECT IdUsuario, Nombre, UsuarioLogin, Rol FROM usuario ORDER BY Nombre");
            }
            $resultado = $stmt->fetchAll();
            break;

        // ==================== STOCK (existencias)====================
        case 'stock':
            if (!$id) {
                echo json_encode(['error' => 'Se requiere id de producto']);
                exit;
            }
            $stmt = $conn->prepare("SELECT StockActual FROM existencia WHERE IdProducto = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $resultado = ['stock' => $row ? $row['stockactual'] : 0];
            break;

        // ==================== STOCK DETALLADO (por lote/ubicación) ====================
        case 'stock_detallado':
            $idProducto = $_GET['idproducto'] ?? null;
            $idAlmacen = $_GET['idalmacen'] ?? null;
            
            $sql = "SELECT * FROM vista_stock_detallado WHERE 1=1";
            $params = [];
            
            if ($idProducto) {
                $sql .= " AND IdProducto = ?";
                $params[] = $idProducto;
            }
            if ($idAlmacen) {
                $sql .= " AND IdAlmacen = ?";
                $params[] = $idAlmacen;
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $resultado = $stmt->fetchAll();
            break;

        // ==================== STOCK TOTAL ====================
        case 'stock_total':
            $stmt = $conn->query("SELECT * FROM vista_stock_total ORDER BY Producto");
            $resultado = $stmt->fetchAll();
            break;

        // ==================== ALERTAS STOCK BAJO ====================
        case 'alertas':
            $stmt = $conn->query("SELECT * FROM vista_alertas_stock_bajo ORDER BY Faltante DESC");
            $resultado = $stmt->fetchAll();
            break;
        
        // ==================== MOVIMIENTOS ====================
        case 'movimientos':
            $limite = $_GET['limite'] ?? 50;
            $stmt = $conn->prepare("
                SELECT m.IdMovimiento, m.Fecha, a.Nombre as almacen, u.Nombre as usuario, 
                       tm.Nombre as tipo, m.Observaciones
                FROM movimiento m
                JOIN almacen a ON m.IdAlmacen = a.IdAlmacen
                JOIN usuario u ON m.IdUsuario = u.IdUsuario
                JOIN tipomovimiento tm ON m.IdTipoMovimiento = tm.IdTipoMovimiento
                ORDER BY m.Fecha DESC
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            $resultado = $stmt->fetchAll();
            break;

        // ==================== TIPOS DE MOVIMIENTO ====================
        case 'tipos_movimiento':
            $stmt = $conn->query("SELECT * FROM tipomovimiento ORDER BY IdTipoMovimiento");
            $resultado = $stmt->fetchAll();
            break;

        // ==================== COLA PENDIENTES ====================
        case 'cola_pendientes':
            $estado = $_GET['estado'] ?? 'pendiente';
            $stmt = $conn->prepare("SELECT * FROM cola_pendientes WHERE estado = ? ORDER BY id");
            $stmt->execute([$estado]);
            $resultado = $stmt->fetchAll();
            break;
        
        default:
            echo json_encode(['error' => 'Acción no valida. Opciones: productos, categorias, almacenes, 
            usuarios, stock, stock_detallado, stock_total, alertas, movimientos, tipos_movimiento, 
            cola_pendientes']);
            exit;
    }

    echo json_encode([
        'success' => true,
        'servidor' => $sucursal,
        'data' => $resultado
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'servidor' => $sucursal
    ]);
}
?>