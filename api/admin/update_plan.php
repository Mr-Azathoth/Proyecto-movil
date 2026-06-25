<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();

$id     = (int)($_POST['id_empresa'] ?? 0);
$tipo   = trim($_POST['plan_tipo']   ?? '');
$estado = trim($_POST['plan_estado'] ?? '');
$venc   = trim($_POST['plan_vencimiento'] ?? '');

if (!$id) sadmin_json_err('Datos incompletos.');

$estados_validos = ['Activo','Vencido','Suspendido','Gratis'];
if ($estado && !in_array($estado, $estados_validos)) sadmin_json_err('Estado inválido.');
if ($venc && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) sadmin_json_err('Fecha inválida.');

$db = getDB();
$sets = [];
$params = [];
if ($tipo  !== '') { $sets[] = 'plan_tipo = ?';        $params[] = $tipo; }
if ($estado !== '') { $sets[] = 'plan_estado = ?';     $params[] = $estado; }
if ($venc   !== '') { $sets[] = 'plan_vencimiento = ?'; $params[] = $venc; }

if (empty($sets)) sadmin_json_err('Nada que actualizar.');
$params[] = $id;
$db->prepare("UPDATE empresas SET " . implode(', ', $sets) . " WHERE id_empresa = ?")->execute($params);

sadmin_json_ok(['msg' => 'Plan actualizado.']);
