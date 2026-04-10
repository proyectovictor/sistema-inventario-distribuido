<?php
/**
 * test_sync.php - Prueba visual de sincronización
 * Ubicación: integration/test_sync.php
 * Abrir en navegador: http://localhost/tu_proyecto/integration/test_sync.php
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧪 Prueba de Sincronización</h1>";
echo "<hr>";

// 1. Probar ColaHandler
echo "<h2>1. Probando guardar en cola</h2>";
require_once __DIR__ . '/../../backend/sync/cola_handler.php';
$cola = new ColaHandler();

$guardado = $cola->guardarEnCola('TEST', 'entrada', [
    'idproducto' => 999,
    'cantidad' => 10,
    'test' => true
]);

if ($guardado) {
    echo "<p style='color:green'>✅ Operación guardada en cola correctamente</p>";
} else {
    echo "<p style='color:red'>❌ Error al guardar en cola</p>";
}

// 2. Ver pendientes
echo "<h2>2. Operaciones pendientes en cola</h2>";
$pendientes = $cola->obtenerPendientes();
echo "<p>Total pendientes: <strong>" . count($pendientes) . "</strong></p>";

if (count($pendientes) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Origen</th><th>Operación</th><th>Estado</th><th>Fecha</th></tr>";
    foreach ($pendientes as $item) {
        echo "<tr>";
        echo "<td>{$item['id']}</td>";
        echo "<td>{$item['sucursal_origen']}</td>";
        echo "<td>{$item['operacion']}</td>";
        echo "<td>{$item['estado']}</td>";
        echo "<td>{$item['fecha_intento']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Estado de servidores
echo "<h2>3. Estado de conexión a servidores</h2>";
require_once __DIR__ . '/../../backend/conexion.php';
$db = new DatabaseManager();

foreach (['A', 'B', 'local'] as $server) {
    $status = $db->testConnection($server);
    $color = $status ? 'green' : 'red';
    $texto = $status ? '✅ Conectado' : '❌ Desconectado';
    echo "<p style='color:$color'>Servidor {$server}: {$texto}</p>";
}

// 4. Probar sincronización
echo "<h2>4. Ejecutar sincronización manual</h2>";
require_once __DIR__ . '/../../backend/sync/sync_manager.php';
$sync = new SyncManager();
$resultado = $sync->ejecutarSincronizacion();

echo "<pre>";
print_r($resultado);
echo "</pre>";

// 5. Botón para limpiar pruebas
echo "<hr>";
echo "<h2>5. Limpiar datos de prueba</h2>";
echo "<form method='post'>";
echo "<button type='submit' name='limpiar' value='1'>🗑️ Limpiar operaciones de prueba</button>";
echo "</form>";

if (isset($_POST['limpiar'])) {
    $pendientes = $cola->obtenerPendientes();
    $limpiados = 0;
    foreach ($pendientes as $item) {
        $datos = json_decode($item['datos'], true);
        if (isset($datos['test']) && $datos['test'] === true) {
            $cola->marcarComoProcesado($item['id']);
            $limpiados++;
        }
    }
    echo "<p style='color:green'>✅ Se limpiaron {$limpiados} registros de prueba</p>";
    echo "<meta http-equiv='refresh' content='2'>";
}
?>