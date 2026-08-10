<?php
// Script temporal de diagnóstico — eliminar después de debuggear
require_once __DIR__.'/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$preapprovalId = $_GET['id'] ?? 'ccf9e7c55a1b40239daa946e66189767';

// Primero: GET para ver el estado actual en MP
$chGet = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($preapprovalId));
curl_setopt_array($chGet, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT        => 15,
]);
$getRespRaw = curl_exec($chGet);
$getCode    = curl_getinfo($chGet, CURLINFO_HTTP_CODE);
$getCurlErr = curl_error($chGet);
curl_close($chGet);

$getResp = json_decode($getRespRaw, true);

// Segundo: PUT para cancelar
$chPut = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($preapprovalId));
curl_setopt_array($chPut, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PUT',
    CURLOPT_POSTFIELDS     => json_encode(['status' => 'canceled']),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
        'X-Idempotency-Key: debug-cancel-' . $preapprovalId . '-' . time(),
    ],
    CURLOPT_TIMEOUT => 30,
]);
$putRespRaw = curl_exec($chPut);
$putCode    = curl_getinfo($chPut, CURLINFO_HTTP_CODE);
$putCurlErr = curl_error($chPut);
curl_close($chPut);

$putResp = json_decode($putRespRaw, true);

echo json_encode([
    'preapproval_id' => $preapprovalId,
    'get' => [
        'http_code'  => $getCode,
        'curl_error' => $getCurlErr,
        'status'     => $getResp['status'] ?? null,
        'response'   => $getResp,
    ],
    'put' => [
        'http_code'   => $putCode,
        'curl_error'  => $putCurlErr,
        'raw_body'    => $putRespRaw,
        'response'    => $putResp,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
