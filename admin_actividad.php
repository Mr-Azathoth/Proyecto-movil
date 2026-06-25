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
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo Admin — Actividad</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
<link rel="stylesheet" href="/reparo/assets/css/admin.css">
</head>
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
    <a href="/reparo/admin_actividad.php" class="adm-btn adm-btn-ghost"><span class="material-icons-round">clear</span>Limpiar</a>
    <?php endif; ?>
  </form>

  <div class="adm-panel">
    <table class="adm-table">
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
        <tr>
          <td style="font-size:12px;color:var(--txt2);white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($l['fecha'])) ?></td>
          <td><a href="/reparo/admin_empresa.php?id=<?= $l['id_empresa'] ?>" style="color:var(--accent);text-decoration:none;font-size:13px;"><?= htmlspecialchars($l['empresa']) ?></a></td>
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

  <!-- Paginación -->
  <?php if ($paginas > 1): ?>
  <div style="display:flex;gap:6px;margin-top:14px;align-items:center;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $paginas; $i++): ?>
    <a href="?empresa=<?= $filtro_empresa ?>&accion=<?= urlencode($filtro_accion) ?>&p=<?= $i ?>"
       class="adm-btn <?= $i === $pagina ? 'adm-btn-primary' : 'adm-btn-ghost' ?>"
       style="padding:5px 10px;min-width:36px;justify-content:center;"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</main>
</body>
</html>
