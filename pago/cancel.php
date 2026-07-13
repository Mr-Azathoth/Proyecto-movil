<?php
require_once __DIR__.'/../includes/config.php';
requireLogin();

$gateway = htmlspecialchars($_GET['gateway'] ?? 'desconocido');
$status  = $_GET['status'] ?? '';

$mensajes = [
    'cancelled' => 'Cancelaste el pago.',
    'failure'   => 'El pago fue rechazado.',
    'rejected'  => 'La transacción fue rechazada por el banco.',
    'pending'   => 'El pago está pendiente de confirmación.',
];
$msg = $mensajes[$status] ?? 'El pago no se completó.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Pago no completado – Centrotec</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<style>
  body { background:#0d1117; color:#e6edf3; font-family:'Inter',sans-serif;
         display:flex; align-items:center; justify-content:center;
         min-height:100vh; flex-direction:column; gap:12px; text-align:center; }
  h2  { font-family:'Outfit',sans-serif; font-size:20px; font-weight:700; margin:0; }
  p   { color:#8b949e; font-size:13px; }
  a   { display:inline-flex; align-items:center; gap:6px; margin-top:8px;
        color:#2f81f7; text-decoration:none; border:1px solid rgba(47,129,247,0.4);
        padding:10px 20px; border-radius:8px; font-size:14px; }
  a:hover { background:rgba(47,129,247,0.08); }
</style>
</head>
<body>
  <span class="material-icons-round" style="font-size:52px;color:#f85149">error_outline</span>
  <h2><?= $msg ?></h2>
  <p>Gateway: <?= ucfirst($gateway) ?></p>
  <a href="<?= BASE ?>/app.php">
    <span class="material-icons-round" style="font-size:18px">arrow_back</span>
    Volver a Centrotec
  </a>
</body>
</html>
