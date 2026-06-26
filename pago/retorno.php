<?php
require_once __DIR__.'/../includes/config.php';
requireLogin();

$gateway = $_GET['gateway'] ?? '';
$eid     = eid();
$db      = getDB();

function activar_plan(PDO $db, int $eid, array $planInfo, string $estado): void {
    $row = $db->prepare("SELECT plan_vencimiento FROM empresas WHERE id = ?");
    $row->execute([$eid]);
    $actual = $row->fetchColumn();

    $base       = ($actual && strtotime($actual) > time()) ? $actual : date('Y-m-d');
    $nuevaFecha = date('Y-m-d', strtotime($base . " +{$planInfo['meses']} month"));

    $db->prepare(
        "UPDATE empresas SET plan_estado='Activo', plan_tipo=?, plan_vencimiento=? WHERE id=?"
    )->execute([$planInfo['nombre'], $nuevaFecha, $eid]);

    $db->prepare(
        "INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $eid,
        date('Y-m-d'),
        $planInfo['precio'],
        'Suscripción Reparo – ' . $planInfo['nombre'] . ' – Mercado Pago',
        $estado,
    ]);
}

// ── MERCADO PAGO — retorno de suscripción recurrente ─────────
if ($gateway === 'mp_sub') {
    $preapprovalId = $_GET['preapproval_id'] ?? '';

    if ($preapprovalId) {
        // Consultar la suscripción creada a MP para saber qué plan eligió el cliente
        $ch = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($preapprovalId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $sub    = json_decode($resp, true);
            $planId = $sub['preapproval_plan_id'] ?? '';
            $status = $sub['status']              ?? '';

            if ($status === 'authorized') {
                // Buscar plan en nuestra configuración
                $planInfo = null;
                foreach (MP_PLANES as $plan) {
                    if ($plan['id'] === $planId) { $planInfo = $plan; break; }
                }
                if ($planInfo) {
                    // Activar plan inmediatamente con estado Pendiente.
                    // El webhook actualizará a Pagado cuando MP confirme el cobro.
                    activar_plan($db, $eid, $planInfo, 'Pendiente');
                }
            }
        }
    }

    header('Location: /reparo/app.php?pago=suscripcion');
    exit;
}

// ── WEBPAY PLUS ──────────────────────────────────────────────
if ($gateway === 'webpay') {
    // Si el usuario canceló en Webpay, Transbank hace POST con TBK_TOKEN (sin token_ws)
    if (!empty($_POST['TBK_TOKEN']) || empty($_POST['token_ws'])) {
        header('Location: /reparo/pago/cancel.php?gateway=webpay&status=cancelled');
        exit;
    }

    $tokenWs = $_POST['token_ws'];
    $wpBase  = (WP_ENV === 'production')
        ? 'https://webpay3g.transbank.cl/rswebpaytransaction/api/webpay/v1.2/transactions/'
        : 'https://webpay3gint.transbank.cl/rswebpaytransaction/api/webpay/v1.2/transactions/';

    $ch = curl_init($wpBase . urlencode($tokenWs));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => '{}',
        CURLOPT_HTTPHEADER     => [
            'Tbk-Api-Key-Id: '     . WP_COMMERCE_CODE,
            'Tbk-Api-Key-Secret: ' . WP_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($resp, true);
        if (($data['response_code'] ?? -1) === 0 && ($data['status'] ?? '') === 'AUTHORIZED') {
            $montoReal = (int)($data['amount'] ?? $precio);
            extender_plan($db, $eid, $meses, $montoReal, 'Suscripción Reparo – Webpay');
            header('Location: /reparo/app.php?pago=ok');
            exit;
        }
    }

    header('Location: /reparo/pago/cancel.php?gateway=webpay&status=rejected');
    exit;
}

header('Location: /reparo/app.php');
exit;
