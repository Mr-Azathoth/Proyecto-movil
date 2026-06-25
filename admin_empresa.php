<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /reparo/admin_clientes.php'); exit; }

$db = getDB();
$emp = $db->prepare("SELECT * FROM empresas WHERE id_empresa = ? LIMIT 1");
$emp->execute([$id]);
$emp = $emp->fetch();
if (!$emp) { header('Location: /reparo/admin_clientes.php'); exit; }

$usuarios = $db->prepare("SELECT id_usuario, nombre, user, cargo, activo FROM usuarios WHERE id_empresa = ? ORDER BY cargo, nombre");
$usuarios->execute([$id]);
$usuarios = $usuarios->fetchAll();

$stats = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Ingresado')     AS ing,
        SUM(status = 'En Reparacion') AS rep,
        SUM(status = 'Reparado')      AS rep2,
        SUM(status = 'Entregado')     AS ent,
        SUM(status = 'Garantia')      AS gar,
        MAX(fecha_ingreso)            AS ultimo
    FROM reparaciones WHERE id_empresa = ?
");
$stats->execute([$id]);
$stats = $stats->fetch();

$pagos = $db->prepare("SELECT fecha, monto, descripcion, estado FROM historial_pagos WHERE id_empresa = ? ORDER BY fecha DESC LIMIT 10");
$pagos->execute([$id]);
$pagos = $pagos->fetchAll();

