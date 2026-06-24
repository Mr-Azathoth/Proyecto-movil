<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db->exec("CREATE TABLE IF NOT EXISTS inventario (
        id_repuesto       INT NOT NULL AUTO_INCREMENT,
        id_empresa        INT NOT NULL,
        codigo            VARCHAR(30)  NOT NULL,
        nombre            VARCHAR(100) NOT NULL,
        marca_compatible  VARCHAR(40)  DEFAULT '',
        modelo_compatible VARCHAR(60)  DEFAULT '',
        precio_venta      INT          DEFAULT 0,
        cantidad          INT          DEFAULT 0,
        PRIMARY KEY (id_repuesto),
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $q   = trim($_GET['q'] ?? '');
    $sql = "SELECT * FROM inventario WHERE id_empresa = ?";
    $p   = [$eid];

    if ($q) {
        $sql .= " AND (nombre LIKE ? OR marca_compatible LIKE ? OR modelo_compatible LIKE ?)";
        $like = "%" . $q . "%";
        $p    = array_merge($p, [$like, $like, $like]);
    }
    $sql .= " ORDER BY nombre ASC";

    $s = $db->prepare($sql);
    $s->execute($p);
    json_ok($s->fetchAll());
}

if ($method === 'POST') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();

    $f = [
        'nombre'            => trim($_POST['nombre']            ?? ''),
        'marca_compatible'  => trim($_POST['marca_compatible']  ?? ''),
        'modelo_compatible' => trim($_POST['modelo_compatible'] ?? ''),
        'precio_venta'      => max(0, (int) ($_POST['precio_venta'] ?? 0)),
        'cantidad'          => max(0, (int) ($_POST['cantidad']     ?? 0)),
    ];

    if (!$f['nombre']) json_err('El nombre es obligatorio.');
    if (strlen($f['nombre']) > 100) json_err('Nombre demasiado largo (máx. 100 caracteres).');

    // Auto-generar código único a partir del nombre
    $slug   = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $f['nombre']));
    $prefix = substr($slug, 0, 6) ?: 'REP';
    $f['codigo'] = $prefix . '-' . substr(uniqid(), -5);

    $db->prepare("INSERT INTO inventario
        (id_empresa, codigo, nombre, marca_compatible, modelo_compatible, precio_venta, cantidad)
        VALUES (?, ?, ?, ?, ?, ?, ?)")
       ->execute([$eid, $f['codigo'], $f['nombre'], $f['marca_compatible'],
                  $f['modelo_compatible'], $f['precio_venta'], $f['cantidad']]);

    json_ok(['msg' => 'Repuesto agregado.']);
}

if ($method === 'PUT') {
    csrf_check();

    $in  = json_decode(file_get_contents('php://input'), true) ?? [];
    $rid = (int) ($in['id'] ?? 0);
    if (!$rid) json_err('ID inválido.');

    $check = $db->prepare("SELECT id_repuesto FROM inventario WHERE id_repuesto = ? AND id_empresa = ?");
    $check->execute([$rid, $eid]);
    if (!$check->fetch()) json_err('Repuesto no encontrado.', 404);

    // Edición completa: solo admin, requiere campo 'nombre' en el payload
    if (isset($in['nombre'])) {
        if (!isAdmin()) json_err('Sin permisos.', 403);
        $nombre = trim($in['nombre']);
        $marca  = trim($in['marca_compatible']  ?? '');
        $modelo = trim($in['modelo_compatible'] ?? '');
        $precio = max(0, (int) ($in['precio_venta'] ?? 0));
        $qty    = max(0, (int) ($in['cantidad']     ?? 0));
        if (!$nombre) json_err('El nombre es obligatorio.');
        if (strlen($nombre) > 100) json_err('Nombre demasiado largo (máx. 100).');
        $db->prepare("UPDATE inventario
            SET nombre=?, marca_compatible=?, modelo_compatible=?, precio_venta=?, cantidad=?
            WHERE id_repuesto=? AND id_empresa=?")
           ->execute([$nombre, $marca, $modelo, $precio, $qty, $rid, $eid]);
        json_ok(['msg' => 'Repuesto actualizado.']);
    }

    // Solo stock: admin y técnico
    $qty = max(0, (int) ($in['cantidad'] ?? 0));
    $db->prepare("UPDATE inventario SET cantidad=? WHERE id_repuesto=? AND id_empresa=?")
       ->execute([$qty, $rid, $eid]);
    json_ok(['msg' => 'Stock actualizado.']);
}
