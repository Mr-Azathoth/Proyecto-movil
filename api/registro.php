<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

// Rate limiting — protege contra creación masiva de cuentas
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!login_check_rate($ip)) {
    $seg = login_segundos_restantes($ip);
    json_err('Demasiados intentos. Espera ' . ceil($seg / 60) . ' minuto(s).', 429);
}

// CSRF
if (!hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
    json_err('Token de seguridad inválido. Recarga la página.');
}

// Recoger inputs
$nombre_local      = trim($_POST['nombre_local'] ?? '');
// Subdominio: generado automáticamente desde el nombre del local
$subdominio        = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', trim($_POST['nombre_local'] ?? ''))), '-'));
$rut               = trim($_POST['rut'] ?? '');
$nombre_admin      = trim($_POST['nombre_admin'] ?? '');
$pass              = $_POST['pass'] ?? '';
$plan_key          = preg_replace('/[^a-z0-9]/', '', $_POST['plan'] ?? 'trial');
if ($plan_key !== 'trial' && !isset(MP_PLANES[$plan_key])) $plan_key = 'trial';
$direccion         = trim($_POST['direccion'] ?? '');
$comuna            = trim($_POST['comuna']    ?? '');

// Email: el del local tiene prioridad; si no se ingresó, se usa el personal
$email_personal = strtolower(trim($_POST['email_personal'] ?? ''));
$email_local    = strtolower(trim($_POST['email_local']    ?? ''));
$email          = $email_local ?: $email_personal;

// Teléfono: el del local tiene prioridad; si no, se usa el personal
$telefono_personal = trim($_POST['telefono_personal'] ?? '');
$telefono_local    = trim($_POST['telefono_local']    ?? '');
$telefono          = $telefono_local ?: $telefono_personal;

// Validaciones básicas
if (!$nombre_local)   json_err('El nombre del local es obligatorio.');
if (!$rut)            json_err('El RUT del local es obligatorio.');
if (!$nombre_admin)   json_err('Tu nombre es obligatorio.');
if (!$email_personal) json_err('El email es obligatorio.');
if (!$pass)           json_err('La contraseña es obligatoria.');

if (!filter_var($email_personal, FILTER_VALIDATE_EMAIL)) json_err('El email personal no es válido.');
if ($email_local && !filter_var($email_local, FILTER_VALIDATE_EMAIL)) json_err('El email del local no es válido.');
if (strlen($pass) < 8) json_err('La contraseña debe tener al menos 8 caracteres.');

// Validar RUT chileno (formato + dígito verificador)
function validar_rut(string $rut): bool {
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    if (!preg_match('/^[0-9]{7,8}[0-9K]$/', $clean)) return false;
    $body = substr($clean, 0, -1);
    $dv   = substr($clean, -1);
    $sum  = 0; $mul = 2;
    for ($i = strlen($body) - 1; $i >= 0; $i--) {
        $sum += (int)$body[$i] * $mul;
        $mul = $mul < 7 ? $mul + 1 : 2;
    }
    $res = 11 - ($sum % 11);
    $expected = $res === 11 ? '0' : ($res === 10 ? 'K' : (string)$res);
    return $dv === $expected;
}

function formatear_rut(string $rut): string {
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    $body  = substr($clean, 0, -1);
    $dv    = substr($clean, -1);
    $body  = strrev(implode('.', str_split(strrev($body), 3)));
    return $body . '-' . $dv;
}

if (!validar_rut($rut)) {
    json_err('RUT inválido. Verifica el formato y el dígito verificador.');
}
$rut = formatear_rut($rut);

$db = getDB();

