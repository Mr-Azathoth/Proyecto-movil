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
        "SELECT id_usuario, nombre, user, cargo, activo, id_sucursal
         FROM usuarios WHERE id_empresa = ? ORDER BY cargo DESC, nombre ASC"
    );
    $s->execute([$eid]);
    $rows = $s->fetchAll();

    // Sucursales extra habilitadas por usuario (ademas de su sucursal base)
    $ex = $db->prepare(
        "SELECT us.id_usuario, us.id_sucursal FROM usuario_sucursales us
           JOIN usuarios u ON u.id_usuario = us.id_usuario
          WHERE u.id_empresa = ?"
    );
    $ex->execute([$eid]);
    $extras = [];
    foreach ($ex->fetchAll() as $e) $extras[(int)$e['id_usuario']][] = (int)$e['id_sucursal'];
    foreach ($rows as &$r) {
        $r['id_sucursal']       = $r['id_sucursal'] !== null ? (int)$r['id_sucursal'] : null;
        $r['sucursales_extra']  = $extras[(int)$r['id_usuario']] ?? [];
    }
    unset($r);
    json_ok($rows);
}

// Maximo de usuarios por cargo y empresa (tecnicos y vendedores se cuentan por separado).
const LIMITE_POR_CARGO = ['Tecnico' => 5, 'Vendedor' => 10];   // la pantalla (app.js, loadUsuarios) repite estos numeros
const NOMBRE_CARGO = ['Tecnico' => 'técnicos', 'Vendedor' => 'vendedores'];

// Corta con error si la empresa ya tiene el maximo de usuarios con ese cargo ($excluir: el usuario que cambia de cargo).
function usr_limite_cargo(PDO $db, int $eid, string $cargo, int $excluir = 0): void {
    if (!isset(NOMBRE_CARGO[$cargo])) return;
    $c = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_empresa = ? AND cargo = ? AND id_usuario <> ?");
    $c->execute([$eid, $cargo, $excluir]);
    if ((int) $c->fetchColumn() >= LIMITE_POR_CARGO[$cargo]) json_err('Límite alcanzado: máximo ' . LIMITE_POR_CARGO[$cargo] . ' ' . NOMBRE_CARGO[$cargo] . ' por cuenta.');
}

// Sucursales adicionales del usuario (ademas de la base): cada una debe ser activa y de esta empresa; la base se descarta.
function usr_extras_validas(PDO $db, int $eid, $lista, int $base): array {
    $extras = [];
    foreach ((array) $lista as $x) {
        $x = usr_sucursal_valida($db, $eid, $x);
        if ($x !== $base) $extras[$x] = $x;
    }
    return $extras;
}

// Valida que la sucursal sea activa y de esta empresa; devuelve su id. Como sucursal BASE debe atender
// publico: una bodega solo se admite como sucursal extra (para ajustar su stock).
function usr_sucursal_valida(PDO $db, int $eid, $id, bool $comoBase = false): int {
    $id = (int) $id;
    $s  = sucursal_de_empresa($db, $eid, $id);
    if (!$s || !(int) $s['activa']) json_err('Sucursal inválida.');
    if ($comoBase && (int) $s['es_bodega']) json_err('Una bodega no puede ser la sucursal base de un usuario: asígnala como sucursal adicional.');
    return $id;
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
        if (!in_array($cargo, CARGOS, true)) json_err('Cargo inválido.');
        usr_limite_cargo($db, $eid, $cargo, $uid);
        $db->prepare("UPDATE usuarios SET cargo = ? WHERE id_usuario = ? AND id_empresa = ?")
           ->execute([$cargo, $uid, $eid]);
        log_accion($db, 'usuario_cargo_cambiado', null, ['id_usuario' => $uid, 'cargo_nuevo' => $cargo]);
        json_ok(['msg' => "Cargo actualizado a $cargo."]);
    }

    // Cambiar sucursal base y/o sucursales habilitadas (traslado de local)
    if (array_key_exists('id_sucursal', $in)) {
        if (!isAdmin()) json_err('Sin permisos.', 403);
        $base   = usr_sucursal_valida($db, $eid, $in['id_sucursal'], true);
        $extras = usr_extras_validas($db, $eid, $in['sucursales_extra'] ?? [], $base);
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE usuarios SET id_sucursal = ? WHERE id_usuario = ? AND id_empresa = ?")
               ->execute([$base, $uid, $eid]);
            $db->prepare("DELETE FROM usuario_sucursales WHERE id_usuario = ?")->execute([$uid]);
            $ins = $db->prepare("INSERT INTO usuario_sucursales (id_usuario, id_sucursal) VALUES (?, ?)");
            foreach ($extras as $x) $ins->execute([$uid, $x]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        log_accion($db, 'usuario_sucursal_cambiada', null, ['id_usuario' => $uid, 'id_sucursal' => $base, 'extras' => array_values($extras)]);
        json_ok(['msg' => 'Sucursal actualizada.']);
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
        log_accion($db, $isSelf ? 'password_propio_cambiado' : 'password_usuario_reseteado', null, ['id_usuario' => $uid]);
        json_ok(['msg' => 'Contraseña actualizada.']);
    }

    json_err('Operación no especificada.');
}

