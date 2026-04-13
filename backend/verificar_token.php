<?php
/**
 * verificar_token.php
 * Verifica si el token de sesión es válido
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$headers = getallheaders();
$token = $headers['X-Auth-Token'] ?? $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode(['valid' => false, 'error' => 'Token requerido']);
    exit;
}

session_start();
if (isset($_SESSION['user_token']) && $_SESSION['user_token'] === $token && isset($_SESSION['user_data'])) {
    echo json_encode([
        'valid' => true,
        'usuario' => $_SESSION['user_data']
    ]);
} else {
    echo json_encode(['valid' => false, 'error' => 'Token inválido o expirado']);
}
?>