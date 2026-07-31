<?php
/**
 * Script de prueba — simula activación de plan tras pago MP.
 * SOLO funciona en MP_ENV=sandbox. Eliminar antes de producción.
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();
if (!isAdmin())        { http_response_code(403); exit('Solo administradores.'); }
if (MP_ENV !== 'sandbox') { http_response_code(403); exit('Solo disponible en modo sandbox.'); }

$planKey  = $_GET['plan'] ?? '1mes';
$planes   = MP_PLANES;
if (!isset($planes[$planKey])) { http_response_code(400); exit('Plan inválido.'); }

$planInfo = $planes[$planKey];
$db       = getDB();
$eid      = eid();

$row = $db->prepare("SELECT plan_vencimiento FROM empresas WHERE id_empresa = ?");
$row->execute([$eid]);
$actual = $row->fetchColumn();

$base       = ($actual && strtotime($actual) > time()) ? $actual : date('Y-m-d');
$nuevaFecha = date('Y-m-d', strtotime($base . " +{$planInfo['meses']} month"));

$db->beginTransaction();
try {
    $db->prepare(
        "UPDATE empresas SET activa=1, plan_estado='Activo', plan_tipo=?, plan_vencimiento=? WHERE id_empresa=?"
    )->execute([$planInfo['nombre'], $nuevaFecha, $eid]);

    $db->prepare(
        "INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado) VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $eid,
        date('Y-m-d'),
        $planInfo['precio'],
        'TEST — Suscripción simulada: ' . $planInfo['nombre'] . ' vía Mercado Pago (sandbox)',
        'Pagado',
    ]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    http_response_code(500);
    exit('Error al activar plan.');
}

header('Location: ' . BASE . '/app.php?pago=suscripcion');
exit;
