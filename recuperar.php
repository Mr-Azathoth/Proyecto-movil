<?php
require_once __DIR__ . '/includes/config.php';
if (logueado()) { header('Location: '.BASE.'/app.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Centrotec — Recuperar contraseña</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">
      <div class="brand-icon">C</div>
      <div>
        <div class="brand-name">Centrotec</div>
        <div class="brand-sub">Recuperar contraseña</div>
      </div>
    </div>

    <div id="rec-form-wrap">
      <p class="auth-intro">
        Ingresa tu nombre de usuario o el correo de tu cuenta y te enviaremos un enlace para restablecer tu contraseña.
      </p>
      <form id="rec-form">
        <div class="fg">
          <label>Usuario o correo electrónico</label>
          <input type="text" id="rec-user" name="user" placeholder="Tu usuario o correo" required autofocus autocomplete="username email">
        </div>
        <button type="submit" class="btn-login" id="rec-btn">
          Enviar enlace <span class="material-icons-round">send</span>
        </button>
      </form>
      <div id="rec-err" class="alert-err auth-err-mt" hidden></div>
    </div>

    <div id="rec-ok" class="auth-ok-wrap" hidden>
      <span class="material-icons-round auth-ok-icon">mark_email_read</span>
      <p class="auth-ok-title">Revisa tu correo</p>
      <p class="auth-ok-msg">
        Si el usuario existe, se envió un enlace de recuperación al correo registrado.<br>
        Puede tardar unos minutos.
      </p>
    </div>

    <div class="auth-back">
      <a href="<?= BASE ?>/index.php">← Volver al inicio de sesión</a>
    </div>
  </div>
</div>
<script src="<?= BASE ?>/assets/js/recuperar.js"></script>
</body>
</html>
