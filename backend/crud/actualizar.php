<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Metodo no permitido. Use PUT o POST']);
    exit;
}

$sucursal = $_SERVER['HTTP_X_SUCURSAL'] ?? $_REQUEST['sucursal'] ?? 'A';

if (!in_array($sucursal, ['A', 'B', 'local'])) {
    echo json_encode(['error' => 'Sucursal no válida']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$tabla = $input['tabla'] ?? '';
$id = $input['id'] ?? null;
$datos = $input['datos'] ?? [];

if (!$tabla || !$id || empty($datos)) {
    echo json_encode(['error' => 'Datos incompletos: se requiere tabla, id y datos']);
    exit;
}

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
    // Construir UPDATE dinámico
    $sets = [];
    $valores = [];
    foreach ($datos as $columna => $valor) {
        $sets[] = "$columna = ?";
        $valores[] = $valor;
    }
    $valores[] = $id;
    
    $sql = sprintf(
        "UPDATE %s SET %s WHERE id%s = ?",
        $tabla,
        implode(', ', $sets),
        $tabla === 'producto' ? 'producto' : $tabla
    );
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($valores);
    
    echo json_encode([
        'success' => true,
        'servidor' => $sucursal,
        'filas_afectadas' => $stmt->rowCount(),
        'mensaje' => 'Registro actualizado correctamente'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'servidor' => $sucursal
    ]);
}