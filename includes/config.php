<?php
// Cargar .env desde la raíz del proyecto (nunca commitear .env)
(static function (): void {
    $file = dirname(__DIR__) . '/.env';
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(ltrim($line), '#')) continue;
        [$k, $v] = explode('=', $line, 2) + ['', ''];
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
})();

define('DB_HOST',    $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME',    $_ENV['DB_NAME'] ?? 'centrotec_db');
define('DB_USER',    $_ENV['DB_USER'] ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');
define('APP_ENV',    $_ENV['APP_ENV'] ?? 'development');
define('EMPRESA_ID', 1);
define('APP_URL',    $_ENV['APP_URL'] ?? 'http://localhost/centrotec');
// BASE = prefijo de ruta para links y redirects (ej: '/centrotec' en dev, '' en producción con dominio propio)
// APP_URL debe incluir esquema (https://...) para que parse_url funcione correctamente
$_aurl = APP_URL;
if (!preg_match('#^https?://#', $_aurl)) $_aurl = 'https://' . $_aurl;
define('BASE', rtrim(parse_url($_aurl, PHP_URL_PATH) ?: '', '/'));
unset($_aurl);

define('VALID_STATUS', ['Ingresado', 'En Reparacion', 'Reparado', 'Entregado', 'Garantia']);

// Google Maps API key para autocompletado de dirección en registro.php
// Activa con: console.cloud.google.com → Maps JavaScript API + Places API
// Al activar, agregar a la cabecera CSP (línea ~31):
//   script-src 'self' https://maps.googleapis.com https://maps.gstatic.com
//   img-src    'self' data: https://maps.gstatic.com https://maps.googleapis.com
// define('GOOGLE_MAPS_KEY', 'AIza...');

// Configuración segura de sesión — debe ir ANTES de session_start()
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

// Producción: ocultar errores y forzar cookie segura (HTTPS)
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('session.cookie_secure', '1');
} else {
    ini_set('display_errors', '1');
}

session_start();

// Headers de seguridad HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Google Fonts y Material Icons necesitan fonts.googleapis.com / fonts.gstatic.com
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function logueado(): bool { return isset($_SESSION['user_id']); }

// Destruye la sesión si lleva más de $segundos sin actividad y redirige a login
function session_check_timeout(int $segundos = 3600): void {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $segundos) {
        session_unset();
        session_destroy();
        // Eliminar cookie de sesión
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        header('Location: '.BASE.'/index.php?expired=1');
        exit;
    }
}

function requireLogin(): void {
    remember_check();
    if (!logueado()) { header('Location: '.BASE.'/index.php'); exit; }
    session_check_timeout();
    $_SESSION['last_activity'] = time();
}

// ── REMEMBER ME ──────────────────────────────────────────────
function _remember_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_empresa INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expira_en  DATETIME NOT NULL,
        creado_en  DATETIME NOT NULL DEFAULT NOW(),
        UNIQUE KEY uq_token (token_hash),
        KEY idx_usuario (id_usuario)
    )");
}

function remember_set(int $id_usuario, int $id_empresa): void {
    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + 30 * 86400);
    $db     = getDB();
    _remember_table($db);
    $db->prepare("DELETE FROM remember_tokens WHERE id_usuario = ? AND expira_en < NOW()")->execute([$id_usuario]);
    $db->prepare("INSERT INTO remember_tokens (id_usuario, id_empresa, token_hash, expira_en) VALUES (?,?,?,?)")
       ->execute([$id_usuario, $id_empresa, $hash, $expira]);
    setcookie('rp_rem', $token, [
        'expires'  => time() + 30 * 86400,
        'path'     => BASE ?: '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function remember_check(): bool {
    if (logueado()) return true;
    $token = $_COOKIE['rp_rem'] ?? '';
    if (!$token || strlen($token) !== 64) return false;
    $hash = hash('sha256', $token);
    try {
        $db = getDB();
        _remember_table($db);
        $st = $db->prepare(
            "SELECT rt.id, rt.id_usuario, rt.id_empresa,
                    u.user, u.nombre, u.cargo, e.activa
             FROM remember_tokens rt
             JOIN usuarios u ON u.id_usuario = rt.id_usuario
             JOIN empresas e ON e.id_empresa = rt.id_empresa
             WHERE rt.token_hash = ? AND rt.expira_en > NOW() AND u.activo = 1
             LIMIT 1"
        );
        $st->execute([$hash]);
        $row = $st->fetch();
    } catch (Throwable $e) {
        return false;
    }
    if (!$row || !(bool)$row['activa']) return false;
    // Rotar token: eliminar el viejo, emitir uno nuevo
    $db->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['id']]);
    session_regenerate_id(true);
    $_SESSION['user_id']       = $row['id_usuario'];
    $_SESSION['user']          = $row['user'];
    $_SESSION['nombre']        = $row['nombre'];
    $_SESSION['cargo']         = $row['cargo'];
    $_SESSION['empresa_id']    = $row['id_empresa'];
    $_SESSION['last_activity'] = time();
    remember_set($row['id_usuario'], $row['id_empresa']);
    return true;
}

function remember_clear(): void {
    $token = $_COOKIE['rp_rem'] ?? '';
    if ($token && strlen($token) === 64) {
        try {
            getDB()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")
                   ->execute([hash('sha256', $token)]);
        } catch (Throwable $ignored) {}
    }
    setcookie('rp_rem', '', ['expires' => time() - 86400, 'path' => BASE ?: '/', 'httponly' => true, 'samesite' => 'Strict']);
}

