<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: sucursales de la empresa (todos los roles) ───────────
if ($method === 'GET') {
    $sql = "SELECT s.id_sucursal, s.nombre, s.direccion, s.telefono, s.es_bodega, s.activa,
                   (SELECT COUNT(*) FROM usuarios u WHERE u.id_sucursal = s.id_sucursal AND u.id_empresa = s.id_empresa) AS n_usuarios,
                   (SELECT COUNT(*) FROM reparaciones r WHERE r.id_sucursal = s.id_sucursal AND r.id_empresa = s.id_empresa AND r.deleted_at IS NULL) AS n_reparaciones
              FROM sucursales s WHERE s.id_empresa = ?";
    if (!isAdmin()) $sql .= " AND s.activa = 1";
    $sql .= " ORDER BY s.es_bodega ASC, s.nombre ASC";
    $s = $db->prepare($sql);
    $s->execute([$eid]);
    $rows = $s->fetchAll();
    foreach ($rows as &$r) {
        $r['id_sucursal']    = (int) $r['id_sucursal'];
        $r['es_bodega']      = (int) $r['es_bodega'];
        $r['activa']         = (int) $r['activa'];
        $r['n_usuarios']     = (int) $r['n_usuarios'];
        $r['n_reparaciones'] = (int) $r['n_reparaciones'];
    }
    unset($r);
    // Casa matriz = datos de la empresa; las sucursales que dejan direccion/telefono vacios los heredan.
    $m = $db->prepare("SELECT nombre, direccion, comuna, telefono FROM empresas WHERE id_empresa = ?");
    $m->execute([$eid]);
    $matriz = $m->fetch() ?: [];
    json_ok([
        'sucursales' => $rows,
        'base'       => sucursal_base(),
        'escritura'  => sucursales_escritura(),
        'matriz'     => [
            'nombre'    => (string) ($matriz['nombre'] ?? ''),
            'direccion' => trim(implode(', ', array_filter([trim((string) ($matriz['direccion'] ?? '')), trim((string) ($matriz['comuna'] ?? ''))]))),
            'telefono'  => trim((string) ($matriz['telefono'] ?? '')),
        ],
    ]);
}

if (!isAdmin()) json_err('Sin permisos.', 403);
csrf_check();

function suc_datos(array $in): array {
    $nombre    = trim((string) ($in['nombre']    ?? ''));
    $direccion = trim((string) ($in['direccion'] ?? ''));
    $telefono  = trim((string) ($in['telefono']  ?? ''));
    if ($nombre === '')                json_err('El nombre de la sucursal es obligatorio.');
    if (mb_strlen($nombre) > 80)       json_err('Nombre demasiado largo (máx. 80).');
    if (mb_strlen($direccion) > 150)   json_err('Dirección demasiado larga (máx. 150).');
    // El telefono es opcional (vacio = el de la casa matriz) y PUEDE repetirse entre sucursales.
    if ($telefono !== '') {
        if (mb_strlen($telefono) > 30)                      json_err('Teléfono demasiado largo (máx. 30).');
        if (!preg_match('/^[0-9+().\s-]+$/', $telefono))      json_err('El teléfono solo puede tener números, espacios y los signos + ( ) - .');
        if (strlen(preg_replace('/\D/', '', $telefono)) < 6)  json_err('El teléfono debe tener al menos 6 dígitos.');
    }
    return [$nombre, $direccion, empty($in['es_bodega']) ? 0 : 1, $telefono];
}

// ── POST: crear sucursal ──────────────────────────────────────
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    [$nombre, $direccion, $bodega, $telefono] = suc_datos($in);

    $dup = $db->prepare("SELECT 1 FROM sucursales WHERE id_empresa = ? AND nombre = ?");
    $dup->execute([$eid, $nombre]);
    if ($dup->fetchColumn()) json_err('Ya existe una sucursal con ese nombre.');

    $db->prepare("INSERT INTO sucursales (id_empresa, nombre, direccion, telefono, es_bodega) VALUES (?,?,?,?,?)")
       ->execute([$eid, $nombre, $direccion, $telefono, $bodega]);
    $id = (int) $db->lastInsertId();
    log_accion($db, 'sucursal_creada', null, ['nombre' => $nombre, 'es_bodega' => $bodega, 'direccion' => $direccion, 'telefono' => $telefono], ['id' => $id]);
    json_ok(['msg' => "Sucursal {$nombre} creada.", 'id' => $id]);
}

