<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/mailer.php';
requireLogin();

$gateway = $_GET['gateway'] ?? '';
$eid     = eid();
$db      = getDB();

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
                    activar_plan($db, $eid, $planInfo, 'Pendiente', 'Mercado Pago');
                    try {
                        $db->prepare("UPDATE empresas SET mp_preapproval_id=? WHERE id_empresa=?")
                           ->execute([$preapprovalId, $eid]);
                    } catch(PDOException $e) {}

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

    header('Location: '.BASE.'/app.php?pago=suscripcion');
    exit;
}

header('Location: '.BASE.'/app.php');
exit;
