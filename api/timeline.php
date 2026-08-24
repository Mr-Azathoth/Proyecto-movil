<?php
require_once __DIR__.'/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();
$db = getDB(); $eid = eid();
$id = (int)($_GET['id'] ?? 0);
if (!$id) json_err('ID requerido.');

// Verificar que la reparación existe y pertenece a la empresa del usuario
$chk = $db->prepare("SELECT id_ingreso FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ? LIMIT 1");
$chk->execute([$id, $eid]);
if (!$chk->fetch()) json_err('No encontrado.', 404);

$obs = $db->prepare("SELECT 'obs' AS tipo, obs AS texto, user, fecha FROM observaciones WHERE id_registro=? AND id_empresa=? ORDER BY fecha DESC");
$obs->execute([$id,$eid]);

$hist = $db->prepare("SELECT 'hist' AS tipo, CONCAT('Estado: ',status_anterior,' → ',status_cambio, IF(detalle IS NOT NULL AND detalle<>'', CONCAT('\n',detalle),'')) AS texto, user, fecha_cambio AS fecha FROM historial WHERE id_reparacion=? AND id_empresa=? ORDER BY fecha_cambio DESC");
$hist->execute([$id,$eid]);

$f = $db->prepare(
    "SELECT 'foto' AS tipo, CONCAT('Foto · ', etiqueta) AS texto, subida_por AS user, fecha, url, etiqueta
       FROM reparacion_fotos WHERE id_reparacion = ? AND id_empresa = ? ORDER BY fecha DESC"
);
$f->execute([$id, $eid]);
$fotos = $f->fetchAll();

$items = array_merge($obs->fetchAll(), $hist->fetchAll(), $fotos);
usort($items, fn($a,$b) => strtotime($b['fecha']) - strtotime($a['fecha']));
json_ok($items);
