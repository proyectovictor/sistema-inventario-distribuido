<?php

require_once __DIR__ . '/conexion.php';

// Forzar salida como texto plano (opcional, pero así no se interpreta HTML)
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE SERVIDORES (Failover A → B → local) ===\n\n";

$dbManager = new DatabaseManager();

// 1. Probar servidor activo según detectActiveServer()
echo "[1] Detectando servidor disponible...\n";
$activo = $dbManager->detectActiveServer();
if ($activo) {
    echo "    ✅ Servidor activo detectado: " . strtoupper($activo) . "\n";
} else {
    echo "    ❌ Ningún servidor responde.\n";
}

echo "\n[2] Intentando obtener conexión real (failover automático)...\n";
$conn = $dbManager->getConnection();
if ($conn) {
    $server = $dbManager->getActiveServer();
    echo "    ✅ Conexión establecida con servidor: " . strtoupper($server) . "\n";
    // Consulta de prueba
    $stmt = $conn->query("SELECT NOW() as hora");
    $row = $stmt->fetch();
    echo "    📅 Hora del servidor: " . $row['hora'] . "\n";
} else {
    echo "    ❌ No se pudo conectar a ningún servidor.\n";
    echo "    Último error: " . $dbManager->getLastError() . "\n";
}

echo "\n==========================================\n";
echo "Prueba completada.\n";