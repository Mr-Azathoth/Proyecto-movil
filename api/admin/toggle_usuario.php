<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();

$id = (int)($_POST['id_usuario'] ?? 0);
if (!$id) sadmin_json_err('Datos incompletos.');

$db = getDB();
$db->prepare("UPDATE usuarios SET activo = NOT activo WHERE id_usuario = ?")->execute([$id]);
$nuevo = $db->query("SELECT activo FROM usuarios WHERE id_usuario = $id")->fetchColumn();

sadmin_json_ok(['activo' => (bool)$nuevo]);
