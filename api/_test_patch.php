<?php
require_once __DIR__.'/../includes/config.php';
guard();
$id = 'ccf9e7c55a1b40239daa946e66189767';

// Primero GET para ver estado actual
$ch = curl_init('https://api.mercadopago.com/preapproval/' . $id);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN], CURLOPT_TIMEOUT => 10]);
$getResp = curl_exec($ch);
$getCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Luego PATCH para cancelar
$ch2 = curl_init('https://api.mercadopago.com/preapproval/' . $id);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => json_encode(['status' => 'cancelled']),
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN, 'Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
]);
$patchResp = curl_exec($ch2);
$patchCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

header('Content-Type: application/json');
echo json_encode([
    'get'   => ['code' => $getCode,   'status' => json_decode($getResp, true)['status'] ?? '?'],
    'patch' => ['code' => $patchCode, 'body'   => json_decode($patchResp, true)],
], JSON_PRETTY_PRINT);
