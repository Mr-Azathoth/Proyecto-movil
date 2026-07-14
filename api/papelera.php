<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

if (!isDueno()) json_err('Sin permiso.', 403);

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $reps = $db->prepare(
        "SELECT id_ingreso, nombre_cliente, telefono_cliente,
                marca_ingreso, modelo_ingreso, status, deleted_at
           FROM reparaciones
          WHERE id_empresa = ? AND deleted_at IS NOT NULL
          ORDER BY deleted_at DESC"
    );
    $reps->execute([$eid]);

    $inv = $db->prepare(
        "SELECT id_repuesto, nombre, marca_compatible, modelo_compatible,
                precio_venta, cantidad, deleted_at
           FROM inventario
          WHERE id_empresa = ? AND deleted_at IS NOT NULL
          ORDER BY deleted_at DESC"
    );
    $inv->execute([$eid]);

    json_ok([
        'reparaciones' => $reps->fetchAll(),
        'inventario'   => $inv->fetchAll(),
    ]);
}

if ($method === 'PUT') {
    csrf_check();
    $in   = json_decode(file_get_contents('php://input'), true) ?? [];
    $tipo = $in['tipo'] ?? '';
    $id   = (int) ($in['id'] ?? 0);

    if (!$id || !in_array($tipo, ['reparacion', 'repuesto'], true)) {
        json_err('Parámetros inválidos.');
    }

    if ($tipo === 'reparacion') {
        $st = $db->prepare(
            "UPDATE reparaciones SET deleted_at = NULL
              WHERE id_ingreso = ? AND id_empresa = ? AND deleted_at IS NOT NULL"
        );
        $st->execute([$id, $eid]);
        if ($st->rowCount() === 0) json_err('Registro no encontrado o ya activo.');
        log_accion($db, 'reparacion_restaurada', $id);
        json_ok(['msg' => "Servicio #{$id} restaurado."]);
    }

    if ($tipo === 'repuesto') {
        $st = $db->prepare(
            "UPDATE inventario SET deleted_at = NULL
              WHERE id_repuesto = ? AND id_empresa = ? AND deleted_at IS NOT NULL"
        );
        $st->execute([$id, $eid]);
        if ($st->rowCount() === 0) json_err('Registro no encontrado o ya activo.');
        log_accion($db, 'repuesto_restaurado', $id);
        json_ok(['msg' => 'Repuesto restaurado.']);
    }
}
