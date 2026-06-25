<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

$empresas = $db->query("
    SELECT e.id_empresa, e.nombre, e.correo, e.activo, e.created_at,
           COUNT(DISTINCT u.id_usuario) AS num_usuarios,
           COUNT(DISTINCT r.id_reparacion) AS num_servicios,
           (SELECT s.plan_tipo FROM suscripciones s
            WHERE s.id_empresa = e.id_empresa AND s.estado = 'activa' AND s.fecha_fin >= CURDATE()
            ORDER BY s.fecha_fin DESC LIMIT 1) AS plan_activo
    FROM empresas e
    LEFT JOIN usuarios u ON u.id_empresa = e.id_empresa AND u.activo = 1
    LEFT JOIN reparaciones r ON r.id_empresa = e.id_empresa
    GROUP BY e.id_empresa
    ORDER BY e.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo Admin — Clientes</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
<link rel="stylesheet" href="/reparo/assets/css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="adm-main">
  <div class="adm-topbar">
    <h1 class="adm-title">Clientes</h1>
  </div>
  <div class="adm-page-hdr">
    <input type="text" class="adm-search" id="srch" placeholder="Buscar empresa...">
    <div style="margin-left:auto;font-size:13px;color:var(--txt2);"><?= count($empresas) ?> empresas</div>
  </div>
  <div class="adm-panel">
    <table class="adm-table" id="tbl">
      <thead><tr><th>Empresa</th><th>Correo</th><th>Usuarios</th><th>Servicios</th><th>Plan</th><th>Estado</th><th>Registro</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($empresas as $e): ?>
        <tr data-q="<?= htmlspecialchars(strtolower($e['nombre'])) ?>">
          <td style="font-weight:600;"><?= htmlspecialchars($e['nombre']) ?></td>
          <td style="color:var(--txt2);font-size:12px;"><?= htmlspecialchars($e['correo'] ?? '—') ?></td>
          <td style="text-align:center;"><?= $e['num_usuarios'] ?></td>
          <td style="text-align:center;"><?= number_format($e['num_servicios']) ?></td>
          <td><?php if ($e['plan_activo']): ?><span class="adm-badge adm-badge-purple"><?= htmlspecialchars($e['plan_activo']) ?></span><?php else: ?><span class="adm-badge adm-badge-off">Sin plan</span><?php endif; ?></td>
          <td><span class="adm-badge <?= $e['activo'] ? 'adm-badge-ok' : 'adm-badge-off' ?>"><?= $e['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
          <td style="color:var(--txt2);font-size:12px;"><?= date('d/m/Y', strtotime($e['created_at'])) ?></td>
          <td><a href="/reparo/admin_empresa.php?id=<?= $e['id_empresa'] ?>" class="adm-btn adm-btn-ghost" style="padding:5px 10px;font-size:12px;"><span class="material-icons-round">open_in_new</span>Ver</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<script>document.getElementById('srch').addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('#tbl tbody tr').forEach(r=>{r.style.display=r.dataset.q.includes(q)?'':' none';});});</script>
</body>
</html>
