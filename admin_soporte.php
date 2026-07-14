<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';
requireSuperAdmin();
$db = getDB();
$pageTitle = 'Centrotec Admin — Soporte';

// Auto-migrate (misma tabla que api/tickets.php)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS tickets (
        id_ticket      INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa     INT NOT NULL,
        id_usuario     INT NOT NULL,
        usuario_nombre VARCHAR(100) NOT NULL,
        asunto         VARCHAR(200) NOT NULL,
        mensaje        TEXT NOT NULL,
        estado         ENUM('Abierto','En revision','Resuelto') NOT NULL DEFAULT 'Abierto',
        respuesta      TEXT NULL,
        respondido_por VARCHAR(100) NULL,
        created_at     DATETIME NOT NULL DEFAULT NOW(),
        updated_at     DATETIME NULL,
        KEY idx_empresa (id_empresa),
        KEY idx_estado  (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$empresas = $db->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre")->fetchAll();
$f_empresa = (int)($_GET['empresa'] ?? 0);
$f_estado  = $_GET['estado'] ?? '';
$estados_validos = ['Abierto', 'En revision', 'Resuelto'];

$where = ['1=1'];
$params = [];
if ($f_empresa) { $where[] = 't.id_empresa = ?'; $params[] = $f_empresa; }
if (in_array($f_estado, $estados_validos, true)) { $where[] = 't.estado = ?'; $params[] = $f_estado; }

$st = $db->prepare(
    "SELECT t.id_ticket, t.id_empresa, e.nombre AS empresa,
            t.usuario_nombre, t.asunto, t.mensaje,
            t.estado, t.respuesta, t.respondido_por,
            t.created_at, t.updated_at
       FROM tickets t JOIN empresas e ON e.id_empresa = t.id_empresa
      WHERE " . implode(' AND ', $where) . "
      ORDER BY FIELD(t.estado,'Abierto','En revision','Resuelto'), t.created_at DESC"
);
$st->execute($params);
$tickets = $st->fetchAll();

$kpis = $db->query(
    "SELECT estado, COUNT(*) AS n FROM tickets GROUP BY estado"
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/includes/admin_head.php'; ?>
<body class="admin-body">
<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div>
      <h1 class="adm-title">Soporte</h1>
      <div style="font-size:13px;color:var(--txt2);margin-top:2px;">Solicitudes de soporte de los clientes</div>
    </div>
  </div>

  <!-- KPIs -->
  <div class="adm-kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;max-width:600px;">
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#f87171;">inbox</span>
      <div><div class="adm-kpi-val"><?= (int)($kpis['Abierto'] ?? 0) ?></div><div class="adm-kpi-lbl">Abiertos</div></div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#fbbf24;">pending</span>
      <div><div class="adm-kpi-val"><?= (int)($kpis['En revision'] ?? 0) ?></div><div class="adm-kpi-lbl">En revisión</div></div>
    </div>
    <div class="adm-kpi-card">
      <span class="material-icons-round" style="color:#4ade80;">check_circle</span>
      <div><div class="adm-kpi-val"><?= (int)($kpis['Resuelto'] ?? 0) ?></div><div class="adm-kpi-lbl">Resueltos</div></div>
    </div>
  </div>

  <!-- Filtros -->
  <form method="GET" style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
    <select name="empresa" class="adm-search" style="max-width:220px;">
      <option value="">Todas las empresas</option>
      <?php foreach ($empresas as $e): ?>
        <option value="<?= $e['id_empresa'] ?>" <?= $e['id_empresa'] === $f_empresa ? 'selected' : '' ?>>
          <?= htmlspecialchars($e['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="estado" class="adm-search" style="max-width:160px;">
      <option value="">Todos los estados</option>
      <?php foreach ($estados_validos as $s): ?>
        <option value="<?= $s ?>" <?= $s === $f_estado ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="adm-btn adm-btn-ghost">
      <span class="material-icons-round">filter_list</span>Filtrar
    </button>
    <?php if ($f_empresa || $f_estado): ?>
      <a href="<?= BASE ?>/admin_soporte.php" class="adm-btn adm-btn-ghost">
        <span class="material-icons-round">clear</span>Limpiar
      </a>
    <?php endif; ?>
  </form>

  <!-- Tabla de tickets -->
  <?php if (empty($tickets)): ?>
    <div class="adm-panel" style="padding:60px;text-align:center;color:var(--txt3);">
      <span class="material-icons-round" style="font-size:48px;color:#4ade80;">support_agent</span>
      <p style="margin-top:12px;">No hay tickets para los filtros seleccionados.</p>
    </div>
  <?php else: ?>
  <div class="ec-card">
    <table class="adm-table">
      <thead><tr>
        <th>#</th>
        <th>Empresa</th>
        <th>Usuario</th>
        <th>Asunto</th>
        <th>Estado</th>
        <th>Fecha</th>
        <th style="text-align:center;">Acción</th>
      </tr></thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td style="font-weight:700;color:var(--txt2);">#<?= $t['id_ticket'] ?></td>
          <td style="font-size:12px;color:var(--txt2);"><?= htmlspecialchars($t['empresa']) ?></td>
          <td><?= htmlspecialchars($t['usuario_nombre']) ?></td>
          <td style="max-width:280px;">
            <div class="tbl-name-main" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= htmlspecialchars($t['asunto']) ?>
            </div>
            <?php if ($t['respondido_por']): ?>
              <div style="font-size:11px;color:var(--txt3);">Resp. por <?= htmlspecialchars($t['respondido_por']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $badge = match($t['estado']) {
                  'Abierto'     => 'adm-badge-off',
                  'En revision' => 'adm-badge-warn',
                  'Resuelto'    => 'adm-badge-ok',
                  default       => ''
              };
            ?>
            <span class="adm-badge <?= $badge ?>"><?= $t['estado'] ?></span>
          </td>
          <td style="font-size:12px;color:var(--txt2);"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
          <td style="text-align:center;">
            <button class="adm-btn adm-btn-ghost btn-ver-ticket"
              data-id="<?= $t['id_ticket'] ?>"
              data-empresa="<?= htmlspecialchars($t['empresa'], ENT_QUOTES) ?>"
              data-usuario="<?= htmlspecialchars($t['usuario_nombre'], ENT_QUOTES) ?>"
              data-asunto="<?= htmlspecialchars($t['asunto'], ENT_QUOTES) ?>"
              data-mensaje="<?= htmlspecialchars($t['mensaje'], ENT_QUOTES) ?>"
              data-estado="<?= htmlspecialchars($t['estado'], ENT_QUOTES) ?>"
              data-respuesta="<?= htmlspecialchars($t['respuesta'] ?? '', ENT_QUOTES) ?>">
              <span class="material-icons-round">open_in_new</span> Ver
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</main>

<!-- Modal: ver y responder ticket -->
<div class="modal-bg pap-hidden" id="modal-ticket">
  <div class="modal-box" style="max-width:600px;">
    <div class="modal-hd">
      <h3 id="mtk-titulo">Ticket #</h3>
      <button class="modal-close" id="btn-modal-ticket-close">
        <span class="material-icons-round">close</span>
      </button>
    </div>
    <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:16px;">
      <div style="font-size:12px;color:var(--txt2);">
        <strong id="mtk-empresa"></strong> — <span id="mtk-usuario"></span>
      </div>
      <div class="ec-card" style="padding:14px 18px;font-size:13px;line-height:1.6;color:var(--txt);" id="mtk-mensaje"></div>

      <div style="border-top:1px solid var(--border);padding-top:16px;">
        <label style="font-size:12px;font-weight:600;color:var(--txt2);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:8px;">Respuesta</label>
        <textarea id="mtk-respuesta" rows="4"
          style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:var(--r);padding:10px 12px;font-size:13px;color:var(--txt);resize:vertical;font-family:inherit;"></textarea>
      </div>

      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:600;color:var(--txt);">Estado:</label>
        <select id="mtk-estado" class="adm-search" style="max-width:180px;">
          <option value="Abierto">Abierto</option>
          <option value="En revision">En revisión</option>
          <option value="Resuelto">Resuelto</option>
        </select>
        <button class="adm-btn adm-btn-primary" id="btn-guardar-ticket" style="margin-left:auto;">
          <span class="material-icons-round">save</span> Guardar
        </button>
      </div>
    </div>
  </div>
</div>

<div id="toast"></div>

<script src="<?= BASE ?>/assets/js/admin_common.js"></script>
<script src="<?= BASE ?>/assets/js/admin_soporte.js"></script>
</body>
</html>
