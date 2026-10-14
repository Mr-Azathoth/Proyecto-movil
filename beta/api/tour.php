<?php
// Tours guiados: que tours ya vio el usuario en sesion. Cada usuario solo ve y modifica los suyos.
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

const TOURS_VALIDOS = ['general', 'servicios', 'inventario', 'config', 'estadisticas', 'soporte', 'ingreso', 'detalle', 'repuesto', 'editrepuesto'];

$db     = getDB();
$uid    = uid();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') json_ok((object) tour_vistos($db, $uid));

csrf_check();

// ── POST: registrar que el usuario completo o salto un tour ──────────────────
if ($method === 'POST') {
    $in      = json_decode(file_get_contents('php://input'), true) ?? [];
    $tour    = $in['tour']    ?? '';
    $version = $in['version'] ?? 1;
    $estado  = $in['estado']  ?? 'completado';

    if (!is_string($tour) || !in_array($tour, TOURS_VALIDOS, true)) json_err('Tour inválido.');
    if (!is_numeric($version) || (int) $version < 1 || (int) $version > 99) json_err('Versión inválida.');
    if (!is_string($estado) || !in_array($estado, ['completado', 'saltado'], true)) json_err('Estado inválido.');

    $db->prepare("INSERT INTO usuario_tour (id_usuario, tour, version, estado) VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE version = VALUES(version), estado = VALUES(estado), visto_en = NOW()")
       ->execute([$uid, $tour, (int) $version, $estado]);
    json_ok(['msg' => 'Guardado.']);
}

// ── DELETE: olvidar lo visto (todos los tours, o solo ?tour=) para que se muestren de nuevo ──
if ($method === 'DELETE') {
    $tour = $_GET['tour'] ?? '';
    if ($tour !== '') {
        if (!in_array($tour, TOURS_VALIDOS, true)) json_err('Tour inválido.');
        $db->prepare("DELETE FROM usuario_tour WHERE id_usuario = ? AND tour = ?")->execute([$uid, $tour]);
    } else {
        $db->prepare("DELETE FROM usuario_tour WHERE id_usuario = ?")->execute([$uid]);
    }
    json_ok(['msg' => 'Los tutoriales volverán a mostrarse.']);
}

json_err('Método no permitido.', 405);
