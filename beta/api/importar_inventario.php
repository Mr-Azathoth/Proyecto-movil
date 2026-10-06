<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');
guard();
if (!isAdmin()) json_err('Acceso denegado.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido.', 405);
csrf_check();

$db  = getDB();
$eid = eid();

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    json_err('No se recibió ningún archivo válido.');
}

$tmp  = $_FILES['archivo']['tmp_name'];
$name = $_FILES['archivo']['name'];
$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

if (!in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
    json_err('Solo se aceptan archivos .xlsx o .csv.');
}

if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
    json_err('El archivo no puede superar 5 MB.');
}

// ── Parsear según tipo ────────────────────────────────────────────────────────
$dataRows = []; // array de arrays; primer elemento = fila de encabezados

if ($ext === 'xlsx') {
    $spreadsheet = IOFactory::load($tmp);
    $sheet       = $spreadsheet->getActiveSheet();

    // Limite de seguridad: un .xlsx armado a proposito puede declarar un rango usado
    // enorme (ej. hasta la columna XFD o la fila 1048576) sin pesar mucho comprimido; sin
    // este chequeo, toArray() materializaria esa area completa en memoria — un "dimension
    // bomb" clasico que puede colgar el worker de PHP aunque el archivo pese poco.
    $highestRow = $sheet->getHighestRow();
    $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    if ($highestRow > 5000 || $highestCol > 100) {
        json_err('El archivo es demasiado grande (máx. 5000 filas / 100 columnas). Divide la planilla en partes más pequeñas.');
    }

    $raw         = $sheet->toArray(null, true, true, false);
    // Filtrar filas completamente vacías
    $dataRows = array_values(array_filter($raw, fn($r) =>
        count(array_filter($r, fn($c) => trim((string)$c) !== '')) > 0
    ));
} else {
    $content = file_get_contents($tmp);
    if ($content === false) json_err('No se pudo leer el archivo.');

    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    $firstLine = strtok($content, "\n");
    $delim     = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

    foreach (array_filter(explode("\n", $content), fn($l) => trim($l) !== '') as $line) {
        $dataRows[] = str_getcsv($line, $delim);
    }
}

if (empty($dataRows)) json_err('El archivo está vacío.');

// ── Mapear columnas ───────────────────────────────────────────────────────────
$header = array_map(
    fn($h) => strtolower(trim(str_replace([' ', '-'], '_', (string)$h))),
    $dataRows[0]
);
if (isset($header[0])) $header[0] = ltrim($header[0], "\xEF\xBB\xBF\xFF\xFE");

$colId     = array_search('id', $header);
$colNombre = array_search('nombre', $header);
$colMarca  = array_search('marca_compatible', $header);
$colModelo = array_search('modelo_compatible', $header);
$colPrecio = array_search('precio_venta', $header);
$colStock  = array_search('cantidad', $header);

if ($colMarca  === false) $colMarca  = array_search('marca', $header);
if ($colModelo === false) $colModelo = array_search('modelo', $header);
if ($colPrecio === false) $colPrecio = array_search('precio', $header);
if ($colStock  === false) $colStock  = array_search('stock', $header);

if ($colNombre === false) {
    json_err('El archivo debe tener una columna "nombre".');
}

// ── Procesar filas ────────────────────────────────────────────────────────────
$inserted = 0;
$updated  = 0;
$skipped  = 0;
$errors   = [];
$cambios  = [];
$rowNum   = 1;

// El stock del archivo se aplica a UNA sucursal (la indicada o la base del usuario).
$sucDestino = sucursal_para_escritura($db, $eid, $_POST['id_sucursal'] ?? null);

$stmtInsert = $db->prepare(
    "INSERT INTO inventario (id_empresa, codigo, nombre, marca_compatible, modelo_compatible, precio_venta)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmtUpdate = $db->prepare(
    "UPDATE inventario
        SET nombre = ?, marca_compatible = ?, modelo_compatible = ?, precio_venta = ?, deleted_at = NULL
      WHERE id_repuesto = ? AND id_empresa = ?"
);
$stmtFetch = $db->prepare(
    "SELECT i.nombre, i.marca_compatible, i.modelo_compatible, i.precio_venta, COALESCE(s.cantidad, 0) AS cantidad
       FROM inventario i
       LEFT JOIN inventario_stock s ON s.id_repuesto = i.id_repuesto AND s.id_sucursal = ?
      WHERE i.id_repuesto = ? AND i.id_empresa = ? LIMIT 1"
);

