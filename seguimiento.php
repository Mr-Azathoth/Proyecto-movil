<?php
require_once __DIR__ . '/includes/config.php';

$db = getDB();

// Migración silenciosa
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN codigo_seguimiento VARCHAR(6) NULL"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD UNIQUE KEY uq_codigo_seguimiento (codigo_seguimiento)"); } catch(PDOException $e) {}

// Poblar registros existentes sin código
$sin_codigo = $db->query("SELECT id_ingreso FROM reparaciones WHERE codigo_seguimiento IS NULL")->fetchAll(PDO::FETCH_COLUMN);
if ($sin_codigo) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXY3456789';
    $len   = strlen($chars);
    $upd   = $db->prepare("UPDATE reparaciones SET codigo_seguimiento = ? WHERE id_ingreso = ?");
    foreach ($sin_codigo as $id) {
        for ($try = 0; $try < 30; $try++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, $len - 1)];
            $chk = $db->prepare("SELECT 1 FROM reparaciones WHERE codigo_seguimiento = ?");
            $chk->execute([$code]);
            if (!$chk->fetch()) { $upd->execute([$code, $id]); break; }
        }
    }
}

// Rate limit: max 10 búsquedas por minuto por IP
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rl_key = 'seg_rl_' . md5($ip);
if (!isset($_SESSION[$rl_key])) $_SESSION[$rl_key] = ['cnt' => 0, 'ts' => time()];
if (time() - $_SESSION[$rl_key]['ts'] > 60) $_SESSION[$rl_key] = ['cnt' => 0, 'ts' => time()];
$rate_ok = $_SESSION[$rl_key]['cnt'] < 10;

// Búsqueda
$codigo = strtoupper(trim($_GET['codigo'] ?? ''));
$orden  = null;
$historial_items = [];
$error  = '';

$estado_labels = [
    'Ingresado'     => ['label' => 'Ingresado',          'icon' => 'inbox',        'class' => 'seg-ingresado'],
    'En Reparacion' => ['label' => 'En reparación',      'icon' => 'build',        'class' => 'seg-proceso'],
    'Reparado'      => ['label' => 'Listo para entrega', 'icon' => 'check_circle', 'class' => 'seg-listo'],
    'Entregado'     => ['label' => 'Entregado',          'icon' => 'done_all',     'class' => 'seg-entregado'],
    'Garantia'      => ['label' => 'En garantía',        'icon' => 'verified',     'class' => 'seg-garantia'],
];

