<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id_marca = (int) ($_GET['id_marca'] ?? 0);
    if (!$id_marca) json_err('id_marca requerido.');

    $s = $db->prepare(
        "SELECT id_modelo, nombre FROM modelos_cat WHERE id_marca = ? AND activo = 1 ORDER BY nombre ASC"
    );
    $s->execute([$id_marca]);
    json_ok($s->fetchAll());
}

if ($method === 'POST') {
    csrf_check();

    $nombre   = trim($_POST['nombre']   ?? '');
    $id_marca = (int) ($_POST['id_marca'] ?? 0);

    if (!$nombre)              json_err('El nombre del modelo es obligatorio.');
    if (strlen($nombre) > 100) json_err('Nombre demasiado largo (máx. 100 caracteres).');
    if (!$id_marca)            json_err('id_marca requerido.');

    // Verificar que la marca existe
    $chk = $db->prepare("SELECT id_marca FROM marcas_cat WHERE id_marca = ? AND activo = 1");
    $chk->execute([$id_marca]);
    if (!$chk->fetch()) json_err('Marca no encontrada.', 404);

    // Idempotente: devolver modelo existente si ya existe para esa marca
    $existe = $db->prepare(
        "SELECT id_modelo, nombre FROM modelos_cat WHERE id_marca = ? AND nombre = ?"
    );
    $existe->execute([$id_marca, $nombre]);
    $row = $existe->fetch();
    if ($row) {
        json_ok(['id_modelo' => (int) $row['id_modelo'], 'nombre' => $row['nombre'], 'nuevo' => false]);
    }

    $db->prepare("INSERT INTO modelos_cat (id_marca, nombre) VALUES (?, ?)")->execute([$id_marca, $nombre]);
    json_ok(['id_modelo' => (int) $db->lastInsertId(), 'nombre' => $nombre, 'nuevo' => true]);
}
