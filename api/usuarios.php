<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db  = getDB();
$eid = eid();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: listar usuarios de la empresa ────────────────────────
if ($method === 'GET') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    $s = $db->prepare(
        "SELECT id_usuario, nombre, user, cargo, activo
         FROM usuarios WHERE id_empresa = ? ORDER BY cargo DESC, nombre ASC"
    );
    $s->execute([$eid]);
    json_ok($s->fetchAll());
}

// ── PUT: cambiar cargo o contraseña ───────────────────────────
if ($method === 'PUT') {
    csrf_check();
    $in  = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid = (int)($in['id_usuario'] ?? 0);
    if (!$uid) json_err('ID inválido.');

    // Verificar que el usuario pertenece a esta empresa
    $check = $db->prepare("SELECT id_usuario, cargo FROM usuarios WHERE id_usuario = ? AND id_empresa = ?");
    $check->execute([$uid, $eid]);
    if (!$check->fetch()) json_err('Usuario no encontrado.', 404);

    $me = (int)($_SESSION['user_id'] ?? 0);

    // Cambiar cargo
    if (array_key_exists('cargo', $in)) {
        if (!isAdmin()) json_err('Sin permisos.', 403);
        if ($uid === $me) json_err('No puedes cambiar tu propio cargo.');
        $cargo = $in['cargo'];
        if (!in_array($cargo, ['Admin', 'Tecnico'])) json_err('Cargo inválido.');
        $db->prepare("UPDATE usuarios SET cargo = ? WHERE id_usuario = ? AND id_empresa = ?")
           ->execute([$cargo, $uid, $eid]);
        log_accion($db, 'usuario_cargo_cambiado', null);
        json_ok(['msg' => "Cargo actualizado a $cargo."]);
    }

    // Cambiar contraseña
    if (array_key_exists('password', $in)) {
        $isSelf = ($uid === $me);

        if ($isSelf) {
            // Requiere contraseña actual
            $actual = $in['password_actual'] ?? '';
            if ($actual === '') json_err('Ingresa tu contraseña actual.');
            $row = $db->prepare("SELECT pass FROM usuarios WHERE id_usuario = ?");
            $row->execute([$uid]);
            $hash = $row->fetchColumn();
            // Soportar bcrypt y MD5 legacy
            $isBcrypt = str_starts_with((string)$hash, '$2');
            $ok = $isBcrypt
                ? password_verify($actual, $hash)
                : ($hash === md5($actual));
            if (!$ok) json_err('Contraseña actual incorrecta.');
            // Migrar hash MD5 a bcrypt en el primer login exitoso
            if (!$isBcrypt) {
                $db->prepare("UPDATE usuarios SET pass = ? WHERE id_usuario = ?")
                   ->execute([password_hash($actual, PASSWORD_BCRYPT), $uid]);
            }
        } else {
            if (!isAdmin()) json_err('Sin permisos.', 403);
        }

        $nueva = (string)($in['password'] ?? '');
        if (strlen($nueva) < 6) json_err('La contraseña debe tener al menos 6 caracteres.');
        $nuevo_hash = password_hash($nueva, PASSWORD_BCRYPT);
        $db->prepare("UPDATE usuarios SET pass = ? WHERE id_usuario = ? AND id_empresa = ?")
           ->execute([$nuevo_hash, $uid, $eid]);
        log_accion($db, $isSelf ? 'password_propio_cambiado' : 'password_usuario_reseteado', null);
        json_ok(['msg' => 'Contraseña actualizada.']);
    }

    json_err('Operación no especificada.');
}

// ── POST: crear técnico ───────────────────────────────────
if ($method === 'POST') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();

    $in     = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre = trim($in['nombre'] ?? '');
    $user   = trim($in['user']   ?? '');
    $pass   = $in['password']    ?? '';

    if (!$nombre || !$user || !$pass) json_err('Completa todos los campos.');
    if (strlen($user) < 3)            json_err('El usuario debe tener al menos 3 caracteres.');
    if (strlen($pass) < 6)            json_err('La contraseña debe tener al menos 6 caracteres.');

    // Límite de 5 técnicos activos por empresa
    $cnt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_empresa = ? AND cargo = 'Tecnico'");
    $cnt->execute([$eid]);
    if ((int)$cnt->fetchColumn() >= 5) json_err('Límite alcanzado: máximo 5 técnicos por cuenta.');

    // Usuario único en toda la plataforma
    $dup = $db->prepare("SELECT 1 FROM usuarios WHERE user = ?");
    $dup->execute([$user]);
    if ($dup->fetch()) json_err('Ese nombre de usuario ya está en uso.');

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $ins  = $db->prepare(
        "INSERT INTO usuarios (id_empresa, nombre, user, pass, cargo, activo)
         VALUES (?, ?, ?, ?, 'Tecnico', 1)"
    );
    $ins->execute([$eid, $nombre, $user, $hash]);
    log_accion($db, 'tecnico_creado', null);
    json_ok(['msg' => "Técnico {$nombre} creado correctamente.", 'id' => (int)$db->lastInsertId()]);
}
