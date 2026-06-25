<?php
require_once __DIR__ . '/includes/config.php';
if (logueado()) { header('Location: /reparo/app.php'); exit; }

$ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$err     = '';
$expired = isset($_GET['expired']);

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
            // Traer usuario solo por nombre (no incluir la contraseña en la query)
            $st = getDB()->prepare(
                "SELECT * FROM usuarios WHERE user = ? AND id_empresa = ? AND activo = 1 LIMIT 1"
            );
            $st->execute([$u, EMPRESA_ID]);
            $row = $st->fetch();

            // Soportar tanto bcrypt (nuevo) como MD5 (legacy) para migración gradual
            $autenticado = false;
            if ($row) {
                if (password_needs_rehash($row['pass'], PASSWORD_BCRYPT) === false && str_starts_with($row['pass'], '$2')) {
                    // Contraseña ya está en bcrypt
                    $autenticado = password_verify($p, $row['pass']);
                } elseif ($row['pass'] === md5($p)) {
                    // Contraseña legacy MD5 — autenticar y migrar al vuelo
                    $autenticado = true;
                    $nuevo_hash  = password_hash($p, PASSWORD_BCRYPT);
                    getDB()->prepare("UPDATE usuarios SET pass = ? WHERE id_usuario = ?")
                           ->execute([$nuevo_hash, $row['id_usuario']]);
                }
            }

            if ($autenticado) {
                login_ok($ip);
                // Regenerar ID de sesión tras login exitoso (previene session fixation)
                session_regenerate_id(true);
                $_SESSION['user_id']       = $row['id_usuario'];
                $_SESSION['user']          = $row['user'];
                $_SESSION['nombre']        = $row['nombre'];
                $_SESSION['cargo']         = $row['cargo'];
                $_SESSION['empresa_id']    = $row['id_empresa'];
                $_SESSION['last_activity'] = time();
                log_accion(getDB(), 'login_ok');
                header('Location: /reparo/app.php');
                exit;
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
<title>Reparo — Acceso</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">
      <div class="brand-icon">R</div>
      <div>
        <div class="brand-name">Reparo</div>
        <div class="brand-sub">Servicios técnicos</div>
      </div>
    </div>
    <?php if ($expired && !$err): ?>
      <div class="alert-warn">Tu sesión expiró por inactividad. Vuelve a ingresar.</div>
    <?php endif; ?>
    <?php if (isset($_GET['reset']) && !$err): ?>
      <div class="alert-ok">Contraseña actualizada correctamente. Ya puedes ingresar.</div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert-err"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="fg">
        <label>Usuario</label>
        <input type="text" name="user" placeholder="Tu usuario"
               value="<?= htmlspecialchars($_POST['user'] ?? '') ?>" required autofocus autocomplete="username">
      </div>
      <div class="fg">
        <label>Contraseña</label>
        <input type="password" name="pass" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-login">
        Ingresar <span class="material-icons-round">arrow_forward</span>
      </button>
      <div style="text-align:center;margin-top:16px;">
        <a href="/reparo/recuperar.php" style="font-size:13px;color:var(--txt2);text-decoration:none;">
          ¿Olvidaste tu contraseña?
        </a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
