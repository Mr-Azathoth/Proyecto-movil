<?php
define('SECRET', 'ct_mpt_xK7m2pQ');
require_once __DIR__ . '/includes/config.php';
if (($_GET['token'] ?? '') !== SECRET) { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

echo "MP_ENV: " . MP_ENV . "\n";
echo "MP_ACCESS_TOKEN (primeros 20 chars): " . substr(MP_ACCESS_TOKEN, 0, 20) . "...\n\n";

// Probar GET /users/me para verificar que el token es válido
$ch = curl_init('https://api.mercadopago.com/users/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "=== /users/me ===\nHTTP: $code\n$resp\n\n";

// Intentar crear un preapproval de prueba (sin payer_email)
$plan = array_values(MP_PLANES)[0]; // primer plan
echo "=== Plan a testear: " . $plan['nombre'] . " ===\n";
$payload = [
    'reason'             => $plan['nombre'] . ' — Centrotec TEST',
    'back_url'           => APP_URL . '/pago/retorno.php',
    'notification_url'   => APP_URL . '/api/webhook_mp.php',
    'external_reference' => 'test_diagnostico',
    'auto_recurring'     => [
        'frequency'          => $plan['meses'],
        'frequency_type'     => 'months',
        'transaction_amount' => $plan['precio'],
        'currency_id'        => 'CLP',
    ],
    'status' => 'pending',
];
echo "Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init('https://api.mercadopago.com/preapproval');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "=== POST /preapproval ===\nHTTP: $code\n$resp\n";
