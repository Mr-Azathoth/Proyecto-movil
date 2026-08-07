<?php
require_once __DIR__.'/../includes/config.php';
guard();
$id = $_GET['id'] ?? '';
if (!$id) { echo 'falta id'; exit; }
$ch = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($id));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
header('Content-Type: application/json');
echo json_encode(['http' => $code, 'body' => json_decode($resp, true)], JSON_PRETTY_PRINT);
