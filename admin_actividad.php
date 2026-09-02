<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

$filtro_empresa     = (int)($_GET['empresa'] ?? 0);
$filtro_accion      = trim($_GET['accion'] ?? '');
$filtro_usuario     = trim($_GET['usuario'] ?? '');
$filtro_fecha_desde = trim($_GET['fecha_desde'] ?? '');
$filtro_fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
$pagina   = max(1, (int)($_GET['p'] ?? 1));
$por_pagina = 50;
$offset   = ($pagina - 1) * $por_pagina;

// Validar fechas
if ($filtro_fecha_desde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_fecha_desde)) $filtro_fecha_desde = '';
if ($filtro_fecha_hasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_fecha_hasta)) $filtro_fecha_hasta = '';

$where  = ['1=1'];
$params = [];
if ($filtro_empresa)     { $where[] = 'la.id_empresa = ?';   $params[] = $filtro_empresa; }
if ($filtro_accion)      { $where[] = 'la.accion LIKE ?';    $params[] = '%'.$filtro_accion.'%'; }
if ($filtro_usuario)     { $where[] = 'la.usuario LIKE ?';   $params[] = '%'.$filtro_usuario.'%'; }
if ($filtro_fecha_desde) { $where[] = 'DATE(la.fecha) >= ?'; $params[] = $filtro_fecha_desde; }
if ($filtro_fecha_hasta) { $where[] = 'DATE(la.fecha) <= ?'; $params[] = $filtro_fecha_hasta; }
$sql_where = implode(' AND ', $where);

$stmt_total = $db->prepare("SELECT COUNT(*) FROM log_acciones la WHERE $sql_where");
$stmt_total->execute($params);
$total   = (int)$stmt_total->fetchColumn();
$paginas = (int)ceil($total / $por_pagina);

