<?php
// Diagnóstico temporal — eliminar tras usar
if (($_GET['t'] ?? '') !== 'ct_diag_9xK2m') { http_response_code(403); exit('403'); }
require_once __DIR__ . '/includes/config.php';

$db = getDB();

// Buscar empresa más reciente con preapproval
$row = $db->query("SELECT id_empresa, nombre, correo, mp_preapproval_id, plan_estado, plan_tipo, creada_en FROM empresas ORDER BY id_empresa DESC LIMIT 5")->fetchAll();

echo "<h3>Últimas empresas</h3><pre>";
foreach ($row as $r) print_r($r);
echo "</pre>";

// Si hay empresa, buscar preapproval en MP por external_reference
$eid = (int)($_GET['eid'] ?? ($row[0]['id_empresa'] ?? 0));
if ($eid) {
    $extRef = 'eid_' . $eid . '_plan_mensual';
    echo "<h3>Buscando preapproval en MP para eid={$eid} (external_reference={$extRef})</h3>";

    $ch = curl_init('https://api.mercadopago.com/preapproval/search?external_reference=' . urlencode($extRef) . '&limit=5');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<p>HTTP {$code}</p><pre>" . json_encode(json_decode($resp, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

    // También probar plan anual
    $extRef2 = 'eid_' . $eid . '_plan_anual';
    $ch2 = curl_init('https://api.mercadopago.com/preapproval/search?external_reference=' . urlencode($extRef2) . '&limit=5');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp2 = curl_exec($ch2);
    curl_close($ch2);
    echo "<h3>Plan anual</h3><pre>" . json_encode(json_decode($resp2, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
}
