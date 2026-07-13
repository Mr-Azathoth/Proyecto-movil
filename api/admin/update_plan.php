<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();
sadmin_csrf_check();

$id     = (int)($_POST['id_empresa'] ?? 0);
$tipo   = trim($_POST['plan_tipo']   ?? '');
$estado = trim($_POST['plan_estado'] ?? '');
$venc   = trim($_POST['plan_vencimiento'] ?? '');

if (!$id) sadmin_json_err('Datos incompletos.');

$estados_validos = ['Activo','Vencido','Suspendido','Gratis'];
$tipos_validos   = ['1mes','3meses','6meses','12meses','manual'];
if ($estado && !in_array($estado, $estados_validos, true)) sadmin_json_err('Estado inválido.');
if ($tipo  && !in_array($tipo,   $tipos_validos,   true)) sadmin_json_err('Tipo de plan inválido.');
if ($venc && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) sadmin_json_err('Fecha inválida.');

$db = getDB();
$sets = [];
$params = [];
if ($tipo  !== '') { $sets[] = 'plan_tipo = ?';        $params[] = $tipo; }
if ($estado !== '') {
    $sets[] = 'plan_estado = ?';
    $params[] = $estado;
    // Sincronizar activa con el estado del plan
    $sets[]   = 'activa = ?';
    $params[] = ($estado === 'Activo' || $estado === 'Gratis') ? 1 : 0;
}
if ($venc   !== '') { $sets[] = 'plan_vencimiento = ?'; $params[] = $venc; }

if (empty($sets)) sadmin_json_err('Nada que actualizar.');
$params[] = $id;
$db->prepare("UPDATE empresas SET " . implode(', ', $sets) . " WHERE id_empresa = ?")->execute($params);

sadmin_json_ok(['msg' => 'Plan actualizado.']);
