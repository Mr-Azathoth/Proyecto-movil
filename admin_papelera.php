<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

$empresas = $db->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre ASC")->fetchAll();
$eid_sel  = (int) ($_GET['empresa'] ?? ($empresas[0]['id_empresa'] ?? 0));

$reparaciones = [];
$inventario   = [];

if ($eid_sel) {
    try { $db->exec("ALTER TABLE reparaciones ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE inventario   ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (PDOException $e) {}

    $st = $db->prepare(
        "SELECT id_ingreso, nombre_cliente, telefono_cliente,
                marca_ingreso, modelo_ingreso, status, deleted_at
           FROM reparaciones
          WHERE id_empresa = ? AND deleted_at IS NOT NULL
          ORDER BY deleted_at DESC"
    );
    $st->execute([$eid_sel]);
    $reparaciones = $st->fetchAll();

    $si = $db->prepare(
        "SELECT id_repuesto, nombre, marca_compatible, modelo_compatible,
                precio_venta, cantidad, deleted_at
           FROM inventario
          WHERE id_empresa = ? AND deleted_at IS NOT NULL
          ORDER BY deleted_at DESC"
    );
    $si->execute([$eid_sel]);
    $inventario = $si->fetchAll();
}

$total_rep = count($reparaciones);
$total_inv = count($inventario);
?>
<!DOCTYPE html>
<html lang="es">
<?php $pageTitle = 'Centrotec Admin — Papelera'; ?>
<?php include __DIR__ . '/includes/admin_head.php'; ?>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div>
      <h1 class="adm-title">Papelera</h1>
      <div style="font-size:13px;color:var(--txt2);margin-top:2px;">Registros eliminados — restaurables desde aquí</div>
    </div>
  </div>

  <!-- Selector de empresa -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <label style="font-size:13px;color:var(--txt2);white-space:nowrap;font-weight:500;">Empresa:</label>
    <form method="GET" style="display:contents;">
      <select name="empresa" onchange="this.form.submit()" class="adm-search" style="max-width:260px;padding:8px 12px;">
        <?php foreach ($empresas as $e): ?>
          <option value="<?= $e['id_empresa'] ?>" <?= $e['id_empresa'] === $eid_sel ? 'selected' : '' ?>>
            <?= htmlspecialchars($e['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <span style="font-size:12px;color:var(--txt3);margin-left:auto;">
      <?= $total_rep ?> reparación<?= $total_rep !== 1 ? 'es' : '' ?> ·
      <?= $total_inv ?> repuesto<?= $total_inv !== 1 ? 's' : '' ?> en papelera
    </span>
  </div>

  <!-- Tabs -->
  <div class="pap-tabs">
    <button class="pap-tab active" data-tab="reparaciones">
      <span class="material-icons-round">build</span>
      Reparaciones (<?= $total_rep ?>)
    </button>
    <button class="pap-tab" data-tab="repuestos">
      <span class="material-icons-round">inventory_2</span>
      Repuestos (<?= $total_inv ?>)
    </button>
  </div>

  <!-- Tab: Reparaciones -->
  <div id="tab-reparaciones" class="pap-panel">
    <?php if (empty($reparaciones)): ?>
      <div class="pap-empty">
        <span class="material-icons-round">check_circle</span>
        <p>No hay reparaciones eliminadas para esta empresa.</p>
      </div>
    <?php else: ?>
    <div class="ec-card">
      <table class="adm-table">
        <thead><tr>
          <th>#</th>
          <th>Cliente</th>
          <th>Equipo</th>
          <th>Estado</th>
          <th>Eliminado el</th>
          <th style="text-align:center;">Acción</th>
        </tr></thead>
        <tbody>
          <?php foreach ($reparaciones as $r): ?>
          <tr>
            <td><span style="font-weight:700;color:var(--txt2);">#<?= $r['id_ingreso'] ?></span></td>
            <td>
              <div class="tbl-name-main"><?= htmlspecialchars($r['nombre_cliente']) ?></div>
              <div style="font-size:11px;color:var(--txt2);"><?= htmlspecialchars($r['telefono_cliente'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($r['marca_ingreso'] . ' ' . $r['modelo_ingreso']) ?></td>
            <td><span class="adm-badge adm-badge-off"><?= htmlspecialchars($r['status']) ?></span></td>
            <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y H:i', strtotime($r['deleted_at'])) ?></td>
            <td style="text-align:center;">
              <button class="adm-btn adm-btn-ghost btn-restaurar"
                data-tipo="reparacion" data-id="<?= $r['id_ingreso'] ?>" data-eid="<?= $eid_sel ?>">
                <span class="material-icons-round">restore</span> Restaurar
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tab: Repuestos -->
  <div id="tab-repuestos" class="pap-panel pap-hidden">
    <?php if (empty($inventario)): ?>
      <div class="pap-empty">
        <span class="material-icons-round">check_circle</span>
        <p>No hay repuestos eliminados para esta empresa.</p>
      </div>
    <?php else: ?>
    <div class="ec-card">
      <table class="adm-table">
        <thead><tr>
          <th>Repuesto</th>
          <th>Marca / Modelo</th>
          <th>Precio</th>
          <th>Stock</th>
          <th>Eliminado el</th>
          <th style="text-align:center;">Acción</th>
        </tr></thead>
        <tbody>
          <?php foreach ($inventario as $i): ?>
          <tr>
            <td><span class="tbl-name-main"><?= htmlspecialchars($i['nombre']) ?></span></td>
            <td style="color:var(--txt2);">
              <?= htmlspecialchars(trim(($i['marca_compatible'] ?? '') . ' ' . ($i['modelo_compatible'] ?? '')) ?: '—') ?>
            </td>
            <td>$<?= number_format((int)$i['precio_venta']) ?></td>
            <td><?= (int)$i['cantidad'] ?> un.</td>
            <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y H:i', strtotime($i['deleted_at'])) ?></td>
            <td style="text-align:center;">
              <button class="adm-btn adm-btn-ghost btn-restaurar"
                data-tipo="repuesto" data-id="<?= $i['id_repuesto'] ?>" data-eid="<?= $eid_sel ?>">
                <span class="material-icons-round">restore</span> Restaurar
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div id="toast"></div>

</main>
<script src="<?= BASE ?>/assets/js/admin_common.js"></script>
<script>
(function () {
  function toast(msg, ok) {
    const t = document.getElementById('toast');
    t.innerHTML = '<span class="material-icons-round" style="font-size:16px;color:' + (ok ? '#4ade80' : '#f87171') + '">' + (ok ? 'check_circle' : 'error') + '</span>' + msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
  }

  // Tabs
  document.querySelectorAll('.pap-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pap-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('.pap-panel').forEach(p => p.classList.add('pap-hidden'));
      document.getElementById('tab-' + btn.dataset.tab).classList.remove('pap-hidden');
    });
  });

  // Restaurar
  document.querySelectorAll('.btn-restaurar').forEach(btn => {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      const fd = new FormData();
      fd.append('accion',     'restaurar');
      fd.append('tipo',       btn.dataset.tipo);
      fd.append('id',         btn.dataset.id);
      fd.append('id_empresa', btn.dataset.eid);
      try {
        const r = await sadminFetch('/reparo/api/papelera.php', fd);
        const j = await r.json();
        if (j.ok) {
          toast(j.data.msg, true);
          const row = btn.closest('tr');
          row.style.transition = 'opacity .4s';
          row.style.opacity = '0.3';
          btn.innerHTML = '<span class="material-icons-round">check</span> Restaurado';
        } else {
          toast(j.msg || 'Error al restaurar.', false);
          btn.disabled = false;
        }
      } catch {
        toast('Error de red.', false);
        btn.disabled = false;
      }
    });
  });
}());
</script>
</body>
</html>
