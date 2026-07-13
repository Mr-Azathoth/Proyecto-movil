<?php
require_once __DIR__.'/../includes/config.php';
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
                    // Activar plan inmediatamente con estado Pendiente.
                    // El webhook actualizará a Pagado cuando MP confirme el cobro.
                    activar_plan($db, $eid, $planInfo, 'Pendiente', 'Mercado Pago');
                }
            }
        }
    }

    header('Location: '.BASE.'/app.php?pago=suscripcion');
    exit;
}

header('Location: '.BASE.'/app.php');
exit;
