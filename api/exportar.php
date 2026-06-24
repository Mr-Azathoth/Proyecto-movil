<?php
require_once __DIR__ . '/../includes/config.php';
guard();
if (!isAdmin()) { http_response_code(403); exit('Sin permisos.'); }

$db  = getDB();
$eid = eid();

// Migración silenciosa: asegurar fecha_ingreso existe
try {
    $db->exec("ALTER TABLE reparaciones ADD COLUMN fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
} catch (PDOException $e) {}

$formato     = $_GET['formato']     ?? 'csv';
$fecha_desde = trim($_GET['f_desde'] ?? '');
$fecha_hasta = trim($_GET['f_hasta'] ?? '');
$valid_estados = ['Ingresado', 'En Reparacion', 'Reparado', 'Entregado', 'No tiene reparacion'];
$estados       = array_values(array_intersect(
    isset($_GET['status']) ? (array) $_GET['status'] : [],
    $valid_estados
));
$precio_min  = isset($_GET['p_min']) && $_GET['p_min'] !== '' ? max(0, (int) $_GET['p_min']) : null;
$precio_max  = isset($_GET['p_max']) && $_GET['p_max'] !== '' ? max(0, (int) $_GET['p_max']) : null;
$id_repuesto = isset($_GET['id_rep']) && $_GET['id_rep'] !== '' ? (int) $_GET['id_rep'] : null;

$sql = "SELECT r.id_ingreso, r.fecha_ingreso, r.nombre_cliente, r.telefono_cliente,
               r.rut_cliente, r.tipo_ingreso, r.marca_ingreso, r.modelo_ingreso,
               r.daño_ingreso, r.status, r.valor_ingreso, r.ingresado_por,
               i.nombre AS repuesto_inicial
          FROM reparaciones r
          LEFT JOIN inventario i ON i.id_repuesto = r.id_repuesto_usado
                                 AND i.id_empresa  = r.id_empresa
         WHERE r.id_empresa = ?";
$params = [$eid];

if ($fecha_desde) { $sql .= " AND DATE(r.fecha_ingreso) >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta) { $sql .= " AND DATE(r.fecha_ingreso) <= ?"; $params[] = $fecha_hasta; }

if (!empty($estados)) {
    $ph     = implode(',', array_fill(0, count($estados), '?'));
    $sql   .= " AND r.status IN ($ph)";
    $params = array_merge($params, $estados);
}

if ($precio_min !== null) { $sql .= " AND r.valor_ingreso >= ?"; $params[] = $precio_min; }
if ($precio_max !== null) { $sql .= " AND r.valor_ingreso <= ?"; $params[] = $precio_max; }
if ($id_repuesto)         { $sql .= " AND r.id_repuesto_usado = ?"; $params[] = $id_repuesto; }

$sql .= " ORDER BY r.fecha_ingreso DESC";

$s = $db->prepare($sql);
$s->execute($params);
$rows = $s->fetchAll();

// ─── CSV (Excel) ──────────────────────────────────────────────────────────────
if ($formato === 'csv') {
    log_accion($db, 'exportacion_csv', null);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="servicios_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 para que Excel abra correctamente acentos
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        '#', 'Fecha Ingreso', 'Cliente', 'Teléfono', 'RUT',
        'Tipo', 'Marca', 'Modelo', 'Falla', 'Estado',
        'Valor ($)', 'Repuesto Inicial', 'Técnico'
    ], ';');

    foreach ($rows as $r) {
        $fecha = $r['fecha_ingreso']
            ? date('d/m/Y H:i', strtotime($r['fecha_ingreso']))
            : '—';
        fputcsv($out, [
            $r['id_ingreso'],
            $fecha,
            $r['nombre_cliente'],
            $r['telefono_cliente'],
            $r['rut_cliente']     ?: '—',
            $r['tipo_ingreso'],
            $r['marca_ingreso'],
            $r['modelo_ingreso'],
            $r['daño_ingreso'],
            $r['status'],
            $r['valor_ingreso'],
            $r['repuesto_inicial'] ?: '—',
            $r['ingresado_por'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ─── PDF (HTML para imprimir) ─────────────────────────────────────────────────
$titulo  = 'Listado de Servicios — ' . date('d/m/Y');
$total   = count($rows);
$sum     = array_sum(array_column($rows, 'valor_ingreso'));

function hesc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmtNum(int $n): string  { return '$' . number_format($n, 0, ',', '.'); }

$filtrosAplicados = [];
if ($fecha_desde || $fecha_hasta) {
    $filtrosAplicados[] = 'Fechas: ' . ($fecha_desde ?: '…') . ' → ' . ($fecha_hasta ?: 'hoy');
}
if (!empty($estados))  $filtrosAplicados[] = 'Estados: ' . implode(', ', $estados);
if ($precio_min !== null) $filtrosAplicados[] = 'Valor mín: ' . fmtNum($precio_min);
if ($precio_max !== null) $filtrosAplicados[] = 'Valor máx: ' . fmtNum($precio_max);
if ($id_repuesto) {
    $rn = $db->prepare("SELECT nombre FROM inventario WHERE id_repuesto = ? AND id_empresa = ?");
    $rn->execute([$id_repuesto, $eid]);
    $rNombre = $rn->fetchColumn() ?: "ID $id_repuesto";
    $filtrosAplicados[] = 'Repuesto: ' . $rNombre;
}

log_accion($db, 'exportacion_pdf', null);
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= hesc($titulo) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 20px; }
  h1 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
  .meta { color: #555; font-size: 10px; margin-bottom: 12px; }
  .filtros { background: #f4f4f4; border-radius: 4px; padding: 6px 10px; margin-bottom: 12px; font-size: 10px; color: #444; }
  .filtros strong { color: #111; }
  .summary { margin-bottom: 14px; padding: 8px 0; border-top: 2px solid #1e293b;
             border-bottom: 1px solid #ddd; font-size: 13px; }
  .summary strong { font-size: 17px; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  thead th { background: #1e293b; color: #fff; padding: 6px 8px; text-align: left; font-weight: 600; white-space: nowrap; }
  tbody tr:nth-child(even) { background: #f8fafc; }
  tbody td { padding: 5px 8px; border-bottom: 1px solid #e8e8e8; vertical-align: top; }
  .status { display: inline-block; border-radius: 3px; padding: 1px 5px; font-size: 9px; font-weight: 600; }
  .st-Ingresado       { background:#dbeafe; color:#1d4ed8; }
  .st-En-Reparacion   { background:#ffedd5; color:#c2410c; }
  .st-Reparado        { background:#d1fae5; color:#065f46; }
  .st-Entregado       { background:#ede9fe; color:#5b21b6; }
  .st-No-tiene-reparacion { background:#fee2e2; color:#991b1b; }
  .val { font-weight: 600; white-space: nowrap; }
  @media print {
    body { padding: 0; }
    .no-print { display: none; }
    thead { display: table-header-group; }
    tbody tr { page-break-inside: avoid; }
  }
</style>
</head>
<body>
<h1><?= hesc($titulo) ?></h1>
<p class="meta">Generado el <?= date('d/m/Y \a \l\a\s H:i') ?></p>

<?php if ($filtrosAplicados): ?>
<div class="filtros"><strong>Filtros:</strong> <?= hesc(implode(' · ', $filtrosAplicados)) ?></div>
<?php endif; ?>

<p class="summary">
  <strong><?= $total ?></strong> servicios &nbsp;&nbsp;·&nbsp;&nbsp;
  <strong><?= fmtNum($sum) ?></strong> valor total
</p>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Fecha</th>
      <th>Cliente</th>
      <th>Teléfono</th>
      <th>Equipo</th>
      <th>Falla</th>
      <th>Estado</th>
      <th>Valor</th>
      <th>Repuesto</th>
      <th>Técnico</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <?php
      $stClass = 'st-' . str_replace(' ', '-', $r['status']);
      $fecha   = $r['fecha_ingreso'] ? date('d/m/Y', strtotime($r['fecha_ingreso'])) : '—';
    ?>
    <tr>
      <td><?= $r['id_ingreso'] ?></td>
      <td><?= $fecha ?></td>
      <td><?= hesc($r['nombre_cliente']) ?></td>
      <td><?= hesc($r['telefono_cliente']) ?></td>
      <td><?= hesc($r['marca_ingreso'] . ' ' . $r['modelo_ingreso']) ?><br><small><?= hesc($r['tipo_ingreso']) ?></small></td>
      <td><?= hesc($r['daño_ingreso']) ?></td>
      <td><span class="status <?= hesc($stClass) ?>"><?= hesc($r['status']) ?></span></td>
      <td class="val"><?= fmtNum((int) $r['valor_ingreso']) ?></td>
      <td><?= hesc($r['repuesto_inicial'] ?: '—') ?></td>
      <td><?= hesc($r['ingresado_por']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
