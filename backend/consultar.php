<?php
header('Content-Type: application/json');
require_once 'conexion.php';

$crud = new InventarioCRUD();
$accion = $_GET['accion'] ?? '';

switch ($accion) {
    case 'productos':
        $id = $_GET['id'] ?? null;
        $resultado = $crud->leerProductos($id);
        break;
    case 'stock':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['error' => 'Se requiere id de producto']);
            exit;
        }
        $resultado = ['stock' => $crud->obtenerStock($id)];
        break;
    case 'alertas':
        $resultado = $crud->obtenerAlertasStockBajo();
        break;
    case 'movimientos':
        $limite = $_GET['limite'] ?? 50;
        $resultado = $crud->obtenerMovimientos($limite);
        break;
    default:
        echo json_encode(['error' => 'Acción no válida']);
        exit;
}

echo json_encode($resultado);
?>