// Migraciones silenciosas — agrega columnas si no existen
try {
    $db->exec("ALTER TABLE empresas
        ADD COLUMN IF NOT EXISTS subdominio      VARCHAR(60)  DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS rut             VARCHAR(20)  DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS direccion       VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS comuna          VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS plan_vencimiento DATE         NULL");
} catch (PDOException $ignored) {}
try {
    $db->exec("ALTER TABLE empresas ADD UNIQUE KEY uq_empresa_subdominio (subdominio)");
} catch (PDOException $ignored) {}
try {
    $db->exec("ALTER TABLE empresas ADD UNIQUE KEY uq_empresa_rut (rut)");
} catch (PDOException $ignored) {}

// Unicidad subdominio: si hay colisión agrega sufijo numérico automáticamente
$base_sub = substr($subdominio, 0, 55) ?: 'empresa';
$subdominio = $base_sub;
$sufijo = 2;
while (true) {
    $st = $db->prepare("SELECT id_empresa FROM empresas WHERE subdominio = ? LIMIT 1");
    $st->execute([$subdominio]);
    if (!$st->fetchColumn()) break;
    $subdominio = $base_sub . '-' . $sufijo++;
}

// Unicidad RUT — impide crear una segunda cuenta con el mismo RUT
$stRut = $db->prepare("SELECT id_empresa FROM empresas WHERE rut = ? LIMIT 1");
$stRut->execute([$rut]);
if ($stRut->fetchColumn()) {
    json_err('El RUT ' . $rut . ' ya tiene una cuenta registrada en Centrotec. Si tu período de prueba terminó, inicia sesión y activa un plan para continuar.');
}

// Unicidad email (usamos el email final que se guardará en empresas.correo)
$st2 = $db->prepare("SELECT id_empresa FROM empresas WHERE correo = ? LIMIT 1");
$st2->execute([$email]);
if ($st2->fetchColumn()) json_err('Ya existe una cuenta registrada con ese email.');
// Si el local usa un email diferente al personal, verificar también el personal
if ($email_local && $email_local !== $email_personal) {
    $st3 = $db->prepare("SELECT id_empresa FROM empresas WHERE correo = ? LIMIT 1");
    $st3->execute([$email_personal]);
    if ($st3->fetchColumn()) json_err('El email personal ya está registrado en otra cuenta.');
}

// Validar logo antes de abrir la transacción
$logo_tmp  = $_FILES['logo']['tmp_name'] ?? '';
$logo_mime = null;
$logo_ext  = null;
if ($logo_tmp && is_uploaded_file($logo_tmp)) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $logo_mime = $finfo->file($logo_tmp);
    if (!isset($allowed[$logo_mime])) json_err('El logo debe ser JPG, PNG o WebP.');
    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) json_err('El logo no debe superar 2 MB.');
    $logo_ext = $allowed[$logo_mime];
}

// Crear empresa + usuario en una transacción
$db->beginTransaction();
try {
    $plan_nombre = $plan_key === 'trial' ? 'Trial' : ((MP_PLANES[$plan_key]['nombre'] ?? null) ?: $plan_key);
    $db->prepare(
        "INSERT INTO empresas (nombre, subdominio, rut, telefono, correo, direccion, comuna, activa, plan_tipo, plan_estado, plan_vencimiento, creada_en)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'Trial', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())"
    )->execute([$nombre_local, $subdominio, $rut, $telefono ?: null, $email, $direccion ?: null, $comuna ?: null, $plan_nombre]);
    $id_empresa = (int)$db->lastInsertId();

    // Guardar logo
    $logo_path = null;
    if ($logo_tmp && $logo_ext) {
        $logo_dir = __DIR__ . '/../assets/uploads/logos/';
        if (!is_dir($logo_dir)) mkdir($logo_dir, 0755, true);
        $logo_file = $id_empresa . '.' . $logo_ext;
        if (!move_uploaded_file($logo_tmp, $logo_dir . $logo_file)) {
            throw new RuntimeException('No se pudo guardar el logo.');
        }
        $logo_path = 'assets/uploads/logos/' . $logo_file;
        $db->prepare("UPDATE empresas SET logo_path = ? WHERE id_empresa = ?")
           ->execute([$logo_path, $id_empresa]);
    }

    // Usuario administrador
    $user_login = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email)[0]));
    if (!$user_login) $user_login = 'admin';
    $pass_hash  = password_hash($pass, PASSWORD_BCRYPT);

    $db->prepare(
        "INSERT INTO usuarios (id_empresa, user, nombre, pass, cargo, activo)
         VALUES (?, ?, ?, ?, 'Admin', 1)"
    )->execute([$id_empresa, $user_login, $nombre_admin, $pass_hash]);
    $id_usuario = (int)$db->lastInsertId();

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    json_err('Error al crear la cuenta. Intenta nuevamente.');
}

