<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();

$id = (int)($_POST['id_empresa'] ?? 0);
if (!$id) sadmin_json_err('Datos incompletos.');

$db = getDB();
$db->prepare("UPDATE empresas SET activa = NOT activa WHERE id_empresa = ?")->execute([$id]);
$nuevo = $db->query("SELECT activa FROM empresas WHERE id_empresa = $id")->fetchColumn();

sadmin_json_ok(['activa' => (bool)$nuevo]);
