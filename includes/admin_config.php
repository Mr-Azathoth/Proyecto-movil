<?php
// Guard: este archivo solo debe ser incluido desde páginas admin_*.php y api/admin/
// Nunca incluir desde código de tenant.

define('SADMIN_TIMEOUT', 7200); // 2 horas de inactividad

function getSuperAdminDB(): PDO {
    return getDB();
}

function superAdminLogueado(): bool {
    return isset($_SESSION['sadmin_id']);
}

function requireSuperAdmin(): void {
    if (!superAdminLogueado()) {
        header('Location: /reparo/admin_login.php');
        exit;
    }
    if (isset($_SESSION['sadmin_last']) && time() - $_SESSION['sadmin_last'] > SADMIN_TIMEOUT) {
        session_destroy();
        header('Location: /reparo/admin_login.php?timeout=1');
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
