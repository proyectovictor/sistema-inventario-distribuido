<?php
/**
 * operaciones/entrada.php
 * Registra una entrada de productos usando el SP sp_registrar_entrada
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
$lote = $_POST['lote'] ?? 'LOTE-' . date('YmdHis');
$fechaIngreso = $_POST['fechaingreso'] ?? date('Y-m-d');
$ubicacion = $_POST['ubicacion'] ?? 'General';
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
    $cola->guardarEnCola($sucursal, 'entrada', $_POST);
    echo json_encode([
        'exito' => false, 
        'cola' => true, 
        'mensaje' => "Servidor {$sucursal} no disponible. Operación guardada en cola."
    ]);
    exit;
}

try {
    $sql = "SELECT sp_registrar_entrada(:almacen, :usuario, :producto, :cantidad, :lote, :fecha, :ubicacion, :obs) as idmov";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':almacen' => $idAlmacen,
        ':usuario' => $idUsuario,
        ':producto' => $idProducto,
        ':cantidad' => $cantidad,
        ':lote' => $lote,
        ':fecha' => $fechaIngreso,
        ':ubicacion' => $ubicacion,
        ':obs' => $observaciones
    ]);
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'exito' => true,
        'servidor' => $sucursal,
        'id_movimiento' => $resultado['idmov'],
        'mensaje' => 'Entrada registrada correctamente'
    ]);
    
} catch (PDOException $e) {
    // Guardar en cola si falla
    $cola = new ColaHandler();
    $cola->guardarEnCola($sucursal, 'entrada', $_POST);
    
    echo json_encode([
        'exito' => false,
        'cola' => true,
        'error' => $e->getMessage(),
        'mensaje' => 'Error al registrar. Operación guardada en cola.'
    ]);
}