// Correo de bienvenida
try {
    $es_trial   = $plan_key === 'trial';
    $plan_label = $es_trial ? '7 días de prueba gratuita' : ($plan_nombre . ' — ' . number_format(MP_PLANES[$plan_key]['precio'], 0, ',', '.') . ' CLP');
    $vence_txt  = date('d/m/Y', strtotime('+7 days'));
    $html_bienvenida = "
    <div style='font-family:Inter,sans-serif;max-width:520px;margin:0 auto;background:#161b22;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;'>
      <div style='background:linear-gradient(135deg,#0e3a5c,#1a7ab0);padding:28px;text-align:center;'>
        <h1 style='color:#fff;margin:0;font-size:22px;font-weight:700;letter-spacing:1px;'>¡Bienvenido a Centrotec!</h1>
        <p style='color:rgba(255,255,255,0.75);margin:8px 0 0;font-size:14px;'>Tu cuenta ha sido creada exitosamente</p>
      </div>
      <div style='padding:28px;color:#e6edf3;line-height:1.7;font-size:14px;'>
        <p>Hola <strong>" . htmlspecialchars($nombre_admin) . "</strong>,</p>
        <p>Tu local <strong>" . htmlspecialchars($nombre_local) . "</strong> ya está registrado en Centrotec. Puedes empezar a gestionar tus reparaciones desde el panel.</p>
        <div style='background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:18px 20px;margin:20px 0;'>
          <table style='width:100%;border-collapse:collapse;font-size:13px;'>
            <tr><td style='color:#8b949e;padding:5px 0;'>Local</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>" . htmlspecialchars($nombre_local) . "</td></tr>
            <tr><td style='color:#8b949e;padding:5px 0;'>Usuario</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>" . htmlspecialchars($user_login) . "</td></tr>
            <tr><td style='color:#8b949e;padding:5px 0;'>Plan</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>" . htmlspecialchars($plan_label) . "</td></tr>" .
            ($es_trial ? "<tr><td style='color:#8b949e;padding:5px 0;'>Prueba hasta</td><td style='text-align:right;color:#50d2ff;font-weight:600;'>{$vence_txt}</td></tr>" : '') . "
          </table>
        </div>
        <div style='text-align:center;margin:24px 0;'>
          <a href='" . APP_URL . "/app.php' style='display:inline-block;background:#1a7ab0;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:600;font-size:14px;'>Ir al panel →</a>
        </div>" .
        ($es_trial ? "<p style='font-size:13px;color:#8b949e;'>Durante tu período de prueba tienes acceso completo. Cuando quieras activar un plan, ve a <strong>Configuración → Suscripción</strong> en el panel.</p>" : '') . "
        <p style='margin-top:24px;font-size:12px;color:#6e7681;'>Este es un correo automático, por favor no respondas a este mensaje.</p>
      </div>
    </div>";
    send_email($email_personal, $nombre_admin, '¡Bienvenido a Centrotec! Tu cuenta está lista', $html_bienvenida);
} catch (Throwable $e) {}

// Iniciar sesión automáticamente
login_ok($ip);
session_regenerate_id(true);
$_SESSION['user_id']       = $id_usuario;
$_SESSION['user']          = $user_login;
$_SESSION['nombre']        = $nombre_admin;
$_SESSION['cargo']         = 'Admin';
$_SESSION['empresa_id']    = $id_empresa;
$_SESSION['last_activity'] = time();

// Registrar acción (sesión ya activa)
log_accion($db, 'registro_empresa', null, ['empresa' => $nombre_local, 'plan' => $plan_key], ['id_empresa' => $id_empresa]);

if ($plan_key === 'trial') {
    json_ok(['redirect' => APP_URL . '/app.php?trial=1']);
}

// Plan de pago — Checkout Pro (pago único)
$url = mp_crear_preferencia($id_empresa, $plan_key, MP_PLANES[$plan_key]);
if (!$url) json_err('No se pudo generar el enlace de pago. Intenta nuevamente.');

json_ok(['redirect' => $url]);
