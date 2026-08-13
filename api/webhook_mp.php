<?php
// Webhook de Mercado Pago — notificaciones de pago (Checkout Pro)
// Documentación: https://www.mercadopago.cl/developers/es/docs/checkout-pro/payment-notifications
require_once __DIR__ . '/../includes/config.php';

// Responder 200 de inmediato (MP reintenta cada 15 min si no recibe respuesta)
http_response_code(200);

$body = file_get_contents('php://input');
$data = json_decode($body, true) ?? [];

// PHP convierte "data.id" → "data_id" en $_GET, por eso parseamos el query string crudo
$rawQuery = $_SERVER['QUERY_STRING'] ?? '';
preg_match('/(?:^|&)data\.id=([^&]*)/', $rawQuery, $mId);
preg_match('/(?:^|&)type=([^&]*)/',     $rawQuery, $mType);

$id    = isset($mId[1])   ? urldecode($mId[1])   : ($data['data']['id'] ?? '');
$topic = isset($mType[1]) ? urldecode($mType[1]) : ($data['type']       ?? '');

// Solo procesar notificaciones de pago
if ($topic !== 'payment' || !$id) exit;

// Verificar firma HMAC si está configurada (clave secreta del panel MP)
if (MP_WEBHOOK_SECRET) {
    $xSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

    $ts = ''; $v1 = '';
    foreach (explode(',', $xSignature) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if (trim($k) === 'ts') $ts = trim($v);
        if (trim($k) === 'v1') $v1 = trim($v);
    }

    // El manifest usa data.id en minúsculas según la doc de MP
    $manifest = 'id:' . strtolower($id) . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
    $expected = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);

    if (!hash_equals($expected, $v1)) {
        error_log('[webhook_mp] firma HMAC inválida');
        exit;
    }
}

// Verificar el pago directamente con la API de MP (nunca confiar solo en la notificación)
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