// ── DELETE: eliminar técnico ──────────────────────────────
if ($method === 'DELETE') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();

    $in  = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid = (int)($in['id_usuario'] ?? 0);
    if (!$uid) json_err('ID inválido.');

    $me = (int)($_SESSION['user_id'] ?? 0);
    if ($uid === $me) json_err('No puedes eliminar tu propia cuenta.');

    $chk = $db->prepare("SELECT id_usuario, nombre, cargo FROM usuarios WHERE id_usuario = ? AND id_empresa = ?");
    $chk->execute([$uid, $eid]);
    $target = $chk->fetch();
    if (!$target) json_err('Usuario no encontrado.', 404);
    if (!in_array($target['cargo'], ['Tecnico', 'Vendedor'], true)) json_err('Solo se pueden eliminar técnicos y vendedores.');

    $db->prepare("DELETE FROM usuarios WHERE id_usuario = ? AND id_empresa = ?")->execute([$uid, $eid]);
    $etq = $target['cargo'] === 'Vendedor' ? 'Vendedor' : 'Técnico';
    log_accion($db, $target['cargo'] === 'Vendedor' ? 'vendedor_eliminado' : 'tecnico_eliminado', null, ['id_usuario' => $uid, 'nombre' => $target['nombre']]);
    json_ok(['msg' => "{$etq} {$target['nombre']} eliminado."]);
}

// ── POST: crear técnico o vendedor ────────────────────────
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

    // Cargo del nuevo usuario (por defecto Técnico) y límite por cargo
    $cargo = (string) ($in['cargo'] ?? 'Tecnico');
    if (!in_array($cargo, ['Tecnico', 'Vendedor'], true)) json_err('Cargo inválido.');
    usr_limite_cargo($db, $eid, $cargo);

    // Usuario único en toda la plataforma
    $dup = $db->prepare("SELECT 1 FROM usuarios WHERE user = ?");
    $dup->execute([$user]);
    if ($dup->fetch()) json_err('Ese nombre de usuario ya está en uso.');

    $sucId = isset($in['id_sucursal']) && $in['id_sucursal'] !== ''
        ? usr_sucursal_valida($db, $eid, $in['id_sucursal'], true)
        : sucursal_default($db, $eid);

    // Sucursales adicionales (opcional): se validan ANTES de crear al usuario
    $extras = usr_extras_validas($db, $eid, $in['sucursales_extra'] ?? [], $sucId);

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $db->beginTransaction();
    try {
        $ins  = $db->prepare(
            "INSERT INTO usuarios (id_empresa, nombre, user, pass, cargo, activo, id_sucursal)
             VALUES (?, ?, ?, ?, ?, 1, ?)"
        );
        $ins->execute([$eid, $nombre, $user, $hash, $cargo, $sucId]);
        $newUid = (int)$db->lastInsertId();
        $insX = $db->prepare("INSERT INTO usuario_sucursales (id_usuario, id_sucursal) VALUES (?, ?)");
        foreach ($extras as $x) $insX->execute([$newUid, $x]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    log_accion($db, $cargo === 'Vendedor' ? 'vendedor_creado' : 'tecnico_creado', null, ['nombre' => $nombre, 'user' => $user, 'id_sucursal' => $sucId, 'extras' => array_values($extras)], ['id' => $newUid]);
    $etq = $cargo === 'Vendedor' ? 'Vendedor' : 'Técnico';
    json_ok(['msg' => "{$etq} {$nombre} creado correctamente.", 'id' => $newUid, 'cargo' => $cargo]);
}
