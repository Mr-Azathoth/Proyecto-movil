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

// Listar todos los preapprovals recientes
echo "<h3>Todos los preapprovals en MP (últimos 20, ordenados por fecha)</h3>";
$ch0 = curl_init('https://api.mercadopago.com/preapproval/search?status=pending&limit=10');
curl_setopt_array($ch0, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT => 15,
]);
$resp0 = curl_exec($ch0);
$code0 = curl_getinfo($ch0, CURLINFO_HTTP_CODE);
curl_close($ch0);
$all = json_decode($resp0, true);
echo "<p>HTTP {$code0} — Total: " . ($all['paging']['total'] ?? '?') . "</p>";
foreach (($all['results'] ?? []) as $pa) {
    $color = $pa['status'] === 'authorized' ? 'green' : ($pa['status'] === 'pending' ? 'orange' : 'gray');
    echo "<div style='border:1px solid #ccc;margin:6px;padding:8px;font-family:monospace;font-size:12px'>";
    echo "<b style='color:{$color}'>[{$pa['status']}]</b> ";
    echo "ID: {$pa['id']}<br>";
    echo "reason: {$pa['reason']} | ext_ref: " . ($pa['external_reference'] ?? '-') . "<br>";
    echo "payer_id: " . ($pa['payer_id'] ?? '-') . " | created: {$pa['date_created']}<br>";
    echo "semaphore: " . ($pa['summarized']['semaphore'] ?? '-');
    echo "</div>";
}

if ($eid) {
    foreach (['1mes','3meses','6meses','12meses'] as $pk) {
        $extRef = 'eid_' . $eid . '_plan_' . $pk;
        $ch = curl_init('https://api.mercadopago.com/preapproval/search?external_reference=' . urlencode($extRef) . '&limit=5');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $d = json_decode($resp, true);
        $found = array_filter($d['results'] ?? [], fn($r) => ($r['external_reference'] ?? '') === $extRef);
        if ($found) {
            echo "<h3>Preapproval encontrado: {$extRef}</h3><pre>" . json_encode(array_values($found), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
    }
}