if ($codigo !== '') {
    if (!$rate_ok) {
        $error = 'Demasiadas búsquedas. Espera un momento e intenta de nuevo.';
    } elseif (!preg_match('/^[A-Z3-9]{6}$/', $codigo)) {
        $error = 'Código inválido. Debe tener 6 caracteres (letras y números).';
    } else {
        $_SESSION[$rl_key]['cnt']++;
        $st = $db->prepare(
            "SELECT id_ingreso, nombre_cliente, tipo_ingreso, marca_ingreso, modelo_ingreso,
                    dano_ingreso, status, fecha_ingreso, obs, ingresado_por, valor_ingreso
               FROM reparaciones
              WHERE codigo_seguimiento = ? LIMIT 1"
        );
        $st->execute([$codigo]);
        $orden = $st->fetch();

        if (!$orden) {
            $error = 'No encontramos una orden con ese código. Verifica e intenta de nuevo.';
        } else {
            $id = $orden['id_ingreso'];

            // ── Historial de estados ─────────────────────────────────────
            $h = $db->prepare(
                "SELECT 'estado' AS tipo, fecha_cambio AS fecha, status_cambio AS contenido,
                        status_anterior, user
                   FROM historial
                  WHERE id_reparacion = ?
                  ORDER BY fecha_cambio ASC"
            );
            $h->execute([$id]);
            $estados = $h->fetchAll();

            // ── Observaciones con timestamp ──────────────────────────────
            $o = $db->prepare(
                "SELECT 'obs' AS tipo, fecha, obs AS contenido, '' AS status_anterior, user
                   FROM observaciones
                  WHERE id_registro = ?
                  ORDER BY fecha ASC"
            );
            $o->execute([$id]);
            $obs_rows = $o->fetchAll();

            // ── obs heredada del campo reparaciones (sin timestamp) ──────
            $obs_legacy = trim($orden['obs'] ?? '');

            // Mezclar y ordenar por fecha
            $historial_items = array_merge($estados, $obs_rows);
            usort($historial_items, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
        }
    }
}

function fmt_fecha(string $fecha): string {
    return date('d/m/Y H:i', strtotime($fecha));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Seguimiento de reparación — Centrotec.cl</title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/seguimiento.css?v=<?= filemtime(__DIR__.'/assets/css/seguimiento.css') ?>">
</head>
<body>

<nav class="seg-nav">
  <a href="<?= BASE ?>/landing.php" class="seg-nav-logo">
    <div class="seg-nav-icon">C</div>
    <span>Centrotec</span>
  </a>
</nav>

<main class="seg-main">
  <div class="seg-card">
    <div class="seg-header">
      <span class="material-icons-round seg-header-icon">manage_search</span>
      <h1>Seguimiento de reparación</h1>
      <p>Ingresa el código de 6 caracteres que te entregaron al dejar tu equipo.</p>
    </div>

    <form class="seg-form" method="GET" action="<?= BASE ?>/seguimiento.php">
      <div class="seg-input-wrap">
        <input
          type="text"
          name="codigo"
          class="seg-input <?= $error ? 'seg-input-error' : '' ?>"
          placeholder="ABC123"
          value="<?= htmlspecialchars($codigo) ?>"
          maxlength="6"
          autocomplete="off"
          autocapitalize="characters"
          spellcheck="false"
          <?= !$orden ? 'autofocus' : '' ?>
        >
        <button type="submit" class="seg-btn">
          <span class="material-icons-round">search</span>
          Buscar
        </button>
      </div>
      <?php if ($error): ?>
        <div class="seg-error">
          <span class="material-icons-round">error_outline</span>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
    </form>

    <?php if ($orden): ?>
      <?php
        $st_info = $estado_labels[$orden['status']] ?? ['label' => $orden['status'], 'icon' => 'help', 'class' => 'seg-ingresado'];
        $equipo  = trim($orden['marca_ingreso'] . ' ' . $orden['modelo_ingreso']) ?: $orden['tipo_ingreso'];
      ?>
      <div class="seg-result">

        <!-- Estado actual + código -->
        <div class="seg-result-top">
          <div class="seg-codigo-tag">Código: <?= htmlspecialchars($codigo) ?></div>
          <span class="seg-estado <?= $st_info['class'] ?>">
            <span class="material-icons-round"><?= $st_info['icon'] ?></span>
            <?= $st_info['label'] ?>
          </span>
        </div>

        <!-- Datos del servicio -->
        <div class="seg-fields">
          <div class="seg-field">
            <div class="seg-field-label">Cliente</div>
            <div class="seg-field-val"><?= htmlspecialchars($orden['nombre_cliente']) ?></div>
          </div>
          <div class="seg-field">
            <div class="seg-field-label">Equipo</div>
            <div class="seg-field-val"><?= htmlspecialchars($equipo) ?></div>
          </div>
          <div class="seg-field">
            <div class="seg-field-label">Falla reportada</div>
            <div class="seg-field-val"><?= htmlspecialchars($orden['dano_ingreso']) ?></div>
          </div>
          <div class="seg-field">
            <div class="seg-field-label">Valor del servicio</div>
            <div class="seg-field-val seg-valor">$<?= number_format($orden['valor_ingreso'], 0, ',', '.') ?></div>
          </div>
          <div class="seg-field">
            <div class="seg-field-label">Ingresado por</div>
            <div class="seg-field-val"><?= htmlspecialchars($orden['ingresado_por']) ?></div>
          </div>
          <div class="seg-field">
            <div class="seg-field-label">Fecha de ingreso</div>
            <div class="seg-field-val"><?= fmt_fecha($orden['fecha_ingreso']) ?></div>
          </div>
        </div>

        <!-- Aviso si está listo o entregado -->
        <?php if ($orden['status'] === 'Reparado'): ?>
        <div class="seg-aviso seg-aviso-ok">
          <span class="material-icons-round">notifications_active</span>
          <strong>Tu equipo está listo.</strong> Puedes pasar a retirarlo cuando quieras.
        </div>
        <?php elseif ($orden['status'] === 'Entregado'): ?>
        <div class="seg-aviso seg-aviso-done">
          <span class="material-icons-round">done_all</span>
          Este equipo ya fue entregado. Si tienes alguna consulta, contacta al servicio técnico.
        </div>
        <?php endif; ?>

        <!-- Línea de tiempo -->
        <?php if ($historial_items || $obs_legacy): ?>
        <div class="seg-timeline-wrap">
          <div class="seg-timeline-title">
            <span class="material-icons-round">history</span>
            Historial del servicio
          </div>
          <div class="seg-timeline">

            <?php
            // Si hay obs legacy (campo obs de reparaciones) y no hay duplicado en observaciones, mostrar al inicio
            if ($obs_legacy && !array_filter($historial_items, fn($i) => $i['tipo'] === 'obs' && trim($i['contenido']) === $obs_legacy)):
            ?>
            <div class="seg-tl-item seg-tl-obs">
              <div class="seg-tl-dot"><span class="material-icons-round">chat</span></div>
              <div class="seg-tl-body">
                <div class="seg-tl-meta">
                  <span class="seg-tl-fecha"><?= fmt_fecha($orden['fecha_ingreso']) ?></span>
                  <span class="seg-tl-user"><?= htmlspecialchars($orden['ingresado_por']) ?></span>
                </div>
                <div class="seg-tl-contenido"><?= nl2br(htmlspecialchars($obs_legacy)) ?></div>
              </div>
            </div>
            <?php endif; ?>

            <?php foreach ($historial_items as $item): ?>

              <?php if ($item['tipo'] === 'estado'): ?>
              <div class="seg-tl-item seg-tl-estado">
                <div class="seg-tl-dot"><span class="material-icons-round">swap_horiz</span></div>
                <div class="seg-tl-body">
                  <div class="seg-tl-meta">
                    <span class="seg-tl-fecha"><?= fmt_fecha($item['fecha']) ?></span>
                    <span class="seg-tl-user"><?= htmlspecialchars($item['user']) ?></span>
                  </div>
                  <div class="seg-tl-contenido">
                    <?php if ($item['status_anterior']): ?>
                      <span class="seg-tl-badge seg-tl-badge-from"><?= htmlspecialchars($item['status_anterior']) ?></span>
                      <span class="material-icons-round seg-tl-arrow">arrow_forward</span>
                    <?php endif; ?>
                    <span class="seg-tl-badge seg-tl-badge-to <?= $estado_labels[$item['contenido']]['class'] ?? '' ?>">
                      <?= htmlspecialchars($estado_labels[$item['contenido']]['label'] ?? $item['contenido']) ?>
                    </span>
                  </div>
                </div>
              </div>

              <?php else: ?>
              <div class="seg-tl-item seg-tl-obs">
                <div class="seg-tl-dot"><span class="material-icons-round">chat</span></div>
                <div class="seg-tl-body">
                  <div class="seg-tl-meta">
                    <span class="seg-tl-fecha"><?= fmt_fecha($item['fecha']) ?></span>
                    <span class="seg-tl-user"><?= htmlspecialchars($item['user']) ?></span>
                  </div>
                  <div class="seg-tl-contenido"><?= nl2br(htmlspecialchars($item['contenido'])) ?></div>
                </div>
              </div>
              <?php endif; ?>

            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /seg-result -->
    <?php endif; ?>

  </div>
</main>

<footer class="seg-footer">
  <a href="<?= BASE ?>/landing.php">Centrotec</a> — Software para servicios técnicos
</footer>

</body>
</html>
