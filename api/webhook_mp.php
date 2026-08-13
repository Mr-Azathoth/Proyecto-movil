<?php
// Webhook de Mercado Pago — notificaciones de pago (Checkout Pro)
// MP hace POST a esta URL cuando hay eventos de pago.
// Documentación: https://www.mercadopago.cl/developers/es/docs/checkout-pro/payment-notifications
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

// Responder 200 de inmediato (MP reintenta si no responde rápido)
http_response_code(200);

$body   = file_get_contents('php://input');
$data   = json_decode($body, true) ?? [];
$topic  = $_GET['topic'] ?? ($data['type'] ?? '');
$id     = $_GET['id']    ?? ($data['data']['id'] ?? '');

// Solo procesar notificaciones de pago
if ($topic !== 'payment' || !$id) exit;

// Verificar firma HMAC si está configurada
if (MP_WEBHOOK_SECRET) {
    $signature  = $_SERVER['HTTP_X_SIGNATURE']          ?? '';
    $requestId  = $_SERVER['HTTP_X_REQUEST_ID']         ?? '';
    $tsMatch    = [];
    preg_match('/ts=(\d+)/', $signature, $tsMatch);
    $ts         = $tsMatch[1] ?? '';
    $v1Match    = [];
    preg_match('/v1=([a-f0-9]+)/', $signature, $v1Match);
    $v1         = $v1Match[1] ?? '';
    $manifest   = 'id:' . $id . ';request-id:' . $requestId . ';ts:' . $ts . ';';
    $expected   = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
    if (!hash_equals($expected, $v1)) {
        error_log('[webhook_mp] firma inválida');
        exit;
    }
}

// Consultar el pago a MP para obtener detalles verificados
$ch = curl_init('https://api.mercadopago.com/v1/payments/' . urlencode($id));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) exit;

$payment = json_decode($resp, true);
$status  = $payment['status']             ?? '';
$extRef  = $payment['external_reference'] ?? '';

if ($status !== 'approved' || !$extRef) exit;

// Parsear external_reference: eid_{id}_plan_{key}
if (!preg_match('/^eid_(\d+)_plan_([a-z0-9]+)$/', $extRef, $m)) exit;
$eid     = (int)$m[1];
$planKey = $m[2];
if (!isset(MP_PLANES[$planKey])) exit;

$db = getDB();

// Migración defensiva
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS mp_preapproval_id VARCHAR(80) NULL DEFAULT NULL"); } catch(PDOException $e) {}

// Idempotencia: no activar dos veces el mismo pago
$already = $db->prepare("SELECT mp_preapproval_id FROM empresas WHERE id_empresa=? LIMIT 1");
$already->execute([$eid]);
if ($already->fetchColumn() === (string)$id) exit;

// Activar plan
function activar_plan_webhook(PDO $db, int $eid, array $planInfo, string $estado, string $paymentId): void {
    $row = $db->prepare("SELECT plan_vencimiento FROM empresas WHERE id_empresa=?");
    $row->execute([$eid]);
    $actual = $row->fetchColumn();
    $base       = ($actual && strtotime($actual) > time()) ? $actual : date('Y-m-d');
    $nuevaFecha = date('Y-m-d', strtotime($base . " +{$planInfo['meses']} month"));

    $db->beginTransaction();
    try {
        $db->prepare(
            "UPDATE empresas SET activa=1, plan_estado='Activo', plan_tipo=?, plan_vencimiento=?, mp_preapproval_id=? WHERE id_empresa=?"
        )->execute([$planInfo['nombre'], $nuevaFecha, $paymentId, $eid]);

        $db->prepare(
            "INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado) VALUES (?,?,?,?,?)"
        )->execute([$eid, date('Y-m-d'), $planInfo['precio'], 'Pago Centrotec — Plan ' . $planInfo['nombre'] . ' — Mercado Pago', $estado]);
        $db->commit();
    } catch(Throwable $e) { $db->rollBack(); }
}

activar_plan_webhook($db, $eid, MP_PLANES[$planKey], 'Pagado', $id);
