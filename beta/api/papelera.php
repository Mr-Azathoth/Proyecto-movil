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
        $db->beginTransaction();
        try {
            $st = $db->prepare(
                "UPDATE reparaciones SET deleted_at = NULL
                  WHERE id_ingreso = ? AND id_empresa = ? AND deleted_at IS NOT NULL"
            );
            $st->execute([$id, $eid]);
            if ($st->rowCount() === 0) {
                $db->rollBack();
                sadmin_json_err('Registro no encontrado o ya activo.');
            }

            // Al borrar la reparacion se libera la reserva de sus repuestos (ver DELETE en
            // api/reparaciones.php); al restaurar hay que volver a reservarlos, o de lo
            // contrario la reparacion queda apuntando a un repuesto sin ninguna reserva real
            // detras, permitiendo que otro trabajo se quede con esa misma unidad.
            $sinStock = 0;

            $repRow = $db->prepare("SELECT id_repuesto_usado, stock_descontado, id_sucursal FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ?");
            $repRow->execute([$id, $eid]);
            $repRow = $repRow->fetch();
            // Las reservas se rehacen en el stock de la sucursal donde esta la reparacion.
            $sucRep = reparacion_sucursal($db, $eid, ($repRow && $repRow['id_sucursal'] !== null) ? (int)$repRow['id_sucursal'] : null);
            if ($repRow && $repRow['id_repuesto_usado'] && !(int)$repRow['stock_descontado']) {
                $idRp = (int) $repRow['id_repuesto_usado'];
                if (!stock_reservar($db, $eid, $idRp, $sucRep, 1)) {
                    // Ya no hay stock disponible: se desvincula en vez de dejar una referencia sin reserva.
                    $db->prepare("UPDATE reparaciones SET id_repuesto_usado = NULL WHERE id_ingreso = ? AND id_empresa = ?")
                       ->execute([$id, $eid]);
                    $sinStock++;
                }
            }

            $adic = $db->prepare("SELECT id, id_repuesto, cantidad FROM reparacion_repuestos WHERE id_reparacion = ? AND id_empresa = ? AND stock_desc = 0");
            $adic->execute([$id, $eid]);
            foreach ($adic->fetchAll() as $ar) {
                if (!stock_reservar($db, $eid, (int)$ar['id_repuesto'], $sucRep, (int)$ar['cantidad'])) {
                    $db->prepare("DELETE FROM reparacion_repuestos WHERE id = ? AND id_empresa = ?")
                       ->execute([(int)$ar['id'], $eid]);
                    $sinStock++;
                }
            }

            $db->prepare("INSERT INTO log_acciones (id_empresa, id_usuario, usuario, accion, id_reparacion, ip)
                          VALUES (?, ?, ?, 'reparacion_restaurada', ?, ?)")
               ->execute([$eid, sadmin_id(), sadmin_user(), $id, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        $msg = "Servicio #{$id} restaurado.";
        if ($sinStock) $msg .= " Aviso: {$sinStock} repuesto(s) ya no tenían stock disponible y quedaron desvinculados.";
        sadmin_json_ok(['msg' => $msg]);
    }

    if ($tipo === 'repuesto') {
        $st = $db->prepare(
            "UPDATE inventario SET deleted_at = NULL
              WHERE id_repuesto = ? AND id_empresa = ? AND deleted_at IS NOT NULL"
        );
        $st->execute([$id, $eid]);
        if ($st->rowCount() === 0) sadmin_json_err('Registro no encontrado o ya activo.');
        $db->prepare("INSERT INTO log_acciones (id_empresa, id_usuario, usuario, accion, id_reparacion, ip)
                      VALUES (?, ?, ?, 'repuesto_restaurado', ?, ?)")
           ->execute([$eid, sadmin_id(), sadmin_user(), $id, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        sadmin_json_ok(['msg' => 'Repuesto restaurado.']);
    }
}

sadmin_json_err('Acción desconocida.');
