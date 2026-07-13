<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';

if (superAdminLogueado()) { header('Location: '.BASE.'/admin.php'); exit; }

// Generar CSRF para el formulario de login
if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

$err = '';
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF del formulario
    $csrf_ok = hash_equals(
        $_SESSION['admin_login_csrf'] ?? '',
        $_POST['login_csrf'] ?? ''
    );
    if (!$csrf_ok) {
        $err = 'Token de seguridad inválido. Recarga la página.';
    } elseif (!login_check_rate($ip)) {
        $seg = login_segundos_restantes($ip);
        $err = 'Demasiados intentos fallidos. Espera ' . ceil($seg / 60) . ' minutos.';
    } else {
        $user = trim($_POST['user'] ?? '');
        $pass = trim($_POST['pass'] ?? '');

        if ($user && $pass) {
            $db = getDB();
            $st = $db->prepare("SELECT id, nombre, user, pass FROM super_admins WHERE user = ? AND activo = 1 LIMIT 1");
            $st->execute([$user]);
            $row = $st->fetch();

            if ($row && password_verify($pass, $row['pass'])) {
                login_ok($ip);
                session_regenerate_id(true);
                $_SESSION['sadmin_id']     = $row['id'];
                $_SESSION['sadmin_user']   = $row['user'];
                $_SESSION['sadmin_nombre'] = $row['nombre'];
                $_SESSION['sadmin_last']   = time();
                $_SESSION['sadmin_csrf']   = bin2hex(random_bytes(32));
                unset($_SESSION['admin_login_csrf']);

                $db->prepare("UPDATE super_admins SET ultimo_acceso = NOW() WHERE id = ?")->execute([$row['id']]);
                header('Location: '.BASE.'/admin.php');
                exit;
            }
            login_fallo($ip);
            $err = 'Usuario o contraseña incorrectos.';
        } else {
            $err = 'Completa todos los campos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Centrotec — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">
      <div class="brand-icon" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">C</div>
      <div>
        <div class="brand-name">Centrotec</div>
        <div class="brand-sub">Panel de administración</div>
      </div>
    </div>

    <?php if (isset($_GET['timeout'])): ?>
      <div class="alert-err">Tu sesión expiró por inactividad. Vuelve a iniciar sesión.</div>
    <?php elseif ($err): ?>
      <div class="alert-err"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="login_csrf" value="<?= htmlspecialchars($_SESSION['admin_login_csrf']) ?>">
      <div class="fg">
        <label>Usuario</label>
        <input type="text" name="user" placeholder="Tu usuario admin" required autofocus autocomplete="username">
      </div>
      <div class="fg">
        <label>Contraseña</label>
        <input type="password" name="pass" placeholder="Contraseña" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-login">
        Ingresar <span class="material-icons-round">admin_panel_settings</span>
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;">
      <a href="<?= BASE ?>/index.php" style="font-size:13px;color:var(--txt2);text-decoration:none;">
        ← Acceso para clientes
      </a>
    </div>
  </div>
</div>
</body>
</html>
