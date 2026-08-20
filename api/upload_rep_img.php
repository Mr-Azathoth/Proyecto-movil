<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido.', 405);
csrf_check();

$db  = getDB();
$eid = eid();

$id_reparacion = (int) ($_POST['id_reparacion'] ?? 0);
if (!$id_reparacion) json_err('ID de servicio requerido.');

$etiqueta_raw = trim($_POST['etiqueta'] ?? 'Reparación');
$etiqueta = in_array($etiqueta_raw, ['Ingreso', 'Reparación'], true) ? $etiqueta_raw : 'Reparación';

// Verificar propiedad
$chk = $db->prepare("SELECT id_ingreso FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ? AND deleted_at IS NULL");
$chk->execute([$id_reparacion, $eid]);
if (!$chk->fetch()) json_err('Servicio no encontrado.', 404);

// Límite de 3 fotos
$cnt = $db->prepare("SELECT COUNT(*) FROM reparacion_fotos WHERE id_reparacion = ? AND id_empresa = ?");
$cnt->execute([$id_reparacion, $eid]);
if ((int) $cnt->fetchColumn() >= 3) json_err('Máximo 3 fotos por servicio.');

$file = $_FILES['imagen'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) json_err('Error al recibir imagen.');
if ($file['size'] > 5 * 1024 * 1024) json_err('La imagen supera el límite de 5 MB.');

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!array_key_exists($mime, $allowed)) json_err('Tipo de archivo no permitido (JPG, PNG, WebP).');

$dir = __DIR__ . '/../assets/uploads/reparaciones/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$fname = $eid . '_r' . $id_reparacion . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $dir . $fname)) json_err('Error al guardar imagen.');

$url = BASE . '/assets/uploads/reparaciones/' . $fname;

$ins = $db->prepare(
    "INSERT INTO reparacion_fotos (id_empresa, id_reparacion, url, etiqueta, subida_por)
     VALUES (?, ?, ?, ?, ?)"
);
$ins->execute([$eid, $id_reparacion, $url, $etiqueta, uname()]);
$foto_id = (int) $db->lastInsertId();

json_ok(['id' => $foto_id, 'url' => $url, 'etiqueta' => $etiqueta]);
