<?php
// Guard: este archivo solo debe ser incluido desde páginas admin_*.php y api/admin/
// Nunca incluir desde código de tenant.

function getSuperAdminDB(): PDO {
    return getDB(); // misma DB, diferente contexto de sesión
}

function superAdminLogueado(): bool {
    return isset($_SESSION['sadmin_id']);
}

function requireSuperAdmin(): void {
    if (!superAdminLogueado()) {
        header('Location: /reparo/admin_login.php');
        exit;
    }
    // Renovar actividad
    $_SESSION['sadmin_last'] = time();
}

function sadmin_id(): int    { return (int)($_SESSION['sadmin_id']    ?? 0); }
function sadmin_user(): string { return $_SESSION['sadmin_user'] ?? ''; }
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
}
