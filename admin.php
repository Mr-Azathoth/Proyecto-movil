<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

// 3 queries en lugar de 7 subconsultas correlacionadas
$kpi_emp = $db->query("
    SELECT
        SUM(activa = 1) AS empresas_activas,
        SUM(activa = 0) AS empresas_inactivas,
        SUM(activa = 1 AND plan_estado = 'Activo' AND (plan_vencimiento IS NULL OR plan_vencimiento >= CURDATE())) AS con_plan_activo,
        SUM(activa = 1 AND (plan_estado != 'Activo' OR (plan_vencimiento IS NOT NULL AND plan_vencimiento < CURDATE()))) AS sin_plan_activo
    FROM empresas
")->fetch(PDO::FETCH_ASSOC);
$kpi_usr = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();
$kpi_rep = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(DATE(fecha_ingreso) = CURDATE()), 0) AS hoy FROM reparaciones")->fetch(PDO::FETCH_ASSOC);
$kpi = array_merge($kpi_emp, [
    'usuarios_totales'  => $kpi_usr,
    'servicios_totales' => (int)($kpi_rep['total'] ?? 0),
    'servicios_hoy'     => (int)($kpi_rep['hoy'] ?? 0),
]);

$nuevas = $db->query("SELECT id_empresa, nombre, correo, activa, creada_en FROM empresas ORDER BY creada_en DESC LIMIT 6")->fetchAll();

$actividad = $db->query("
    SELECT la.accion, la.usuario, la.ip, la.fecha, e.nombre AS empresa
    FROM log_acciones la
    JOIN empresas e ON e.id_empresa = la.id_empresa
    ORDER BY la.fecha DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<?php $pageTitle = 'Reparo Admin'; ?>
<?php include __DIR__ . '/includes/admin_head.php'; ?>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

<main class="adm-main">
  <div class="adm-topbar">
    <div>
      <h1 class="adm-title">Resumen general</h1>
      <div style="font-size:13px;color:var(--txt2);margin-top:2px;"><?= date('l d \d\e F \d\e Y') ?></div>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="adm-kpi-grid">
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#4ade80;">business</span>
      <div><div class="adm-kpi-val"><?= $kpi['empresas_activas'] ?></div><div class="adm-kpi-lbl">Empresas activas</div></div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#60a5fa;">people</span>
      <div><div class="adm-kpi-val"><?= $kpi['usuarios_totales'] ?></div><div class="adm-kpi-lbl">Usuarios totales</div></div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#f59e0b;">build</span>
      <div><div class="adm-kpi-val"><?= number_format($kpi['servicios_totales']) ?></div><div class="adm-kpi-lbl">Servicios registrados</div></div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#a78bfa;">today</span>
      <div><div class="adm-kpi-val"><?= $kpi['servicios_hoy'] ?></div><div class="adm-kpi-lbl">Servicios hoy</div></div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#34d399;">workspace_premium</span>
      <div><div class="adm-kpi-val"><?= $kpi['con_plan_activo'] ?></div><div class="adm-kpi-lbl">Con plan activo</div></div>
    </div>
    <div class="adm-kpi-card <?= $kpi['sin_plan_activo'] > 0 ? 'adm-kpi-warn' : '' ?>">
      <span class="material-icons-round" style="color:#f87171;">warning</span>
      <div><div class="adm-kpi-val"><?= $kpi['sin_plan_activo'] ?></div><div class="adm-kpi-lbl">Sin plan activo</div></div>
    </div>
  </div>

  <div class="adm-two-col">

    <!-- Últimas empresas -->
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round">business</span>
        Últimas empresas registradas
        <a href="/reparo/admin_clientes.php" style="margin-left:auto;font-size:12px;color:var(--accent);text-decoration:none;font-weight:400;">Ver todas →</a>
      </div>
      <table class="adm-table" id="tbl">
        <thead><tr><th>Empresa</th><th>Estado</th><th>Registro</th></tr></thead>
        <tbody>
          <?php foreach ($nuevas as $e):
            $ini = sadmin_iniciales($e['nombre']);
          ?>
          <tr data-href="/reparo/admin_empresa.php?id=<?= $e['id_empresa'] ?>">
            <td>
              <div class="tbl-name-cell">
                <div class="tbl-avatar"><?= $ini ?></div>
                <div>
                  <div class="tbl-name-main"><?= htmlspecialchars($e['nombre']) ?></div>
                  <div class="tbl-name-sub"><?= htmlspecialchars($e['correo'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="adm-badge <?= $e['activa'] ? 'adm-badge-ok' : 'adm-badge-off' ?>">
                <?= $e['activa'] ? 'Activa' : 'Inactiva' ?>
              </span>
            </td>
            <td style="color:var(--txt2);font-size:12px;"><?= date('d/m/Y', strtotime($e['creada_en'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Actividad reciente -->
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round">history</span>
        Actividad reciente
        <a href="/reparo/admin_actividad.php" style="margin-left:auto;font-size:12px;color:var(--accent);text-decoration:none;font-weight:400;">Ver todo →</a>
      </div>
      <div style="padding:4px 0;">
        <?php if (empty($actividad)): ?>
          <div style="padding:32px;text-align:center;color:var(--txt3);">Sin actividad registrada aún.</div>
        <?php else: foreach ($actividad as $a): ?>
        <div class="adm-activity-row">
          <div style="width:8px;height:8px;border-radius:50%;background:var(--border2);flex-shrink:0;margin-top:5px;"></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <strong><?= htmlspecialchars($a['usuario'] ?? '—') ?></strong>
              <span style="color:var(--txt2);"> — <?= htmlspecialchars($a['accion']) ?></span>
            </div>
            <div style="font-size:11px;color:var(--txt3);margin-top:2px;">
              <?= htmlspecialchars($a['empresa']) ?> · <?= date('d/m H:i', strtotime($a['fecha'])) ?>
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>
</main>
<script src="/reparo/assets/js/admin_common.js"></script>
</body>
</html>
