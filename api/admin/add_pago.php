<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
sadmin_guard();
sadmin_csrf_check();

$id     = (int)($_POST['id_empresa']  ?? 0);
$monto  = (float)($_POST['monto']     ?? 0);
$desc   = trim($_POST['descripcion']  ?? '');
$estado = trim($_POST['estado']       ?? 'Pagado');
$fecha  = trim($_POST['fecha']        ?? date('Y-m-d'));

if (!$id || $monto <= 0 || $desc === '') sadmin_json_err('Completa todos los campos.');

$estados_validos = ['Pagado', 'Pendiente', 'Anulado'];
if (!in_array($estado, $estados_validos)) sadmin_json_err('Estado inválido.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) sadmin_json_err('Fecha inválida.');

$db = getDB();
$db->prepare("INSERT INTO historial_pagos (id_empresa, fecha, monto, descripcion, estado) VALUES (?,?,?,?,?)")
   ->execute([$id, $fecha, $monto, $desc, $estado]);

$nuevo_id = $db->lastInsertId();
sadmin_json_ok([
    'id'          => $nuevo_id,
    'fecha'       => $fecha,
    'monto'       => $monto,
    'descripcion' => $desc,
    'estado'      => $estado,
]);
