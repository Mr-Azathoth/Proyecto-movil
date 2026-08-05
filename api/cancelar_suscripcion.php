<?php
require_once __DIR__.'/../includes/config.php';
guard();

if (!isAdmin()) json_err('Solo administradores pueden cancelar la suscripción', 403);
csrf_check();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido', 405);

$db  = getDB();
$eid = eid();

$row = $db->prepare("SELECT mp_preapproval_id, plan_estado, plan_vencimiento FROM empresas WHERE id_empresa = ? LIMIT 1");
$row->execute([$eid]);
$empresa = $row->fetch();

if (!$empresa) json_err('Empresa no encontrada', 404);
if ($empresa['plan_estado'] !== 'Activo') json_err('No hay suscripción activa para cancelar');

$preapprovalId = $empresa['mp_preapproval_id'] ?? '';
if (!$preapprovalId) json_err('No se encontró el ID de suscripción. Cancela directamente desde tu cuenta de Mercado Pago.');

// Cancelar en Mercado Pago
$ch = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($preapprovalId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => json_encode(['status' => 'cancelled']),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 && $code !== 201) {
    $mpError = json_decode($resp, true);
    $msg = $mpError['message'] ?? 'Error al comunicarse con Mercado Pago (HTTP ' . $code . ')';
    json_err($msg);
}

// Marcar como cancelado localmente
$db->prepare("UPDATE empresas SET plan_estado='Cancelado', mp_preapproval_id=NULL WHERE id_empresa=?")
   ->execute([$eid]);

json_ok(['vencimiento' => $empresa['plan_vencimiento']]);
