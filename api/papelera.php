<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
sadmin_guard();
sadmin_csrf_check();

$db  = getDB();
$eid = (int) ($_POST['id_empresa'] ?? 0);
if (!$eid) sadmin_json_err('Empresa requerida.');

$accion = $_POST['accion'] ?? '';
$tipo   = $_POST['tipo']   ?? '';
$id     = (int) ($_POST['id'] ?? 0);

if (!$id || !in_array($tipo, ['reparacion', 'repuesto'], true)) {
    sadmin_json_err('Parámetros inválidos.');
}

if ($accion === 'restaurar') {
    if ($tipo === 'reparacion') {
        $st = $db->prepare(
            "UPDATE reparaciones SET deleted_at = NULL
              WHERE id_ingreso = ? AND id_empresa = ? AND deleted_at IS NOT NULL"
        );
        $st->execute([$id, $eid]);
        if ($st->rowCount() === 0) sadmin_json_err('Registro no encontrado o ya activo.');
        $db->prepare("INSERT INTO log_acciones (id_empresa, id_usuario, usuario, accion, id_reparacion, ip)
                      VALUES (?, ?, ?, 'reparacion_restaurada', ?, ?)")
           ->execute([$eid, sadmin_id(), sadmin_user(), $id, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        sadmin_json_ok(['msg' => "Servicio #{$id} restaurado."]);
    }

    if ($tipo === 'repuesto') {
        $st = $db->prepare(
            "UPDATE inventario SET deleted_at = NULL
              WHERE id_repuesto = ? AND id_empresa = ? AND deleted_at IS NOT NULL"
        );
        $st->execute([$id, $eid]);
        if ($st->rowCount() === 0) sadmin_json_err('Registro no encontrado o ya activo.');
        sadmin_json_ok(['msg' => 'Repuesto restaurado.']);
    }
}

sadmin_json_err('Acción desconocida.');
