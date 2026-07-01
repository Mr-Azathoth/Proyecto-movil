<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$db = getDB();

// Crear tabla si no existe
$db->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_empresa  INT UNSIGNED NOT NULL,
    id_usuario  INT UNSIGNED NOT NULL,
    token       CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$username = trim($_POST['user'] ?? '');
if (!$username) { json_ok(['msg' => 'ok']); } // respuesta genérica siempre

// Buscar por nombre de usuario O por correo de la empresa (Admin primero)
$su = $db->prepare(
    "SELECT u.id_usuario, u.nombre, u.id_empresa
       FROM usuarios u
  LEFT JOIN empresas e ON e.id_empresa = u.id_empresa
      WHERE (u.user = ? OR e.correo = ?) AND u.activo = 1
   ORDER BY FIELD(u.cargo, 'Admin', 'Tecnico')
      LIMIT 1"
);
$su->execute([$username, $username]);
$user = $su->fetch();

if ($user) {
    // Obtener correo de la empresa
    $se = $db->prepare("SELECT correo, nombre FROM empresas WHERE id_empresa = ? LIMIT 1");
    $se->execute([$user['id_empresa']]);
    $emp = $se->fetch();

    $correo = $emp['correo'] ?? '';

    if ($correo) {
        // Invalidar tokens anteriores no usados del mismo usuario
        $db->prepare("UPDATE password_resets SET used=1 WHERE id_usuario=? AND used=0")
           ->execute([$user['id_usuario']]);

        // Generar token seguro
        $token     = bin2hex(random_bytes(32)); // 64 hex chars
        $expires   = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        $db->prepare("INSERT INTO password_resets (id_empresa, id_usuario, token, expires_at)
                      VALUES (?, ?, ?, ?)")
           ->execute([$user['id_empresa'], $user['id_usuario'], $token, $expires]);

        $link     = rtrim(APP_URL, '/') . '/reset_password.php?token=' . $token;
        $nombre   = htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8');
        $empresa  = htmlspecialchars($emp['nombre'] ?? 'Reparo', ENT_QUOTES, 'UTF-8');

        $html = "
<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:40px 0;'>
    <tr><td align='center'>
      <table width='520' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);'>
        <tr>
          <td style='background:#0d1117;padding:28px 32px;'>
            <span style='font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.5px;'>R</span>
            <span style='font-size:18px;font-weight:700;color:#e6edf3;'>eparo</span>
            <span style='font-size:12px;color:#8b949e;margin-left:8px;'>Servicios Técnicos</span>
          </td>
        </tr>
        <tr>
          <td style='padding:32px;'>
            <h2 style='margin:0 0 12px;font-size:20px;color:#0d1117;'>Recuperar contraseña</h2>
            <p style='margin:0 0 20px;color:#374151;font-size:15px;line-height:1.6;'>
              Hola <strong>{$nombre}</strong>, recibimos una solicitud para restablecer la contraseña
              de tu cuenta en <strong>{$empresa}</strong>.
            </p>
            <p style='margin:0 0 24px;color:#374151;font-size:15px;'>
              Haz clic en el botón para crear una nueva contraseña. El enlace expira en <strong>1 hora</strong>.
            </p>
            <table cellpadding='0' cellspacing='0'>
              <tr>
                <td style='background:#2f81f7;border-radius:8px;'>
                  <a href='{$link}' style='display:inline-block;padding:14px 28px;color:#fff;font-size:15px;font-weight:600;text-decoration:none;'>
                    Restablecer contraseña
                  </a>
                </td>
              </tr>
            </table>
            <p style='margin:24px 0 0;color:#9ca3af;font-size:12px;line-height:1.5;'>
              Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña actual no cambiará.<br>
              O copia este enlace en tu navegador:<br>
              <a href='{$link}' style='color:#2f81f7;word-break:break-all;'>{$link}</a>
            </p>
          </td>
        </tr>
        <tr>
          <td style='background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;'>
            <p style='margin:0;color:#9ca3af;font-size:11px;'>
              Este correo fue enviado automáticamente desde {$empresa}. No respondas a este mensaje.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";

        send_email($correo, $user['nombre'], 'Recuperar contraseña — Reparo', $html);
    }
}

// Respuesta genérica siempre (evita enumerar usuarios)
json_ok(['msg' => 'ok']);