$params_pag = array_merge($params, [$por_pagina, $offset]);
$stmt_logs  = $db->prepare("
    SELECT la.id, la.accion, la.usuario, la.ip, la.fecha, la.id_reparacion,
           la.datos_entrada, la.datos_salida,
           e.nombre AS empresa, e.id_empresa
    FROM log_acciones la
    JOIN empresas e ON e.id_empresa = la.id_empresa
    WHERE $sql_where
    ORDER BY la.fecha DESC
    LIMIT ? OFFSET ?
");
$stmt_logs->execute($params_pag);
$logs = $stmt_logs->fetchAll();

$empresas_list = $db->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre")->fetchAll();
$acciones_list = $db->query("SELECT DISTINCT accion FROM log_acciones ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);

$hay_filtros = $filtro_empresa || $filtro_accion || $filtro_usuario || $filtro_fecha_desde || $filtro_fecha_hasta;
$base_pg     = '?empresa='.$filtro_empresa
             .'&accion='.urlencode($filtro_accion)
             .'&usuario='.urlencode($filtro_usuario)
             .'&fecha_desde='.urlencode($filtro_fecha_desde)
             .'&fecha_hasta='.urlencode($filtro_fecha_hasta);
?>
<!DOCTYPE html>
<html lang="es">
<?php $pageTitle = 'Centrotec Admin — Actividad'; ?>
<?php
$extra_css = '<link rel="stylesheet" href="'.BASE.'/assets/css/admin_actividad.css?v='.filemtime(__DIR__.'/assets/css/admin_actividad.css').'">';
include __DIR__ . '/includes/admin_head.php';
?>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="adm-main">
  <div class="adm-topbar">
    <h1 class="adm-title">Actividad del sistema</h1>
    <div style="font-size:13px;color:var(--txt2);"><?= number_format($total) ?> registros</div>
  </div>

  <!-- Filtros -->
  <form method="GET" class="act-filters">
    <label>
      Empresa
      <select name="empresa" class="adm-search" style="min-width:180px;">
        <option value="">Todas</option>
        <?php foreach ($empresas_list as $e): ?>
        <option value="<?= $e['id_empresa'] ?>" <?= $filtro_empresa == $e['id_empresa'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($e['nombre']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      Acción
      <select name="accion" class="adm-search" style="min-width:180px;">
        <option value="">Todas</option>
        <?php foreach ($acciones_list as $a): ?>
        <option value="<?= htmlspecialchars($a) ?>" <?= $filtro_accion === $a ? 'selected' : '' ?>>
          <?= htmlspecialchars($a) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      Usuario
      <input type="text" name="usuario" class="adm-search" placeholder="Nombre..." value="<?= htmlspecialchars($filtro_usuario) ?>" style="min-width:150px;">
    </label>

    <label>
      Fecha desde
      <input type="date" name="fecha_desde" class="adm-search" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
    </label>

    <label>
      Fecha hasta
      <input type="date" name="fecha_hasta" class="adm-search" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
    </label>

    <div style="display:flex;gap:8px;align-items:flex-end;">
      <button type="submit" class="adm-btn adm-btn-primary">
        <span class="material-icons-round">filter_list</span>Filtrar
      </button>
      <?php if ($hay_filtros): ?>
      <a href="<?= BASE ?>/admin_actividad.php" class="adm-btn adm-btn-ghost">
        <span class="material-icons-round">clear</span>Limpiar
      </a>
      <?php endif; ?>
    </div>
  </form>

  <div class="ec-card">
    <table class="adm-table" id="tbl">
      <thead>
        <tr>
          <th>Fecha / Hora</th>
          <th>Empresa</th>
          <th>Usuario</th>
          <th>Acción</th>
          <th>Servicio</th>
          <th>IP</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-size:12px;color:var(--txt2);white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($l['fecha'])) ?></td>
          <td>
            <div class="tbl-name-cell">
              <?php $ini = mb_strtoupper(mb_substr($l['empresa'], 0, 1)); ?>
              <div class="tbl-avatar" style="width:28px;height:28px;font-size:10px;border-radius:6px;"><?= $ini ?></div>
              <a href="<?= BASE ?>/admin_empresa.php?id=<?= $l['id_empresa'] ?>" style="font-size:13px;color:var(--txt);text-decoration:none;" onclick="event.stopPropagation()">
                <?= htmlspecialchars($l['empresa']) ?>
              </a>
            </div>
          </td>
          <td style="font-size:13px;font-weight:600;"><?= htmlspecialchars($l['usuario'] ?? '—') ?></td>
          <td><span class="act-badge-action" title="<?= htmlspecialchars($l['accion']) ?>"><?= htmlspecialchars($l['accion']) ?></span></td>
          <td style="font-size:12px;color:var(--txt2);"><?= $l['id_reparacion'] ? '#'.$l['id_reparacion'] : '—' ?></td>
          <td style="font-size:11px;color:var(--txt3);"><?= htmlspecialchars($l['ip'] ?? '—') ?></td>
          <td>
            <button class="act-ver-btn"
              data-empresa="<?= htmlspecialchars($l['empresa']) ?>"
              data-empresa-id="<?= $l['id_empresa'] ?>"
              data-usuario="<?= htmlspecialchars($l['usuario'] ?? '') ?>"
              data-accion="<?= htmlspecialchars($l['accion']) ?>"
              data-fecha="<?= date('d/m/Y H:i:s', strtotime($l['fecha'])) ?>"
              data-ip="<?= htmlspecialchars($l['ip'] ?? '') ?>"
              data-servicio="<?= $l['id_reparacion'] ? '#'.$l['id_reparacion'] : '' ?>"
              data-log-id="<?= $l['id'] ?>"
              data-entrada="<?= htmlspecialchars($l['datos_entrada'] ?? '') ?>"
              data-salida="<?= htmlspecialchars($l['datos_salida'] ?? '') ?>">
              Ver datos
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--txt2);padding:40px;">Sin registros para los filtros aplicados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($paginas > 1):
    $shown = []; $prev_pg = null;
    for ($i = 1; $i <= $paginas; $i++) {
      if ($i === 1 || $i === $paginas || abs($i - $pagina) <= 2) $shown[] = $i;
    }
  ?>
  <div style="display:flex;gap:6px;margin-top:14px;align-items:center;flex-wrap:wrap;">
    <?php foreach ($shown as $pg): ?>
      <?php if ($prev_pg !== null && $pg - $prev_pg > 1): ?>
        <span style="color:var(--txt3);padding:0 6px;">…</span>
      <?php endif; ?>
      <a href="<?= $base_pg ?>&p=<?= $pg ?>"
         class="adm-btn <?= $pg === $pagina ? 'adm-btn-primary' : 'adm-btn-ghost' ?>"
         style="padding:5px 10px;min-width:36px;justify-content:center;"><?= $pg ?></a>
    <?php $prev_pg = $pg; endforeach; ?>
  </div>
  <?php endif; ?>

</main>

<!-- Modal Ver datos -->
<div id="modal-log-detalle">
  <div class="log-detail-box">
    <div class="log-detail-head">
      <h3>Detalle de registro</h3>
      <button class="log-detail-close" id="btn-close-log">✕</button>
    </div>
    <div class="log-detail-body">
      <div class="log-detail-row">
        <span class="log-detail-label">ID</span>
        <span class="log-detail-val" id="ld-id"></span>
      </div>
      <div class="log-detail-row">
        <span class="log-detail-label">Fecha</span>
        <span class="log-detail-val" id="ld-fecha"></span>
      </div>
      <div class="log-detail-row">
        <span class="log-detail-label">Empresa</span>
        <span class="log-detail-val" id="ld-empresa"></span>
      </div>
      <div class="log-detail-row">
        <span class="log-detail-label">Usuario</span>
        <span class="log-detail-val" id="ld-usuario"></span>
      </div>
      <div class="log-detail-row">
        <span class="log-detail-label">Acción</span>
        <span class="log-detail-val" id="ld-accion"></span>
      </div>
      <div class="log-detail-row" id="ld-row-servicio" style="display:none">
        <span class="log-detail-label">Servicio</span>
        <span class="log-detail-val" id="ld-servicio"></span>
      </div>
      <div class="log-detail-row">
        <span class="log-detail-label">IP</span>
        <span class="log-detail-val" id="ld-ip"></span>
      </div>
    </div>
    <div class="log-json-section" id="ld-section-entrada" style="display:none">
      <div class="log-json-head">DATOS ENTRADA</div>
      <pre class="log-json-pre" id="ld-entrada"></pre>
    </div>
    <div class="log-json-section" id="ld-section-salida" style="display:none">
      <div class="log-json-head">DATOS SALIDA</div>
      <pre class="log-json-pre" id="ld-salida"></pre>
    </div>
  </div>
</div>

<script src="<?= BASE ?>/assets/js/admin_common.js?v=<?= filemtime(__DIR__.'/assets/js/admin_common.js') ?>"></script>
<script src="<?= BASE ?>/assets/js/admin_actividad.js?v=<?= filemtime(__DIR__.'/assets/js/admin_actividad.js') ?>"></script>
</body>
</html>
