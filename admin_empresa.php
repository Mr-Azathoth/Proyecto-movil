<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /reparo/admin_clientes.php'); exit; }

$db  = getDB();
$emp = $db->prepare("SELECT * FROM empresas WHERE id_empresa = ? LIMIT 1");
$emp->execute([$id]);
$emp = $emp->fetch();
if (!$emp) { header('Location: /reparo/admin_clientes.php'); exit; }

$usuarios = $db->prepare("SELECT id_usuario, nombre, user, cargo, activo FROM usuarios WHERE id_empresa = ? ORDER BY cargo, nombre");
$usuarios->execute([$id]);
$usuarios = $usuarios->fetchAll();

$stats = $db->prepare("
    SELECT COUNT(*) AS total,
        SUM(status='Ingresado') AS ing, SUM(status='En Reparacion') AS rep,
        SUM(status='Reparado') AS rep2, SUM(status='Entregado') AS ent,
        SUM(status='Garantia') AS gar, MAX(fecha_ingreso) AS ultimo
    FROM reparaciones WHERE id_empresa = ?
");
$stats->execute([$id]);
$stats = $stats->fetch();

$pagos = $db->prepare("SELECT fecha, monto, descripcion, estado FROM historial_pagos WHERE id_empresa = ? ORDER BY fecha DESC LIMIT 10");
$pagos->execute([$id]);
$pagos = $pagos->fetchAll();

$planOk = $emp['plan_estado'] === 'Activo' && ($emp['plan_vencimiento'] === null || $emp['plan_vencimiento'] >= date('Y-m-d'));

// Iniciales para avatar de empresa
$palabras = preg_split('/\s+/', trim($emp['nombre']));
$iniciales = mb_strtoupper(mb_substr($palabras[0], 0, 1) . (isset($palabras[1]) ? mb_substr($palabras[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reparo Admin — <?= htmlspecialchars($emp['nombre']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
<link rel="stylesheet" href="/reparo/assets/css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

<main class="adm-main">

  <!-- Hero header -->
  <div class="emp-hero">
    <div class="emp-avatar"><?= $iniciales ?></div>
    <div class="emp-hero-info">
      <div class="emp-hero-name"><?= htmlspecialchars($emp['nombre']) ?></div>
      <div class="emp-hero-sub">
        <?= htmlspecialchars($emp['correo'] ?: '—') ?>
        <?php if ($emp['telefono']): ?> &nbsp;·&nbsp; <?= htmlspecialchars($emp['telefono']) ?><?php endif; ?>
        &nbsp;·&nbsp; Registrada el <?= date('d/m/Y', strtotime($emp['creada_en'])) ?>
      </div>
    </div>
    <div class="emp-hero-actions">
      <a href="/reparo/admin_clientes.php" class="adm-btn adm-btn-ghost">
        <span class="material-icons-round">arrow_back</span>Volver
      </a>
      <button class="adm-btn <?= $emp['activa'] ? 'adm-btn-danger' : 'adm-btn-ghost' ?>"
              id="btn-toggle-empresa" data-id="<?= $id ?>" data-activa="<?= $emp['activa'] ?>">
        <span class="material-icons-round"><?= $emp['activa'] ? 'block' : 'check_circle' ?></span>
        <span id="lbl-toggle"><?= $emp['activa'] ? 'Suspender' : 'Reactivar' ?></span>
      </button>
      <span class="adm-badge <?= $emp['activa'] ? 'adm-badge-ok' : 'adm-badge-off' ?>" id="badge-activa"
            style="font-size:12px;padding:5px 12px;">
        <?= $emp['activa'] ? 'Activa' : 'Inactiva' ?>
      </span>
    </div>
  </div>

  <div class="ec-grid">

    <!-- Información -->
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round">business</span>
        Información de la empresa
      </div>
      <div class="ec-card-body">
        <div class="info-grid">
          <?php $fields = [
            ['alternate_email','Correo',    $emp['correo']      ?? null],
            ['phone',          'Teléfono',  $emp['telefono']    ?? null],
            ['badge',          'RUT',       $emp['rut_empresa'] ?? null],
            ['location_on',    'Dirección', $emp['direccion']   ?? null],
            ['location_city',  'Comuna',    $emp['comuna']      ?? null],
            ['map',            'Región',    $emp['region']      ?? null],
          ]; foreach ($fields as [$icon, $lbl, $val]): ?>
          <div class="info-item">
            <div class="info-label" style="display:flex;align-items:center;gap:4px;">
              <span class="material-icons-round" style="font-size:13px;"><?= $icon ?></span>
              <?= $lbl ?>
            </div>
            <div class="info-value <?= $val ? '' : 'empty' ?>"><?= $val ? htmlspecialchars($val) : 'Sin registrar' ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Servicios técnicos -->
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round">build</span>
        Servicios técnicos
      </div>
      <div class="ec-card-body">
        <div class="stat-big"><?= number_format($stats['total']) ?></div>
        <div class="stat-sub">servicios registrados<?= $stats['ultimo'] ? ' · último ' . date('d/m/Y', strtotime($stats['ultimo'])) : '' ?></div>
        <div class="stat-chips">
          <?php $st_map = [
            ['Ingresado',     $stats['ing'],  '#60a5fa'],
            ['En reparación', $stats['rep'],  '#fbbf24'],
            ['Reparado',      $stats['rep2'], '#34d399'],
            ['Entregado',     $stats['ent'],  '#4ade80'],
            ['Garantía',      $stats['gar'],  '#c084fc'],
          ]; foreach ($st_map as [$lbl, $cnt, $color]): ?>
          <div class="stat-chip">
            <div class="stat-chip-dot" style="background:<?= $color ?>;"></div>
            <span style="font-weight:700;color:<?= $color ?>;"><?= (int)$cnt ?></span>
            <span style="color:var(--txt2);"><?= $lbl ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Plan activo -->
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round" style="color:#a78bfa;">workspace_premium</span>
        Plan de suscripción
      </div>
      <div class="ec-card-body">
        <!-- Visualización actual del plan -->
        <div class="plan-display">
          <div class="plan-icon"><span class="material-icons-round">workspace_premium</span></div>
          <div>
            <div class="plan-name"><?= htmlspecialchars($emp['plan_tipo'] ?: 'Sin plan') ?></div>
            <div class="plan-meta">
              <span class="adm-badge <?= $planOk ? 'adm-badge-ok' : 'adm-badge-off' ?>"><?= htmlspecialchars($emp['plan_estado'] ?: '—') ?></span>
              <?php if ($emp['plan_vencimiento']): ?>
                &nbsp;·&nbsp;
                <span style="color:<?= $planOk ? 'var(--txt2)' : '#f87171' ?>;">
                  <?= $planOk ? 'Vence' : 'Venció' ?> el <?= date('d/m/Y', strtotime($emp['plan_vencimiento'])) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Editor de plan -->
        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--txt3);margin-bottom:10px;">Editar plan</div>
        <div class="plan-form">
          <div class="plan-row2">
            <div class="fg">
              <label>Tipo de plan</label>
              <input id="plan-tipo" type="text" value="<?= htmlspecialchars($emp['plan_tipo'] ?? '') ?>" placeholder="Básico, Pro, Enterprise...">
            </div>
            <div class="fg">
              <label>Estado</label>
              <select id="plan-estado">
                <?php foreach (['Activo','Vencido','Suspendido','Gratis'] as $s): ?>
                <option value="<?= $s ?>" <?= $emp['plan_estado'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="fg">
            <label>Fecha de vencimiento</label>
            <input id="plan-venc" type="date" value="<?= htmlspecialchars($emp['plan_vencimiento'] ?? '') ?>">
          </div>
          <button class="adm-btn adm-btn-primary" id="btn-save-plan" data-id="<?= $id ?>" style="width:100%;justify-content:center;">
            <span class="material-icons-round">save</span>Guardar cambios
          </button>
        </div>
      </div>
    </div>

    <!-- Historial de pagos -->
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round">receipt_long</span>
        Historial de pagos
        <?php if (!empty($pagos)): ?>
        <span class="adm-badge adm-badge-info" style="margin-left:auto;"><?= count($pagos) ?></span>
        <?php endif; ?>
      </div>
      <div class="ec-card-body" style="padding:0 20px;">
        <?php if (empty($pagos)): ?>
          <div style="padding:32px 0;text-align:center;color:var(--txt3);">
            <span class="material-icons-round" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4;">receipt_long</span>
            Sin pagos registrados
          </div>
        <?php else: foreach ($pagos as $p): ?>
          <div class="pago-row">
            <div>
              <div class="pago-desc"><?= htmlspecialchars($p['descripcion']) ?></div>
              <div class="pago-fecha"><?= date('d/m/Y', strtotime($p['fecha'])) ?></div>
            </div>
            <div style="text-align:right;">
              <div class="pago-monto">$<?= number_format($p['monto'], 0, ',', '.') ?></div>
              <span class="adm-badge <?= $p['estado'] === 'Pagado' ? 'adm-badge-ok' : 'adm-badge-warn' ?>" style="margin-top:4px;display:inline-block;">
                <?= htmlspecialchars($p['estado']) ?>
              </span>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div><!-- /ec-grid -->

  <!-- Usuarios -->
  <div class="ec-card">
    <div class="ec-card-hdr">
      <span class="material-icons-round">group</span>
      Usuarios
      <span class="adm-badge adm-badge-info" style="margin-left:6px;"><?= count($usuarios) ?></span>
    </div>
    <?php if (empty($usuarios)): ?>
      <div style="padding:32px;text-align:center;color:var(--txt3);">Sin usuarios registrados.</div>
    <?php else: foreach ($usuarios as $u):
        $ini = mb_strtoupper(mb_substr($u['nombre'], 0, 1));
        $colors = ['#7c3aed','#2f81f7','#059669','#d97706','#dc2626'];
        $color  = $colors[crc32($u['nombre']) % count($colors)];
    ?>
    <div class="user-row" id="urow-<?= $u['id_usuario'] ?>">
      <div class="user-avatar" style="background:<?= $color ?>1a;border-color:<?= $color ?>33;color:<?= $color ?>;"><?= $ini ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($u['nombre']) ?></div>
        <div class="user-handle">@<?= htmlspecialchars($u['user']) ?></div>
      </div>
      <span class="adm-badge adm-badge-info" style="font-size:11px;"><?= htmlspecialchars($u['cargo']) ?></span>
      <span class="adm-badge <?= $u['activo'] ? 'adm-badge-ok' : 'adm-badge-off' ?>" id="badge-u-<?= $u['id_usuario'] ?>" style="font-size:11px;">
        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
      </span>
      <div class="user-actions">
        <button class="adm-btn-xs adm-btn-xs-ghost btn-reset-pass"
                data-id="<?= $u['id_usuario'] ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>">
          <span class="material-icons-round">mail</span>Reset password
        </button>
        <?php if ($u['activo']): ?>
        <button class="adm-btn-xs adm-btn-xs-danger btn-toggle-user"
                data-id="<?= $u['id_usuario'] ?>" data-activo="1">
          <span class="material-icons-round">block</span>Suspender
        </button>
        <?php else: ?>
        <button class="adm-btn-xs adm-btn-xs-ok btn-toggle-user"
                data-id="<?= $u['id_usuario'] ?>" data-activo="0">
          <span class="material-icons-round">check_circle</span>Reactivar
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

</main>

<div id="toast">
  <span class="material-icons-round" id="toast-icon">check_circle</span>
  <span id="toast-msg"></span>
</div>

<script src="/reparo/assets/js/admin_empresa.js"></script>
</body>
</html>
