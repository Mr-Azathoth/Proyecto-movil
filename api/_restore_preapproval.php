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

// Restaurar en DB
$db->prepare("UPDATE empresas SET plan_estado='Activo', mp_preapproval_id=? WHERE id_empresa=?")
   ->execute([$preapprovalId, $eid]);

// Devolver primeros/últimos 4 chars para verificar sin exponer el ID completo
$preview = substr($preapprovalId, 0, 4) . '...' . substr($preapprovalId, -4);
echo json_encode(['ok' => true, 'id_preview' => $preview, 'status' => $match['status'], 'external_ref' => $match['external_reference'] ?? '', 'restored' => true]);
