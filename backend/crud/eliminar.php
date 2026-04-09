<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Metodo no permitido. Use DELETE o POST']);
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

if (!$tabla || !$id) {
    echo json_encode(['error' => 'Datos incompletos: se requiere tabla e id']);
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
    $sql = sprintf(
        "DELETE FROM %s WHERE id%s = ?",
        $tabla,
        $tabla === 'producto' ? 'producto' : $tabla
    );
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'servidor' => $sucursal,
        'filas_afectadas' => $stmt->rowCount(),
        'mensaje' => 'Registro eliminado correctamente'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'servidor' => $sucursal
    ]);
}