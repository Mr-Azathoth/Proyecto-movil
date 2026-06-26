<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$token    = trim($_POST['token']    ?? '');
$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['confirm']  ?? '');

if (!$token || !$password) json_err('Datos incompletos.');
if (strlen($password) < 6)  json_err('La contraseña debe tener al menos 6 caracteres.');
if ($password !== $confirm)  json_err('Las contraseñas no coinciden.');

$db = getDB();

$st = $db->prepare(
    "SELECT pr.id, pr.id_usuario, pr.id_empresa
     FROM password_resets pr
     WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
     LIMIT 1"
);
$st->execute([$token]);
$row = $st->fetch();

if (!$row) json_err('El enlace no es válido o ya expiró. Solicita uno nuevo.', 400);

$hash = password_hash($password, PASSWORD_BCRYPT);

$db->prepare("UPDATE usuarios SET pass = ? WHERE id_usuario = ? AND id_empresa = ?")
   ->execute([$hash, $row['id_usuario'], $row['id_empresa']]);

$db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
   ->execute([$row['id']]);

json_ok(['msg' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
