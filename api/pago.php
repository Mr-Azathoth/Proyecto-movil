<?php
require_once __DIR__.'/../includes/config.php';

// Verificación liviana: permite planes vencidos para que el muro de pago funcione
if (!logueado()) json_err('No autorizado', 401);
session_check_timeout();
$_SESSION['last_activity'] = time();
$_pago_db = getDB();
$_pu = $_pago_db->prepare("SELECT activo FROM usuarios WHERE id_usuario = ? LIMIT 1");
$_pu->execute([uid()]);
$_purow = $_pu->fetch();
if ($_purow && !(bool)$_purow['activo']) json_err('Cuenta desactivada.', 403);

csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido', 405);
if (!isAdmin()) json_err('Solo administradores pueden gestionar pagos', 403);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$metodo = $input['metodo'] ?? '';
$eid    = eid();
$db     = getDB();

$returnUrl = APP_URL . '/pago/retorno.php';

// ── MERCADO PAGO — Checkout Pro (pago único por período) ─────
if ($metodo === 'mercadopago') {
    $planKey = $input['plan'] ?? '';
    if (!isset(MP_PLANES[$planKey])) json_err('Plan no válido');

    $url = mp_crear_preferencia($eid, $planKey, MP_PLANES[$planKey]);
    if (!$url) json_err('No se pudo generar el enlace de pago. Intenta nuevamente.');

    json_ok(['url' => $url]);
}

json_err('Método de pago no válido');
