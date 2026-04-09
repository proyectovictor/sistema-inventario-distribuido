<?php
header('Content-Type: application/json');
require_once 'conexion.php';

function setSingleBranch(DatabaseManager $db, string $branch) {
    $reflection = new ReflectionClass($db);
    $prop = $reflection->getProperty('config');
    $prop->setAccessible(true);
    $config = $prop->getValue($db);
    if (!isset($config[$branch])) {
        throw new Exception("Sucursal $branch no está configurada");
    }
    $newConfig = [$branch => $config[$branch]];
    $prop->setValue($db, $newConfig);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        parse_str(file_get_contents('php://input'), $input);
    }
    
    $branch = strtoupper($input['branch'] ?? 'A');
    if (!in_array($branch, ['A', 'B'])) {
        throw new Exception("Sucursal inválida. Use 'A' o 'B'.");
    }
    
    $db = new DatabaseManager();
    setSingleBranch($db, $branch);
    
    $conn = $db->getConnection();
    if (!$conn) {
        throw new Exception("No se pudo conectar a la sucursal $branch");
    }
    
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception("ID inválido");
    }
    
    $camposPermitidos = ['nombre', 'descripcion', 'codigobarras', 'precio', 'stockminimo', 'idcategoria'];
    $sets = [];
    $params = [':id' => $id];
    foreach ($camposPermitidos as $campo) {
        if (array_key_exists($campo, $input)) {
            $sets[] = "$campo = :$campo";
            $params[":$campo"] = $input[$campo];
        }
    }
    if (empty($sets)) {
        throw new Exception("Ningún campo para actualizar");
    }
    
    $sql = "UPDATE producto SET " . implode(', ', $sets) . " WHERE idproducto = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'rows_affected' => $stmt->rowCount(),
        'server_used' => $db->getActiveServer()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}