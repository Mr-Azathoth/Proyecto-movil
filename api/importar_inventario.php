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

// ── Parsear CSV ───────────────────────────────────────────────────────────────
$handle = fopen($tmp, 'r');
if (!$handle) json_err('No se pudo leer el archivo.');

// Detectar delimitador (coma o punto y coma)
$firstLine = fgets($handle);
rewind($handle);
$delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

// Leer encabezado
$header = fgetcsv($handle, 0, $delim);
if (!$header) { fclose($handle); json_err('El archivo está vacío.'); }

// Normalizar nombres de columna
$header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

$colNombre  = array_search('nombre', $header);
$colMarca   = array_search('marca_compatible', $header);
$colModelo  = array_search('modelo_compatible', $header);
$colPrecio  = array_search('precio_venta', $header);
$colStock   = array_search('cantidad', $header);

// Aceptar alias comunes
if ($colMarca   === false) $colMarca  = array_search('marca', $header);
if ($colModelo  === false) $colModelo = array_search('modelo', $header);
if ($colPrecio  === false) $colPrecio = array_search('precio', $header);
if ($colStock   === false) $colStock  = array_search('stock', $header);

if ($colNombre === false) {
    fclose($handle);
    json_err('El CSV debe tener una columna "nombre".');
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
    while (($row = fgetcsv($handle, 0, $delim)) !== false) {
        $rowNum++;

        $nombre = trim($row[$colNombre] ?? '');
        if ($nombre === '') {
            $skipped++;
            continue;
        }
        if (strlen($nombre) > 100) {
            $errors[] = "Fila $rowNum: nombre demasiado largo (máx. 100 caracteres).";
            $skipped++;
            continue;
        }

        $marca  = $colMarca  !== false ? trim($row[$colMarca]  ?? '') : '';
        $modelo = $colModelo !== false ? trim($row[$colModelo] ?? '') : '';
        $precio = $colPrecio !== false ? max(0, (int) preg_replace('/[^0-9]/', '', $row[$colPrecio] ?? '0')) : 0;
        $stock  = $colStock  !== false ? max(0, (int) preg_replace('/[^0-9]/', '', $row[$colStock]  ?? '0')) : 0;

        // Código único
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
    fclose($handle);
    json_err('Error al procesar el archivo. Intente nuevamente.');
}

fclose($handle);

if ($inserted > 0) {
    log_accion($db, 'importacion_inv_csv', null);
}

json_ok([
    'insertados' => $inserted,
    'omitidos'   => $skipped,
    'errores'    => $errors,
]);
