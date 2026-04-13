<?php
/**
 * test_probar_sync.php
 * Prueba completa del sistema de sincronización
 * Ubicación: integration/test_probar_sync.php
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧪 Prueba de Sincronización</h1>";

// 1. Probar ColaHandler
echo "<h2>1. Probando ColaHandler</h2>";
require_once __DIR__ . '/../../backend/sync/cola_handler.php';
$cola = new ColaHandler();

// Guardar operación de prueba
echo "<p>📝 Guardando operación de prueba...</p>";
$guardado = $cola->guardarEnCola('TEST', 'entrada', [
    'idproducto' => 999,
    'cantidad' => 10,
    'test' => true
]);
echo "<p>Resultado: " . ($guardado ? "✅ Guardado" : "❌ Fallo") . "</p>";

// Ver pendientes
$pendientes = $cola->obtenerPendientes();
echo "<p>📋 Pendientes en cola: " . count($pendientes) . "</p>";

// 2. Probar SyncManager
echo "<h2>2. Probando SyncManager</h2>";
require_once __DIR__ . '/../../backend/sync/sync_manager.php';
$sync = new SyncManager();

$estado = $sync->getEstado();
echo "<pre>Estado actual: " . json_encode($estado, JSON_PRETTY_PRINT) . "</pre>";

// 3. Probar conexión a servidores
echo "<h2>3. Estado de servidores</h2>";
require_once __DIR__ . '/../../backend/conexion.php';
$db = new DatabaseManager();

foreach (['A', 'B', 'local'] as $server) {
    $status = $db->testConnection($server) ? '✅ Activo' : '❌ Inactivo';
    echo "<p>Servidor {$server}: {$status}</p>";
}

// 4. Limpiar datos de prueba (opcional)
echo "<h2>4. Limpiar datos de prueba</h2>";
$pendientes = $cola->obtenerPendientes();
foreach ($pendientes as $item) {
    $datos = json_decode($item['datos'], true);
    if (isset($datos['test']) && $datos['test'] === true) {
        $cola->marcarComoProcesado($item['id']);
        echo "<p>🗑️ Eliminado registro de prueba ID: {$item['id']}</p>";
    }
}

echo "<hr>";
echo "<p>✅ Pruebas completadas</p>";
?>