<?php
/**
 * insertar.php
 * API REST para insertar datos (POST)
 * 
 * Uso:
 * - POST /insertar.php
 *   Headers: X-Sucursal: A
 *   Body: {"tipo": "entrada", "idproducto": 1, "cantidad": 10, ...}
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Sucursal');

// Responder a preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido. Use POST']);
    exit;
}

// Obtener sucursal (header o POST)
$sucursal = $_SERVER['HTTP_X_SUCURSAL'] ?? $_POST['sucursal'] ?? 'A';

if (!in_array($sucursal, ['A', 'B', 'local'])) {
    echo json_encode(['error' => 'Sucursal no válida. Use A, B o local']);
    exit;
}

// Leer body (JSON o form-data)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$tipo = $input['tipo'] ?? '';

// Mapear tipo a archivo CRUD
switch ($tipo) {
    case 'producto':
        $archivo = 'crear.php';
        $tabla = 'producto';
        break;
    case 'categoria':
        $archivo = 'crear.php';
        $tabla = 'categoria';
        break;
    case 'almacen':
        $archivo = 'crear.php';
        $tabla = 'almacen';
        break;
    case 'usuario':
        $archivo = 'crear.php';
        $tabla = 'usuario';
        break;
    case 'entrada':
        $archivo = 'entrada.php';
        break;
    case 'salida':
        $archivo = 'salida.php';
        break;
    default:
        echo json_encode(['error' => 'Tipo de operacion no valido', 'tipos_permitidos' => ['producto', 'categoria', 'almacen', 'usuario', 'entrada', 'salida']]);
        exit;
}

// Para operaciones CRUD normales
if (in_array($tipo, ['producto', 'categoria', 'almacen', 'usuario'])) {
    $crudFile = __DIR__ . '/crud/' . $archivo;
    if (!file_exists($crudFile)) {
        echo json_encode(['error' => 'Archivo CRUD no encontrado']);
        exit;
    }
    
    // Preparar datos para el CRUD
    $_POST['tabla'] = $tabla;
    $_POST['datos'] = $input['datos'] ?? $input;
    $_POST['sucursal'] = $sucursal;
    
    require_once $crudFile;
    exit;
}

// Para operaciones de inventario (entrada/salida)
$archivoInventario = __DIR__ . '/operaciones/' . $archivo;
if (!file_exists($archivoInventario)) {
    echo json_encode(['error' => 'Archivo de operación no encontrado. Crea la carpeta operaciones/']);
    exit;
}

// Preparar datos para operación
$_POST = array_merge($_POST, $input);
$_POST['sucursal'] = $sucursal;

require_once $archivoInventario;