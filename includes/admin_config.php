<?php
// Guard: este archivo solo debe ser incluido desde páginas admin_*.php y api/admin/
// Nunca incluir desde código de tenant.

define('SADMIN_TIMEOUT', 7200);        // 2 horas de inactividad
define('SADMIN_REM_DAYS', 30);         // días que dura el remember me

function getSuperAdminDB(): PDO {
    return getDB();
}

function superAdminLogueado(): bool {
    return isset($_SESSION['sadmin_id']);
}

function _sadmin_rem_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS sadmin_remember_tokens (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        sadmin_id  INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expira_en  DATETIME NOT NULL,
        creado_en  DATETIME NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_sadmin_token (token_hash),
        KEY idx_sadmin (sadmin_id)
    )");
}

function sadmin_remember_set(int $sadmin_id): void {
    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + SADMIN_REM_DAYS * 86400);
    $db     = getDB();
    _sadmin_rem_table($db);
    $db->prepare("DELETE FROM sadmin_remember_tokens WHERE sadmin_id = ? AND expira_en < NOW()")->execute([$sadmin_id]);
    $db->prepare("INSERT INTO sadmin_remember_tokens (sadmin_id, token_hash, expira_en) VALUES (?,?,?)")
       ->execute([$sadmin_id, $hash, $expira]);
    $secure = IS_HTTPS;
    setcookie('sadmin_rem', $token, [
        'expires'  => time() + SADMIN_REM_DAYS * 86400,
        'path'     => BASE ?: '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
}

function sadmin_remember_check(): bool {
    if (superAdminLogueado()) return true;
    $token = $_COOKIE['sadmin_rem'] ?? '';
    if (!$token) return false;
    try {
        $db = getDB();
        _sadmin_rem_table($db);
        $hash = hash('sha256', $token);
        $row = $db->prepare(
            "SELECT sa.id, sa.user, sa.nombre
             FROM sadmin_remember_tokens rt
             JOIN super_admins sa ON sa.id = rt.sadmin_id
             WHERE rt.token_hash = ? AND rt.expira_en > NOW()"
        );
        $row->execute([$hash]);
        $row = $row->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        // Renovar token (rotación)
        $db->prepare("DELETE FROM sadmin_remember_tokens WHERE token_hash = ?")->execute([$hash]);
        session_regenerate_id(true);
        $_SESSION['sadmin_id']     = $row['id'];
        $_SESSION['sadmin_user']   = $row['user'];
        $_SESSION['sadmin_nombre'] = $row['nombre'];
        $_SESSION['sadmin_last']   = time();
        $_SESSION['sadmin_csrf']   = bin2hex(random_bytes(32));
        sadmin_remember_set($row['id']);
        return true;
    } catch (Exception $e) { return false; }
}

function sadmin_remember_clear(): void {
    $token = $_COOKIE['sadmin_rem'] ?? '';
    if ($token) {
        try {
            $db = getDB();
            $db->prepare("DELETE FROM sadmin_remember_tokens WHERE token_hash = ?")->execute([hash('sha256', $token)]);
        } catch (Exception $e) {}
    }
    setcookie('sadmin_rem', '', time() - 86400, BASE ?: '/', '', IS_HTTPS, true);
}

function requireSuperAdmin(): void {
    if (!superAdminLogueado()) {
        sadmin_remember_check();
    }
    if (!superAdminLogueado()) {
        header('Location: '.BASE.'/admin_login.php');
        exit;
    }
    if (isset($_SESSION['sadmin_last']) && time() - $_SESSION['sadmin_last'] > SADMIN_TIMEOUT) {
        sadmin_remember_clear();
        session_destroy();
        header('Location: '.BASE.'/admin_login.php?timeout=1');
        exit;
    }
    $_SESSION['sadmin_last'] = time();
}

function sadmin_id(): int      { return (int)($_SESSION['sadmin_id']    ?? 0); }
function sadmin_user(): string  { return $_SESSION['sadmin_user']   ?? ''; }
function sadmin_nombre(): string { return $_SESSION['sadmin_nombre'] ?? ''; }

function sadmin_json_ok(mixed $d): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $d]);
    exit;
}
function sadmin_json_err(string $m, int $c = 400): void {
    http_response_code($c);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => $m]);
    exit;
}
function sadmin_guard(): void {
    if (!superAdminLogueado()) sadmin_json_err('No autorizado', 401);
    if (isset($_SESSION['sadmin_last']) && time() - $_SESSION['sadmin_last'] > SADMIN_TIMEOUT) {
        session_destroy();
        sadmin_json_err('Sesión expirada.', 401);
    }
    $_SESSION['sadmin_last'] = time();
}
function sadmin_csrf_check(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['sadmin_csrf'] ?? '', $token)) {
        sadmin_json_err('Token de seguridad inválido.', 403);
    }
}
function sadmin_iniciales(string $nombre): string {
    $p = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
    return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1) . (isset($p[1]) ? mb_substr($p[1], 0, 1) : ''));
}
