<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

// Migración silenciosa
$db->exec("CREATE TABLE IF NOT EXISTS reparacion_repuestos (
    id            INT NOT NULL AUTO_INCREMENT,
    id_empresa    INT NOT NULL,
    id_reparacion INT NOT NULL,
    id_repuesto   INT NOT NULL,
    nombre_snap   VARCHAR(120) NOT NULL,
    precio_snap   INT NOT NULL DEFAULT 0,
    cantidad      INT NOT NULL DEFAULT 1,
    stock_desc    TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (id_reparacion) REFERENCES reparaciones(id_ingreso) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── GET: listar repuestos de un servicio ──────────────────
if ($method === 'GET') {
    $id_reparacion = (int) ($_GET['id'] ?? 0);
    if (!$id_reparacion) json_err('ID requerido.');

    $chk = $db->prepare(
        "SELECT id_ingreso, id_repuesto_usado, stock_descontado
           FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ?"
    );
    $chk->execute([$id_reparacion, $eid]);
    $servicio = $chk->fetch();
    if (!$servicio) json_err('Servicio no encontrado.', 404);

    // Repuesto inicial (snapshot desde inventario actual)
    $inicial = null;
    if ($servicio['id_repuesto_usado']) {
        $ri = $db->prepare(
            "SELECT id_repuesto, nombre, precio_venta
               FROM inventario WHERE id_repuesto = ? AND id_empresa = ?"
        );
        $ri->execute([$servicio['id_repuesto_usado'], $eid]);
        $row = $ri->fetch();
        if ($row) {
            $inicial = [
                'id_repuesto' => (int) $row['id_repuesto'],
                'nombre'      => $row['nombre'],
                'precio'      => (int) $row['precio_venta'],
                'stock_desc'  => (int) $servicio['stock_descontado'],
            ];
        }
    }

    // Repuestos adicionales
    $s = $db->prepare(
        "SELECT * FROM reparacion_repuestos
          WHERE id_reparacion = ? AND id_empresa = ? ORDER BY id ASC"
    );
    $s->execute([$id_reparacion, $eid]);

    json_ok(['inicial' => $inicial, 'adicionales' => $s->fetchAll()]);
}

// ── POST: agregar repuesto adicional ──────────────────────
if ($method === 'POST') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();

    $in            = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_reparacion = (int) ($in['id_reparacion'] ?? 0);
    $id_repuesto   = (int) ($in['id_repuesto']   ?? 0);
    $cantidad      = max(1, (int) ($in['cantidad'] ?? 1));

    if (!$id_reparacion || !$id_repuesto) json_err('Datos incompletos.');

    // Verificar servicio
    $chk = $db->prepare(
        "SELECT id_ingreso FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ?"
    );
    $chk->execute([$id_reparacion, $eid]);
    if (!$chk->fetch()) json_err('Servicio no encontrado.', 404);

    // Snapshot del repuesto
    $ri = $db->prepare(
        "SELECT nombre, marca_compatible, modelo_compatible, precio_venta FROM inventario WHERE id_repuesto = ? AND id_empresa = ?"
    );
    $ri->execute([$id_repuesto, $eid]);
    $rep = $ri->fetch();
    if (!$rep) json_err('Repuesto no encontrado.', 404);

    $nombre_snap = $rep['nombre'];
    if ($rep['marca_compatible'])  $nombre_snap .= ' · ' . $rep['marca_compatible'];
    if ($rep['modelo_compatible']) $nombre_snap .= ' · ' . $rep['modelo_compatible'];

    $db->prepare(
        "INSERT INTO reparacion_repuestos
             (id_empresa, id_reparacion, id_repuesto, nombre_snap, precio_snap, cantidad)
         VALUES (?,?,?,?,?,?)"
    )->execute([$eid, $id_reparacion, $id_repuesto, $nombre_snap, $rep['precio_venta'], $cantidad]);
    $newId = (int) $db->lastInsertId();

    // Sumar precio al valor del servicio
    $delta = (int) $rep['precio_venta'] * $cantidad;
    $db->prepare(
        "UPDATE reparaciones SET valor_ingreso = valor_ingreso + ?
          WHERE id_ingreso = ? AND id_empresa = ?"
    )->execute([$delta, $id_reparacion, $eid]);

    $qv = $db->prepare("SELECT valor_ingreso FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ?");
    $qv->execute([$id_reparacion, $eid]);
    $nuevo_valor = (int) $qv->fetchColumn();

    log_accion($db, 'repuesto_agregado', $id_reparacion);

    json_ok([
        'id'          => $newId,
        'nombre_snap' => $nombre_snap,
        'precio_snap' => (int) $rep['precio_venta'],
        'cantidad'    => $cantidad,
        'nuevo_valor' => $nuevo_valor,
    ]);
}

// ── DELETE: quitar repuesto adicional ─────────────────────
if ($method === 'DELETE') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();

    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($in['id'] ?? 0);
    if (!$id) json_err('ID inválido.');

    $s = $db->prepare(
        "SELECT * FROM reparacion_repuestos WHERE id = ? AND id_empresa = ?"
    );
    $s->execute([$id, $eid]);
    $row = $s->fetch();
    if (!$row) json_err('No encontrado.', 404);

    $db->prepare(
        "DELETE FROM reparacion_repuestos WHERE id = ? AND id_empresa = ?"
    )->execute([$id, $eid]);

    // Restar precio al valor del servicio
    $delta = (int) $row['precio_snap'] * (int) $row['cantidad'];
    $db->prepare(
        "UPDATE reparaciones SET valor_ingreso = GREATEST(0, valor_ingreso - ?)
          WHERE id_ingreso = ? AND id_empresa = ?"
    )->execute([$delta, (int) $row['id_reparacion'], $eid]);

    $qv = $db->prepare("SELECT valor_ingreso FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ?");
    $qv->execute([(int) $row['id_reparacion'], $eid]);
    $nuevo_valor = $qv->fetchColumn();
    if ($nuevo_valor === false) json_err('Reparación no encontrada.', 404);

    log_accion($db, 'repuesto_eliminado', (int) $row['id_reparacion']);

    json_ok(['nuevo_valor' => $nuevo_valor]);
}
