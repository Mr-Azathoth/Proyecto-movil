<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db  = getDB();
$eid = eid();

// Migración silenciosa de columnas nuevas
try {
    $db->exec("ALTER TABLE empresas
        ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS direccion VARCHAR(150) DEFAULT '',
        ADD COLUMN IF NOT EXISTS telefono  VARCHAR(30)  DEFAULT '',
        ADD COLUMN IF NOT EXISTS correo    VARCHAR(80)  DEFAULT '',
        ADD COLUMN IF NOT EXISTS comuna    VARCHAR(60)  DEFAULT '',
        ADD COLUMN IF NOT EXISTS region    VARCHAR(60)  DEFAULT ''");
} catch (PDOException $ignored) {}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: datos de la empresa ──────────────────────────────────
if ($method === 'GET') {
    $s = $db->prepare("SELECT nombre, logo_path, direccion, telefono, correo, comuna, region
                       FROM empresas WHERE id_empresa = ?");
    $s->execute([$eid]);
    $row = $s->fetch();
    if (!$row) json_err('Empresa no encontrada.', 404);
    json_ok($row);
}

// ── PUT: actualizar datos de contacto ─────────────────────────
if ($method === 'PUT') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['direccion', 'telefono', 'correo', 'comuna', 'region'];
    $sets = []; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $in)) {
            $sets[] = "$f = ?";
            $vals[] = trim((string)$in[$f]);
        }
    }
    if (!$sets) json_err('Nada que actualizar.');
    $vals[] = $eid;
    $db->prepare("UPDATE empresas SET " . implode(', ', $sets) . " WHERE id_empresa = ?")
       ->execute($vals);
    log_accion($db, 'empresa_contacto_actualizado', null);
    json_ok(['msg' => 'Datos guardados.']);
}

// ── POST: nombre + logo (multipart) ──────────────────────────
if ($method === 'POST') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();
    $sets = []; $vals = [];

    // Nombre
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre !== '') {
        if (strlen($nombre) > 80) json_err('Nombre demasiado largo.');
        $sets[] = "nombre = ?"; $vals[] = $nombre;
    }

    // Logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['logo'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed)) json_err('Tipo de imagen no permitido (jpg, png, webp, gif).');
        if ($file['size'] > 2 * 1024 * 1024) json_err('La imagen debe ser menor a 2 MB.');

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = "logo_{$eid}_" . time() . "." . $ext;
        $dir      = __DIR__ . '/../assets/uploads/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Eliminar logo anterior
        $prev = $db->prepare("SELECT logo_path FROM empresas WHERE id_empresa = ?");
        $prev->execute([$eid]);
        $old = $prev->fetchColumn();
        if ($old && file_exists(__DIR__ . '/../' . $old)) @unlink(__DIR__ . '/../' . $old);

        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) json_err('Error al guardar la imagen.');
        $sets[] = "logo_path = ?"; $vals[] = "assets/uploads/" . $filename;
    }

    if (!$sets) json_ok(['msg' => 'Sin cambios.', 'data' => null]);
    $vals[] = $eid;
    $db->prepare("UPDATE empresas SET " . implode(', ', $sets) . " WHERE id_empresa = ?")
       ->execute($vals);
    log_accion($db, 'empresa_identidad_actualizada', null);

    $s = $db->prepare("SELECT nombre, logo_path FROM empresas WHERE id_empresa = ?");
    $s->execute([$eid]);
    json_ok($s->fetch());
}
