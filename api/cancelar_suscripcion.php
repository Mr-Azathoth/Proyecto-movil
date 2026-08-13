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
if ($empresa['plan_estado'] !== 'Activo') json_err('No hay plan activo para cancelar');

// Intentar cancelar preapproval antiguo en MP si existe (compatibilidad con cuentas legacy)
$mpCancelOk    = true;
$preapprovalId = $empresa['mp_preapproval_id'] ?? '';
if ($preapprovalId) {
    $mpCancelOk = false;
    $mpLastCode = 0; $mpLastBody = '';
    $idempotencyKey = 'cancel-' . $eid . '-' . $preapprovalId;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        if ($attempt > 1) sleep(2);
        $ch = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($preapprovalId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode(['status' => 'cancelled']),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . MP_ACCESS_TOKEN,
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . $idempotencyKey . '-' . $attempt,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $mpLastBody = curl_exec($ch);
        $mpLastCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($mpLastCode === 200 || $mpLastCode === 201) {
            $mpBody = json_decode($mpLastBody, true);
            if (($mpBody['status'] ?? '') === 'cancelled') { $mpCancelOk = true; break; }
        }
    }
    if (!$mpCancelOk) {
        error_log("[cancelar_suscripcion] eid={$eid} preapproval={$preapprovalId} code={$mpLastCode} body=" . substr($mpLastBody ?? '', 0, 300));
    }
}

// Marcar como cancelado localmente (independiente de si MP respondió ok)
$db->prepare("UPDATE empresas SET plan_estado='Cancelado', mp_preapproval_id=NULL WHERE id_empresa=?")
   ->execute([$eid]);

// Registrar cancelación en el historial de suscripción
$db->prepare(
    "INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado)
     VALUES (?, ?, 0, 'Cancelación de suscripción', 'Cancelado')"
)->execute([$eid, date('Y-m-d')]);

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
        <p>Tu suscripción a Centrotec ha sido cancelada en nuestro sistema. Tu acceso no se renovará automáticamente.</p>
        <p style='background:rgba(29,158,117,0.08);border:1px solid rgba(29,158,117,0.25);border-radius:6px;padding:12px 14px;font-size:13px;color:#1d9e75;'>Tu plan no se renovará automáticamente al vencer. No se realizarán cobros adicionales.</p>
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

$out = ['vencimiento' => $empresa['plan_vencimiento'], 'mp_cancel_ok' => $mpCancelOk];
if (!$mpCancelOk) {
    $mpErr = json_decode($mpLastBody, true);
    $out['mp_error'] = ($mpErr['message'] ?? null) ?: "HTTP {$mpLastCode}";
}
json_ok($out);
