<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_config.php';
sadmin_guard();
sadmin_csrf_check();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sadmin_json_err('Método no permitido.', 405);

$file = $_FILES['imagen'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) sadmin_json_err('Error al recibir imagen.');
if ($file['size'] > 3 * 1024 * 1024) sadmin_json_err('La imagen supera el límite de 3 MB.');

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!array_key_exists($mime, $allowed)) sadmin_json_err('Tipo de archivo no permitido.');

$dir = __DIR__ . '/../assets/uploads/tickets/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$fname = 'adm_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $dir . $fname)) sadmin_json_err('Error al guardar imagen.');

sadmin_json_ok(['url' => BASE . '/assets/uploads/tickets/' . $fname]);
