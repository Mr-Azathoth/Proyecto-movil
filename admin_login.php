<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';

if (superAdminLogueado()) { header('Location: /reparo/admin.php'); exit; }

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    if ($user && $pass) {
        $db = getDB();
        $st = $db->prepare("SELECT id, nombre, user, pass FROM super_admins WHERE user = ? AND activo = 1 LIMIT 1");
        $st->execute([$user]);
        $row = $st->fetch();

        if ($row && password_verify($pass, $row['pass'])) {
            session_regenerate_id(true);
            $_SESSION['sadmin_id']     = $row['id'];
            $_SESSION['sadmin_user']   = $row['user'];
            $_SESSION['sadmin_nombre'] = $row['nombre'];
            $_SESSION['sadmin_last']   = time();
            $_SESSION['sadmin_csrf']   = bin2hex(random_bytes(32));

            $db->prepare("UPDATE super_admins SET ultimo_acceso = NOW() WHERE id = ?")->execute([$row['id']]);
            header('Location: /reparo/admin.php');
            exit;
        }
        $err = 'Usuario o contraseña incorrectos.';
    } else {
        $err = 'Completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">
      <div class="brand-icon" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">R</div>
      <div>
        <div class="brand-name">Reparo</div>
        <div class="brand-sub">Panel de administración</div>
      </div>
    </div>

    <?php if (isset($_GET['timeout'])): ?>
      <div class="alert-err">Tu sesión expiró por inactividad. Vuelve a iniciar sesión.</div>
    <?php elseif ($err): ?>
      <div class="alert-err"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="POST">
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
      <a href="/reparo/index.php" style="font-size:13px;color:var(--txt2);text-decoration:none;">
        ← Acceso para clientes
      </a>
    </div>
  </div>
</div>
</body>
</html>
