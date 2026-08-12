<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_config.php';
requireSuperAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']); exit;
}

sadmin_csrf_check();

$id = (int)($_POST['id_empresa'] ?? 0);
if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']); exit;
}

$db = getDB();

// Obtener empresa (SELECT * para tolerar entornos donde mp_preapproval_id aún no existe)
$row = $db->prepare("SELECT * FROM empresas WHERE id_empresa = ? LIMIT 1");
$row->execute([$id]);
$empresa = $row->fetch();
if (!$empresa) {
    echo json_encode(['ok' => false, 'msg' => 'Empresa no encontrada.']); exit;
}

// Cancelar suscripción en Mercado Pago antes de borrar
$mpPreapprovalId = $empresa['mp_preapproval_id'] ?? null;
if ($mpPreapprovalId) {
    $ch = curl_init('https://api.mercadopago.com/preapproval/' . urlencode($mpPreapprovalId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => json_encode(['status' => 'cancelled']),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $mpResp = curl_exec($ch);
    $mpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $mpStatus = json_decode($mpResp, true)['status'] ?? '';
    if ($mpCode !== 200 || $mpStatus !== 'cancelled') {
        error_log("[admin_borrar_empresa] eid={$id} MP cancel failed: code={$mpCode} body={$mpResp}");
        // Log warning but proceed — not blocking deletion
    }
}

// Borrar en cascada.
// Tablas sin FK CASCADE (explícitas): ticket_mensajes → tickets → historial_pagos → inventario → log_acciones
// Tablas con FK CASCADE (automáticas al borrar empresas): reparaciones, historial, observaciones, usuarios, marcas
$db->beginTransaction();
try {
    // ticket_mensajes no tiene FK CASCADE; borrar por id_ticket de esta empresa
    $db->prepare(
        "DELETE tm FROM ticket_mensajes tm
         INNER JOIN tickets t ON t.id_ticket = tm.id_ticket
         WHERE t.id_empresa = ?"
    )->execute([$id]);
    $db->prepare("DELETE FROM tickets        WHERE id_empresa = ?")->execute([$id]);
    $db->prepare("DELETE FROM historial_pagos WHERE id_empresa = ?")->execute([$id]);
    $db->prepare("DELETE FROM inventario     WHERE id_empresa = ?")->execute([$id]);
    $db->prepare("DELETE FROM log_acciones   WHERE id_empresa = ?")->execute([$id]);
    // reparaciones y sus sub-tablas (historial, observaciones), usuarios, marcas → CASCADE
    $db->prepare("DELETE FROM empresas       WHERE id_empresa = ?")->execute([$id]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log("[admin_borrar_empresa] eid={$id} DB error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error al borrar empresa en base de datos.']); exit;
}

echo json_encode(['ok' => true, 'msg' => 'Empresa "' . $empresa['nombre'] . '" eliminada correctamente.']);
