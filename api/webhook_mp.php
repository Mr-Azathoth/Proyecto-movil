<?php
/**
 * Webhook de Mercado Pago — recibe notificaciones de pagos de suscripciones.
 * Configura esta URL en tu dashboard de MP:
 *   https://tu-dominio.com/reparo/api/webhook_mp.php
 *
 * Para pruebas en local: usa ngrok y configura https://abc.ngrok.io/reparo/api/webhook_mp.php
 */
require_once __DIR__.'/../includes/config.php';

http_response_code(200); // MP espera 200 inmediato

$payload = file_get_contents('php://input');
$data    = json_decode($payload, true) ?? [];

$type   = $data['type']       ?? ($_GET['type']    ?? '');
$dataId = $data['data']['id'] ?? ($_GET['data_id'] ?? '');

if (!$type || !$dataId) exit;

// Solo procesar pagos (las suscripciones envían type="payment")
if ($type !== 'payment') exit;

// Consultar el pago a la API de MP
$ch = curl_init('https://api.mercadopago.com/v1/payments/' . urlencode($dataId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) exit;

$pago = json_decode($resp, true);
if (!$pago || ($pago['status'] ?? '') !== 'approved') exit;

// Extraer empresa del external_reference (formato: "eid_{id}")
$extRef = $pago['external_reference'] ?? '';
if (!preg_match('/^eid_(\d+)$/', $extRef, $m)) exit;
$eid = (int) $m[1];

// Determinar meses según el monto pagado
$monto  = (int)($pago['transaction_amount'] ?? 0);
$planes = MP_PLANES;
$meses  = 1;
foreach ($planes as $plan) {
    if ((int)$plan['precio'] === $monto) { $meses = $plan['meses']; break; }
}

// Extender plan en la DB
$db  = getDB();
$row = $db->prepare("SELECT plan_vencimiento FROM empresas WHERE id = ?");
$row->execute([$eid]);
$actual = $row->fetchColumn();

$base       = ($actual && strtotime($actual) > time()) ? $actual : date('Y-m-d');
$nuevaFecha = date('Y-m-d', strtotime($base . " +{$meses} month"));

$db->prepare("UPDATE empresas SET plan_estado='Activo', plan_vencimiento=? WHERE id=?")
   ->execute([$nuevaFecha, $eid]);

// Si ya existe una entrada Pendiente de hoy (creada en retorno.php), confirmarla.
// Si no, insertar una nueva (cobros recurrentes posteriores).
$upd = $db->prepare(
    "UPDATE historial_pagos SET estado='Pagado',
            descripcion=CONCAT(descripcion, ' #', ?)
     WHERE id_empresa=? AND estado='Pendiente' AND monto=? AND fecha=?
     LIMIT 1"
);
$upd->execute([$dataId, $eid, $monto, date('Y-m-d')]);

if ($upd->rowCount() === 0) {
    $db->prepare(
        "INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado)
         VALUES (?, ?, ?, ?, 'Pagado')"
    )->execute([
        $eid,
        date('Y-m-d'),
        $monto,
        'Suscripción Reparo – Mercado Pago #' . $dataId,
    ]);
}

exit;
