<?php
/**
 * operaciones/salida.php
 * Registra una salida de productos usando el SP sp_registrar_salida_fifo
 */

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../sync/cola_handler.php';

$sucursal = $_POST['sucursal'] ?? $_SERVER['HTTP_X_SUCURSAL'] ?? 'A';

if (!in_array($sucursal, ['A', 'B', 'local'])) {
    echo json_encode(['error' => 'Sucursal no válida']);
    exit;
}

$idAlmacen = $_POST['idalmacen'] ?? 1;
$idUsuario = $_POST['idusuario'] ?? 1;
$idProducto = $_POST['idproducto'] ?? null;
$cantidad = $_POST['cantidad'] ?? 0;
$observaciones = $_POST['observaciones'] ?? '';

if (!$idProducto || $cantidad <= 0) {
    echo json_encode(['error' => 'Datos incompletos: idproducto y cantidad requeridos']);
    exit;
}

$db = new DatabaseManager();
$conn = $db->getConnection($sucursal);

if (!$conn) {
    // Guardar en cola para después
    $cola = new ColaHandler();
    $cola->guardarEnCola($sucursal, 'salida', $_POST);
    echo json_encode([
        'exito' => false, 
        'cola' => true, 
        'mensaje' => "Servidor {$sucursal} no disponible. Operación guardada en cola."
    ]);
    exit;
}

try {
    $sql = "SELECT sp_registrar_salida_fifo(:almacen, :usuario, :producto, :cantidad, :obs) as idmov";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':almacen' => $idAlmacen,
        ':usuario' => $idUsuario,
        ':producto' => $idProducto,
        ':cantidad' => $cantidad,
        ':obs' => $observaciones
    ]);
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'exito' => true,
        'servidor' => $sucursal,
        'id_movimiento' => $resultado['idmov'],
        'mensaje' => 'Salida registrada correctamente'
    ]);
    
} catch (PDOException $e) {
    // Guardar en cola si falla
    $cola = new ColaHandler();
    $cola->guardarEnCola($sucursal, 'salida', $_POST);
    
    echo json_encode([
        'exito' => false,
        'cola' => true,
        'error' => $e->getMessage(),
        'mensaje' => 'Error al registrar. Operación guardada en cola.'
    ]);
}