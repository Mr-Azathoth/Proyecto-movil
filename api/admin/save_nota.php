<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();
sadmin_csrf_check();

$id   = (int)($_POST['id_empresa'] ?? 0);
$nota = trim($_POST['nota'] ?? '');
if (!$id) sadmin_json_err('Datos incompletos.');

$db   = getDB();
$stmt = $db->prepare("UPDATE empresas SET notas_internas = ? WHERE id_empresa = ?");
$stmt->execute([$nota ?: null, $id]);

if ($stmt->rowCount() === 0) sadmin_json_err('Empresa no encontrada.', 404);

sadmin_json_ok(['msg' => 'Nota guardada.']);
