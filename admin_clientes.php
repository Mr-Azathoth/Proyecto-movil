<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

$empresas = $db->query("
    SELECT e.id_empresa, e.nombre, e.correo, e.activa, e.creada_en,
           e.plan_tipo, e.plan_estado, e.plan_vencimiento,
           COALESCE(u.num_usuarios, 0) AS num_usuarios,
           COALESCE(r.num_servicios, 0) AS num_servicios
    FROM empresas e
    LEFT JOIN (SELECT id_empresa, COUNT(*) AS num_usuarios FROM usuarios WHERE activo = 1 GROUP BY id_empresa) u ON u.id_empresa = e.id_empresa
    LEFT JOIN (SELECT id_empresa, COUNT(*) AS num_servicios FROM reparaciones GROUP BY id_empresa) r ON r.id_empresa = e.id_empresa
    ORDER BY e.creada_en DESC
")->fetchAll();

$hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<?php $pageTitle = 'Reparo Admin — Clientes'; ?>
<?php include __DIR__ . '/includes/admin_head.php'; ?>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <h1 class="adm-title">Clientes</h1>
  </div>

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div style="position:relative;flex:1;max-width:320px;">
      <span class="material-icons-round" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:var(--txt3);pointer-events:none;">search</span>
      <input type="text" id="srch" class="adm-search" style="padding-left:36px;max-width:100%;width:100%;" placeholder="Buscar empresa...">
    </div>
    <div style="font-size:13px;color:var(--txt2);margin-left:auto;">
      <?= count($empresas) ?> <?= count($empresas) === 1 ? 'empresa' : 'empresas' ?>
    </div>
  </div>

  <div class="ec-card">
    <table class="adm-table" id="tbl">
      <thead>
        <tr>
          <th>Empresa</th>
          <th>Usuarios</th>
          <th>Servicios</th>
          <th>Plan</th>
          <th>Estado</th>
          <th>Registro</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empresas as $e):
          $ini = sadmin_iniciales($e['nombre']);
          $planOk = $e['plan_estado'] === 'Activo' && ($e['plan_vencimiento'] === null || $e['plan_vencimiento'] >= $hoy);
        ?>
        <tr data-href="/reparo/admin_empresa.php?id=<?= $e['id_empresa'] ?>"
            data-q="<?= htmlspecialchars(strtolower($e['nombre'] . ' ' . $e['correo']), ENT_QUOTES) ?>">
          <td>
            <div class="tbl-name-cell">
              <div class="tbl-avatar"><?= $ini ?></div>
              <div>
                <div class="tbl-name-main"><?= htmlspecialchars($e['nombre']) ?></div>
                <div class="tbl-name-sub"><?= htmlspecialchars($e['correo'] ?? '—') ?></div>
              </div>
            </div>
          </td>
          <td style="text-align:center;">
            <span style="font-weight:600;"><?= $e['num_usuarios'] ?></span>
          </td>
          <td style="text-align:center;">
            <span style="font-weight:600;"><?= number_format($e['num_servicios']) ?></span>
          </td>
          <td>
            <?php if ($planOk): ?>
              <span class="adm-badge adm-badge-purple"><?= htmlspecialchars($e['plan_tipo'] ?: 'Activo') ?></span>
            <?php elseif ($e['plan_vencimiento'] && $e['plan_vencimiento'] < $hoy): ?>
              <span class="adm-badge adm-badge-off">Vencido</span>
            <?php else: ?>
              <span class="adm-badge adm-badge-off">Sin plan</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="adm-badge <?= $e['activa'] ? 'adm-badge-ok' : 'adm-badge-off' ?>">
              <?= $e['activa'] ? 'Activa' : 'Inactiva' ?>
            </span>
          </td>
          <td style="color:var(--txt2);font-size:12px;"><?= date('d/m/Y', strtotime($e['creada_en'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($empresas)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--txt3);">Sin empresas registradas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>
<script src="/reparo/assets/js/admin_common.js"></script>
</body>
</html>
