<?php
// Datos sinteticos para las pruebas de integracion. SOLO consola y SOLO contra centrotec_beta.
// Uso: php tests/seed.php   (antes: php tests/reset.php para partir de cero)
// Genera claves aleatorias y las guarda en tests/.creds.json (ignorado por git y bloqueado por .htaccess).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/config.php';

$db = getDB();
if ($db->query("SELECT DATABASE()")->fetchColumn() !== 'centrotec_beta') exit("Abortado: esta herramienta solo corre contra centrotec_beta.\n");
schema_sucursales_asegurar($db);
if ($db->query("SELECT COUNT(*) FROM empresas WHERE subdominio IN ('testsuca','testsucb')")->fetchColumn() > 0) {
    exit("Ya existen las empresas de prueba. Ejecuta primero: php tests/reset.php\n");
}

$creds = [];
$mkUser = function (int $eid, string $user, string $nombre, string $cargo, int $suc) use ($db, &$creds) {
    $p = bin2hex(random_bytes(10));
    $db->prepare("INSERT INTO usuarios (id_empresa, user, nombre, pass, cargo, activo, id_sucursal) VALUES (?,?,?,?,?,1,?)")
       ->execute([$eid, $user, $nombre, password_hash($p, PASSWORD_BCRYPT), $cargo, $suc]);
    $creds[$user] = $p;
    return (int) $db->lastInsertId();
};
$mkEmp = function (string $nombre, string $sub, string $mail) use ($db) {
    $db->prepare("INSERT INTO empresas (nombre, subdominio, correo, activa, plan_tipo, plan_estado, plan_vencimiento, creada_en)
                  VALUES (?,?,?,1,'Trial','Trial',DATE_ADD(NOW(), INTERVAL 30 DAY),NOW())")->execute([$nombre, $sub, $mail]);
    return (int) $db->lastInsertId();
};
$mkSuc = function (int $eid, string $n, int $bodega = 0) use ($db) {
    $db->prepare("INSERT INTO sucursales (id_empresa, nombre, es_bodega) VALUES (?,?,?)")->execute([$eid, $n, $bodega]);
    return (int) $db->lastInsertId();
};
$mkRep = function (int $eid, int $suc, string $cli, string $by) use ($db) {
    $db->prepare("INSERT INTO reparaciones (id_empresa, nombre_cliente, telefono_cliente, tipo_ingreso, marca_ingreso, modelo_ingreso,
                  dano_ingreso, valor_ingreso, status, ingresado_por, id_sucursal, codigo_seguimiento)
                  VALUES (?,?,'+56911111111','Telefono','Samsung','A54','Pantalla',10000,'Ingresado',?,?,?)")
       ->execute([$eid, $cli, $by, $suc, strtoupper(substr(md5($cli . microtime()), 0, 6))]);
    return (int) $db->lastInsertId();
};

$A = $mkEmp('Test Sucursales A', 'testsuca', 'a@test.invalid');
$cen = $mkSuc($A, 'Centro'); $nor = $mkSuc($A, 'Norte'); $bod = $mkSuc($A, 'Bodega', 1);
$mkUser($A, 'adminA', 'Admin A', 'Admin', $cen);
$mkUser($A, 'tecCentro', 'Tec Centro', 'Tecnico', $cen);
$mkUser($A, 'tecNorte',  'Tec Norte',  'Tecnico', $nor);
$tm = $mkUser($A, 'tecMulti', 'Tec Multi', 'Tecnico', $cen);
$db->prepare("INSERT INTO usuario_sucursales VALUES (?,?)")->execute([$tm, $nor]);

$B  = $mkEmp('Test Sucursales B', 'testsucb', 'b@test.invalid');
$b1 = $mkSuc($B, 'B1');
$mkUser($B, 'adminB', 'Admin B', 'Admin', $b1);

$ids = [
    'A' => $A, 'B' => $B, 'cen' => $cen, 'nor' => $nor, 'bod' => $bod, 'b1' => $b1,
    'repCen1' => $mkRep($A, $cen, 'Cliente Centro 1', 'tecCentro'),
    'repCen2' => $mkRep($A, $cen, 'Cliente Centro 2', 'tecCentro'),
    'repNor1' => $mkRep($A, $nor, 'Cliente Norte 1', 'tecNorte'),
    'repNor2' => $mkRep($A, $nor, 'Cliente Norte 2', 'tecNorte'),
    'repB1'   => $mkRep($B, $b1,  'Cliente B1', 'adminB'),
];

// Inventario en formato antiguo (columna cantidad de inventario): asi tambien se prueba la migracion de stock.
$ins = $db->prepare("INSERT INTO inventario (id_empresa,codigo,nombre,marca_compatible,modelo_compatible,precio_venta,cantidad,cantidad_reservada) VALUES (?,?,?,?,?,?,?,0)");
$inv = [];
foreach ([[$A, 'PANT-A1', 'Pantalla A54', 'Samsung', 'A54', 45000, 10], [$A, 'BAT-X1', 'Bateria X', 'Samsung', 'A54', 12000, 5],
          [$A, 'RARO-1', 'Repuesto Raro', 'Apple', 'X', 9000, 1], [$B, 'PANT-B1', 'Repuesto B', 'Xiaomi', 'R', 5000, 3]] as $r) {
    $ins->execute($r);
    $inv[$r[2]] = (int) $db->lastInsertId();
}
$db->exec("INSERT IGNORE INTO inventario_stock (id_repuesto, id_sucursal, id_empresa, cantidad, cantidad_reservada)
           SELECT i.id_repuesto, s.id_sucursal, i.id_empresa, i.cantidad, 0 FROM inventario i
             JOIN sucursales s ON s.id_empresa = i.id_empresa AND s.es_bodega = 0
            WHERE i.id_repuesto IN (" . implode(',', array_map('intval', $inv)) . ")
              AND s.id_sucursal = (SELECT MIN(x.id_sucursal) FROM sucursales x WHERE x.id_empresa = i.id_empresa AND x.es_bodega = 0)");

file_put_contents(__DIR__ . '/.creds.json', json_encode(['creds' => $creds, 'ids' => $ids, 'inv' => $inv]));
echo "Datos de prueba creados. Claves en tests/.creds.json\n";
