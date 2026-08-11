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

// Cancelar preapprovals huérfanos pending (eid_7)
if (isset($_GET['cancel'])) {
    $ids = ['f1d81c7808bb475aad6c077cff668990', '77de6045f9a0425c8d0ddad7ef5a3769'];
    echo "<h3>Cancelando preapprovals huérfanos</h3>";
    foreach ($ids as $pid) {
        $ch = curl_init('https://api.mercadopago.com/preapproval/' . $pid);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode(['status' => 'cancelled']),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
        ]);
        $r = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $bd = json_decode($r, true);
        $ok = ($bd['status'] ?? '') === 'cancelled';
        echo "<p><b>{$pid}</b>: HTTP {$c} → " . ($ok ? '✅ Cancelado' : '❌ ' . ($bd['message'] ?? $r)) . "</p>";
    }
}

// Buscar preapproval de eid_9 en todos los estados
if ($eid) {
    echo "<h3>Buscando preapprovals para eid={$eid} (todos los estados)</h3>";
    foreach (['pending','authorized','cancelled'] as $st) {
        $ch = curl_init('https://api.mercadopago.com/preapproval/search?status=' . $st . '&limit=20');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN], CURLOPT_TIMEOUT => 15]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $d = json_decode($resp, true);
        foreach (['1mes','3meses','6meses','12meses'] as $pk) {
            $extRef = 'eid_' . $eid . '_plan_' . $pk;
            $found = array_filter($d['results'] ?? [], fn($r) => ($r['external_reference'] ?? '') === $extRef);
            if ($found) {
                echo "<p style='color:green'>Encontrado [{$st}]: {$extRef}</p><pre>" . json_encode(array_values($found), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            }
        }
    }
    echo "<p><a href='?t=ct_diag_9xK2m&cancel=1&eid={$eid}' style='background:red;color:white;padding:8px 16px;text-decoration:none;border-radius:4px'>Cancelar preapprovals huérfanos (eid_7)</a></p>";
}

// Borrar empresa de prueba (solo eid con nombre que no sea cliente real)
if (isset($_GET['del'])) {
    $delId = (int)$_GET['del'];
    $check = $db->prepare("SELECT nombre, correo FROM empresas WHERE id_empresa=? LIMIT 1");
    $check->execute([$delId]);
    $emp = $check->fetch();
    if ($emp && strtolower($emp['correo']) !== 'planetelectroled@gmail.com') {
        $db->prepare("DELETE FROM usuarios WHERE id_empresa=?")->execute([$delId]);
        $db->prepare("DELETE FROM empresas WHERE id_empresa=?")->execute([$delId]);
        echo "<p style='color:green'>✅ Empresa {$delId} ({$emp['nombre']}) eliminada.</p>";
    } else {
        echo "<p style='color:red'>❌ No se puede eliminar esa empresa.</p>";
    }
}
echo "<p><a href='?t=ct_diag_9xK2m&del=9' style='background:#c00;color:white;padding:8px 16px;text-decoration:none;border-radius:4px' onclick=\"return confirm('¿Borrar empresa 9?')\">Borrar empresa 9 (Empresa3)</a></p>";
