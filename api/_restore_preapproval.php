<?php
// Script temporal de diagnóstico — eliminar tras uso
require_once __DIR__.'/../includes/config.php';
guard();
if (!isAdmin()) die('forbidden');

$db  = getDB();
$eid = eid();

// Buscar suscripciones autorizadas del collector en MP
$ch = curl_init('https://api.mercadopago.com/preapproval/search?status=authorized&limit=20');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);
$results = $data['results'] ?? [];

header('Content-Type: application/json');

$match = $results[0] ?? null;
if (!$match) {
    echo json_encode(['ok' => false, 'msg' => 'No se encontró ninguna suscripción autorizada', 'http_code' => $code]);
    exit;
}

// Usar el campo 'id' real del objeto (instance ID, no el plan ID)
$preapprovalId = $match['id'] ?? '';
if (!$preapprovalId) {
    echo json_encode(['ok' => false, 'msg' => 'No se pudo extraer el id de la suscripción', 'match_keys' => array_keys($match)]);
    exit;
}

// Verificar GET sobre ese ID antes de hacer PATCH
$chGet = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($preapprovalId));
curl_setopt_array($chGet, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT        => 10,
]);
$getRespBody = curl_exec($chGet);
$getCode     = curl_getinfo($chGet, CURLINFO_HTTP_CODE);
curl_close($chGet);
$getResp     = json_decode($getRespBody, true);

// Intentar PATCH con ese mismo ID
$chPatch = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($preapprovalId));
curl_setopt_array($chPatch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => json_encode(['status' => 'cancelled']),
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN, 'Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 15,
]);
$patchRespBody = curl_exec($chPatch);
$patchCode     = curl_getinfo($chPatch, CURLINFO_HTTP_CODE);
curl_close($chPatch);

// Restaurar en DB
$db->prepare("UPDATE empresas SET plan_estado='Activo', mp_preapproval_id=? WHERE id_empresa=?")
   ->execute([$preapprovalId, $eid]);

$preview = substr($preapprovalId, 0, 4) . '...' . substr($preapprovalId, -4);
echo json_encode([
    'ok'           => true,
    'id_preview'   => $preview,
    'external_ref' => $match['external_reference'] ?? '',
    'get_code'     => $getCode,
    'get_status'   => $getResp['status'] ?? null,
    'get_collector'=> $getResp['collector_id'] ?? null,
    'patch_code'   => $patchCode,
    'patch_body'   => $patchRespBody,
    'restored'     => true,
]);
