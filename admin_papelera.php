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
    // Migración silenciosa por si la columna aún no existe en este servidor
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
  <form method="GET" style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <label style="font-size:13px;color:var(--txt2);white-space:nowrap;">Empresa:</label>
    <select name="empresa" onchange="this.form.submit()" style="
      background:var(--surface2);border:1px solid var(--border);
      color:var(--txt);padding:7px 12px;border-radius:8px;font-size:14px;min-width:220px;">
      <?php foreach ($empresas as $e): ?>
        <option value="<?= $e['id_empresa'] ?>" <?= $e['id_empresa'] === $eid_sel ? 'selected' : '' ?>>
          <?= htmlspecialchars($e['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div style="margin-left:auto;font-size:13px;color:var(--txt3);">
      <?= count($reparaciones) ?> reparación<?= count($reparaciones) !== 1 ? 'es' : '' ?> ·
      <?= count($inventario) ?> repuesto<?= count($inventario) !== 1 ? 's' : '' ?> eliminados
    </div>
  </form>

  <!-- Tabs -->
  <div style="display:flex;gap:4px;border-bottom:2px solid var(--border);margin-bottom:20px;">
    <button class="pap-tab active" data-tab="reparaciones"
      style="background:none;border:none;padding:10px 18px;font-size:14px;font-weight:600;
             color:var(--txt);border-bottom:2px solid var(--accent);margin-bottom:-2px;cursor:pointer;">
      <span class="material-icons-round" style="font-size:16px;vertical-align:-3px;">build</span>
      Reparaciones (<?= count($reparaciones) ?>)
    </button>
    <button class="pap-tab" data-tab="repuestos"
      style="background:none;border:none;padding:10px 18px;font-size:14px;font-weight:600;
             color:var(--txt2);cursor:pointer;">
      <span class="material-icons-round" style="font-size:16px;vertical-align:-3px;">inventory_2</span>
      Repuestos (<?= count($inventario) ?>)
    </button>
  </div>

  <!-- Tab: Reparaciones -->
  <div id="tab-reparaciones" class="pap-panel">
    <?php if (empty($reparaciones)): ?>
      <div style="text-align:center;padding:60px 20px;color:var(--txt3);">
        <span class="material-icons-round" style="font-size:48px;color:#4ade80;">check_circle</span>
        <p style="margin-top:12px;">No hay reparaciones eliminadas para esta empresa.</p>
      </div>
    <?php else: ?>
    <div class="ec-card">
      <table class="adm-table" id="tbl-rep">
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
          <tr data-q="<?= htmlspecialchars(strtolower($r['nombre_cliente'].' '.$r['marca_ingreso'].' '.$r['modelo_ingreso']), ENT_QUOTES) ?>">
            <td style="color:var(--txt2);font-weight:600;">#<?= $r['id_ingreso'] ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($r['nombre_cliente']) ?></div>
              <div style="font-size:12px;color:var(--txt2);"><?= htmlspecialchars($r['telefono_cliente'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($r['marca_ingreso'] . ' ' . $r['modelo_ingreso']) ?></td>
            <td><span class="adm-badge adm-badge-off"><?= htmlspecialchars($r['status']) ?></span></td>
            <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y H:i', strtotime($r['deleted_at'])) ?></td>
            <td style="text-align:center;">
              <button class="adm-btn adm-btn-ghost btn-restaurar" style="gap:4px;"
                data-tipo="reparacion" data-id="<?= $r['id_ingreso'] ?>" data-eid="<?= $eid_sel ?>">
                <span class="material-icons-round" style="font-size:16px;">restore</span> Restaurar
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
  <div id="tab-repuestos" class="pap-panel" style="display:none;">
    <?php if (empty($inventario)): ?>
      <div style="text-align:center;padding:60px 20px;color:var(--txt3);">
        <span class="material-icons-round" style="font-size:48px;color:#4ade80;">check_circle</span>
        <p style="margin-top:12px;">No hay repuestos eliminados para esta empresa.</p>
      </div>
    <?php else: ?>
    <div class="ec-card">
      <table class="adm-table" id="tbl-inv">
        <thead><tr>
          <th>Repuesto</th>
          <th>Marca</th>
          <th>Modelo</th>
          <th>Precio</th>
          <th>Stock</th>
          <th>Eliminado el</th>
          <th style="text-align:center;">Acción</th>
        </tr></thead>
        <tbody>
          <?php foreach ($inventario as $i): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($i['nombre']) ?></td>
            <td><?= htmlspecialchars($i['marca_compatible'] ?: '—') ?></td>
            <td><?= htmlspecialchars($i['modelo_compatible'] ?: '—') ?></td>
            <td>$<?= number_format((int)$i['precio_venta']) ?></td>
            <td><?= (int)$i['cantidad'] ?> un.</td>
            <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y H:i', strtotime($i['deleted_at'])) ?></td>
            <td style="text-align:center;">
              <button class="adm-btn adm-btn-ghost btn-restaurar" style="gap:4px;"
                data-tipo="repuesto" data-id="<?= $i['id_repuesto'] ?>" data-eid="<?= $eid_sel ?>">
                <span class="material-icons-round" style="font-size:16px;">restore</span> Restaurar
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Toast -->
  <div id="pap-toast" style="
    position:fixed;bottom:24px;right:24px;
    background:var(--surface2);border:1px solid var(--border);
    color:var(--txt);padding:12px 20px;border-radius:10px;
    font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,.3);
    opacity:0;transition:opacity .3s;pointer-events:none;z-index:9999;">
  </div>

</main>
<script src="<?= BASE ?>/assets/js/admin_common.js"></script>
<script>
(function () {
  // Tabs
  document.querySelectorAll('.pap-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pap-tab').forEach(b => {
        b.style.color        = 'var(--txt2)';
        b.style.borderBottom = 'none';
      });
      btn.style.color        = 'var(--txt)';
      btn.style.borderBottom = '2px solid var(--accent)';
      btn.style.marginBottom = '-2px';
      document.querySelectorAll('.pap-panel').forEach(p => p.style.display = 'none');
      document.getElementById('tab-' + btn.dataset.tab).style.display = '';
    });
  });

  // Toast
  function toast(msg, ok) {
    const t = document.getElementById('pap-toast');
    t.textContent    = msg;
    t.style.color    = ok ? '#4ade80' : '#f87171';
    t.style.opacity  = '1';
    setTimeout(() => { t.style.opacity = '0'; }, 3000);
  }

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
          btn.closest('tr').style.opacity = '0.3';
          btn.textContent = '✔ Restaurado';
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
