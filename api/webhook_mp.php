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

// Log headers para debug de HMAC (temporal)
$xSigRaw  = $_SERVER['HTTP_X_SIGNATURE']  ?? '';
$xReqRaw  = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
error_log('[webhook_mp] HIT | topic=' . $topic . ' | id=' . $id . ' | x-sig=' . $xSigRaw . ' | x-req-id=' . $xReqRaw);

// Solo procesar notificaciones de pago
if ($topic !== 'payment' || !$id) exit;

// Verificar firma HMAC si está configurada (clave secreta del panel MP)
if (MP_WEBHOOK_SECRET) {
    $xSignature = $xSigRaw;
    $xRequestId = $xReqRaw;

    $ts = ''; $v1 = '';
    foreach (explode(',', $xSignature) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if (trim($k) === 'ts') $ts = trim($v);
        if (trim($k) === 'v1') $v1 = trim($v);
    }

    // Con secreto configurado, la firma es obligatoria: cabeceras ausentes o invalidas
    // se rechazan (antes se dejaba pasar sin firma, lo cual anulaba la verificacion).
    if ($v1 === '' || $xRequestId === '' || $ts === '') {
        error_log('[webhook_mp] rechazado: cabeceras de firma ausentes | v1=' . ($v1 ?: 'vacío') . ' | xreqid=' . ($xRequestId ?: 'vacío'));
        exit;
    }
    // La notificacion no debe ser mas vieja que 5 minutos (evita reintento/repeticion de una firma capturada)
    if (!ctype_digit($ts) || abs(time() - (int)$ts) > 300) {
        error_log('[webhook_mp] rechazado: ts fuera de rango | ts=' . $ts);
        exit;
    }
    // El manifest usa data.id en minúsculas según la doc de MP
    $manifest = 'id:' . strtolower($id) . ';request-id:' . $xRequestId . ';ts:' . $ts;
    $expected = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);

    if (!hash_equals($expected, $v1)) {
        error_log('[webhook_mp] HMAC fail | manifest=' . $manifest . ' | xsig=' . $xSignature . ' | xreqid=' . $xRequestId);
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

// Activar plan con idempotencia atómica (SELECT FOR UPDATE evita race condition con retorno.php)
function activar_plan_webhook(PDO $db, int $eid, array $planInfo, string $estado, string $paymentId): bool {
    try {
        // Migracion silenciosa: columna para recordar de forma permanente que payment_id ya se acredito
        // (a diferencia de empresas.mp_preapproval_id, que se pone en NULL al cancelar la suscripcion
        // y por eso no sirve por si sola para bloquear el reenvio/repeticion de un pago viejo).
        try { $db->exec("ALTER TABLE historial_pagos ADD COLUMN IF NOT EXISTS mp_payment_id VARCHAR(40) NULL DEFAULT NULL"); } catch (PDOException $e) {}

        $db->beginTransaction();

        $lock = $db->prepare("SELECT mp_preapproval_id FROM empresas WHERE id_empresa=? LIMIT 1 FOR UPDATE");
        $lock->execute([$eid]);
        if ($lock->fetchColumn() === (string)$paymentId) {
            $db->rollBack();
            return false;
        }
        $yaPagado = $db->prepare("SELECT 1 FROM historial_pagos WHERE id_empresa = ? AND mp_payment_id = ? LIMIT 1");
        $yaPagado->execute([$eid, $paymentId]);
        if ($yaPagado->fetch()) {
            $db->rollBack();
            return false;
        }

        $row = $db->prepare("SELECT plan_vencimiento FROM empresas WHERE id_empresa=?");
        $row->execute([$eid]);
        $actual     = $row->fetchColumn();
        $base       = ($actual && strtotime($actual) > time()) ? $actual : date('Y-m-d');
        $nuevaFecha = date('Y-m-d', strtotime($base . " +{$planInfo['meses']} month"));

        $db->prepare(
            "UPDATE empresas SET activa=1, plan_estado='Activo', plan_tipo=?, plan_vencimiento=?, mp_preapproval_id=? WHERE id_empresa=?"
        )->execute([$planInfo['nombre'], $nuevaFecha, $paymentId, $eid]);

        $db->prepare(
            "INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado, mp_payment_id) VALUES (?,?,?,?,?,?)"
        )->execute([$eid, date('Y-m-d'), $planInfo['precio'], 'Pago Centrotec — Plan ' . $planInfo['nombre'] . ' — Mercado Pago', $estado, $paymentId]);

        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[webhook_mp] activar_plan FAILED | eid=' . $eid . ' | payment=' . $paymentId . ' | err=' . $e->getMessage());
        return false;
    }
}

$activado = activar_plan_webhook($db, $eid, MP_PLANES[$planKey], 'Pagado', $id);
error_log('[webhook_mp] resultado=' . ($activado ? 'ACTIVADO' : 'ya-procesado') . ' | eid=' . $eid . ' | plan=' . $planKey . ' | payment=' . $id);
