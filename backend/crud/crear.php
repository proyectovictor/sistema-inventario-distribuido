<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../conexion.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

// Obtener sucursal desde header
$sucursal = $_SERVER['HTTP_X_SUCURSAL'] ?? $_POST['sucursal'] ?? 'A';

if (!in_array($sucursal, ['A', 'B', 'local'])) {
    echo json_encode(['error' => 'Sucursal no válida']);
    exit;
}

// Leer datos de entrada (JSON o POST)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$tabla = $input['tabla'] ?? '';
$datos = $input['datos'] ?? [];

if (!$tabla || empty($datos)) {
    echo json_encode(['error' => 'Datos incompletos: se requiere tabla y datos']);
    exit;
}

// Lista de tablas permitidas (seguridad)
$tablasPermitidas = ['producto', 'categoria', 'almacen', 'usuario'];
if (!in_array($tabla, $tablasPermitidas)) {
    echo json_encode(['error' => 'Tabla no permitida']);
    exit;
}

$db = new DatabaseManager();
$conn = $db->getConnection($sucursal);

if (!$conn) {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
    exit;
}

try {
    // Construir INSERT dinámico
    $columnas = array_keys($datos);
    $placeholders = array_fill(0, count($columnas), '?');
    
    $sql = sprintf(
        "INSERT INTO %s (%s) VALUES (%s) RETURNING id%s",
        $tabla,
        implode(', ', $columnas),
        implode(', ', $placeholders),
        $tabla === 'producto' ? 'producto' : ''
    );
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(array_values($datos));
    
    $row = $stmt->fetch();
    $nuevoId = $row ? $row[0] : null;
    
    echo json_encode([
        'success' => true,
        'servidor' => $sucursal,
        'id' => $nuevoId,
        'mensaje' => 'Registro creado correctamente'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'servidor' => $sucursal
    ]);
}