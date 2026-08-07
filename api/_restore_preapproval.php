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

// Filtrar las que tengan external_reference = eid_{eid}
$match = null;
foreach ($results as $r) {
    if (($r['external_reference'] ?? '') === 'eid_' . $eid) {
        $match = $r;
        break;
    }
}

header('Content-Type: application/json');
if (!$match) {
    echo json_encode(['ok' => false, 'msg' => 'No se encontró suscripción autorizada para eid_'.$eid, 'all' => $results]);
    exit;
}

$preapprovalId = $match['id'];

// Restaurar en DB
$db->prepare("UPDATE empresas SET plan_estado='Activo', mp_preapproval_id=? WHERE id_empresa=?")
   ->execute([$preapprovalId, $eid]);

echo json_encode(['ok' => true, 'preapproval_id' => $preapprovalId, 'status' => $match['status'], 'restored' => true]);
