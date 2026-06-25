<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();
sadmin_csrf_check();

$id = (int)($_POST['id_usuario'] ?? 0);
if (!$id) sadmin_json_err('Datos incompletos.');

$db = getDB();
$db->prepare("UPDATE usuarios SET activo = NOT activo WHERE id_usuario = ?")->execute([$id]);
$st = $db->prepare("SELECT activo FROM usuarios WHERE id_usuario = ?");
$st->execute([$id]);
$nuevo = $st->fetchColumn();

sadmin_json_ok(['activo' => (bool)$nuevo]);
