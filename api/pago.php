<?php
require_once __DIR__.'/../includes/config.php';

// Verificación liviana: permite planes vencidos para que el muro de pago funcione
if (!logueado()) json_err('No autorizado', 401);
session_check_timeout();
$_SESSION['last_activity'] = time();
$_pago_db = getDB();
$_pu = $_pago_db->prepare("SELECT activo FROM usuarios WHERE id_usuario = ? LIMIT 1");
$_pu->execute([uid()]);
$_purow = $_pu->fetch();
if ($_purow && !(bool)$_purow['activo']) json_err('Cuenta desactivada.', 403);

csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido', 405);
if (!isAdmin()) json_err('Solo administradores pueden gestionar pagos', 403);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$metodo = $input['metodo'] ?? '';
$eid    = eid();
$db     = getDB();

$returnUrl = APP_URL . '/pago/retorno.php';

// ── MERCADO PAGO — Suscripción recurrente ─────────────────────
// Crea la suscripción vía API (el vendedor la controla → puede cancelar via PATCH).
if ($metodo === 'mercadopago') {
    $planKey = $input['plan'] ?? '';
    $planes  = MP_PLANES;
    if (!isset($planes[$planKey])) json_err('Plan no válido');

    $plan = $planes[$planKey];

    $empRow = $db->prepare("SELECT correo FROM empresas WHERE id_empresa = ? LIMIT 1");
    $empRow->execute([$eid]);
    $payerEmail = $empRow->fetchColumn() ?: '';
    if (!$payerEmail) json_err('No se encontró el correo registrado. Actualiza tu correo en Configuración.');

    $ch = curl_init('https://api.mercadopago.com/preapproval');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'preapproval_plan_id' => $plan['id'],
            'payer_email'         => $payerEmail,
            'back_url'            => $returnUrl . '?gateway=mp_sub',
            'external_reference'  => 'eid_' . $eid . '_plan_' . $planKey,
            'status'              => 'pending',
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 && $code !== 201) json_err('Error al crear la suscripción en Mercado Pago. Intenta de nuevo.');

    $data      = json_decode($resp, true);
    $initPoint = $data['init_point'] ?? '';
    if (!$initPoint) json_err('No se pudo obtener el link de pago. Intenta de nuevo.');

    json_ok(['url' => $initPoint]);
}

json_err('Método de pago no válido');
