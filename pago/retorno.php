<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/mailer.php';

// Si la sesión expiró durante el pago, guardar URL de retorno y redirigir al login con aviso
remember_check();
if (!logueado()) {
    $here = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $_SESSION['redirect_after_login'] = $here;
    header('Location: ' . BASE . '/index.php?pago_pendiente=1'); exit;
}
session_check_timeout();
$_SESSION['last_activity'] = time();

$gateway       = $_GET['gateway']        ?? '';
$preapprovalId = $_GET['preapproval_id'] ?? '';
$paymentId     = $_GET['payment_id']     ?? $_GET['collection_id'] ?? '';
$mpStatus      = $_GET['status']         ?? '';
$extRef        = $_GET['external_reference'] ?? '';

// Detectar gateway por parámetros que MP agrega automáticamente
if (!$gateway && $preapprovalId) $gateway = 'mp_sub';
if (!$gateway && $paymentId)     $gateway = 'mp_checkout';
$eid = eid();
$db  = getDB();

// Migración silenciosa — columna usada más abajo; registro.php llega aquí sin pasar por suscripcion.php
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS mp_preapproval_id VARCHAR(80) NULL DEFAULT NULL"); } catch(PDOException $e) {}

function activar_plan(PDO $db, int $eid, array $planInfo, string $estado, string $gateway = ''): void {
    $row = $db->prepare("SELECT plan_vencimiento FROM empresas WHERE id_empresa = ?");
    $row->execute([$eid]);
    $actual = $row->fetchColumn();

    // Si el plan vigente no ha vencido aún, extender desde ese día. Si ya venció, desde hoy.
    $base       = ($actual && strtotime($actual) > time()) ? $actual : date('Y-m-d');
    $nuevaFecha = date('Y-m-d', strtotime($base . " +{$planInfo['meses']} month"));
    $label      = $gateway ? 'Suscripción Centrotec – ' . $planInfo['nombre'] . ' – ' . $gateway
                           : 'Suscripción Centrotec – ' . $planInfo['nombre'];

    $db->beginTransaction();
    try {
        $db->prepare(
            "UPDATE empresas
             SET activa=1, plan_estado='Activo', plan_tipo=?, plan_vencimiento=?
             WHERE id_empresa=?"
        )->execute([$planInfo['nombre'], $nuevaFecha, $eid]);

        $db->prepare(
            "INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $eid,
            date('Y-m-d'),
            $planInfo['precio'],
            $label,
            $estado,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// ── MERCADO PAGO — retorno de Checkout Pro (pago único) ──────
if ($gateway === 'mp_checkout') {
    $activado = false;

    if ($paymentId && $mpStatus === 'approved' && $extRef) {
        // Evitar doble activación con el mismo payment_id
        try {
            $already = $db->prepare("SELECT mp_preapproval_id FROM empresas WHERE id_empresa=? LIMIT 1");
            $already->execute([$eid]);
            if ($already->fetchColumn() === $paymentId) {
                header('Location: ' . BASE . '/app.php?pago=exitoso');
                exit;
            }
        } catch(PDOException $e) {}

        // external_reference: eid_{id}_plan_{key}
        if (preg_match('/^eid_(\d+)_plan_([a-z0-9]+)$/', $extRef, $m)) {
            $refEid  = (int)$m[1];
            $planKey = $m[2];

            // Verificar que corresponde a este usuario y plan válido
            if ($refEid === $eid && isset(MP_PLANES[$planKey])) {
                // Verificar el pago directamente con la API de MP (nunca confiar solo en GET params)
                $ch = curl_init('https://api.mercadopago.com/v1/payments/' . urlencode($paymentId));
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
                    CURLOPT_TIMEOUT        => 10,
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code === 200) {
                    $payment = json_decode($resp, true);
                    if (($payment['status'] ?? '') === 'approved') {
                        $planInfo = MP_PLANES[$planKey];
                        activar_plan($db, $eid, $planInfo, 'Pagado', 'Mercado Pago');

                        // Guardar payment_id para idempotencia
                        try {
                            $db->prepare("UPDATE empresas SET mp_preapproval_id=? WHERE id_empresa=?")
                               ->execute([$paymentId, $eid]);
                        } catch(PDOException $e) {}

                        $activado = true;

                        // Correo de confirmación
                        try {
                            $emp = $db->prepare("SELECT nombre, correo, plan_vencimiento FROM empresas WHERE id_empresa=? LIMIT 1");
                            $emp->execute([$eid]);
                            $empresa = $emp->fetch();
                            if ($empresa && $empresa['correo']) {
                                $nombre = htmlspecialchars($empresa['nombre'] ?? 'Cliente');
                                $plan   = htmlspecialchars($planInfo['nombre']);
                                $monto  = number_format($planInfo['precio'], 0, ',', '.');
                                $fecha  = date('d/m/Y', strtotime($empresa['plan_vencimiento']));
                                $html = "
                                <div style='font-family:Inter,sans-serif;max-width:520px;margin:0 auto;background:#161b22;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;'>
                                  <div style='background:linear-gradient(135deg,#1a3a2a,#1d9e75);padding:24px 28px;'>
                                    <h2 style='color:#fff;margin:0;font-size:18px;'>Pago confirmado</h2>
                                  </div>
                                  <div style='padding:24px 28px;color:#e6edf3;line-height:1.6;'>
                                    <p>Hola <strong>{$nombre}</strong>,</p>
                                    <p>Tu pago fue procesado exitosamente. Tu acceso a Centrotec ha sido activado.</p>
                                    <div style='background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:16px 20px;margin:20px 0;'>
                                      <table style='width:100%;border-collapse:collapse;font-size:13px;'>
                                        <tr><td style='color:#8b949e;padding:4px 0;'>Plan</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>{$plan}</td></tr>
                                        <tr><td style='color:#8b949e;padding:4px 0;'>Monto</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>\${$monto} CLP</td></tr>
                                        <tr><td style='color:#8b949e;padding:4px 0;'>Acceso hasta</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>{$fecha}</td></tr>
                                      </table>
                                    </div>
                                    <p>Puedes revisar tu historial de pagos desde <a href='".BASE."/app.php' style='color:#2f81f7;'>el panel</a>.</p>
                                    <p style='margin-top:24px;font-size:12px;color:#6e7681;'>Este es un correo automático, por favor no respondas.</p>
                                  </div>
                                </div>";
                                send_email($empresa['correo'], $nombre, 'Pago confirmado — Centrotec', $html);
                            }
                        } catch(Throwable $e) {}
                    }
                }
            }
        }
    }

    header('Location: ' . BASE . '/app.php?pago=' . ($activado ? 'exitoso' : 'procesando'));
    exit;
}

// ── MERCADO PAGO — retorno de suscripción recurrente (legacy) ─
if ($gateway === 'mp_sub') {
    if ($preapprovalId) {
        // Evitar activar dos veces el mismo preapproval
        try {
            $already = $db->prepare("SELECT mp_preapproval_id FROM empresas WHERE id_empresa=? LIMIT 1");
            $already->execute([$eid]);
            if ($already->fetchColumn() === $preapprovalId) {
                header('Location: '.BASE.'/app.php?pago=suscripcion');
                exit;
            }
        } catch(PDOException $e) {}
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

        $activado = false;
        if ($code === 200) {
            $sub    = json_decode($resp, true);
            $status = $sub['status'] ?? '';

            if ($status === 'authorized') {
                // Identificar plan: por external_reference o por preapproval_plan_id
                $planInfo = null;
                $extRef   = $sub['external_reference'] ?? '';
                if (preg_match('/^eid_\d+_plan_([a-z0-9]+)$/', $extRef, $m) && isset(MP_PLANES[$m[1]])) {
                    $planInfo = MP_PLANES[$m[1]];
                }
                if (!$planInfo) {
                    $planId = $sub['preapproval_plan_id'] ?? '';
                    foreach (MP_PLANES as $p) {
                        if ($p['id'] === $planId) { $planInfo = $p; break; }
                    }
                }
                if ($planInfo) {
                    activar_plan($db, $eid, $planInfo, 'Pagado', 'Mercado Pago');
                    try {
                        $db->prepare("UPDATE empresas SET mp_preapproval_id=? WHERE id_empresa=?")
                           ->execute([$preapprovalId, $eid]);
                    } catch(PDOException $e) {}
                    $activado = true;

                    // Correo de confirmación de suscripción
                    try {
                        $emp = $db->prepare("SELECT nombre, correo, plan_vencimiento FROM empresas WHERE id_empresa = ? LIMIT 1");
                        $emp->execute([$eid]);
                        $empresa = $emp->fetch();
                        if ($empresa && $empresa['correo']) {
                            $nombre = htmlspecialchars($empresa['nombre'] ?? 'Cliente');
                            $plan   = htmlspecialchars($planInfo['nombre']);
                            $monto  = number_format($planInfo['precio'], 0, ',', '.');
                            $fecha  = date('d/m/Y', strtotime($empresa['plan_vencimiento']));
                            $html = "
                            <div style='font-family:Inter,sans-serif;max-width:520px;margin:0 auto;background:#161b22;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;'>
                              <div style='background:linear-gradient(135deg,#1a3a2a,#1d9e75);padding:24px 28px;'>
                                <h2 style='color:#fff;margin:0;font-size:18px;'>Suscripción activada</h2>
                              </div>
                              <div style='padding:24px 28px;color:#e6edf3;line-height:1.6;'>
                                <p>Hola <strong>{$nombre}</strong>,</p>
                                <p>Tu suscripción a Centrotec ha sido activada correctamente. Gracias por confiar en nosotros.</p>
                                <div style='background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:16px 20px;margin:20px 0;'>
                                  <table style='width:100%;border-collapse:collapse;font-size:13px;'>
                                    <tr><td style='color:#8b949e;padding:4px 0;'>Plan</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>{$plan}</td></tr>
                                    <tr><td style='color:#8b949e;padding:4px 0;'>Monto</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>\${$monto} CLP</td></tr>
                                    <tr><td style='color:#8b949e;padding:4px 0;'>Acceso hasta</td><td style='text-align:right;color:#e6edf3;font-weight:600;'>{$fecha}</td></tr>
                                  </table>
                                </div>
                                <p>Puedes revisar tu suscripción y el historial de pagos desde el panel en <a href='".BASE."/app.php' style='color:#2f81f7;'>centrotec.cl</a>.</p>
                                <p style='margin-top:24px;font-size:12px;color:#6e7681;'>Este es un correo automático, por favor no respondas a este mensaje.</p>
                              </div>
                            </div>";
                            send_email($empresa['correo'], $nombre, 'Tu suscripción ha sido activada — Centrotec', $html);
                        }
                    } catch(Throwable $e) {}
                }
            }
        }
    }

    // authorized → plan activado; cualquier otro estado (pending, etc.) → aviso de procesamiento
    header('Location: '.BASE.'/app.php?pago=' . ($activado ? 'suscripcion' : 'procesando'));
    exit;
}

header('Location: '.BASE.'/app.php');
exit;
