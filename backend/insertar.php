<?php
header('Content-Type: application/json');
require_once 'conexion.php';

$crud = new InventarioCRUD();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST (JSON o formulario)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$tipo = $input['tipo'] ?? ''; // 'entrada' o 'salida'
if (!in_array($tipo, ['entrada', 'salida'])) {
    echo json_encode(['error' => 'Tipo de operación inválido']);
    exit;
}

// Datos comunes
$idAlmacen = $input['idalmacen'] ?? 1;
$idUsuario = $input['idusuario'] ?? 1;
$idProducto = $input['idproducto'] ?? null;
$cantidad = $input['cantidad'] ?? 0;
$observaciones = $input['observaciones'] ?? '';

if (!$idProducto || $cantidad <= 0) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

// Intentar la operación
$exito = false;
if ($tipo == 'entrada') {
    $lote = $input['lote'] ?? 'LOTE-' . date('YmdHis');
    $fechaIngreso = $input['fechaingreso'] ?? date('Y-m-d');
    $ubicacion = $input['ubicacion'] ?? 'General';
    $exito = $crud->registrarEntrada($idAlmacen, $idUsuario, $idProducto, $cantidad, $lote, $fechaIngreso, $ubicacion, $observaciones);
} else {
    $exito = $crud->registrarSalida($idAlmacen, $idUsuario, $idProducto, $cantidad, $observaciones);
}

if ($exito) {
    echo json_encode(['exito' => true, 'id_movimiento' => $exito]);
} else {
    // Guardar en cola de pendientes
    $crud->guardarEnCola($crud->getActiveServer(), $tipo, $input);
    echo json_encode(['exito' => false, 'cola' => true, 'mensaje' => 'Operación guardada en cola por fallo del servidor']);
}
?>