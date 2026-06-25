<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();
sadmin_csrf_check();

$dry_run = isset($_POST['dry_run']);
if ($dry_run) {
    $_GET['dry_run'] = '1';
} else {
    unset($_GET['dry_run']);
}

define('CRON_CALL_INTERNAL', true);
ob_start();
require __DIR__ . '/../../cron/vencimientos.php';
ob_end_clean();

sadmin_json_ok(['msg' => 'Cron ejecutado.', 'log' => $log ?? [],
    'pronto' => $total_pronto ?? 0, 'vencidas' => $total_vencidas ?? 0]);
