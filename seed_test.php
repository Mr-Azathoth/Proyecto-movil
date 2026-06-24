<?php
// ── SEED DE DATOS DE PRUEBA ────────────────────────────────
// Solo accesible desde localhost. Ejecutar una vez.
// Acceder en: http://localhost/reparo/seed_test.php
// ─────────────────────────────────────────────────────────

if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403); exit('Acceso denegado.');
}

require_once __DIR__ . '/includes/config.php';
$db  = getDB();
$eid = 1; // empresa de prueba

$msg  = [];
$errs = [];

// ──────────────────────────────────────────────────────────
// 10 REPARACIONES DE PRUEBA
// ──────────────────────────────────────────────────────────
$reps = [
    ['Juan García',       '+56987654321', '12.345.678-9', 'Telefono',  'Samsung',  'Galaxy A54',   '352981123456789', '1234',    'Pantalla rota, cristal fisurado por caída',     45000, 'Ingresado',     'Equipo recibido con pantalla rota.'],
    ['María López',       '+56998765432', '',             'Telefono',  'Apple',    'iPhone 14',    '359876543210001', '9876',    'Batería dura menos de 2 horas',                 35000, 'En Reparacion', 'Se procede a reemplazar batería.'],
    ['Carlos Martínez',   '+56976543210', '9.876.543-2',  'Telefono',  'Xiaomi',   'Redmi Note 12','355432109876543', '',        'No enciende, cayó al agua',                     25000, 'Reparado',      'Limpieza ultrasónica, equipo funcional.'],
    ['Ana Torres',        '+56965432109', '',             'Tablet',    'Samsung',  'Galaxy Tab A8','356789012345678', '0000',    'Cámara trasera no enfoca',                      18000, 'Entregado',     'Módulo de cámara reemplazado.'],
    ['Pedro Rodríguez',   '+56954321098', '11.222.333-4', 'Telefono',  'Samsung',  'Galaxy S22',   '357654321098765', '4321',    'Puerto de carga no funciona, no carga',         15000, 'En Reparacion', 'Puerto USB-C con daño físico, solicitando repuesto.'],
    ['Laura Sánchez',     '+56943210987', '',             'Telefono',  'Apple',    'iPhone 12',    '358901234567890', '2580',    'Pantalla se activa sola (ghost touch)',          40000, 'Ingresado',     ''],
    ['Diego Morales',     '+56932109876', '7.654.321-0',  'Telefono',  'Huawei',   'P40',          '351234567890123', '',        'Altavoz no suena, solo audífono funciona',      12000, 'Garantia',      'Reemplazo previo de altavoz con falla. En revisión garantía.'],
    ['Valentina Castro',  '+56921098765', '',             'Telefono',  'Xiaomi',   'Poco X5 Pro',  '354567890123456', '1111',    'Vidrio trasero roto, bisel abollado',            8000, 'Reparado',      'Vidrio trasero reemplazado.'],
    ['Felipe Muñoz',      '+56910987654', '16.789.012-3', 'Telefono',  'Samsung',  'Galaxy A34',   '352345678901234', '5678',    'Botón de volumen se traba, no responde',        10000, 'Entregado',     'Botones laterales reemplazados.'],
    ['Camila Vega',       '+56909876543', '',             'Notebook',  'Apple',    'MacBook Air',  '',                '',        'Conector MagSafe con pin doblado',             120000, 'En Reparacion', 'Pendiente diagnóstico completo de placa.'],
];

