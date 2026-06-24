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

$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl   = $proto . '://' . $_SERVER['HTTP_HOST'];
$returnUrl = $baseUrl . '/reparo/pago/retorno.php';
$cancelUrl = $baseUrl . '/reparo/pago/cancel.php';

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

// ── WEBPAY PLUS ───────────────────────────────────────────────
if ($metodo === 'webpay') {
    $wpBase   = (WP_ENV === 'production')
        ? 'https://webpay3g.transbank.cl/rswebpaytransaction/api/webpay/v1.2/transactions'
        : 'https://webpay3gint.transbank.cl/rswebpaytransaction/api/webpay/v1.2/transactions';
    $buyOrder = 'REP-' . $eid . '-' . time();
    $precio   = (int)(MP_PLANES['1mes']['precio'] ?? 4990);

    $payload = json_encode([
        'buy_order'  => $buyOrder,
        'session_id' => 'eid' . $eid . '-' . substr(session_id(), 0, 16),
        'amount'     => $precio,
        'return_url' => $returnUrl . '?gateway=webpay&eid=' . $eid,
    ]);

    $ch = curl_init($wpBase);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Tbk-Api-Key-Id: '     . WP_COMMERCE_CODE,
            'Tbk-Api-Key-Secret: ' . WP_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)     json_err('Error de conexión con Webpay: ' . $err, 502);
    if ($code !== 200) {
        $msg = json_decode($resp, true)['error_message'] ?? "Error HTTP $code";
        json_err($msg, 502);
    }

    $data = json_decode($resp, true);
    $_SESSION['webpay_pending'] = [
        'token'     => $data['token'],
        'url'       => $data['url'],
        'buy_order' => $buyOrder,
        'eid'       => $eid,
    ];
    json_ok(['redirect' => '/reparo/pago/webpay_go.php']);
}

json_err('Método de pago no válido');
