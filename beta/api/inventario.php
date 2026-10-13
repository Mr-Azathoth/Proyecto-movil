<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: catalogo con stock de una sucursal (?sucursal=ID) o el total de todas ──
if ($method === 'GET') {
    $q   = trim($_GET['q'] ?? '');
    $suc = sucursal_filtro($db, $eid);

    $cols = "i.id_repuesto, i.id_empresa, i.codigo, i.nombre, i.marca_compatible, i.modelo_compatible, i.precio_venta, i.stock_minimo, i.deleted_at";
    if ($suc !== null) {
        $sql = "SELECT $cols, COALESCE(s.cantidad, 0) AS cantidad, COALESCE(s.cantidad_reservada, 0) AS cantidad_reservada
                  FROM inventario i
                  LEFT JOIN inventario_stock s ON s.id_repuesto = i.id_repuesto AND s.id_sucursal = ?
                 WHERE i.id_empresa = ? AND i.deleted_at IS NULL";
        $p = [$suc, $eid];
    } else {
        $sql = "SELECT $cols, COALESCE(t.c, 0) AS cantidad, COALESCE(t.r, 0) AS cantidad_reservada
                  FROM inventario i
                  LEFT JOIN (SELECT id_repuesto, SUM(cantidad) AS c, SUM(cantidad_reservada) AS r
                               FROM inventario_stock WHERE id_empresa = ? GROUP BY id_repuesto) t
                         ON t.id_repuesto = i.id_repuesto
                 WHERE i.id_empresa = ? AND i.deleted_at IS NULL";
        $p = [$eid, $eid];
    }

    if ($q) {
        $sql .= " AND (i.nombre LIKE ? OR i.marca_compatible LIKE ? OR i.modelo_compatible LIKE ?)";
        $like = "%" . addcslashes($q, '%_\\') . "%";
        $p    = array_merge($p, [$like, $like, $like]);
    }
    $sql .= " ORDER BY i.nombre ASC";

    $s = $db->prepare($sql);
    $s->execute($p);
    $rows = $s->fetchAll();

    // Desglose por sucursal (para mostrar donde mas hay y para los traspasos)
    $porRep = [];
    $idsDev = array_map('intval', array_column($rows, 'id_repuesto'));
    if ($idsDev) {
        if (count($idsDev) <= 1000) {
            $st = $db->prepare("SELECT id_repuesto, id_sucursal, cantidad, cantidad_reservada FROM inventario_stock
                                 WHERE id_empresa = ? AND id_repuesto IN (" . implode(',', array_fill(0, count($idsDev), '?')) . ")");
            $st->execute(array_merge([$eid], $idsDev));
        } else {
            $st = $db->prepare("SELECT id_repuesto, id_sucursal, cantidad, cantidad_reservada FROM inventario_stock WHERE id_empresa = ?");
            $st->execute([$eid]);
        }
    }
    foreach ($idsDev ? $st->fetchAll() : [] as $r) {
        $porRep[(int)$r['id_repuesto']][] = [
            'id_sucursal'        => (int) $r['id_sucursal'],
            'cantidad'           => (int) $r['cantidad'],
            'cantidad_reservada' => (int) $r['cantidad_reservada'],
        ];
    }
    foreach ($rows as &$r) {
        $r['cantidad']           = (int) $r['cantidad'];
        $r['cantidad_reservada'] = (int) $r['cantidad_reservada'];
        $r['stock']              = $porRep[(int)$r['id_repuesto']] ?? [];
    }
    unset($r);
    json_ok($rows);
}

// ── POST: crear repuesto (admin). La cantidad inicial va a la sucursal indicada ──
if ($method === 'POST') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();

    $f = [
        'nombre'            => trim($_POST['nombre']            ?? ''),
        'marca_compatible'  => trim($_POST['marca_compatible']  ?? ''),
        'modelo_compatible' => trim($_POST['modelo_compatible'] ?? ''),
        'precio_venta'      => max(0, (int) ($_POST['precio_venta'] ?? 0)),
        'cantidad'          => max(0, (int) ($_POST['cantidad']     ?? 0)),
        'stock_minimo'      => max(0, min(1000000, (int) ($_POST['stock_minimo'] ?? 0))),
    ];

    if (!$f['nombre']) json_err('El nombre es obligatorio.');
    if (strlen($f['nombre']) > 100) json_err('Nombre demasiado largo (máx. 100 caracteres).');

    $sid = sucursal_para_escritura($db, $eid, $_POST['id_sucursal'] ?? null);

    // Auto-generar código único a partir del nombre
    $slug   = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $f['nombre']));
    $prefix = substr($slug, 0, 6) ?: 'REP';
    $f['codigo'] = $prefix . '-' . substr(uniqid(), -5);

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO inventario
            (id_empresa, codigo, nombre, marca_compatible, modelo_compatible, precio_venta, stock_minimo)
            VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$eid, $f['codigo'], $f['nombre'], $f['marca_compatible'],
                      $f['modelo_compatible'], $f['precio_venta'], $f['stock_minimo']]);
        $newRid = (int) $db->lastInsertId();
        stock_fijar($db, $eid, $newRid, $sid, $f['cantidad']);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    log_accion($db, 'repuesto_creado_inv', null, $f + ['id_sucursal' => $sid], ['id' => $newRid]);
    json_ok(['msg' => 'Repuesto agregado.']);
}

