<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) json_err('ID requerido.');

    $chk = $db->prepare("SELECT id_ingreso FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ? AND deleted_at IS NULL");
    $chk->execute([$id, $eid]);
    if (!$chk->fetch()) json_err('No encontrado.', 404);

    $st = $db->prepare(
        "SELECT id, url, etiqueta, subida_por, fecha
           FROM reparacion_fotos
          WHERE id_reparacion = ? AND id_empresa = ?
          ORDER BY fecha ASC"
    );
    $st->execute([$id, $eid]);
    json_ok($st->fetchAll());
}

if ($method === 'DELETE') {
    csrf_check();
    $in     = json_decode(file_get_contents('php://input'), true) ?? [];
    $foto_id = (int) ($in['id'] ?? 0);
    if (!$foto_id) json_err('ID inválido.');

    $st = $db->prepare("SELECT url FROM reparacion_fotos WHERE id = ? AND id_empresa = ?");
    $st->execute([$foto_id, $eid]);
    $foto = $st->fetch();
    if (!$foto) json_err('Foto no encontrada.', 404);

    // Eliminar archivo físico
    $upload_dir = realpath(__DIR__ . '/../assets/uploads/reparaciones');
    $url_path   = parse_url($foto['url'], PHP_URL_PATH);
    $base_path  = parse_url(BASE, PHP_URL_PATH);
    $rel        = ($base_path && strncmp($url_path, $base_path, strlen($base_path)) === 0)
                    ? substr($url_path, strlen($base_path))
                    : $url_path;
    $file_path  = realpath(__DIR__ . '/..') . $rel;
    $real_path  = $file_path ? realpath($file_path) : false;
    if ($real_path && $upload_dir && strncmp($real_path, $upload_dir, strlen($upload_dir)) === 0) {
        @unlink($real_path);
    }

    $db->prepare("DELETE FROM reparacion_fotos WHERE id = ? AND id_empresa = ?")->execute([$foto_id, $eid]);
    json_ok(['msg' => 'Foto eliminada.']);
}

json_err('Método no soportado.', 405);
