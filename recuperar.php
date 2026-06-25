<?php
require_once __DIR__ . '/includes/config.php';
if (logueado()) { header('Location: /reparo/app.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo — Recuperar contraseña</title>
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
        <div class="brand-sub">Recuperar contraseña</div>
      </div>
    </div>

    <div id="rec-form-wrap">
      <p style="font-size:14px;color:var(--txt2);margin:0 0 20px;">
        Ingresa tu nombre de usuario y te enviaremos un enlace para restablecer tu contraseña.
      </p>
      <form id="rec-form">
        <div class="fg">
          <label>Usuario</label>
          <input type="text" id="rec-user" name="user" placeholder="Tu usuario" required autofocus autocomplete="username">
        </div>
        <button type="submit" class="btn-login" id="rec-btn">
          Enviar enlace <span class="material-icons-round">send</span>
        </button>
      </form>
      <div id="rec-err" class="alert-err" hidden style="margin-top:12px;"></div>
    </div>

    <div id="rec-ok" hidden style="text-align:center;padding:12px 0;">
      <span class="material-icons-round" style="font-size:48px;color:#4ade80;">mark_email_read</span>
      <p style="margin:12px 0 4px;font-size:16px;font-weight:600;color:var(--txt);">Revisa tu correo</p>
      <p style="font-size:13px;color:var(--txt2);line-height:1.5;">
        Si el usuario existe, se envió un enlace de recuperación al correo registrado.<br>
        Puede tardar unos minutos.
      </p>
    </div>

    <div style="text-align:center;margin-top:20px;">
      <a href="/reparo/index.php" style="font-size:13px;color:var(--txt2);text-decoration:none;">
        ← Volver al inicio de sesión
      </a>
    </div>
  </div>
</div>
<script src="/reparo/assets/js/recuperar.js"></script>
</body>
</html>
