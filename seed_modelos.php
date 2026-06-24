<?php
// ── SEED DE MODELOS ────────────────────────────────────────
// Solo accesible desde localhost. Agrega modelos masivamente.
// ─────────────────────────────────────────────────────────
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403); exit('Acceso denegado.');
}
require_once __DIR__ . '/includes/config.php';
$db = getDB();

// ── 1. ASEGURAR MARCAS NUEVAS ──────────────────────────────
$marcas_nuevas = ['Google', 'Nothing', 'Infinix', 'Tecno', 'ZTE', 'Alcatel'];
$ins_marca = $db->prepare("INSERT IGNORE INTO marcas_cat (nombre) VALUES (?)");
foreach ($marcas_nuevas as $m) {
    $ins_marca->execute([$m]);
}

// ── 2. LEER IDs DE TODAS LAS MARCAS ───────────────────────
$rows = $db->query("SELECT id_marca, nombre FROM marcas_cat WHERE activo=1")->fetchAll(PDO::FETCH_KEY_PAIR);
// $rows = ['Samsung' => 1, 'Apple' => 2, ...]
$id = array_flip($rows); // ['Samsung' => 1, ...]

// ── 3. LISTA DE MODELOS A INSERTAR ────────────────────────
// Formato: ['Marca', 'Modelo']
$modelos = [

    // ── Samsung ─────────────────────────────────────────────
    ['Samsung', 'Galaxy A05'],
    ['Samsung', 'Galaxy A05s'],
    ['Samsung', 'Galaxy A15'],
    ['Samsung', 'Galaxy A15 5G'],
    ['Samsung', 'Galaxy A25'],
    ['Samsung', 'Galaxy A25 5G'],
    ['Samsung', 'Galaxy A35'],
    ['Samsung', 'Galaxy A55'],
    ['Samsung', 'Galaxy A73'],
    ['Samsung', 'Galaxy M14'],
    ['Samsung', 'Galaxy M14 5G'],
    ['Samsung', 'Galaxy M34'],
    ['Samsung', 'Galaxy M54'],
    ['Samsung', 'Galaxy M55'],
    ['Samsung', 'Galaxy F14'],
    ['Samsung', 'Galaxy F54'],
    ['Samsung', 'Galaxy S21'],
    ['Samsung', 'Galaxy S21+'],
    ['Samsung', 'Galaxy S21 Ultra'],
    ['Samsung', 'Galaxy S22 Ultra'],
    ['Samsung', 'Galaxy S25'],
    ['Samsung', 'Galaxy S25+'],
    ['Samsung', 'Galaxy S25 Ultra'],
    ['Samsung', 'Galaxy Z Fold 4'],
    ['Samsung', 'Galaxy Z Fold 6'],
    ['Samsung', 'Galaxy Z Flip 4'],
    ['Samsung', 'Galaxy Z Flip 6'],
    ['Samsung', 'Galaxy Tab A9+'],
    ['Samsung', 'Galaxy Tab S7 FE'],
    ['Samsung', 'Galaxy Tab S10'],
    ['Samsung', 'Galaxy Tab S10+'],
    ['Samsung', 'Galaxy Tab S10 Ultra'],
    ['Samsung', 'Galaxy Tab S10 FE'],
    ['Samsung', 'Galaxy Xcover 7'],

    // ── Apple ────────────────────────────────────────────────
    ['Apple', 'iPhone 16'],
    ['Apple', 'iPhone 16 Plus'],
    ['Apple', 'iPhone 16 Pro'],
    ['Apple', 'iPhone 16 Pro Max'],
    ['Apple', 'iPhone 16e'],
    ['Apple', 'iPad Air M2'],
    ['Apple', 'iPad Air M3'],
    ['Apple', 'iPad Pro 11" M4'],
    ['Apple', 'iPad Pro 13" M4'],
    ['Apple', 'MacBook Pro 14" M4'],
    ['Apple', 'MacBook Pro 16" M4'],
    ['Apple', 'MacBook Air 15" M3'],

    // ── Xiaomi ───────────────────────────────────────────────
    ['Xiaomi', 'Redmi 12'],
    ['Xiaomi', 'Redmi 12C'],
    ['Xiaomi', 'Redmi 13'],
    ['Xiaomi', 'Redmi 13C'],
    ['Xiaomi', 'Redmi A2'],
    ['Xiaomi', 'Redmi A2+'],
    ['Xiaomi', 'Redmi A3'],
    ['Xiaomi', 'Redmi Note 12 Turbo'],
    ['Xiaomi', 'Redmi Note 13'],
    ['Xiaomi', 'Redmi Note 13 Pro'],
    ['Xiaomi', 'Redmi Note 13 Pro+'],
    ['Xiaomi', 'Redmi Note 13 5G'],
    ['Xiaomi', 'Redmi Note 14'],
    ['Xiaomi', 'Redmi Note 14 Pro'],
    ['Xiaomi', 'Redmi Note 14 Pro+'],
    ['Xiaomi', 'Xiaomi 13'],
    ['Xiaomi', 'Xiaomi 13 Pro'],
    ['Xiaomi', 'Xiaomi 13T'],
    ['Xiaomi', 'Xiaomi 13T Pro'],
    ['Xiaomi', 'Xiaomi 14'],
    ['Xiaomi', 'Xiaomi 14 Ultra'],
    ['Xiaomi', 'Xiaomi 14T'],
    ['Xiaomi', 'Xiaomi 14T Pro'],
    ['Xiaomi', 'POCO C55'],
    ['Xiaomi', 'POCO C65'],
    ['Xiaomi', 'POCO M6'],
    ['Xiaomi', 'POCO M6 Pro'],
    ['Xiaomi', 'POCO X5'],
    ['Xiaomi', 'POCO X5 Pro'],
    ['Xiaomi', 'POCO X6'],
    ['Xiaomi', 'POCO X6 Pro'],
    ['Xiaomi', 'POCO F5'],
    ['Xiaomi', 'POCO F5 Pro'],
    ['Xiaomi', 'POCO F6'],
    ['Xiaomi', 'POCO F6 Pro'],
    ['Xiaomi', 'Pad 6'],
    ['Xiaomi', 'Pad 6 Pro'],

    // ── Motorola ─────────────────────────────────────────────
    ['Motorola', 'Moto E14'],
    ['Motorola', 'Moto E22'],
    ['Motorola', 'Moto E22i'],
    ['Motorola', 'Moto E32s'],
    ['Motorola', 'Moto E40'],
    ['Motorola', 'Moto G04'],
    ['Motorola', 'Moto G04s'],
    ['Motorola', 'Moto G14'],
    ['Motorola', 'Moto G24'],
    ['Motorola', 'Moto G24 Power'],
    ['Motorola', 'Moto G34 5G'],
    ['Motorola', 'Moto G44 5G'],
    ['Motorola', 'Moto G54 5G'],
    ['Motorola', 'Moto G64 5G'],
    ['Motorola', 'Moto G84 5G'],
    ['Motorola', 'Moto G85 5G'],
    ['Motorola', 'Moto G Power 5G (2024)'],
    ['Motorola', 'Moto G Stylus 5G (2024)'],
    ['Motorola', 'Edge 30 Fusion'],
    ['Motorola', 'Edge 30 Ultra'],
    ['Motorola', 'Edge 40'],
    ['Motorola', 'Edge 40 Neo'],
    ['Motorola', 'Edge 40 Pro'],
    ['Motorola', 'Edge 50 Fusion'],
    ['Motorola', 'Edge 50 Neo'],
    ['Motorola', 'Edge 50 Pro'],
    ['Motorola', 'Edge 50 Ultra'],
    ['Motorola', 'Razr 40'],
    ['Motorola', 'Razr 40 Ultra'],
    ['Motorola', 'Razr 50'],
    ['Motorola', 'Razr 50 Ultra'],

    // ── Huawei ───────────────────────────────────────────────
    ['Huawei', 'Y7a'],
    ['Huawei', 'Y9a'],
    ['Huawei', 'nova 9'],
    ['Huawei', 'nova 10'],
    ['Huawei', 'nova 10 Pro'],
    ['Huawei', 'nova 11'],
    ['Huawei', 'nova 11 Pro'],
    ['Huawei', 'nova 11 Ultra'],
    ['Huawei', 'nova 12'],
    ['Huawei', 'nova 12 Pro'],
    ['Huawei', 'nova 12 Ultra'],
    ['Huawei', 'Mate 50'],
    ['Huawei', 'Mate 50 Pro'],
    ['Huawei', 'Mate 60'],
    ['Huawei', 'Mate 60 Pro'],
    ['Huawei', 'Pura 70'],
    ['Huawei', 'Pura 70 Pro'],
    ['Huawei', 'Pura 70 Ultra'],

    // ── Sony ─────────────────────────────────────────────────
    ['Sony', 'Xperia 10 IV'],
    ['Sony', 'Xperia 10 V'],
    ['Sony', 'Xperia 10 VI'],
    ['Sony', 'Xperia 1 IV'],
    ['Sony', 'Xperia 1 V'],
    ['Sony', 'Xperia 1 VI'],
    ['Sony', 'Xperia 5 IV'],
    ['Sony', 'Xperia 5 V'],
    ['Sony', 'Xperia 5 VI'],
    ['Sony', 'Xperia Pro-I'],

    // ── LG ───────────────────────────────────────────────────
    ['LG', 'K22'],
    ['LG', 'K42'],
    ['LG', 'K52'],
    ['LG', 'K62'],
    ['LG', 'K71'],
    ['LG', 'Q52'],
    ['LG', 'Velvet'],
    ['LG', 'Wing'],
    ['LG', 'V60 ThinQ'],
    ['LG', 'V50 ThinQ'],
    ['LG', 'Stylo 6'],
    ['LG', 'Stylo 7'],

    // ── OPPO ─────────────────────────────────────────────────
    ['Oppo', 'A18'],
    ['Oppo', 'A38'],
    ['Oppo', 'A58'],
    ['Oppo', 'A58x'],
    ['Oppo', 'A78'],
    ['Oppo', 'A98'],
    ['Oppo', 'A3 Pro'],
    ['Oppo', 'Reno 10'],
    ['Oppo', 'Reno 10 Pro'],
    ['Oppo', 'Reno 10 Pro+'],
    ['Oppo', 'Reno 11'],
    ['Oppo', 'Reno 11 Pro'],
    ['Oppo', 'Reno 12'],
    ['Oppo', 'Reno 12 Pro'],
    ['Oppo', 'Find X6 Pro'],
    ['Oppo', 'Find X7 Pro'],
    ['Oppo', 'Find N3 Flip'],
    ['Oppo', 'Find N3'],

    // ── OnePlus ───────────────────────────────────────────────
    ['OnePlus', 'Nord CE 2 Lite'],
    ['OnePlus', 'Nord CE 3 Lite'],
    ['OnePlus', 'Nord CE 3'],
    ['OnePlus', 'Nord CE 4'],
    ['OnePlus', 'Nord CE 4 Lite'],
    ['OnePlus', 'Nord 3'],
    ['OnePlus', 'Nord 4'],
    ['OnePlus', 'Nord N30'],
    ['OnePlus', 'OnePlus 11'],
    ['OnePlus', 'OnePlus 11R'],
    ['OnePlus', 'OnePlus 12'],
    ['OnePlus', 'OnePlus 12R'],
    ['OnePlus', 'OnePlus 13'],
    ['OnePlus', 'OnePlus Open'],
    ['OnePlus', 'Ace 2'],
    ['OnePlus', 'Ace 2 Pro'],
    ['OnePlus', 'Ace 3'],
    ['OnePlus', 'Ace 3 Pro'],

    // ── Realme ───────────────────────────────────────────────
    ['Realme', 'C33'],
    ['Realme', 'C35'],
    ['Realme', 'C51'],
    ['Realme', 'C53'],
    ['Realme', 'C55'],
    ['Realme', 'C67'],
    ['Realme', 'C67 5G'],
    ['Realme', 'Narzo 60'],
    ['Realme', 'Narzo 60 Pro'],
    ['Realme', 'Narzo 60x'],
    ['Realme', '11'],
    ['Realme', '11 Pro'],
    ['Realme', '11 Pro+'],
    ['Realme', '11x'],
    ['Realme', '12'],
    ['Realme', '12 Pro'],
    ['Realme', '12 Pro+'],
    ['Realme', '12x'],
    ['Realme', 'GT 5 Pro'],
    ['Realme', 'GT 6'],
    ['Realme', 'GT 6T'],
    ['Realme', 'GT Neo 5'],
    ['Realme', 'GT Neo 6'],

    // ── Nokia ─────────────────────────────────────────────────
    ['Nokia', 'C21'],
    ['Nokia', 'C22'],
    ['Nokia', 'C32'],
    ['Nokia', 'C300'],
    ['Nokia', 'G11'],
    ['Nokia', 'G21'],
    ['Nokia', 'G22'],
    ['Nokia', 'G42 5G'],
    ['Nokia', 'G60 5G'],
    ['Nokia', 'XR21'],
    ['Nokia', 'X30 5G'],
    ['Nokia', '3.4'],
    ['Nokia', '5.4'],
    ['Nokia', '6.3'],
    ['Nokia', '7.2'],

    // ── Hisense ───────────────────────────────────────────────
    ['Hisense', 'Infinity H40'],
    ['Hisense', 'Infinity H50'],
    ['Hisense', 'Infinity H60'],
    ['Hisense', 'Infinity H70'],
    ['Hisense', 'U30'],
    ['Hisense', 'U50'],
    ['Hisense', 'U60'],
    ['Hisense', 'U70'],
    ['Hisense', 'Rock 6'],
    ['Hisense', 'A9'],

    // ── TCL ───────────────────────────────────────────────────
    ['TCL', '305'],
    ['TCL', '306'],
    ['TCL', '30'],
    ['TCL', '30 SE'],
    ['TCL', '30+'],
    ['TCL', '40 SE'],
    ['TCL', '40 R 5G'],
    ['TCL', '40 NxtPaper'],
    ['TCL', '40 XE 5G'],
    ['TCL', '408'],
    ['TCL', '501'],
    ['TCL', '503'],
    ['TCL', '505'],

    // ── Asus ─────────────────────────────────────────────────
    ['Asus', 'Zenfone 9'],
    ['Asus', 'Zenfone 10'],
    ['Asus', 'Zenfone 11 Ultra'],
    ['Asus', 'ROG Phone 6'],
    ['Asus', 'ROG Phone 6 Pro'],
    ['Asus', 'ROG Phone 7'],
    ['Asus', 'ROG Phone 7 Pro'],
    ['Asus', 'ROG Phone 8'],
    ['Asus', 'ROG Phone 8 Pro'],

    // ── Google (nueva marca) ──────────────────────────────────
    ['Google', 'Pixel 5'],
    ['Google', 'Pixel 5a'],
    ['Google', 'Pixel 6'],
    ['Google', 'Pixel 6 Pro'],
    ['Google', 'Pixel 6a'],
    ['Google', 'Pixel 7'],
    ['Google', 'Pixel 7 Pro'],
    ['Google', 'Pixel 7a'],
    ['Google', 'Pixel 8'],
    ['Google', 'Pixel 8 Pro'],
    ['Google', 'Pixel 8a'],
    ['Google', 'Pixel 9'],
    ['Google', 'Pixel 9 Pro'],
    ['Google', 'Pixel 9 Pro XL'],
    ['Google', 'Pixel 9 Pro Fold'],
    ['Google', 'Pixel Fold'],
    ['Google', 'Pixel Tablet'],

    // ── Nothing (nueva marca) ─────────────────────────────────
    ['Nothing', 'Phone (1)'],
    ['Nothing', 'Phone (2)'],
    ['Nothing', 'Phone (2a)'],
    ['Nothing', 'Phone (2a) Plus'],
    ['Nothing', 'Phone (3a)'],
    ['Nothing', 'CMF Phone 1'],

    // ── Infinix (nueva marca) ─────────────────────────────────
    ['Infinix', 'Hot 12'],
    ['Infinix', 'Hot 20'],
    ['Infinix', 'Hot 20i'],
    ['Infinix', 'Hot 30'],
    ['Infinix', 'Hot 30i'],
    ['Infinix', 'Hot 40'],
    ['Infinix', 'Hot 40 Pro'],
    ['Infinix', 'Smart 7'],
    ['Infinix', 'Smart 8'],
    ['Infinix', 'Note 30'],
    ['Infinix', 'Note 30 Pro'],
    ['Infinix', 'Note 40'],
    ['Infinix', 'Note 40 Pro'],
    ['Infinix', 'Zero 30'],
    ['Infinix', 'Zero 40'],
    ['Infinix', 'GT 20 Pro'],

    // ── Tecno (nueva marca) ───────────────────────────────────
    ['Tecno', 'Spark 9'],
    ['Tecno', 'Spark 10'],
    ['Tecno', 'Spark 10 Pro'],
    ['Tecno', 'Spark 20'],
    ['Tecno', 'Spark 20 Pro'],
    ['Tecno', 'Pop 6'],
    ['Tecno', 'Pop 7'],
    ['Tecno', 'Pop 8'],
    ['Tecno', 'Camon 19'],
    ['Tecno', 'Camon 20'],
    ['Tecno', 'Camon 20 Pro'],
    ['Tecno', 'Pova 5'],
    ['Tecno', 'Pova 6 Pro'],
    ['Tecno', 'Phantom X2'],
    ['Tecno', 'Phantom V Fold'],

    // ── ZTE (nueva marca) ─────────────────────────────────────
    ['ZTE', 'Blade A52'],
    ['ZTE', 'Blade A72'],
    ['ZTE', 'Blade A73'],
    ['ZTE', 'Blade V40'],
    ['ZTE', 'Blade V50'],
    ['ZTE', 'Blade V50 Design'],
    ['ZTE', 'Axon 40 Ultra'],
    ['ZTE', 'Axon 50 Ultra'],
    ['ZTE', 'Nubia Z50S Pro'],
    ['ZTE', 'Nubia Red Magic 8 Pro'],
    ['ZTE', 'Nubia Red Magic 9 Pro'],

    // ── Alcatel (nueva marca) ─────────────────────────────────
    ['Alcatel', '1B'],
    ['Alcatel', '1L'],
    ['Alcatel', '1V'],
    ['Alcatel', '3L'],
    ['Alcatel', '5V'],
    ['Alcatel', 'Go Flip 4'],
    ['Alcatel', 'TCL 20 SE'],
];

