<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db  = getDB();
$eid = eid();

// Migración silenciosa de columnas nuevas (una por una para compatibilidad MySQL 5.x)
foreach ([
    "ALTER TABLE empresas ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE empresas ADD COLUMN direccion VARCHAR(150) DEFAULT ''",
    "ALTER TABLE empresas ADD COLUMN telefono  VARCHAR(30)  DEFAULT ''",
    "ALTER TABLE empresas ADD COLUMN correo    VARCHAR(80)  DEFAULT ''",
    "ALTER TABLE empresas ADD COLUMN comuna    VARCHAR(60)  DEFAULT ''",
    "ALTER TABLE empresas ADD COLUMN region    VARCHAR(60)  DEFAULT ''",
    "ALTER TABLE empresas ADD COLUMN default_tipo_equipo VARCHAR(100) DEFAULT ''",
] as $_sql) {
    try { $db->exec($_sql); } catch (PDOException $ignored) {}
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: datos de la empresa ──────────────────────────────────
if ($method === 'GET') {
    $s = $db->prepare("SELECT nombre, logo_path, direccion, telefono, correo, comuna, region, default_tipo_equipo
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
    $allowed = ['direccion', 'telefono', 'correo', 'comuna', 'region', 'default_tipo_equipo'];
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
    log_accion($db, 'empresa_contacto_actualizado', null, array_intersect_key($in, array_flip($allowed)));
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
        $mime_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime     = mime_content_type($file['tmp_name']);
        if (!array_key_exists($mime, $mime_map)) json_err('Tipo de imagen no permitido (jpg, png, webp, gif).');
        if ($file['size'] > 2 * 1024 * 1024) json_err('La imagen debe ser menor a 2 MB.');

        // Extensión derivada del MIME real, nunca del nombre de archivo del usuario
        $ext      = $mime_map[$mime];
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
    log_accion($db, 'empresa_identidad_actualizada', null, ['nombre' => $nombre ?: null, 'logo_subido' => isset($_FILES['logo'])]);

    $s = $db->prepare("SELECT nombre, logo_path FROM empresas WHERE id_empresa = ?");
    $s->execute([$eid]);
    json_ok($s->fetch());
}
