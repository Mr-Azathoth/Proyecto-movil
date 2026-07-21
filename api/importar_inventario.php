<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();
if (!isAdmin()) { http_response_code(403); json_err('Acceso denegado.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); json_err('Método no permitido.'); }
csrf_check();

$db  = getDB();
$eid = eid();

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    json_err('No se recibió ningún archivo válido.');
}

$tmp  = $_FILES['archivo']['tmp_name'];
$name = $_FILES['archivo']['name'];
$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

if (!in_array($ext, ['csv', 'txt'], true)) {
    json_err('Solo se aceptan archivos CSV (.csv).');
}

if ($_FILES['archivo']['size'] > 2 * 1024 * 1024) {
    json_err('El archivo no puede superar 2 MB.');
}

// ── Leer y normalizar contenido ──────────────────────────────────────────────
$content = file_get_contents($tmp);
if ($content === false) json_err('No se pudo leer el archivo.');

// Quitar BOM UTF-8 si existe
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}

// Normalizar saltos de línea (\r\n y \r sólo → \n)
$content = str_replace(["\r\n", "\r"], "\n", $content);

// ── Detectar delimitador ──────────────────────────────────────────────────────
$firstLine = strtok($content, "\n");
$delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

// ── Parsear líneas ────────────────────────────────────────────────────────────
$lines = array_values(array_filter(
    explode("\n", $content),
    fn($l) => trim($l) !== ''
));

if (empty($lines)) json_err('El archivo está vacío.');

$header = str_getcsv(array_shift($lines), $delim);
$header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

$colNombre = array_search('nombre', $header);
$colMarca  = array_search('marca_compatible', $header);
$colModelo = array_search('modelo_compatible', $header);
$colPrecio = array_search('precio_venta', $header);
$colStock  = array_search('cantidad', $header);

// Aceptar alias comunes
if ($colMarca  === false) $colMarca  = array_search('marca', $header);
if ($colModelo === false) $colModelo = array_search('modelo', $header);
if ($colPrecio === false) $colPrecio = array_search('precio', $header);
if ($colStock  === false) $colStock  = array_search('stock', $header);

if ($colNombre === false) {
    json_err('El archivo debe tener una columna "nombre".');
}

// ── Procesar filas ────────────────────────────────────────────────────────────
$inserted = 0;
$skipped  = 0;
$errors   = [];
$rowNum   = 1;

$stmt = $db->prepare("INSERT INTO inventario
    (id_empresa, codigo, nombre, marca_compatible, modelo_compatible, precio_venta, cantidad)
    VALUES (?, ?, ?, ?, ?, ?, ?)");

$db->beginTransaction();
try {
    foreach ($lines as $line) {
        $rowNum++;
        $row = str_getcsv($line, $delim);

        $nombre = trim($row[$colNombre] ?? '');
        if ($nombre === '') { $skipped++; continue; }

        if (strlen($nombre) > 100) {
            $errors[] = "Fila $rowNum: nombre demasiado largo (máx. 100 caracteres).";
            $skipped++;
            continue;
        }

        $marca  = $colMarca  !== false ? trim($row[$colMarca]  ?? '') : '';
        $modelo = $colModelo !== false ? trim($row[$colModelo] ?? '') : '';
        $precio = $colPrecio !== false ? max(0, (int) preg_replace('/[^0-9]/', '', $row[$colPrecio] ?? '0')) : 0;
        $stock  = $colStock  !== false ? max(0, (int) preg_replace('/[^0-9]/', '', $row[$colStock]  ?? '0')) : 0;

        $slug   = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $nombre));
        $prefix = substr($slug, 0, 6) ?: 'REP';
        $codigo = $prefix . '-' . substr(uniqid(), -5);

        try {
            $stmt->execute([$eid, $codigo, $nombre, $marca, $modelo, $precio, $stock]);
            $inserted++;
        } catch (\PDOException $e) {
            $errors[] = "Fila $rowNum: error al guardar «$nombre».";
            $skipped++;
        }
    }
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    json_err('Error al procesar el archivo. Intente nuevamente.');
}

if ($inserted > 0) {
    log_accion($db, 'importacion_inv_csv', null);
}

json_ok([
    'insertados' => $inserted,
    'omitidos'   => $skipped,
    'errores'    => $errors,
]);
