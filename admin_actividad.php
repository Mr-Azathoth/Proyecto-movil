<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

$filtro_empresa = (int)($_GET['empresa'] ?? 0);
$filtro_accion  = trim($_GET['accion'] ?? '');
$pagina = max(1, (int)($_GET['p'] ?? 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

$where = ['1=1'];
$params = [];
if ($filtro_empresa) { $where[] = 'la.id_empresa = ?'; $params[] = $filtro_empresa; }
if ($filtro_accion)  { $where[] = 'la.accion LIKE ?'; $params[] = '%'.$filtro_accion.'%'; }
$sql_where = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM log_acciones la WHERE $sql_where");
$total->execute($params);
$total = (int)$total->fetchColumn();
$paginas = ceil($total / $por_pagina);

$params_pag = array_merge($params, [$por_pagina, $offset]);
$logs = $db->prepare("
    SELECT la.id, la.accion, la.usuario, la.ip, la.fecha, la.id_reparacion,
           e.nombre AS empresa, e.id_empresa
    FROM log_acciones la
    JOIN empresas e ON e.id_empresa = la.id_empresa
    WHERE $sql_where
    ORDER BY la.fecha DESC
    LIMIT ? OFFSET ?
");
$logs->execute($params_pag);
$logs = $logs->fetchAll();

$empresas_list = $db->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<?php $pageTitle = 'Centrotec Admin — Actividad'; ?>
<?php include __DIR__ . '/includes/admin_head.php'; ?>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="adm-main">
  <div class="adm-topbar">
    <h1 class="adm-title">Actividad del sistema</h1>
    <div style="font-size:13px;color:var(--txt2);"><?= number_format($total) ?> registros</div>
  </div>

  <!-- Filtros -->
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
    <select name="empresa" class="adm-search" style="max-width:220px;">
      <option value="">Todas las empresas</option>
      <?php foreach ($empresas_list as $e): ?>
      <option value="<?= $e['id_empresa'] ?>" <?= $filtro_empresa == $e['id_empresa'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($e['nombre']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="accion" class="adm-search" placeholder="Filtrar por acción..." value="<?= htmlspecialchars($filtro_accion) ?>">
    <button type="submit" class="adm-btn adm-btn-primary"><span class="material-icons-round">filter_list</span>Filtrar</button>
    <?php if ($filtro_empresa || $filtro_accion): ?>
    <a href="<?= BASE ?>/admin_actividad.php" class="adm-btn adm-btn-ghost"><span class="material-icons-round">clear</span>Limpiar</a>
    <?php endif; ?>
  </form>

  <div class="ec-card">
    <table class="adm-table" id="tbl">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Empresa</th>
          <th>Usuario</th>
          <th>Acción</th>
          <th>Servicio</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr data-href="<?= BASE ?>/admin_empresa.php?id=<?= $l['id_empresa'] ?>">
          <td style="font-size:12px;color:var(--txt2);white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($l['fecha'])) ?></td>
          <td>
            <div class="tbl-name-cell">
              <?php $ini2 = mb_strtoupper(mb_substr($l['empresa'], 0, 1)); ?>
              <div class="tbl-avatar" style="width:28px;height:28px;font-size:10px;border-radius:6px;"><?= $ini2 ?></div>
              <span style="font-size:13px;"><?= htmlspecialchars($l['empresa']) ?></span>
            </div>
          </td>
          <td style="font-size:13px;font-weight:600;"><?= htmlspecialchars($l['usuario'] ?? '—') ?></td>
          <td style="font-size:13px;"><?= htmlspecialchars($l['accion']) ?></td>
          <td style="font-size:12px;color:var(--txt2);"><?= $l['id_reparacion'] ? '#'.$l['id_reparacion'] : '—' ?></td>
          <td style="font-size:11px;color:var(--txt3);"><?= htmlspecialchars($l['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--txt2);padding:32px;">Sin registros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación con ventana (evita renderizar cientos de links) -->
  <?php if ($paginas > 1):
    $base_pg = '?empresa=' . $filtro_empresa . '&accion=' . urlencode($filtro_accion);
    $shown = [];
    for ($i = 1; $i <= $paginas; $i++) {
        if ($i === 1 || $i === $paginas || abs($i - $pagina) <= 2) $shown[] = $i;
    }
  ?>
  <div style="display:flex;gap:6px;margin-top:14px;align-items:center;flex-wrap:wrap;">
    <?php $prev_pg = null; foreach ($shown as $pg): ?>
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
<script src="<?= BASE ?>/assets/js/admin_common.js"></script>
</body>
</html>
