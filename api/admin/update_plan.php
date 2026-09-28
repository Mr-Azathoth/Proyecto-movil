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

$db = getDB();

$estados_validos = ['Activo','Vencido','Suspendido','Gratis','Trial'];
$tipos_validos    = ['Trial','Trimestral','Semestral','Anual','Para siempre'];
// Ademas de las 5 opciones del combo, se acepta sin cambios el valor que la empresa ya tenia
// (ej. "3 meses" de una compra real por Mercado Pago) — el select del panel lo inyecta como una
// opcion extra "(actual)" cuando no calza con ninguna de las 5, para no bloquear un guardado que
// no toco el tipo de plan.
if ($tipo !== '' && !in_array($tipo, $tipos_validos, true)) {
    $actualRow = $db->prepare("SELECT plan_tipo FROM empresas WHERE id_empresa = ? LIMIT 1");
    $actualRow->execute([$id]);
    if ($tipo !== ($actualRow->fetchColumn() ?: '')) sadmin_json_err('Tipo de plan inválido.');
}
if ($estado && !in_array($estado, $estados_validos, true)) sadmin_json_err('Estado inválido.');
if ($venc && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) sadmin_json_err('Fecha inválida.');
$sets = [];
$params = [];
if ($tipo  !== '') { $sets[] = 'plan_tipo = ?';        $params[] = $tipo; }
if ($estado !== '') {
    $sets[] = 'plan_estado = ?';
    $params[] = $estado;
    // Sincronizar activa con el estado del plan
    $sets[]   = 'activa = ?';
    $params[] = in_array($estado, ['Activo', 'Gratis', 'Trial'], true) ? 1 : 0;
}
if ($venc   !== '') { $sets[] = 'plan_vencimiento = ?'; $params[] = $venc; }

if (empty($sets)) sadmin_json_err('Nada que actualizar.');
$params[] = $id;
$db->prepare("UPDATE empresas SET " . implode(', ', $sets) . " WHERE id_empresa = ?")->execute($params);

log_accion($db, 'sadmin_update_plan', null, ['sadmin_user' => sadmin_user(), 'plan_tipo' => $tipo, 'plan_estado' => $estado, 'plan_vencimiento' => $venc], null, $id);

sadmin_json_ok(['msg' => 'Plan actualizado.']);
