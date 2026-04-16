<?php
/**
 * ejecutar_sync.php
 * Endpoint para ejecutar sincronización manualmente
 */

declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/sync_manager.php';

$sync = new SyncManager();

// Al inicio del archivo, agregar esta opción
if ($_GET['accion'] === 'sincronizar_datos') {
    $servidor = $_GET['servidor'] ?? 'A';
    $resultado = $sync->sincronizarDatosCompletos($servidor);
    echo json_encode($resultado);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['force'])) {
    echo json_encode(['estado' => $sync->getEstado()], JSON_PRETTY_PRINT);
    exit;
}

$forceServer = $_GET['force'] ?? $_POST['force'] ?? null;
if ($forceServer && !in_array($forceServer, ['A', 'B'])) {
    echo json_encode(['error' => 'Servidor no válido. Use A o B']);
    exit;
}

$resultado = $sync->ejecutarSincronizacion($forceServer);
echo json_encode($resultado, JSON_PRETTY_PRINT);