// ── PUT: editar / activar / desactivar ────────────────────────
if ($method === 'PUT') {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($in['id_sucursal'] ?? 0);
    if (!$id) json_err('ID inválido.');

    $cur = $db->prepare("SELECT * FROM sucursales WHERE id_sucursal = ? AND id_empresa = ?");
    $cur->execute([$id, $eid]);
    $suc = $cur->fetch();
    if (!$suc) json_err('Sucursal no encontrada.', 404);

    [$nombre, $direccion, $bodega, $telefono] = suc_datos($in + ['nombre' => $suc['nombre'], 'direccion' => $suc['direccion'], 'telefono' => $suc['telefono'], 'es_bodega' => $suc['es_bodega']]);
    $activa = array_key_exists('activa', $in) ? (empty($in['activa']) ? 0 : 1) : (int) $suc['activa'];

    $dup = $db->prepare("SELECT 1 FROM sucursales WHERE id_empresa = ? AND nombre = ? AND id_sucursal <> ?");
    $dup->execute([$eid, $nombre, $id]);
    if ($dup->fetchColumn()) json_err('Ya existe una sucursal con ese nombre.');

    // Una sucursal "con atencion al publico" es activa y no bodega. Perderlo (desactivarla o convertirla
    // en bodega) deja sin sitio a sus usuarios y servicios, asi que se exige que no queden huerfanos.
    $desactiva    = $activa === 0 && (int) $suc['activa'] === 1;
    $aBodega      = $bodega === 1 && (int) $suc['es_bodega'] === 0;
    $eraServicio  = (int) $suc['activa'] === 1 && (int) $suc['es_bodega'] === 0;
    $seraServicio = $activa === 1 && $bodega === 0;
    $pierdeServicio = $eraServicio && !$seraServicio;

    if ($pierdeServicio) {
        $otras = $db->prepare("SELECT COUNT(*) FROM sucursales WHERE id_empresa = ? AND activa = 1 AND es_bodega = 0 AND id_sucursal <> ?");
        $otras->execute([$eid, $id]);
        if ((int) $otras->fetchColumn() === 0) {
            json_err($aBodega
                ? 'No puedes convertir en bodega la única sucursal con atención al público.'
                : 'No puedes desactivar la única sucursal con atención al público.');
        }
    }
    if ($desactiva || $aBodega) {
        $us = $db->prepare(
            "SELECT COUNT(*) FROM usuarios u WHERE u.id_empresa = ? AND (u.id_sucursal = ?
                OR EXISTS (SELECT 1 FROM usuario_sucursales x WHERE x.id_usuario = u.id_usuario AND x.id_sucursal = ?))"
        );
        $us->execute([$eid, $id, $id]);
        if ((int) $us->fetchColumn() > 0) {
            json_err('Hay usuarios asignados a esta sucursal. Reasígnalos antes de ' . ($aBodega ? 'convertirla en bodega.' : 'desactivarla.'));
        }
    }
    if ($aBodega) {
        $rp = $db->prepare("SELECT COUNT(*) FROM reparaciones WHERE id_empresa = ? AND id_sucursal = ? AND deleted_at IS NULL");
        $rp->execute([$eid, $id]);
        if ((int) $rp->fetchColumn() > 0) json_err('Esta sucursal tiene servicios registrados: no puede ser una bodega. Traslada o elimina esos servicios primero.');
    } elseif ($desactiva) {
        $rp = $db->prepare("SELECT COUNT(*) FROM reparaciones WHERE id_empresa = ? AND id_sucursal = ? AND deleted_at IS NULL AND status NOT IN ('Entregado')");
        $rp->execute([$eid, $id]);
        if ((int) $rp->fetchColumn() > 0) json_err('Hay reparaciones sin entregar en esta sucursal. Reasígnalas o ciérralas antes de desactivarla.');
    }

    $db->prepare("UPDATE sucursales SET nombre = ?, direccion = ?, telefono = ?, es_bodega = ?, activa = ? WHERE id_sucursal = ? AND id_empresa = ?")
       ->execute([$nombre, $direccion, $telefono, $bodega, $activa, $id, $eid]);
    log_accion($db, 'sucursal_editada', null, ['id' => $id, 'nombre' => $nombre, 'es_bodega' => $bodega, 'activa' => $activa, 'direccion' => $direccion, 'telefono' => $telefono]);
    json_ok(['msg' => 'Sucursal actualizada.']);
}

json_err('Método no permitido.', 405);
