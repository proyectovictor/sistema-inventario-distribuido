<?php
/**
 * login.php
 * Endpoint para autenticación de usuarios
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST']);
    exit;
}

require_once __DIR__ . '/conexion.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Usuario y contraseña requeridos']);
    exit;
}

$db = new DatabaseManager();
$servidores = ['A', 'B', 'local'];
$usuarioEncontrado = null;
$servidorUsado = null;

foreach ($servidores as $servidor) {
    $conn = $db->getConnection($servidor);
    if (!$conn) continue;
    
    try {
        $stmt = $conn->prepare("SELECT IdUsuario, Nombre, UsuarioLogin, Rol, sucursal_defecto 
                                FROM usuario 
                                WHERE UsuarioLogin = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $stmt2 = $conn->prepare("SELECT Password FROM usuario WHERE IdUsuario = :id");
            $stmt2->execute([':id' => $user['idusuario']]);
            $dbPass = $stmt2->fetchColumn();
            
            $hashedPassword = hash('sha256', $password);
            
            if ($dbPass === $hashedPassword) {
                $usuarioEncontrado = $user;
                $servidorUsado = $servidor;
                break;
            }
        }
    } catch (PDOException $e) {
        continue;
    }
}

if ($usuarioEncontrado) {
    session_start();
    $token = bin2hex(random_bytes(32));
    $_SESSION['user_token'] = $token;
    $_SESSION['user_data'] = [
        'id' => $usuarioEncontrado['idusuario'],
        'nombre' => $usuarioEncontrado['nombre'],
        'login' => $usuarioEncontrado['usuariologin'],
        'rol' => $usuarioEncontrado['rol'],
        'sucursal_defecto' => $usuarioEncontrado['sucursal_defecto'] ?? 'A'
    ];
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'usuario' => $_SESSION['user_data'],
        'servidor_usado' => $servidorUsado
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Usuario o contraseña incorrectos']);
}
?>