$ins_rep = $db->prepare("
    INSERT INTO reparaciones
        (id_empresa, nombre_cliente, telefono_cliente, rut_cliente, tipo_ingreso,
         marca_ingreso, modelo_ingreso, imei, pass_ingreso, daño_ingreso,
         valor_ingreso, status, obs, ingresado_por)
    VALUES (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?)
");

$ins_hist = $db->prepare("
    INSERT INTO historial (id_empresa, id_reparacion, status_anterior, status_cambio, user)
    VALUES (?,?,?,?,?)
");

$ins_obs = $db->prepare("
    INSERT INTO observaciones (id_empresa, id_registro, obs, user)
    VALUES (?,?,?,?)
");

foreach ($reps as $r) {
    try {
        $ins_rep->execute([
            $eid, $r[0], $r[1], $r[2], $r[3],
            $r[4], $r[5], $r[6], $r[7], $r[8],
            $r[9], $r[10], $r[11] ?? '', 'admin',
        ]);
        $id = (int) $db->lastInsertId();
        $ins_hist->execute([$eid, $id, '', $r[10], 'admin']);
        if (!empty($r[11])) $ins_obs->execute([$eid, $id, $r[11], 'admin']);
        $msg[] = "✔ Reparación #{$id}: {$r[0]} — {$r[4]} {$r[5]}";
    } catch (Exception $e) {
        $errs[] = "✘ Error en reparación {$r[0]}: " . $e->getMessage();
    }
}

// ──────────────────────────────────────────────────────────
// 10 ENTRADAS DE INVENTARIO
// ──────────────────────────────────────────────────────────
$inv = [
    ['PANT-SAM-A54',  'Pantalla Samsung A54 OLED',        'Samsung',  'Galaxy A54',      38000, 5],
    ['BAT-APL-14',    'Batería iPhone 14 Original',       'Apple',    'iPhone 14',        28000, 8],
    ['CARG-USB-C-1M', 'Cable USB-C 1 metro certificado',  '',         '',                  3500, 25],
    ['PANT-SAM-A34',  'Pantalla Samsung A34 AMOLED',      'Samsung',  'Galaxy A34',       32000, 3],
    ['BAT-SAM-A54',   'Batería Samsung A54 4900mAh',      'Samsung',  'Galaxy A54',       18000, 10],
    ['MOD-CAM-APL12', 'Módulo cámara trasera iPhone 12',  'Apple',    'iPhone 12',        45000, 2],
    ['VID-XIA-NI12',  'Vidrio trasero Xiaomi Note 12',    'Xiaomi',   'Redmi Note 12',     8500, 7],
    ['PANT-APL-12',   'Pantalla iPhone 12 OEM',           'Apple',    'iPhone 12',        55000, 4],
    ['BAT-HUA-P40',   'Batería Huawei P40 Li-Ion',        'Huawei',   'P40',              22000, 6],
    ['KIT-HERR-PRO',  'Kit herramientas reparación Pro',  '',         '',                 15000, 3],
];

$ins_inv = $db->prepare("
    INSERT INTO inventario
        (id_empresa, codigo, nombre, marca_compatible, modelo_compatible, precio_venta, cantidad)
    VALUES (?,?,?,?,?,?,?)
");

foreach ($inv as $it) {
    try {
        $ins_inv->execute([$eid, $it[0], $it[1], $it[2], $it[3], $it[4], $it[5]]);
        $id = (int) $db->lastInsertId();
        $msg[] = "✔ Repuesto #{$id}: {$it[0]} — {$it[1]}";
    } catch (Exception $e) {
        $errs[] = "✘ Error en repuesto {$it[0]}: " . $e->getMessage();
    }
}

// ──────────────────────────────────────────────────────────
// RESULTADO
// ──────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Seed Reparo</title>
<style>
  body { font-family: monospace; background: #0d1117; color: #e6edf3; padding: 32px; }
  h2   { color: #2f81f7; margin-bottom: 20px; }
  .ok  { color: #3fb950; }
  .err { color: #f85149; }
  .btn { display: inline-block; margin-top: 24px; padding: 12px 24px; background: #2f81f7;
         color: #fff; text-decoration: none; border-radius: 8px; font-size: 14px; }
</style>
</head>
<body>
  <h2>Seed de datos de prueba — Reparo</h2>
  <?php foreach ($msg  as $m): ?><p class="ok"><?= htmlspecialchars($m) ?></p><?php endforeach; ?>
  <?php foreach ($errs as $e): ?><p class="err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
  <p style="margin-top:16px;color:#8b949e">
    <?= count($msg) ?> insertados · <?= count($errs) ?> errores
  </p>
  <a class="btn" href="/reparo/app.php">Ir a la app →</a>
</body>
</html>
