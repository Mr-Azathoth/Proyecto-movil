<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

guard();
if (!isAdmin()) { http_response_code(403); exit('Sin permisos.'); }

$db  = getDB();
$eid = eid();

$formato  = $_GET['formato'] ?? 'csv';
$q        = trim($_GET['q']  ?? '');
$valid_sort = [
    'nombre' => 'nombre', 'marca' => 'marca_compatible',
    'modelo' => 'modelo_compatible', 'precio' => 'precio_venta',
    'stock'  => 'cantidad',
];
$sort_col = $valid_sort[$_GET['sort_col'] ?? ''] ?? 'nombre';
$sort_dir = ($_GET['sort_dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

$sql    = "SELECT id_repuesto, nombre, marca_compatible, modelo_compatible, precio_venta, cantidad
             FROM inventario WHERE id_empresa = ? AND deleted_at IS NULL";
$params = [$eid];

if ($q) {
    $like    = '%' . $q . '%';
    $sql    .= " AND (nombre LIKE ? OR marca_compatible LIKE ? OR modelo_compatible LIKE ?)";
    $params  = array_merge($params, [$like, $like, $like]);
}
$sql .= " ORDER BY $sort_col $sort_dir";

$s = $db->prepare($sql);
$s->execute($params);
$rows = $s->fetchAll();

function hesc(string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fmtNum(int $n): string  { return '$' . number_format($n, 0, ',', '.'); }

// ─── XLSX ─────────────────────────────────────────────────────────────────────
if ($formato === 'xlsx') {
    log_accion($db, 'exportacion_inv_xlsx', null);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Inventario');

    // Encabezados
    $cols = ['A' => 'id', 'B' => 'nombre', 'C' => 'marca_compatible', 'D' => 'modelo_compatible', 'E' => 'precio_venta', 'F' => 'cantidad'];
    foreach ($cols as $col => $label) {
        $sheet->setCellValue("{$col}1", $label);
    }

    // Estilo encabezado
    $sheet->getStyle('A1:F1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);

    // Datos
    $i = 2;
    foreach ($rows as $r) {
        $sheet->setCellValue("A$i", (int)$r['id_repuesto']);
        $sheet->setCellValue("B$i", $r['nombre']);
        $sheet->setCellValue("C$i", $r['marca_compatible'] ?: '');
        $sheet->setCellValue("D$i", $r['modelo_compatible'] ?: '');
        $sheet->setCellValue("E$i", (int)$r['precio_venta']);
        $sheet->setCellValue("F$i", (int)$r['cantidad']);
        $i++;
    }

    // Ancho automático
    foreach (array_keys($cols) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->freezePane('A2');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="inventario_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: no-cache');

    $writer = new XlsxWriter($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ─── CSV ──────────────────────────────────────────────────────────────────────
if ($formato === 'csv') {
    log_accion($db, 'exportacion_inv_csv', null);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventario_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache');

    $csvSafe = fn(string $v): string => preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['id', 'nombre', 'marca_compatible', 'modelo_compatible', 'precio_venta', 'cantidad'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_repuesto'],
            $csvSafe($r['nombre']),
            $csvSafe($r['marca_compatible'] ?: ''),
            $csvSafe($r['modelo_compatible'] ?: ''),
            $r['precio_venta'],
            $r['cantidad'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ─── PDF (HTML para imprimir) ─────────────────────────────────────────────────
log_accion($db, 'exportacion_inv_pdf', null);
$titulo = 'Inventario — ' . date('d/m/Y');
$total  = count($rows);
$stock  = array_sum(array_column($rows, 'cantidad'));

$filtros = [];
if ($q) $filtros[] = 'Búsqueda: "' . $q . '"';
$sortLabels = ['nombre'=>'Repuesto','marca'=>'Marca','modelo'=>'Modelo','precio'=>'Precio','stock'=>'Stock'];
$sortKey    = array_search($sort_col, $valid_sort);
if ($sortKey) $filtros[] = 'Orden: ' . ($sortLabels[$sortKey] ?? $sortKey) . ' ' . ($sort_dir === 'DESC' ? '↓' : '↑');

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= hesc($titulo) ?></title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI',Arial,sans-serif; font-size:11px; color:#111; background:#fff; padding:20px; }
  h1 { font-size:16px; font-weight:700; margin-bottom:4px; }
  .meta { color:#555; font-size:10px; margin-bottom:12px; }
  .filtros { background:#f4f4f4; border-radius:4px; padding:6px 10px; margin-bottom:12px; font-size:10px; color:#444; }
  .filtros strong { color:#111; }
  .summary { margin-bottom:14px; padding:8px 0; border-top:2px solid #1e293b; border-bottom:1px solid #ddd; font-size:13px; }
  .summary strong { font-size:17px; }
  table { width:100%; border-collapse:collapse; font-size:10px; }
  thead th { background:#1e293b; color:#fff; padding:6px 8px; text-align:left; font-weight:600; white-space:nowrap; }
  tbody tr:nth-child(even) { background:#f8fafc; }
  tbody td { padding:5px 8px; border-bottom:1px solid #e8e8e8; vertical-align:top; }
  .num { font-weight:600; white-space:nowrap; }
  .stock-hi { color:#15803d; font-weight:600; }
  .stock-md { color:#c2410c; font-weight:600; }
  .stock-lo { color:#b91c1c; font-weight:600; }
  @media print {
    body { padding:0; }
    thead { display:table-header-group; }
    tbody tr { page-break-inside:avoid; }
  }
</style>
</head>
<body>
<h1><?= hesc($titulo) ?></h1>
<p class="meta">Generado el <?= date('d/m/Y \a \l\a\s H:i') ?></p>
<?php if ($filtros): ?>
<div class="filtros"><strong>Filtros:</strong> <?= hesc(implode(' · ', $filtros)) ?></div>
<?php endif; ?>
<p class="summary">
  <strong><?= $total ?></strong> repuestos &nbsp;&nbsp;·&nbsp;&nbsp;
  <strong><?= $stock ?></strong> unidades en stock
</p>
<table>
  <thead>
    <tr>
      <th>Repuesto</th><th>Marca compatible</th><th>Modelo compatible</th>
      <th>Precio venta</th><th>Stock</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r):
    $sc = $r['cantidad'] > 5 ? 'stock-hi' : ($r['cantidad'] > 0 ? 'stock-md' : 'stock-lo');
  ?>
    <tr>
      <td><?= hesc($r['nombre']) ?></td>
      <td><?= hesc($r['marca_compatible'] ?: '—') ?></td>
      <td><?= hesc($r['modelo_compatible'] ?: '—') ?></td>
      <td class="num"><?= fmtNum((int)$r['precio_venta']) ?></td>
      <td class="<?= $sc ?>"><?= (int)$r['cantidad'] ?> un.</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
