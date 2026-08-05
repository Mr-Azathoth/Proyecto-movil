<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/mailer.php';
guard();

if (!isAdmin()) json_err('Solo administradores pueden cancelar la suscripción', 403);
csrf_check();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido', 405);

$db  = getDB();
$eid = eid();

// Migración defensiva — columna puede no existir en instalaciones antiguas
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS mp_preapproval_id VARCHAR(80) NULL DEFAULT NULL"); } catch(PDOException $e) {}

$row = $db->prepare("SELECT mp_preapproval_id, plan_estado, plan_vencimiento, nombre, correo FROM empresas WHERE id_empresa = ? LIMIT 1");
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
    if ($code === 0) {
        json_err('No se pudo conectar con Mercado Pago. Intenta nuevamente o cancela desde tu cuenta de MP.');
    }
    json_err('No se pudo cancelar la suscripción en Mercado Pago. Por favor cancela directamente desde mercadopago.cl → Tu actividad → Suscripciones.');
}

// Marcar como cancelado localmente
$db->prepare("UPDATE empresas SET plan_estado='Cancelado', mp_preapproval_id=NULL WHERE id_empresa=?")
   ->execute([$eid]);

// Enviar correo de confirmación al cliente
$correo = $empresa['correo'] ?? '';
$nombre = htmlspecialchars($empresa['nombre'] ?? 'Cliente');
$fecha  = date('d/m/Y', strtotime($empresa['plan_vencimiento']));

if ($correo) {
    $html = "
    <div style='font-family:Inter,sans-serif;max-width:520px;margin:0 auto;background:#161b22;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;'>
      <div style='background:linear-gradient(135deg,#374151,#6b7280);padding:24px 28px;'>
        <h2 style='color:#fff;margin:0;font-size:18px;'>Suscripción cancelada</h2>
      </div>
      <div style='padding:24px 28px;color:#e6edf3;line-height:1.6;'>
        <p>Hola <strong>{$nombre}</strong>,</p>
        <p>Tu suscripción a Centrotec ha sido cancelada correctamente. No se realizarán más cobros automáticos.</p>
        <div style='background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:16px 20px;margin:20px 0;'>
          <p style='margin:0 0 6px;font-size:13px;color:#8b949e;'>Acceso hasta</p>
          <p style='margin:0;font-size:20px;font-weight:700;color:#e6edf3;'>{$fecha}</p>
        </div>
        <p>Puedes seguir usando Centrotec con todas sus funciones hasta esa fecha. Si cambias de opinión, puedes reactivar tu suscripción desde el panel en cualquier momento.</p>
        <p style='margin-top:24px;font-size:12px;color:#6e7681;'>Este es un correo automático, por favor no respondas a este mensaje.</p>
      </div>
    </div>";

    send_email($correo, $nombre, 'Tu suscripción ha sido cancelada — Centrotec', $html);
}

json_ok(['vencimiento' => $empresa['plan_vencimiento']]);
