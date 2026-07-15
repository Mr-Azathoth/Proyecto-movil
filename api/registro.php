<?php
require_once __DIR__ . '/../includes/config.php';
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
$plan_key          = preg_replace('/[^a-z0-9]/', '', $_POST['plan'] ?? '1mes');
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
        ADD COLUMN IF NOT EXISTS subdominio VARCHAR(60)  DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS rut        VARCHAR(20)  DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS direccion  VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS comuna     VARCHAR(100) DEFAULT NULL");
} catch (PDOException $ignored) {}
try {
    $db->exec("ALTER TABLE empresas ADD UNIQUE KEY uq_empresa_subdominio (subdominio)");
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
    $plan_nombre = (MP_PLANES[$plan_key]['nombre'] ?? null) ?: $plan_key;
    $db->prepare(
        "INSERT INTO empresas (nombre, subdominio, rut, telefono, correo, direccion, comuna, activa, plan_tipo, plan_estado, creada_en)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 'Pendiente', NOW())"
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
log_accion($db, 'registro_empresa', null);

// Generar URL de pago Mercado Pago
$planes = defined('MP_PLANES') ? MP_PLANES : [];
if (isset($planes[$plan_key])) {
    $back_url = APP_URL . '/pago/retorno.php?gateway=mp_sub&eid=' . $id_empresa;
    $mp_url   = 'https://www.mercadopago.cl/subscriptions/checkout'
              . '?preapproval_plan_id=' . urlencode($planes[$plan_key]['id'])
              . '&back_url=' . urlencode($back_url)
              . '&external_reference=' . urlencode('eid_' . $id_empresa);
    json_ok(['redirect' => $mp_url]);
}

// Si MP no está configurado, ir directo al app (útil en desarrollo)
json_ok(['redirect' => APP_URL . '/app.php']);
