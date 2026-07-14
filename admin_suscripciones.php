<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

// KPIs de planes — query separado para evitar duplicar filas por LEFT JOIN con historial_pagos
$kpi = $db->query("
    SELECT
        SUM(plan_estado = 'Activo' AND (plan_vencimiento IS NULL OR plan_vencimiento >= CURDATE())) AS activos,
        SUM(plan_vencimiento IS NOT NULL AND plan_vencimiento < CURDATE())                           AS vencidos,
        SUM(plan_estado = 'Gratis')                                                                  AS gratis,
        SUM(plan_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))              AS vencen_7d
    FROM empresas
")->fetch(PDO::FETCH_ASSOC);
$kpi['ingresos_total'] = (float)$db->query("SELECT COALESCE(SUM(monto), 0) FROM historial_pagos")->fetchColumn();

// Por vencer próximos 30 días
$proximos = $db->query("
    SELECT e.id_empresa, e.nombre, e.plan_tipo, e.plan_vencimiento,
           DATEDIFF(e.plan_vencimiento, CURDATE()) AS dias_restantes
    FROM empresas e
    WHERE e.activa = 1 AND e.plan_vencimiento IS NOT NULL
      AND e.plan_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY e.plan_vencimiento ASC
")->fetchAll();

// Vencidos / morosos
$morosos = $db->query("
    SELECT e.id_empresa, e.nombre, e.plan_tipo, e.plan_estado, e.plan_vencimiento,
           DATEDIFF(CURDATE(), e.plan_vencimiento) AS dias_vencido
    FROM empresas e
    WHERE e.activa = 1 AND e.plan_vencimiento IS NOT NULL AND e.plan_vencimiento < CURDATE()
    ORDER BY dias_vencido DESC
")->fetchAll();

// Historial de pagos recientes (todos los tenants)
$pagos = $db->query("
    SELECT hp.fecha, hp.monto, hp.descripcion, hp.estado, e.nombre AS empresa
    FROM historial_pagos hp
    JOIN empresas e ON e.id_empresa = hp.id_empresa
    ORDER BY hp.fecha DESC LIMIT 20
")->fetchAll();

// Distribución por tipo de plan
$dist = $db->query("
    SELECT plan_tipo, COUNT(*) AS total
    FROM empresas WHERE activa = 1 AND plan_tipo IS NOT NULL AND plan_tipo != ''
    GROUP BY plan_tipo ORDER BY total DESC
")->fetchAll();
$dist_total = array_sum(array_column($dist, 'total')) ?: 1;
$pageTitle = 'Centrotec Admin — Suscripciones';
?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/includes/admin_head.php'; ?>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="adm-main">
  <div class="adm-topbar">
    <h1 class="adm-title">Suscripciones</h1>
    <div style="display:flex;gap:8px;align-items:center;">
      <button class="adm-btn adm-btn-ghost" id="btn-cron-dry" style="font-size:12px;padding:7px 12px;">
        <span class="material-icons-round">preview</span>Vista previa
      </button>
      <button class="adm-btn adm-btn-primary" id="btn-cron-run" style="font-size:12px;padding:7px 14px;">
        <span class="material-icons-round">send</span>Enviar notificaciones
      </button>
    </div>
  </div>
  <div id="cron-result" hidden style="margin-bottom:16px;"></div>

  <div class="adm-kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:20px;">
    <div class="adm-kpi-card"><span class="material-icons-round" style="color:#4ade80;">check_circle</span><div><div class="adm-kpi-val"><?= $kpi['activos'] ?></div><div class="adm-kpi-lbl">Planes activos</div></div></div>
    <div class="adm-kpi-card <?= $kpi['vencidos'] > 0 ? 'adm-kpi-warn' : '' ?>"><span class="material-icons-round" style="color:#f87171;">warning</span><div><div class="adm-kpi-val"><?= $kpi['vencidos'] ?></div><div class="adm-kpi-lbl">Vencidos</div></div></div>
    <div class="adm-kpi-card <?= $kpi['vencen_7d'] > 0 ? 'adm-kpi-warn' : '' ?>"><span class="material-icons-round" style="color:#fbbf24;">schedule</span><div><div class="adm-kpi-val"><?= $kpi['vencen_7d'] ?></div><div class="adm-kpi-lbl">Vencen en 7 días</div></div></div>
    <div class="adm-kpi-card"><span class="material-icons-round" style="color:#a78bfa;">volunteer_activism</span><div><div class="adm-kpi-val"><?= $kpi['gratis'] ?></div><div class="adm-kpi-lbl">Plan gratuito</div></div></div>
    <div class="adm-kpi-card"><span class="material-icons-round" style="color:#34d399;">payments</span><div><div class="adm-kpi-val">$<?= number_format($kpi['ingresos_total'], 0, ',', '.') ?></div><div class="adm-kpi-lbl">Ingresos totales</div></div></div>
  </div>

  <div class="adm-two-col" style="margin-bottom:16px;">
    <!-- Distribución por plan -->
    <div class="ec-card">
      <div class="ec-card-hdr"><span class="material-icons-round">pie_chart</span>Distribución por plan</div>
      <div style="padding:16px 18px;">
        <?php if (empty($dist)): ?>
          <div style="padding:24px 0;color:var(--txt3);font-size:13px;text-align:center;">Sin datos.</div>
        <?php else: foreach ($dist as $d):
          $pct = round($d['total'] / $dist_total * 100); ?>
          <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
              <span><?= htmlspecialchars($d['plan_tipo']) ?></span>
              <span style="color:var(--txt2);"><?= $d['total'] ?> (<?= $pct ?>%)</span>
            </div>
            <div style="background:var(--bg3);border-radius:4px;height:6px;">
              <div style="background:#7c3aed;width:<?= $pct ?>%;height:100%;border-radius:4px;"></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Por vencer -->
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round">schedule</span>
        Vencen en 30 días
        <?php if ($proximos): ?><span class="adm-badge adm-badge-warn" style="margin-left:4px;"><?= count($proximos) ?></span><?php endif; ?>
      </div>
      <?php if (empty($proximos)): ?>
        <div style="padding:24px;text-align:center;color:var(--txt3);font-size:13px;">Ninguno próximamente.</div>
      <?php else: ?>
      <table class="adm-table">
        <thead><tr><th>Empresa</th><th>Plan</th><th>Vence</th><th>Días</th></tr></thead>
        <tbody>
          <?php foreach ($proximos as $p): ?>
          <tr data-href="<?= BASE ?>/admin_empresa.php?id=<?= $p['id_empresa'] ?>">
            <td>
              <div class="tbl-name-cell">
                <div class="tbl-avatar"><?= sadmin_iniciales($p['nombre']) ?></div>
                <span class="tbl-name-main"><?= htmlspecialchars($p['nombre']) ?></span>
              </div>
            </td>
            <td><span class="adm-badge adm-badge-purple"><?= htmlspecialchars($p['plan_tipo']) ?></span></td>
            <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y', strtotime($p['plan_vencimiento'])) ?></td>
            <td><span class="adm-badge <?= $p['dias_restantes'] <= 7 ? 'adm-badge-warn' : 'adm-badge-info' ?>"><?= $p['dias_restantes'] ?>d</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Morosos -->
  <?php if ($morosos): ?>
  <div class="ec-card" style="margin-bottom:16px;border-color:rgba(248,113,113,0.3);">
    <div class="ec-card-hdr" style="color:#f87171;">
      <span class="material-icons-round" style="color:#f87171;">warning</span>
      Planes vencidos
      <span class="adm-badge adm-badge-off" style="margin-left:4px;"><?= count($morosos) ?></span>
    </div>
    <table class="adm-table">
      <thead><tr><th>Empresa</th><th>Plan</th><th>Venció</th><th>Días vencido</th></tr></thead>
      <tbody>
        <?php foreach ($morosos as $m): ?>
        <tr data-href="<?= BASE ?>/admin_empresa.php?id=<?= $m['id_empresa'] ?>">
          <td>
            <div class="tbl-name-cell">
              <div class="tbl-avatar" style="background:linear-gradient(135deg,#dc2626,#f87171);"><?= sadmin_iniciales($m['nombre']) ?></div>
              <div class="tbl-name-main"><?= htmlspecialchars($m['nombre']) ?></div>
            </div>
          </td>
          <td><?= htmlspecialchars($m['plan_tipo'] ?: '—') ?></td>
          <td style="color:#f87171;font-size:12px;"><?= date('d/m/Y', strtotime($m['plan_vencimiento'])) ?></td>
          <td><span class="adm-badge adm-badge-off"><?= $m['dias_vencido'] ?>d</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Historial de pagos -->
  <div class="ec-card">
    <div class="ec-card-hdr"><span class="material-icons-round">receipt_long</span>Historial de pagos recientes</div>
    <?php if (empty($pagos)): ?>
      <div style="padding:24px 18px;color:var(--txt2);font-size:13px;">Sin pagos registrados.</div>
    <?php else: ?>
    <table class="adm-table">
      <thead><tr><th>Empresa</th><th>Descripción</th><th>Monto</th><th>Estado</th><th>Fecha</th></tr></thead>
      <tbody>
        <?php foreach ($pagos as $p): ?>
        <tr>
          <td style="font-weight:600;"><?= htmlspecialchars($p['empresa']) ?></td>
          <td style="font-size:13px;color:var(--txt2);"><?= htmlspecialchars($p['descripcion']) ?></td>
          <td style="font-weight:700;">$<?= number_format($p['monto'], 0, ',', '.') ?></td>
          <td><span class="adm-badge <?= $p['estado'] === 'Pagado' ? 'adm-badge-ok' : 'adm-badge-warn' ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
          <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</main>
<script src="<?= BASE ?>/assets/js/admin_common.js"></script>
<script src="<?= BASE ?>/assets/js/admin_suscripciones.js"></script>
</body>
</html>