$planOk = $emp['plan_estado'] === 'Activo' && ($emp['plan_vencimiento'] === null || $emp['plan_vencimiento'] >= date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reparo Admin — <?= htmlspecialchars($emp['nombre']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
<link rel="stylesheet" href="/reparo/assets/css/admin.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

<main class="adm-main">
  <div class="adm-topbar">
    <div style="display:flex;align-items:center;gap:12px;">
      <a href="/reparo/admin_clientes.php" style="color:var(--txt2);text-decoration:none;display:flex;align-items:center;">
        <span class="material-icons-round" style="font-size:20px;">arrow_back</span>
      </a>
      <h1 class="adm-title"><?= htmlspecialchars($emp['nombre']) ?></h1>
      <span class="adm-badge <?= $emp['activa'] ? 'adm-badge-ok' : 'adm-badge-off' ?>" id="badge-activa">
        <?= $emp['activa'] ? 'Activa' : 'Inactiva' ?>
      </span>
    </div>
    <button class="adm-btn <?= $emp['activa'] ? 'adm-btn-danger' : 'adm-btn-ghost' ?>" id="btn-toggle-empresa"
            data-id="<?= $id ?>" data-activa="<?= $emp['activa'] ?>">
      <span class="material-icons-round"><?= $emp['activa'] ? 'block' : 'check_circle' ?></span>
      <?= $emp['activa'] ? 'Suspender' : 'Reactivar' ?>
    </button>
  </div>

  <div class="adm-empresa-grid">

    <!-- Info general -->
    <div class="adm-panel">
      <div class="adm-panel-hdr">Información</div>
      <div style="padding:16px 18px;display:grid;grid-template-columns:1fr 1fr;gap:10px 24px;font-size:13px;">
        <?php $fields = [
          ['Correo',    $emp['correo']     ?? '—'],
          ['Teléfono',  $emp['telefono']   ?? '—'],
          ['RUT',       $emp['rut_empresa'] ?? '—'],
          ['Dirección', $emp['direccion']  ?? '—'],
          ['Comuna',    $emp['comuna']     ?? '—'],
          ['Región',    $emp['region']     ?? '—'],
          ['Registro',  $emp['creada_en']  ? date('d/m/Y', strtotime($emp['creada_en'])) : '—'],
        ]; foreach ($fields as [$lbl, $val]): ?>
        <div>
          <div style="color:var(--txt2);font-size:11px;margin-bottom:2px;"><?= $lbl ?></div>
          <div style="color:var(--txt);"><?= htmlspecialchars($val) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Estadísticas de servicios -->
    <div class="adm-panel">
      <div class="adm-panel-hdr">Servicios técnicos</div>
      <div style="padding:16px 18px;">
        <div style="font-family:'Outfit',sans-serif;font-size:36px;font-weight:700;color:var(--txt);">
          <?= number_format($stats['total']) ?>
        </div>
        <div style="font-size:12px;color:var(--txt2);margin-bottom:14px;">servicios en total</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php $st_map = [
            ['Ingresado',     $stats['ing'],  '#60a5fa'],
            ['En reparación', $stats['rep'],  '#fbbf24'],
            ['Reparado',      $stats['rep2'], '#34d399'],
            ['Entregado',     $stats['ent'],  '#4ade80'],
            ['Garantía',      $stats['gar'],  '#c084fc'],
          ]; foreach ($st_map as [$lbl, $cnt, $color]): ?>
          <div style="background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:6px 10px;font-size:12px;">
            <span style="font-weight:700;color:<?= $color ?>;"><?= $cnt ?></span>
            <span style="color:var(--txt2);margin-left:4px;"><?= $lbl ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($stats['ultimo']): ?>
        <div style="font-size:11px;color:var(--txt3);margin-top:12px;">
          Último ingreso: <?= date('d/m/Y H:i', strtotime($stats['ultimo'])) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Plan / suscripción -->
    <div class="adm-panel">
      <div class="adm-panel-hdr">Plan activo</div>
      <div style="padding:16px 18px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
          <span class="material-icons-round" style="color:#a78bfa;font-size:28px;">workspace_premium</span>
          <div>
            <div style="font-weight:700;font-size:16px;"><?= htmlspecialchars($emp['plan_tipo'] ?: 'Sin plan') ?></div>
            <div style="font-size:12px;color:var(--txt2);">
              Estado: <span class="adm-badge <?= $planOk ? 'adm-badge-ok' : 'adm-badge-off' ?>"><?= htmlspecialchars($emp['plan_estado'] ?: '—') ?></span>
            </div>
          </div>
        </div>
        <?php if ($emp['plan_vencimiento']): ?>
        <div style="font-size:13px;color:var(--txt2);margin-bottom:16px;">
          Vence: <strong style="color:<?= $planOk ? 'var(--txt)' : '#f87171' ?>;"><?= date('d/m/Y', strtotime($emp['plan_vencimiento'])) ?></strong>
          <?php if (!$planOk): ?>
            <span style="color:#f87171;font-size:12px;"> — VENCIDO</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- Editar plan -->
        <div style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:12px;display:grid;gap:8px;">
          <div style="font-size:12px;font-weight:600;color:var(--txt2);margin-bottom:2px;">Editar plan manualmente</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>
              <label style="font-size:11px;color:var(--txt2);">Tipo</label>
              <input id="plan-tipo" class="adm-search" style="width:100%;margin-top:2px;" value="<?= htmlspecialchars($emp['plan_tipo'] ?? '') ?>" placeholder="Ej: Pro">
            </div>
            <div>
              <label style="font-size:11px;color:var(--txt2);">Estado</label>
              <select id="plan-estado" class="adm-search" style="width:100%;margin-top:2px;">
                <?php foreach (['Activo','Vencido','Suspendido','Gratis'] as $s): ?>
                <option value="<?= $s ?>" <?= $emp['plan_estado'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label style="font-size:11px;color:var(--txt2);">Vencimiento</label>
            <input id="plan-venc" type="date" class="adm-search" style="width:100%;margin-top:2px;" value="<?= htmlspecialchars($emp['plan_vencimiento'] ?? '') ?>">
          </div>
          <button class="adm-btn adm-btn-primary" id="btn-save-plan" data-id="<?= $id ?>" style="width:100%;justify-content:center;">
            <span class="material-icons-round">save</span>Guardar plan
          </button>
          <div id="plan-msg" style="font-size:12px;color:#4ade80;display:none;text-align:center;"></div>
        </div>
      </div>
    </div>

    <!-- Historial de pagos -->
    <div class="adm-panel">
      <div class="adm-panel-hdr">Historial de pagos</div>
      <?php if (empty($pagos)): ?>
        <div style="padding:24px 18px;color:var(--txt2);font-size:13px;">Sin pagos registrados.</div>
      <?php else: ?>
      <table class="adm-table">
        <thead><tr><th>Fecha</th><th>Descripción</th><th>Monto</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($pagos as $p): ?>
          <tr>
            <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
            <td style="font-size:13px;"><?= htmlspecialchars($p['descripcion']) ?></td>
            <td style="font-weight:600;">$<?= number_format($p['monto'], 0, ',', '.') ?></td>
            <td><span class="adm-badge <?= $p['estado'] === 'Pagado' ? 'adm-badge-ok' : 'adm-badge-warn' ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div><!-- /grid -->

  <!-- Usuarios -->
  <div class="adm-panel" style="margin-top:16px;">
    <div class="adm-panel-hdr">
      <span>Usuarios (<?= count($usuarios) ?>)</span>
    </div>
    <table class="adm-table">
      <thead><tr><th>Nombre</th><th>Usuario</th><th>Cargo</th><th>Estado</th><th>Resetear contraseña</th><th>Acceso</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr id="row-user-<?= $u['id_usuario'] ?>">
          <td style="font-weight:600;"><?= htmlspecialchars($u['nombre']) ?></td>
          <td style="color:var(--txt2);font-size:12px;"><?= htmlspecialchars($u['user']) ?></td>
          <td><span class="adm-badge adm-badge-info"><?= htmlspecialchars($u['cargo']) ?></span></td>
          <td>
            <span class="adm-badge <?= $u['activo'] ? 'adm-badge-ok' : 'adm-badge-off' ?>" id="badge-u-<?= $u['id_usuario'] ?>">
              <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td>
            <button class="adm-btn adm-btn-ghost btn-reset-pass" style="padding:4px 10px;font-size:12px;"
                    data-id="<?= $u['id_usuario'] ?>" data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
              <span class="material-icons-round">mail</span>Enviar enlace
            </button>
          </td>
          <td>
            <button class="adm-btn adm-btn-ghost btn-toggle-user" style="padding:4px 10px;font-size:12px;"
                    data-id="<?= $u['id_usuario'] ?>" data-activo="<?= $u['activo'] ?>">
              <span class="material-icons-round"><?= $u['activo'] ? 'block' : 'check_circle' ?></span>
              <?= $u['activo'] ? 'Suspender' : 'Reactivar' ?>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</main>

<script src="/reparo/assets/js/admin_empresa.js"></script>
</body>
</html>