// Si el stock fisico queda por debajo de lo reservado para trabajos activos, se avisa (no se bloquea:
// puede ser una perdida real de unidades).
function aviso_reservas(PDO $db, int $eid, int $rid, int $sid, int $qty): array {
    $r = $db->prepare("SELECT cantidad_reservada FROM inventario_stock WHERE id_repuesto = ? AND id_sucursal = ? AND id_empresa = ?");
    $r->execute([$rid, $sid, $eid]);
    $res = (int) $r->fetchColumn();
    if ($res <= $qty) return [];
    return ['aviso' => "Atención: hay {$res} unidad" . ($res !== 1 ? 'es' : '') . " reservada" . ($res !== 1 ? 's' : '') . " para trabajos activos y solo quedan {$qty} en stock."];
}

// ── PUT: editar catalogo (admin) y/o fijar stock de una sucursal ──
if ($method === 'PUT') {
    csrf_check();

    $in  = json_decode(file_get_contents('php://input'), true) ?? [];
    $rid = (int) ($in['id'] ?? 0);
    if (!$rid) json_err('ID inválido.');

    $check = $db->prepare("SELECT id_repuesto FROM inventario WHERE id_repuesto = ? AND id_empresa = ?");
    $check->execute([$rid, $eid]);
    if (!$check->fetch()) json_err('Repuesto no encontrado.', 404);

    $datosCatalogo = isset($in['nombre']);
    $esDelta       = array_key_exists('cantidad_delta', $in);
    $datosStock    = $esDelta || array_key_exists('cantidad', $in);
    if (!$datosCatalogo && !$datosStock) json_err('Nada que actualizar.');

    if ($datosCatalogo) {
        if (!isAdmin()) json_err('Sin permisos.', 403);
        $nombre = trim($in['nombre']);
        $marca  = trim($in['marca_compatible']  ?? '');
        $modelo = trim($in['modelo_compatible'] ?? '');
        $precio = max(0, (int) ($in['precio_venta'] ?? 0));
        // Si el cliente no envia el minimo, se conserva el actual.
        $minimo = array_key_exists('stock_minimo', $in) ? max(0, min(1000000, (int) $in['stock_minimo'])) : null;
        if (!$nombre) json_err('El nombre es obligatorio.');
        if (strlen($nombre) > 100) json_err('Nombre demasiado largo (máx. 100).');
    }
    // Se valida el permiso sobre la sucursal ANTES de escribir cualquier cosa.
    if ($datosStock && isVendedor()) json_err('Un vendedor puede consultar el inventario, no modificar el stock.', 403);
    if ($datosStock) {
        $delta = $esDelta ? max(-1000000, min(1000000, (int) $in['cantidad_delta'])) : 0;
        $qty   = $esDelta ? 0 : max(0, (int) $in['cantidad']);
        $sid   = sucursal_para_escritura($db, $eid, $in['id_sucursal'] ?? null);
    }

    $db->beginTransaction();
    try {
        if ($datosCatalogo) {
            $db->prepare("UPDATE inventario
                SET nombre=?, marca_compatible=?, modelo_compatible=?, precio_venta=?, stock_minimo=COALESCE(?, stock_minimo)
                WHERE id_repuesto=? AND id_empresa=?")
               ->execute([$nombre, $marca, $modelo, $precio, $minimo, $rid, $eid]);
        }
        // cantidad_reservada NO se toca aqui: refleja reservas reales de trabajos activos.
        // Los botones +/- envian un delta: se acumula en la BD en vez de pisar el valor con uno que
        // la pantalla pudo leer hace rato (traspasos o consumos intermedios se conservan).
        if ($datosStock) {
            if ($esDelta) $qty = stock_ajustar($db, $eid, $rid, $sid, $delta);
            else          stock_fijar($db, $eid, $rid, $sid, $qty);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    if ($datosCatalogo) {
        log_accion($db, 'repuesto_editado_inv', null, ['id' => $rid, 'nombre' => $nombre, 'precio_venta' => $precio, 'stock_minimo' => $minimo] + ($datosStock ? ['cantidad' => $qty, 'id_sucursal' => $sid] : []));
        json_ok(['msg' => 'Repuesto actualizado.'] + ($datosStock ? ['cantidad' => $qty] + aviso_reservas($db, $eid, $rid, $sid, $qty) : []));
    }
    log_accion($db, 'stock_actualizado_inv', null, ['id' => $rid, 'cantidad_nueva' => $qty, 'id_sucursal' => $sid] + ($esDelta ? ['delta' => $delta] : []));
    json_ok(['msg' => 'Stock actualizado.', 'cantidad' => $qty] + aviso_reservas($db, $eid, $rid, $sid, $qty));
}

if ($method === 'DELETE') {
    if (!isAdmin()) json_err('Sin permiso.', 403);
    csrf_check();
    $rid = (int) ($_GET['id'] ?? 0);
    if (!$rid) json_err('ID inválido.');
    $st = $db->prepare("UPDATE inventario SET deleted_at = NOW() WHERE id_repuesto=? AND id_empresa=? AND deleted_at IS NULL");
    $st->execute([$rid, $eid]);
    if ($st->rowCount() === 0) json_err('Repuesto no encontrado.');
    log_accion($db, 'repuesto_eliminado', null, ['id' => $rid]);
    json_ok(['msg' => 'Repuesto eliminado.']);
}
