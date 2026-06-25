<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();

$db = getDB();

// KPIs globales
$kpi = $db->query("
    SELECT
        (SELECT COUNT(*) FROM empresas WHERE activa = 1)                                     AS empresas_activas,
        (SELECT COUNT(*) FROM empresas WHERE activa = 0)                                     AS empresas_inactivas,
        (SELECT COUNT(*) FROM usuarios WHERE activo = 1)                                     AS usuarios_totales,
        (SELECT COUNT(*) FROM reparaciones)                                                   AS servicios_totales,
        (SELECT COUNT(*) FROM reparaciones WHERE DATE(fecha_ingreso) = CURDATE())             AS servicios_hoy,
        (SELECT COUNT(*) FROM empresas WHERE activa = 1
            AND plan_estado = 'Activo' AND (plan_vencimiento IS NULL OR plan_vencimiento >= CURDATE())) AS con_plan_activo,
        (SELECT COUNT(*) FROM empresas WHERE activa = 1
            AND (plan_estado != 'Activo' OR (plan_vencimiento IS NOT NULL AND plan_vencimiento < CURDATE()))) AS sin_plan_activo
")->fetch();

// Últimas 6 empresas registradas
$nuevas = $db->query("SELECT id_empresa, nombre, correo, activa, creada_en FROM empresas ORDER BY creada_en DESC LIMIT 6")->fetchAll();

// Actividad reciente
$actividad = $db->query("
    SELECT la.accion, la.usuario, la.ip, la.fecha, e.nombre AS empresa
    FROM log_acciones la
    JOIN empresas e ON e.id_empresa = la.id_empresa
    ORDER BY la.fecha DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
<link rel="stylesheet" href="/reparo/assets/css/admin.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

<!-- Main -->
<main class="adm-main">
  <div class="adm-topbar">
    <h1 class="adm-title">Resumen general</h1>
    <div style="font-size:13px;color:var(--txt2);"><?= date('d/m/Y') ?></div>
  </div>

  <!-- KPI Cards -->
  <div class="adm-kpi-grid">
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#4ade80;">business</span>
      <div>
        <div class="adm-kpi-val"><?= $kpi['empresas_activas'] ?></div>
        <div class="adm-kpi-lbl">Empresas activas</div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#60a5fa;">people</span>
      <div>
        <div class="adm-kpi-val"><?= $kpi['usuarios_totales'] ?></div>
        <div class="adm-kpi-lbl">Usuarios totales</div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#f59e0b;">build</span>
      <div>
        <div class="adm-kpi-val"><?= number_format($kpi['servicios_totales']) ?></div>
        <div class="adm-kpi-lbl">Servicios registrados</div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#a78bfa;">build_circle</span>
      <div>
        <div class="adm-kpi-val"><?= $kpi['servicios_hoy'] ?></div>
        <div class="adm-kpi-lbl">Servicios hoy</div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#34d399;">workspace_premium</span>
      <div>
        <div class="adm-kpi-val"><?= $kpi['con_plan_activo'] ?></div>
        <div class="adm-kpi-lbl">Con plan activo</div>
      </div>
    </div>
    <div class="adm-kpi-card <?= $kpi['sin_plan_activo'] > 0 ? 'adm-kpi-warn' : '' ?>">
      <span class="material-icons-round" style="color:#f87171;">warning</span>
      <div>
        <div class="adm-kpi-val"><?= $kpi['sin_plan_activo'] ?></div>
        <div class="adm-kpi-lbl">Sin plan activo</div>
      </div>
    </div>
  </div>

  <div class="adm-two-col">
    <!-- Últimas empresas -->
    <div class="adm-panel">
      <div class="adm-panel-hdr">
        <span>Últimas empresas registradas</span>
        <a href="/reparo/admin_clientes.php" style="font-size:12px;color:var(--accent);text-decoration:none;">Ver todas →</a>
      </div>
      <table class="adm-table">
        <thead><tr><th>Empresa</th><th>Correo</th><th>Estado</th><th>Registro</th></tr></thead>
        <tbody>
          <?php foreach ($nuevas as $e): ?>
          <tr>
            <td><?= htmlspecialchars($e['nombre']) ?></td>
            <td style="color:var(--txt2);font-size:12px;"><?= htmlspecialchars($e['correo'] ?? '—') ?></td>
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
    <div class="adm-panel">
      <div class="adm-panel-hdr">
        <span>Actividad reciente</span>
        <a href="/reparo/admin_actividad.php" style="font-size:12px;color:var(--accent);text-decoration:none;">Ver todo →</a>
      </div>
      <div class="adm-activity-list">
        <?php foreach ($actividad as $a): ?>
        <div class="adm-activity-row">
          <span class="material-icons-round" style="font-size:16px;color:var(--txt3);">radio_button_checked</span>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <strong><?= htmlspecialchars($a['usuario'] ?? '—') ?></strong>
              — <?= htmlspecialchars($a['accion']) ?>
            </div>
            <div style="font-size:11px;color:var(--txt2);"><?= htmlspecialchars($a['empresa']) ?> · <?= date('d/m H:i', strtotime($a['fecha'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($actividad)): ?>
          <div style="color:var(--txt2);font-size:13px;padding:12px 0;">Sin actividad registrada aún.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

</body>
</html>
