<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/conexion.php';

echo "<h1>🔌 Diagnóstico de conexiones</h1>";
echo "<hr>";

$db = new DatabaseManager();
$diagnostico = $db->diagnosticar();

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #333; color: white;'>";
echo "<th>Servidor</th>";
echo "<th>Host</th>";
echo "<th>Estado</th>";
echo "<th>Tiempo</th>";
echo "<th>Error</th>";
echo "</tr>";

foreach ($diagnostico as $key => $info) {
    $color = $info['conecta'] ? 'green' : 'red';
    $estado = $info['conecta'] ? '✅ CONECTA' : '❌ NO CONECTA';
    
    echo "<tr>";
    echo "<td><strong>{$info['nombre']} ($key)</strong></td>";
    echo "<td>{$info['host']}</td>";
    echo "<td style='color: $color; font-weight: bold;'>$estado</td>";
    echo "<td>" . ($info['conecta'] ? $info['tiempo_ms'] . ' ms' : '-') . "</td>";
    echo "<td style='color: red;'>" . ($info['error'] ?? '-') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Probar conexión manual a cada servidor
echo "<h2>📡 Prueba de conexión activa</h2>";

foreach (['A', 'B', 'local'] as $server) {
    echo "<h3>Conectando a servidor: $server</h3>";
    $conn = $db->getConnection($server);
    
    if ($conn) {
        $stmt = $conn->query("SELECT NOW() as hora, version() as version");
        $row = $stmt->fetch();
        echo "✅ Conexión exitosa<br>";
        echo "📅 Hora del servidor: " . $row['hora'] . "<br>";
        echo "🐘 Versión: " . substr($row['version'], 0, 80) . "...<br>";
        $db->closeConnection();
    } else {
        echo "❌ No se pudo conectar<br>";
        echo "Error: " . $db->getLastError() . "<br>";
    }
    echo "<hr>";
}

// Mostrar ubicación del log
echo "<h2>📝 Archivo de log</h2>";
echo "Ruta: " . __DIR__ . "/../db_errors.log<br>";
if (file_exists(__DIR__ . "/../db_errors.log")) {
    echo "✅ El archivo de log existe<br>";
    echo "<details>";
    echo "<summary>Ver últimas líneas del log</summary>";
    echo "<pre style='background: #f4f4f4; padding: 10px; overflow: auto; max-height: 300px;'>";
    echo htmlspecialchars(file_get_contents(__DIR__ . "/../db_errors.log"));
    echo "</pre>";
    echo "</details>";
} else {
    echo "⚠️ El archivo de log aún no se ha creado (se creará cuando haya actividad)";
}
?>