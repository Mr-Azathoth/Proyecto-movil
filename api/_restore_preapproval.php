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

// Usar la primera autorizada, o forzar con ?id=xxx
$forcedId = $_GET['id'] ?? '';

header('Content-Type: application/json');

if ($forcedId) {
    $preapprovalId = $forcedId;
} else {
    $match = $results[0] ?? null;
    if (!$match) {
        echo json_encode(['ok' => false, 'msg' => 'No se encontró ninguna suscripción autorizada', 'code' => $code]);
        exit;
    }
    // Extraer ID desde init_point ya que el campo id puede estar bloqueado
    $initPoint = $match['init_point'] ?? '';
    preg_match('/preapproval_id=([a-f0-9]+)/i', $initPoint, $m);
    $preapprovalId = $m[1] ?? $match['id'];
}

// Restaurar en DB
$db->prepare("UPDATE empresas SET plan_estado='Activo', mp_preapproval_id=? WHERE id_empresa=?")
   ->execute([$preapprovalId, $eid]);

echo json_encode(['ok' => true, 'preapproval_id' => $preapprovalId, 'restored' => true]);
