<?php
/**
 * cron_sync.php
 * Para ejecutar por CRON cada 5 minutos
 */

require_once __DIR__ . '/sync_manager.php';

$sync = new SyncManager();
$resultado = $sync->ejecutarSincronizacion();

if ($resultado['errores'] > 0) {
    error_log("Sync: {$resultado['errores']} errores");
}

echo json_encode($resultado);