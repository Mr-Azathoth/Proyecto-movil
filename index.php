<?php
require_once __DIR__ . '/includes/config.php';
remember_check();
if (logueado()) { header('Location: '.BASE.'/app.php'); exit; }

$ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$err       = '';
$suspended = false;
$expired   = isset($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar CSRF
    $csrf_ok = hash_equals(csrf_token(), $_POST['csrf_token'] ?? '');

    if (!$csrf_ok) {
        $err = 'Error de seguridad. Recarga la página e intenta de nuevo.';

    } elseif (!login_check_rate($ip)) {
        $mins = ceil(login_segundos_restantes($ip) / 60);
        $err  = "Demasiados intentos fallidos. Espera {$mins} minuto(s) para volver a intentar.";

    } else {
        $u = trim($_POST['user'] ?? '');
        $p = trim($_POST['pass'] ?? '');

        if (!$u || !$p) {
            $err = 'Completa todos los campos.';
        } else {
            // Buscar por nombre de usuario O correo de la empresa (Admin primero)
            $st = getDB()->prepare(
                "SELECT u.*, e.activa AS empresa_activa, e.plan_estado FROM usuarios u
                 LEFT JOIN empresas e ON e.id_empresa = u.id_empresa
                 WHERE (u.user = ? OR e.correo = ?) AND u.activo = 1
                 ORDER BY FIELD(u.cargo, 'Admin', 'Tecnico') LIMIT 1"
            );
            $st->execute([$u, $u]);
            $row = $st->fetch();

            // Soportar tanto bcrypt (nuevo) como MD5 (legacy) para migración gradual
            $autenticado = false;
            if ($row) {
                if (str_starts_with($row['pass'], '$2')) {
                    // Contraseña en bcrypt — verificar siempre con password_verify
                    $autenticado = password_verify($p, $row['pass']);
                    // Re-hashear si el cost factor cambió
                    if ($autenticado && password_needs_rehash($row['pass'], PASSWORD_BCRYPT)) {
                        getDB()->prepare("UPDATE usuarios SET pass = ? WHERE id_usuario = ?")
                               ->execute([password_hash($p, PASSWORD_BCRYPT), $row['id_usuario']]);
                    }
                } elseif ($row['pass'] === md5($p)) {
                    // Contraseña legacy MD5 — autenticar y migrar al vuelo
                    $autenticado = true;
                    $nuevo_hash  = password_hash($p, PASSWORD_BCRYPT);
                    getDB()->prepare("UPDATE usuarios SET pass = ? WHERE id_usuario = ?")
                           ->execute([$nuevo_hash, $row['id_usuario']]);
                }
            }

            if ($autenticado) {
                if (!(bool)$row['empresa_activa']) {
                    $suspended = $row['plan_estado'] === 'Pendiente' ? 'pendiente' : 'vencido';
                } else {
                    login_ok($ip);
                    session_regenerate_id(true);
                    $_SESSION['user_id']       = $row['id_usuario'];
                    $_SESSION['user']          = $row['user'];
                    $_SESSION['nombre']        = $row['nombre'];
                    $_SESSION['cargo']         = $row['cargo'];
                    $_SESSION['empresa_id']    = $row['id_empresa'];
                    $_SESSION['last_activity'] = time();
                    if (!empty($_POST['remember'])) {
                        remember_set($row['id_usuario'], $row['id_empresa']);
                    }
                    log_accion(getDB(), 'login_ok');
                    header('Location: '.BASE.'/app.php');
                    exit;
                }
            } else {
                login_fallo($ip);
                log_accion(getDB(), 'login_fallo');
                $intentos = ($_SESSION['login_' . md5($ip)]['intentos'] ?? 0);
                $restantes = max(0, 5 - $intentos);
                $err = $restantes > 0
                    ? "Usuario o contraseña incorrectos. ($restantes intento(s) restante(s))"
                    : 'Cuenta bloqueada temporalmente por múltiples intentos fallidos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Centrotec — Acceso</title>
<style nonce="<?= CSP_NONCE ?>">html,body{background:#0d1117;margin:0}</style>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
<link rel="manifest" href="<?= BASE ?>/manifest.php">
<meta name="theme-color" content="#7c3aed">
<link rel="apple-touch-icon" href="<?= BASE ?>/assets/img/icon.php?s=192">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Centrotec">
<meta name="base-path" content="<?= BASE ?>">
</head>
<body class="login-page">
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">
      <div class="brand-icon">C</div>
      <div>
        <div class="brand-name">Centrotec</div>
        <div class="brand-sub">Servicios técnicos</div>
      </div>
    </div>
    <?php if ($expired && !$err): ?>
      <div class="alert-warn">Tu sesión expiró por inactividad. Vuelve a ingresar.</div>
    <?php endif; ?>
    <?php if (isset($_GET['reset']) && !$err): ?>
      <div class="alert-ok">Contraseña actualizada correctamente. Ya puedes ingresar.</div>
    <?php endif; ?>
    <?php if ($suspended === 'pendiente'): ?>
      <div class="alert-susp">
        <span class="material-icons-round" style="font-size:18px;vertical-align:middle;margin-right:6px;">schedule</span>
        Pago pendiente. Completa tu suscripción para acceder.
        <div class="alert-susp-links">
          <a href="<?= BASE ?>/landing.php#precios">Ver planes</a>
          <a href="mailto:soporte@centrotec.cl">soporte@centrotec.cl</a>
        </div>
      </div>
    <?php elseif ($suspended === 'vencido'): ?>
      <div class="alert-susp">
        <span class="material-icons-round" style="font-size:18px;vertical-align:middle;margin-right:6px;">schedule</span>
        Tu suscripción ha vencido.
        <div class="alert-susp-links">
          <a href="<?= BASE ?>/landing.php#precios">Renovar suscripción</a>
          <a href="mailto:soporte@centrotec.cl">soporte@centrotec.cl</a>
        </div>
      </div>
    <?php elseif ($err): ?>
      <div class="alert-err"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="fg">
        <label>Usuario o correo</label>
        <input type="text" name="user" placeholder="Tu usuario o correo"
               value="<?= htmlspecialchars($_POST['user'] ?? '') ?>" required autofocus autocomplete="username email">
      </div>
      <div class="fg">
        <label>Contraseña</label>
        <input type="password" name="pass" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <div class="remember-row">
        <input type="checkbox" name="remember" id="remember" value="1">
        <label for="remember">Recordar este dispositivo por 30 días</label>
      </div>
      <button type="submit" class="btn-login">
        Ingresar <span class="material-icons-round">arrow_forward</span>
      </button>
      <div class="auth-back">
        <a href="<?= BASE ?>/recuperar.php">¿Olvidaste tu contraseña?</a>
      </div>
    </form>
  </div>
</div>
<script src="<?= BASE ?>/assets/js/sw-register.js"></script>
</body>
</html>
