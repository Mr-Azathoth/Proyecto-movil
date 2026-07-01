<?php
require_once __DIR__ . '/includes/config.php';
if (logueado()) { header('Location: /reparo/app.php'); exit; }

$token = trim($_GET['token'] ?? '');
if (!$token) { header('Location: /reparo/index.php'); exit; }

$db  = getDB();
$st  = $db->prepare(
    "SELECT id FROM password_resets
     WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1"
);
$st->execute([$token]);
$valid = (bool) $st->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo — Nueva contraseña</title>
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
        <div class="brand-sub">Nueva contraseña</div>
      </div>
    </div>

    <?php if (!$valid): ?>
      <div class="alert-err">Este enlace no es válido o ya expiró.</div>
      <div class="auth-back">
        <a href="/reparo/recuperar.php" class="auth-back-accent">Solicitar un nuevo enlace</a>
      </div>
    <?php else: ?>
      <p class="auth-intro">Elige una nueva contraseña para tu cuenta.</p>
      <form id="rst-form" novalidate>
        <input type="hidden" id="rst-token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="fg">
          <label>Nueva contraseña</label>
          <input type="password" id="rst-pass" placeholder="Mínimo 6 caracteres" required autofocus autocomplete="new-password">
        </div>
        <div class="fg">
          <label>Confirmar contraseña</label>
          <input type="password" id="rst-confirm" placeholder="Repite la contraseña" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn-login" id="rst-btn">
          Guardar contraseña <span class="material-icons-round">lock_reset</span>
        </button>
      </form>
      <div id="rst-err" class="alert-err mt-10" hidden></div>
      <div class="auth-back">
        <a href="/reparo/index.php">← Volver al inicio de sesión</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="/reparo/assets/js/recuperar.js"></script>
</body>
</html>
