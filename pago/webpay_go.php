<?php
require_once __DIR__.'/../includes/config.php';
requireLogin();

$pending = $_SESSION['webpay_pending'] ?? null;
if (!$pending || empty($pending['token']) || empty($pending['url'])) {
    header('Location: /reparo/app.php');
    exit;
}

unset($_SESSION['webpay_pending']);

$token = htmlspecialchars($pending['token'], ENT_QUOTES);
$url   = htmlspecialchars($pending['url'],   ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Redirigiendo a Webpay...</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<style>
  body { background:#0d1117; color:#e6edf3; font-family:sans-serif;
         display:flex; align-items:center; justify-content:center;
         min-height:100vh; flex-direction:column; gap:12px; }
  .spin { animation:spin 1s linear infinite; font-size:32px; color:#2f81f7; }
  @keyframes spin { to { transform:rotate(360deg); } }
  p { color:#8b949e; font-size:14px; }
</style>
</head>
<body>
  <span class="material-icons-round spin">sync</span>
  <p>Redirigiendo a Webpay...</p>
  <form method="post" action="<?= $url ?>" id="wp-form">
    <input type="hidden" name="token_ws" value="<?= $token ?>">
  </form>
  <script src="/reparo/assets/js/webpay_go.js"></script>
</body>
</html>
