<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $s = $db->query("SELECT id_marca, nombre FROM marcas_cat WHERE activo = 1 ORDER BY nombre ASC");
    json_ok($s->fetchAll());
}

if ($method === 'POST') {
    csrf_check();

    $nombre = trim($_POST['nombre'] ?? '');
    if (!$nombre)              json_err('El nombre de la marca es obligatorio.');
    if (strlen($nombre) > 60)  json_err('Nombre demasiado largo (máx. 60 caracteres).');

    // Idempotente: devolver marca existente si ya existe
    $existe = $db->prepare("SELECT id_marca, nombre FROM marcas_cat WHERE nombre = ?");
    $existe->execute([$nombre]);
    $row = $existe->fetch();
    if ($row) {
        json_ok(['id_marca' => (int) $row['id_marca'], 'nombre' => $row['nombre'], 'nueva' => false]);
    }

    $db->prepare("INSERT INTO marcas_cat (nombre) VALUES (?)")->execute([$nombre]);
    json_ok(['id_marca' => (int) $db->lastInsertId(), 'nombre' => $nombre, 'nueva' => true]);
}
