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

// ── MERCADO PAGO — checkout del plan de suscripción ──────────
if ($metodo === 'mercadopago') {
    $planKey = $input['plan'] ?? '';
    if (!isset(MP_PLANES[$planKey])) json_err('Plan no válido');

    $planId = MP_PLANES[$planKey]['id'];

    $ch = curl_init('https://api.mercadopago.com/preapproval_plan/' . urlencode($planId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $initPoint = '';
    if ($code === 200) {
        $planData  = json_decode($resp, true);
        $initPoint = $planData['init_point'] ?? '';
    }
    if (!$initPoint) {
        $initPoint = 'https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=' . urlencode($planId);
    }

    json_ok(['url' => $initPoint]);
}

json_err('Método de pago no válido');
