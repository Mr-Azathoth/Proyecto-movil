<?php
require_once __DIR__.'/../includes/config.php';
guard();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido', 405);
if (!isAdmin()) json_err('Solo administradores pueden gestionar pagos', 403);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$metodo = $input['metodo'] ?? '';
$eid    = eid();
$db     = getDB();

$returnUrl = APP_URL . '/pago/retorno.php';

// ── MERCADO PAGO — Suscripción recurrente ─────────────────────
// Redirige directamente al checkout del plan en MP.
// No se necesita llamada a la API para iniciar el flujo.
if ($metodo === 'mercadopago') {
    $planKey = $input['plan'] ?? '';
    $planes  = MP_PLANES;
    if (!isset($planes[$planKey])) json_err('Plan no válido');

    $planId  = $planes[$planKey]['id'];
    $backUrl = $returnUrl . '?gateway=mp_sub&eid=' . $eid;
    $url     = 'https://www.mercadopago.cl/subscriptions/checkout'
             . '?preapproval_plan_id=' . $planId
             . '&back_url=' . urlencode($backUrl);

    json_ok(['url' => $url]);
}

json_err('Método de pago no válido');
