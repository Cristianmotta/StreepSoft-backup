<?php
define('APP_PATH', __DIR__ . '/../app');
define('PROJECT_ROOT', __DIR__ . '/..');

require_once PROJECT_ROOT . '/config/database.php';
require_once APP_PATH . '/utils/GeneradorDeudas.php';

try{
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log("CRON - Error BD: " . $e->getMessage());
    exit(1);
}

$generador = new GeneradorDeudas($pdo);

echo "=== CRON: Generar Deudas Mensuales ===\n";
echo "Hora: " . date('Y-m-d H:i:s') . "\n\n";

echo "1. Generando deudas del próximo mes...\n";
$resultado1 = $generador->generarDeudasProximoMes();
echo "   Generadas: " . $resultado1['generadas'] . "\n";
echo "   " . $resultado1['mensaje'] . "\n\n";

echo "2. Actualizando moras...\n";
$resultado2 = $generador->actualizarMoras();
echo "   Actualizadas: " . $resultado2['actualizadas'] . "\n";

echo "\n=== CRON Completado ===\n";

$log = "\n[" . date('Y-m-d H:i:s') . "] Generadas: " . $resultado1['generadas'] . ", Moras: " . $resultado2['actualizadas'];
@file_put_contents(PROJECT_ROOT . '/logs/cron-deudas.log', $log, FILE_APPEND);

exit(0);
?>