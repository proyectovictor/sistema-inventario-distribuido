<?php
header('Content-Type: application/json');
require_once 'conexion.php';

/**
 * Configura DatabaseManager para que SOLO use la sucursal indicada (sin fallback)
 */
function setSingleBranch(DatabaseManager $db, string $branch) {
    $reflection = new ReflectionClass($db);
    $prop = $reflection->getProperty('config');
    $prop->setAccessible(true);
    $config = $prop->getValue($db);
    
    // Verificar que la sucursal existe en la configuración
    if (!isset($config[$branch])) {
        throw new Exception("Sucursal $branch no está configurada");
    }
    
    // Dejar solo la sucursal elegida
    $newConfig = [$branch => $config[$branch]];
    $prop->setValue($db, $newConfig);
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $branch = strtoupper($input['branch'] ?? 'A');
    if (!in_array($branch, ['A', 'B'])) {
        throw new Exception("Sucursal inválida. Use 'A' o 'B'.");
    }
    
    $db = new DatabaseManager();
    setSingleBranch($db, $branch);
    
    $conn = $db->getConnection();  // Solo intentará conectar a la sucursal elegida
    if (!$conn) {
        throw new Exception("No se pudo conectar a la sucursal $branch");
    }
    
    $nombre = trim($input['nombre'] ?? '');
    $precio = floatval($input['precio'] ?? 0);
    $descripcion = $input['descripcion'] ?? null;
    $codigoBarras = $input['codigo_barras'] ?? null;
    $stockMinimo = intval($input['stock_minimo'] ?? 0);
    $idCategoria = !empty($input['id_categoria']) ? intval($input['id_categoria']) : null;
    
    if (empty($nombre)) {
        throw new Exception("El nombre es obligatorio");
    }
    
    $sql = "INSERT INTO producto (nombre, descripcion, codigobarras, precio, stockminimo, idcategoria)
            VALUES (:nombre, :descripcion, :codigo, :precio, :stockminimo, :idcategoria)
            RETURNING idproducto";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':nombre' => $nombre,
        ':descripcion' => $descripcion,
        ':codigo' => $codigoBarras,
        ':precio' => $precio,
        ':stockminimo' => $stockMinimo,
        ':idcategoria' => $idCategoria
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'id' => $row['idproducto'],
        'server_used' => $db->getActiveServer()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}