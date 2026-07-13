<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
require_once __DIR__ . '/../../includes/mailer.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();
sadmin_csrf_check();

$id_usuario = (int)($_POST['id_usuario'] ?? 0);
if (!$id_usuario) sadmin_json_err('Datos incompletos.');

$db = getDB();
$st = $db->prepare("SELECT u.id_usuario, u.nombre, u.user, u.id_empresa, e.correo, e.nombre AS empresa
    FROM usuarios u JOIN empresas e ON e.id_empresa = u.id_empresa
    WHERE u.id_usuario = ? AND u.activo = 1 LIMIT 1");
$st->execute([$id_usuario]);
$row = $st->fetch();

if (!$row) sadmin_json_err('Usuario no encontrado.');
if (!$row['correo']) sadmin_json_err('La empresa no tiene correo registrado.');

$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 3600);
$link    = rtrim(APP_URL, '/') . '/reset_password.php?token=' . $token;
$nombre  = htmlspecialchars($row['nombre'],  ENT_QUOTES, 'UTF-8');
$empresa = htmlspecialchars($row['empresa'], ENT_QUOTES, 'UTF-8');
$html    = "<h2>Recuperar contraseña — {$empresa}</h2>
<p>Hola <strong>{$nombre}</strong>, el administrador del sistema ha solicitado el restablecimiento de tu contraseña.</p>
<p>Haz clic en el enlace para crear una nueva contraseña. Expira en <strong>1 hora</strong>.</p>
<p><a href='{$link}' style='background:#2f81f7;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;'>Restablecer contraseña</a></p>
<p style='color:#888;font-size:12px;'>O copia: <a href='{$link}'>{$link}</a></p>";

// Operación atómica: persistir token y enviar email en la misma transacción lógica.
// Si el email falla el token se revierte, evitando dejar al usuario sin enlace válido.
$db->beginTransaction();
try {
    $db->prepare("UPDATE password_resets SET used=1 WHERE id_usuario=? AND used=0")->execute([$id_usuario]);
    $db->prepare("INSERT INTO password_resets (id_empresa, id_usuario, token, expires_at) VALUES (?,?,?,?)")
       ->execute([$row['id_empresa'], $id_usuario, $token, $expires]);
    send_email($row['correo'], $row['nombre'], 'Restablecer contraseña — Centrotec', $html);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    sadmin_json_err('No se pudo enviar el correo. Intente nuevamente.', 502);
}

sadmin_json_ok(['msg' => 'Correo enviado a ' . $row['correo']]);
