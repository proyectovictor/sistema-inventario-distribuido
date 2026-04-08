<?php
header('Content-Type: application/json');
require_once 'conexion.php';

$crud = new InventarioCRUD();
$activo = $crud->detectActiveServer();

echo json_encode([
    'servidor_activo' => $activo,
    'error' => $activo ? null : $crud->getLastError()
]);
?>