// ── 4. INSERTAR ────────────────────────────────────────────
$ins = $db->prepare(
    "INSERT IGNORE INTO modelos_cat (id_marca, nombre) VALUES (?, ?)"
);

$ok = 0; $skip = 0; $err = [];

foreach ($modelos as [$marca, $nombre]) {
    if (!isset($id[$marca])) {
        $err[] = "Marca no encontrada: $marca";
        continue;
    }
    try {
        $ins->execute([$id[$marca], $nombre]);
        if ($ins->rowCount() > 0) $ok++;
        else $skip++;
    } catch (Exception $e) {
        $err[] = "$marca / $nombre: " . $e->getMessage();
    }
}

// ── 5. RESULTADO ──────────────────────────────────────────
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Seed Modelos — Reparo</title>
<style>
  body { font-family:monospace; background:#0d1117; color:#e6edf3; padding:32px; }
  h2   { color:#2f81f7; }
  .ok  { color:#3fb950; }
  .skip{ color:#8b949e; }
  .err { color:#f85149; }
  a    { display:inline-block; margin-top:24px; padding:12px 24px;
         background:#2f81f7; color:#fff; text-decoration:none; border-radius:8px; }
</style>
</head>
<body>
<h2>Seed de modelos — Reparo</h2>
<p class="ok">✔ Insertados: <?= $ok ?></p>
<p class="skip">— Ya existían (ignorados): <?= $skip ?></p>
<?php foreach ($err as $e): ?>
  <p class="err">✘ <?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>
<p>Total procesados: <?= count($modelos) ?> | Errores: <?= count($err) ?></p>
<a href="/reparo/app.php">Ir a la app →</a>
</body>
</html>