$db->beginTransaction();
try {
    foreach (array_slice($dataRows, 1) as $row) {
        $rowNum++;
        $nombre = trim((string)($row[$colNombre] ?? ''));
        if ($nombre === '') { $skipped++; continue; }

        if (strlen($nombre) > 100) {
            $errors[] = "Fila $rowNum: nombre demasiado largo (máx. 100 caracteres).";
            $skipped++;
            continue;
        }

        $id     = $colId !== false ? (int)($row[$colId] ?? 0) : 0;
        $marca  = $colMarca  !== false ? trim((string)($row[$colMarca]  ?? '')) : '';
        $modelo = $colModelo !== false ? trim((string)($row[$colModelo] ?? '')) : '';
        $precio = $colPrecio !== false ? max(0, (int) preg_replace('/[^0-9]/', '', (string)($row[$colPrecio] ?? '0'))) : 0;
        $stock  = $colStock  !== false ? max(0, (int) preg_replace('/[^0-9]/', '', (string)($row[$colStock]  ?? '0'))) : 0;

        try {
            if ($id > 0) {
                $stmtFetch->execute([$sucDestino, $id, $eid]);
                $actual = $stmtFetch->fetch();

                if ($actual) {
                    $numFields = ['precio_venta', 'cantidad'];
                    $labels = ['nombre' => 'Nombre', 'marca_compatible' => 'Marca', 'modelo_compatible' => 'Modelo', 'precio_venta' => 'Precio', 'cantidad' => 'Stock'];
                    $nuevos = ['nombre' => $nombre, 'marca_compatible' => $marca, 'modelo_compatible' => $modelo, 'precio_venta' => $precio, 'cantidad' => $stock];
                    $diffs  = [];
                    foreach ($labels as $field => $label) {
                        $vActual = in_array($field, $numFields) ? (int)($actual[$field] ?? 0) : trim((string)($actual[$field] ?? ''));
                        $vNuevo  = in_array($field, $numFields) ? (int)$nuevos[$field]         : trim((string)$nuevos[$field]);
                        if ($vActual !== $vNuevo) {
                            $diffs[] = "$label: «{$actual[$field]}» → «{$nuevos[$field]}»";
                        }
                    }
                    $stmtUpdate->execute([$nombre, $marca, $modelo, $precio, $id, $eid]);
                    stock_fijar($db, $eid, $id, $sucDestino, $stock);
                    $updated++;
                    if ($diffs) $cambios[] = ['id' => $id, 'nombre' => $nombre, 'diffs' => $diffs];
                    continue;
                }
            }

            $slug   = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $nombre));
            $prefix = substr($slug, 0, 6) ?: 'REP';
            $codigo = $prefix . '-' . substr(uniqid(), -5);
            $stmtInsert->execute([$eid, $codigo, $nombre, $marca, $modelo, $precio]);
            stock_fijar($db, $eid, (int)$db->lastInsertId(), $sucDestino, $stock);
            $inserted++;
        } catch (\PDOException $e) {
            $errors[] = "Fila $rowNum: error al guardar el repuesto.";
            $skipped++;
        }
    }
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    json_err('Error al procesar el archivo. Intente nuevamente.');
}

if ($inserted > 0 || $updated > 0) {
    log_accion($db, 'importacion_inv_xlsx', null, ['archivo' => $_FILES['archivo']['name'] ?? '', 'id_sucursal' => $sucDestino], ['insertados' => $inserted, 'actualizados' => $updated]);
}

json_ok([
    'insertados'   => $inserted,
    'actualizados' => $updated,
    'omitidos'     => $skipped,
    'errores'      => $errors,
    'cambios'      => $cambios,
]);
