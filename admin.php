<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();

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
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
<link rel="stylesheet" href="/reparo/assets/css/admin.css">
</head>
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
            $palabras = preg_split('/\s+/', trim($e['nombre']));
            $ini = mb_strtoupper(mb_substr($palabras[0], 0, 1) . (isset($palabras[1]) ? mb_substr($palabras[1], 0, 1) : ''));
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
      <div style="padding:10px 18px;border-top:1px solid var(--border);" class="dblclick-hint">
        <span class="material-icons-round">touch_app</span>Doble clic para ver detalle
      </div>
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
