<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../conexion.php';

$sucursal = $_SERVER['HTTP_X_SUCURSAL'] ?? $_GET['sucursal'] ?? 'A';

if (!in_array($sucursal, ['A', 'B', 'local'])) {
    // error_log("Sucursal inválida: $sucursal");
    echo json_encode(['error' => 'Sucursal inválida']);
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
        case 'productos' :
            if ($id) {
                $stmt = $conn->prepare("
                    SELECT p.*, c.nombre as categoria_nombre
                    FROM productos p
                    JOIN categorias c ON p.idcategoria = c.idcategoria
                    WHERE p.idproducto = ?
                ");
                $stmt->execute([$id]);

            } else {
                $stmt = $conn->query("
                    SELECT p.*, c.nombre as categoria_nombre
                    FROM productos p
                    LEFT JOIN categorias c ON p.idcategoria = c.idcategoria
                    ORDER BY p.idproducto
                ");
                echo json_encode(['error' => 'ID de producto no proporcionado']);
            }
            $resultado = $stmt->fetchAll();
            break;

        case 'stock' :
            if (!$id) {
                echo json_encode(['error' => 'Se requiere id de producto']);
                exit;
            }
            $stmt = $conn->prepare("SELECT stockactual FROM existencia WHERE idproducto = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $resultado = ['stock' => $row ? $row['stockactual'] : 0];
            break;

        case 'movimientos':
            $limite = $_GET['limite'] ?? 50;
            $stmt = $conn->prepare("
                SELECT m.idmovimiento, m.fecha, a.nombre as almacen, u.nombre as usuario, 
                       tm.nombre as tipo, m.observaciones
                FROM movimiento m
                JOIN almacen a ON m.idalmacen = a.idalmacen
                JOIN usuario u ON m.idusuario = u.idusuario
                JOIN tipomovimiento tm ON m.idtipomovimiento = tm.idtipomovimiento
                ORDER BY m.fecha DESC
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            $resultado = $stmt->fetchAll();
            break;
        
        case 'categorias':
            $stmt = $conn->query("SELECT * FROM categoria ORDER BY nombre");
            $resultado = $stmt->fetchAll();
            break;
        
        default:
            echo json_encode(['error' => 'Accion no valida']);
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