// ── SMTP (recuperación de contraseña) ────────────────────────
// Usar una cuenta Gmail con contraseña de aplicación habilitada.
// Generar en: myaccount.google.com → Seguridad → Contraseñas de aplicaciones
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? '');
define('SMTP_NAME', $_ENV['SMTP_NAME'] ?? 'Centrotec - Servicios Técnicos');

// ── SUSCRIPCIÓN / PAGOS ──────────────────────────────────────
// Mercado Pago — https://www.mercadopago.cl/developers/
define('MP_ACCESS_TOKEN', $_ENV['MP_ACCESS_TOKEN'] ?? '');
define('MP_PUBLIC_KEY',   $_ENV['MP_PUBLIC_KEY']   ?? '');
define('MP_ENV',          $_ENV['MP_ENV']          ?? 'sandbox');   // 'sandbox' | 'production'

// Planes de suscripción (IDs de preapproval_plan de Mercado Pago)
define('MP_PLANES', [
    '1mes'    => ['id' => '9ff62047640046eea92e392dc14fb459', 'nombre' => '1 mes',    'meses' => 1,  'precio' => 4990],
    '3meses'  => ['id' => '4108c57b01b7402d8e6966f40164f836', 'nombre' => '3 meses',  'meses' => 3,  'precio' => 13990],
    '6meses'  => ['id' => 'c6e46ec28cb44765951bb96aa86e4aaa', 'nombre' => '6 meses',  'meses' => 6,  'precio' => 25990],
    '12meses' => ['id' => 'db9a46e00a7a44d4bca5dcc852ea584f', 'nombre' => '12 meses', 'meses' => 12, 'precio' => 49990],
]);

// Webpay Plus (Transbank)
// Credenciales de integración pública (funcionan sin registro para pruebas)
// Para producción: https://www.transbankdevelopers.cl/
define('WP_COMMERCE_CODE', $_ENV['WP_COMMERCE_CODE'] ?? '597055555532');
define('WP_API_KEY',       $_ENV['WP_API_KEY']       ?? '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C');
define('WP_ENV',           $_ENV['WP_ENV']           ?? 'integration');   // 'integration' | 'production'

function eid(): int           { return (int)($_SESSION['empresa_id'] ?? EMPRESA_ID); }
function uid(): int           { return (int)($_SESSION['user_id']    ?? 0); }
function uname(): string      { return $_SESSION['user']   ?? ''; }
function unombre(): string    { return $_SESSION['nombre'] ?? ''; }
function ucargo(): string     { return $_SESSION['cargo']  ?? ''; }
function isAdmin(): bool      { return ucargo() === 'Admin'; }

function json_ok(mixed $d): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $d]);
    exit;
}
function json_err(string $m, int $c = 400): void {
    http_response_code($c);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => $m]);
    exit;
}
function guard(): void {
    if (!logueado()) json_err('No autorizado', 401);
    session_check_timeout();
    $_SESSION['last_activity'] = time();
    $s = getDB()->prepare("SELECT activa, plan_estado FROM empresas WHERE id_empresa = ? LIMIT 1");
    $s->execute([eid()]);
    $emp = $s->fetch();
    if ($emp && !(bool)$emp['activa']) {
        remember_clear();
        session_unset(); session_destroy();
        $msg = ($emp['plan_estado'] === 'Pendiente')
            ? 'Pago pendiente. Completa tu suscripción para continuar.'
            : 'Tu suscripción ha vencido.';
        json_err($msg, 403);
    }
}

// Registra acciones críticas en la tabla log_acciones
function log_accion(PDO $pdo, string $accion, ?int $id_reparacion = null): void {
    $pdo->prepare(
        "INSERT INTO log_acciones (id_empresa, id_usuario, usuario, accion, id_reparacion, ip)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        eid(),
        $_SESSION['user_id'] ?? null,
        $_SESSION['user']    ?? null,
        $accion,
        $id_reparacion,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ]);
}

// ── CSRF ─────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Valida el token enviado por el cliente (header o campo POST)
function csrf_check(): void {
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $guardado = $_SESSION['csrf_token'] ?? '';
    if (!$guardado || !hash_equals($guardado, $enviado)) {
        json_err('Token de seguridad inválido.', 403);
    }
}

// ── Rate limiting de login (basado en sesión + IP) ────────────
// Almacena intentos en sesión indexados por IP para limitar fuerza bruta.
// Para producción reemplazar con almacenamiento en DB o Redis.
function login_check_rate(string $ip): bool {
    $key     = 'login_' . md5($ip);
    $datos   = $_SESSION[$key] ?? ['intentos' => 0, 'bloqueado_hasta' => 0];
    $ahora   = time();

    if ($datos['bloqueado_hasta'] > $ahora) {
        return false; // Bloqueado
    }
    if ($datos['bloqueado_hasta'] > 0 && $datos['bloqueado_hasta'] <= $ahora) {
        // El bloqueo expiró, reiniciar contador
        $datos = ['intentos' => 0, 'bloqueado_hasta' => 0];
    }
    $_SESSION[$key] = $datos;
    return true;
}

function login_fallo(string $ip): int {
    $key   = 'login_' . md5($ip);
    $datos = $_SESSION[$key] ?? ['intentos' => 0, 'bloqueado_hasta' => 0];
    $datos['intentos']++;
    if ($datos['intentos'] >= 5) {
        $datos['bloqueado_hasta'] = time() + 900; // 15 minutos
        $datos['intentos']        = 0;
    }
    $_SESSION[$key] = $datos;
    return $datos['intentos'];
}

function login_ok(string $ip): void {
    unset($_SESSION['login_' . md5($ip)]);
}

function login_segundos_restantes(string $ip): int {
    $datos = $_SESSION['login_' . md5($ip)] ?? ['bloqueado_hasta' => 0];
    return max(0, $datos['bloqueado_hasta'] - time());
}
