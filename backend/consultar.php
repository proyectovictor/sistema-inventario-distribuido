<?php
/**
 * consultar.php
 * API REST para consultar datos (GET)
 * 
 * Uso:
 * - GET /consultar.php?accion=productos&sucursal=A
 * - GET /consultar.php?accion=stock&id=1&sucursal=B
 * - GET /consultar.php?accion=alertas&sucursal=local
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Sucursal');

// Responder a preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use GET']);
    exit;
}

// Obtener sucursal (header o GET parameter)
$sucursal = $_SERVER['HTTP_X_SUCURSAL'] ?? $_GET['sucursal'] ?? 'A';

if (!in_array($sucursal, ['A', 'B', 'local'])) {
    echo json_encode(['error' => 'Sucursal no válida. Use A, B o local']);
    exit;
}

$accion = $_GET['accion'] ?? '';
$id = $_GET['id'] ?? null;

// Mapear acción a archivo CRUD
$mapaAcciones = [
    'productos' => 'leer.php',
    'categorias' => 'leer.php',
    'almacenes' => 'leer.php',
    'usuarios' => 'leer.php',
    'stock' => 'leer.php',
    'stock_detallado' => 'leer.php',
    'stock_total' => 'leer.php',
    'alertas' => 'leer.php',
    'movimientos' => 'leer.php',
    'tipos_movimiento' => 'leer.php',
    'cola_pendientes' => 'leer.php'
];

if (!isset($mapaAcciones[$accion])) {
    echo json_encode(['error' => 'Acción no válida', 'acciones_disponibles' => array_keys($mapaAcciones)]);
    exit;
}

// Redirigir al CRUD correspondiente con los parámetros
$crudFile = __DIR__ . '/crud/' . $mapaAcciones[$accion];
if (!file_exists($crudFile)) {
    echo json_encode(['error' => 'Archivo CRUD no encontrado']);
    exit;
}

// Incluir el archivo CRUD (pasará los parámetros por $_GET)
$_GET['accion'] = $accion;
if ($id) {
    $_GET['id'] = $id;
}
$_GET['sucursal'] = $sucursal;

require_once $crudFile;