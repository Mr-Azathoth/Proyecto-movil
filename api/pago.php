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

// ── MERCADO PAGO — Link de pago único (preference) ────────────
if ($metodo === 'mercadopago') {
    $planKey = $input['plan'] ?? '';
    if (!isset(MP_PLANES[$planKey])) json_err('Plan no válido');

    $plan = MP_PLANES[$planKey];

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'items' => [[
                'title'       => 'Centrotec — Plan ' . $plan['nombre'],
                'quantity'    => 1,
                'currency_id' => 'CLP',
                'unit_price'  => (float)$plan['precio'],
            ]],
            'back_urls' => [
                'success' => $returnUrl,
                'failure' => $returnUrl,
                'pending' => $returnUrl,
            ],
            'auto_return'        => 'approved',
            'external_reference' => 'eid_' . $eid . '_plan_' . $planKey,
            'notification_url'   => APP_URL . '/api/webhook_mp.php',
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 && $code !== 201) json_err('Error al crear el link de pago. Intenta de nuevo.');
    $mpData    = json_decode($resp, true);
    $initPoint = MP_ENV === 'sandbox'
        ? ($mpData['sandbox_init_point'] ?? '')
        : ($mpData['init_point']         ?? '');
    if (!$initPoint) json_err('No se pudo obtener el link de pago. Intenta de nuevo.');

    json_ok(['url' => $initPoint]);
}

json_err('Método de pago no válido');
