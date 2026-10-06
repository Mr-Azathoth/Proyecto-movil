<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();
if (!isAdmin()) json_err('Solo un administrador puede gestionar traspasos.', 403);

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: historial de traspasos (opcionalmente de un repuesto) ──
if ($method === 'GET') {
    $sql = "SELECT t.id, t.id_repuesto, i.nombre AS repuesto, t.id_origen, so.nombre AS origen,
                   t.id_destino, sd.nombre AS destino, t.cantidad, t.usuario, t.nota, t.fecha
              FROM traspasos t
              LEFT JOIN inventario i  ON i.id_repuesto  = t.id_repuesto  AND i.id_empresa  = t.id_empresa
              LEFT JOIN sucursales so ON so.id_sucursal = t.id_origen    AND so.id_empresa = t.id_empresa
              LEFT JOIN sucursales sd ON sd.id_sucursal = t.id_destino   AND sd.id_empresa = t.id_empresa
             WHERE t.id_empresa = ?";
    $p = [$eid];
    $rid = (int) ($_GET['id_repuesto'] ?? 0);
    if ($rid) { $sql .= " AND t.id_repuesto = ?"; $p[] = $rid; }
    $sql .= " ORDER BY t.id DESC LIMIT 30";
    $s = $db->prepare($sql);
    $s->execute($p);
    json_ok($s->fetchAll());
}

if ($method !== 'POST') json_err('Método no permitido.', 405);
csrf_check();

// ── POST: mover stock disponible de una sucursal a otra ──
$in      = json_decode(file_get_contents('php://input'), true) ?? [];
$rid     = (int) ($in['id_repuesto'] ?? 0);
$origen  = (int) ($in['id_origen']   ?? 0);
$destino = (int) ($in['id_destino']  ?? 0);
$cant    = (int) ($in['cantidad']    ?? 0);
$nota    = mb_substr(trim((string) ($in['nota'] ?? '')), 0, 200);

if (!$rid || !$origen || !$destino) json_err('Datos incompletos.');
if ($cant < 1)                      json_err('La cantidad debe ser al menos 1.');
if ($origen === $destino)           json_err('El origen y el destino deben ser distintos.');

$r = $db->prepare("SELECT nombre FROM inventario WHERE id_repuesto = ? AND id_empresa = ? AND deleted_at IS NULL");
$r->execute([$rid, $eid]);
$rep = $r->fetch();
if (!$rep) json_err('Repuesto no encontrado.', 404);

$sucs = [];
foreach ([$origen, $destino] as $sid) {
    $x = sucursal_de_empresa($db, $eid, $sid);
    if (!$x || !(int) $x['activa']) json_err('Sucursal inválida.');
    $sucs[$sid] = $x['nombre'];
}

// Dos traspasos en sentido contrario (A->B y B->A) bloquearian las mismas dos filas en orden inverso y
// se provocarian un deadlock. Por eso se crea la fila destino si falta, se bloquean AMBAS en orden fijo
// de id_sucursal y, si aun asi InnoDB aborta por concurrencia, se reintenta.
$idTr = 0;
for ($intento = 1; $intento <= 5; $intento++) {
    $db->beginTransaction();
    try {
        $lk = $db->prepare("SELECT id_sucursal FROM inventario_stock
                             WHERE id_repuesto = ? AND id_empresa = ? AND id_sucursal IN (?, ?)
                             ORDER BY id_sucursal FOR UPDATE");
        $lk->execute([$rid, $eid, $origen, $destino]);
        // La fila destino solo se crea si falta (y ya con las demas bloqueadas): un INSERT IGNORE previo
        // tomaria un bloqueo compartido sobre la fila existente y dos traspasos opuestos se bloquearian
        // al intentar subirlo a exclusivo.
        if (!in_array($destino, array_map("intval", $lk->fetchAll(PDO::FETCH_COLUMN)), true)) {
            $db->prepare("INSERT IGNORE INTO inventario_stock (id_repuesto, id_sucursal, id_empresa, cantidad) VALUES (?,?,?,0)")
               ->execute([$rid, $destino, $eid]);
        }

        // Solo se mueve stock DISPONIBLE: el reservado para trabajos activos no sale de su sucursal.
        $out = $db->prepare("UPDATE inventario_stock SET cantidad = cantidad - ?
                              WHERE id_repuesto = ? AND id_sucursal = ? AND id_empresa = ?
                                AND (cantidad - cantidad_reservada) >= ?");
        $out->execute([$cant, $rid, $origen, $eid, $cant]);
        if ($out->rowCount() === 0) {
            $disp = stock_disponible($db, $eid, $rid, $origen);
            $db->rollBack();
            json_err("Stock disponible insuficiente en {$sucs[$origen]} (disponible: {$disp}).");
        }
        $db->prepare("UPDATE inventario_stock SET cantidad = cantidad + ?
                       WHERE id_repuesto = ? AND id_sucursal = ? AND id_empresa = ?")
           ->execute([$cant, $rid, $destino, $eid]);
        $db->prepare("INSERT INTO traspasos (id_empresa, id_repuesto, id_origen, id_destino, cantidad, id_usuario, usuario, nota)
                      VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$eid, $rid, $origen, $destino, $cant, uid() ?: null, uname(), $nota]);
        $idTr = (int) $db->lastInsertId();
        $db->commit();
        break;
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $transitorio = (bool) preg_match('/\b(1213|1205)\b|deadlock|lock wait timeout/i', $e->getMessage());
        if ($transitorio && $intento < 5) { usleep(random_int(20000, 120000)); continue; }
        error_log('[traspasos] ' . $e->getMessage());
        json_err('No se pudo completar el traspaso por concurrencia. Inténtalo de nuevo.', 503);
    }
}

log_accion($db, 'traspaso_stock', null,
    ['id_repuesto' => $rid, 'origen' => $origen, 'destino' => $destino, 'cantidad' => $cant], ['id' => $idTr]);
json_ok(['msg' => "Traspasadas {$cant} unidad" . ($cant !== 1 ? 'es' : '') . " de {$rep['nombre']}: {$sucs[$origen]} → {$sucs[$destino]}.", 'id' => $idTr]);
