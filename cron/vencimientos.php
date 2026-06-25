<?php
/**
 * Cron de notificaciones de vencimiento de plan.
 *
 * Ejecutar diariamente vía Windows Task Scheduler:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\reparo\cron\vencimientos.php
 *
 * También puede lanzarse desde el admin: admin_suscripciones.php → "Ejecutar ahora"
 * En ese caso se envía con ?dry_run=1 para previsualización.
 */

define('CRON_CALL', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

$dry_run = isset($_GET['dry_run']) || in_array('--dry-run', $argv ?? []);
$db      = getDB();
$hoy     = date('Y-m-d');
$log     = [];

// ── Buscar super admin email ──────────────────────────────────────
$sadmin_email = $db->query("SELECT email, nombre FROM super_admins WHERE activo = 1 LIMIT 1")->fetch();
$sadmin_to    = $sadmin_email['email'] ?? null;
$sadmin_nombre = $sadmin_email['nombre'] ?? 'Administrador';

// ── Empresas que vencen en 7 o 1 día (sin notificación enviada hoy) ──
$pronto = $db->query("
    SELECT id_empresa, nombre, correo, plan_tipo, plan_vencimiento,
           DATEDIFF(plan_vencimiento, '$hoy') AS dias_restantes,
           notif_vencimiento
    FROM empresas
    WHERE activa = 1
      AND plan_vencimiento IS NOT NULL
      AND plan_vencimiento > '$hoy'
      AND DATEDIFF(plan_vencimiento, '$hoy') IN (7, 1)
      AND (notif_vencimiento IS NULL OR notif_vencimiento < '$hoy')
")->fetchAll();

// ── Empresas ya vencidas (plan_vencimiento = ayer, notif no enviada) ──
$vencidas = $db->query("
    SELECT id_empresa, nombre, correo, plan_tipo, plan_vencimiento,
           notif_vencimiento
    FROM empresas
    WHERE activa = 1
      AND plan_vencimiento IS NOT NULL
      AND plan_vencimiento < '$hoy'
      AND (notif_vencimiento IS NULL OR notif_vencimiento < plan_vencimiento)
")->fetchAll();

// ── Enviar correos a empresas por vencer ────────────────────────
foreach ($pronto as $e) {
    $dias  = (int)$e['dias_restantes'];
    $label = $dias === 1 ? 'mañana' : "en $dias días";
    $plan  = htmlspecialchars($e['plan_tipo'] ?: 'tu plan');
    $fecha = date('d/m/Y', strtotime($e['plan_vencimiento']));
    $nombre = htmlspecialchars($e['nombre']);

    $html = "
    <div style='font-family:Inter,sans-serif;max-width:520px;margin:0 auto;background:#161b22;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;'>
      <div style='background:linear-gradient(135deg,#7c3aed,#a78bfa);padding:24px 28px;'>
        <h2 style='color:#fff;margin:0;font-size:18px;'>⚠️ Tu plan vence {$label}</h2>
      </div>
      <div style='padding:24px 28px;color:#e6edf3;'>
        <p>Hola <strong>{$nombre}</strong>,</p>
        <p>Te informamos que <strong>{$plan}</strong> vence el <strong>{$fecha}</strong>.</p>
        <p>Para continuar usando Reparo sin interrupciones, contacta a tu administrador para renovar tu suscripción.</p>
        <div style='background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.3);border-radius:8px;padding:14px 18px;margin-top:16px;'>
          <strong style='color:#fbbf24;'>Fecha límite: {$fecha}</strong>
        </div>
      </div>
    </div>";

    $log[] = ['tipo' => 'pronto', 'empresa' => $e['nombre'], 'dias' => $dias, 'correo' => $e['correo'], 'enviado' => false];

    if (!$dry_run && $e['correo']) {
        $ok = send_email($e['correo'], $e['nombre'], "Tu plan vence {$label} — Reparo", $html);
        $log[array_key_last($log)]['enviado'] = $ok;
        if ($ok) {
            $db->prepare("UPDATE empresas SET notif_vencimiento = ? WHERE id_empresa = ?")
               ->execute([$hoy, $e['id_empresa']]);
        }
    }
}

// ── Enviar correos a empresas vencidas ───────────────────────────
foreach ($vencidas as $e) {
    $plan  = htmlspecialchars($e['plan_tipo'] ?: 'tu plan');
    $fecha = date('d/m/Y', strtotime($e['plan_vencimiento']));
    $nombre = htmlspecialchars($e['nombre']);

    $html = "
    <div style='font-family:Inter,sans-serif;max-width:520px;margin:0 auto;background:#161b22;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;'>
      <div style='background:linear-gradient(135deg,#dc2626,#f87171);padding:24px 28px;'>
        <h2 style='color:#fff;margin:0;font-size:18px;'>❌ Tu plan ha vencido</h2>
      </div>
      <div style='padding:24px 28px;color:#e6edf3;'>
        <p>Hola <strong>{$nombre}</strong>,</p>
        <p>Tu <strong>{$plan}</strong> venció el <strong>{$fecha}</strong>.</p>
        <p>Tu acceso a Reparo puede verse limitado. Contacta a tu administrador para renovar.</p>
      </div>
    </div>";

    $log[] = ['tipo' => 'vencida', 'empresa' => $e['nombre'], 'correo' => $e['correo'], 'enviado' => false];

    if (!$dry_run && $e['correo']) {
        $ok = send_email($e['correo'], $e['nombre'], 'Tu plan ha vencido — Reparo', $html);
        $log[array_key_last($log)]['enviado'] = $ok;
        if ($ok) {
            $db->prepare("UPDATE empresas SET notif_vencimiento = ? WHERE id_empresa = ?")
               ->execute([$hoy, $e['id_empresa']]);
        }
    }
}

// ── Email resumen al super admin ─────────────────────────────────
$total_pronto  = count($pronto);
$total_vencidas = count($vencidas);

if ($sadmin_to && ($total_pronto > 0 || $total_vencidas > 0)) {
    $filas_pronto  = '';
    foreach ($pronto as $e) {
        $dias = (int)$e['dias_restantes'];
        $filas_pronto .= "<tr>
            <td style='padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.06);'>{$e['nombre']}</td>
            <td style='padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.06);'>" . htmlspecialchars($e['plan_tipo'] ?: '—') . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#fbbf24;'>{$dias} día(s)</td>
        </tr>";
    }
    $filas_vencidas = '';
    foreach ($vencidas as $e) {
        $filas_vencidas .= "<tr>
            <td style='padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.06);'>{$e['nombre']}</td>
            <td style='padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.06);'>" . htmlspecialchars($e['plan_tipo'] ?: '—') . "</td>
            <td style='padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.06);color:#f87171;'>" . date('d/m/Y', strtotime($e['plan_vencimiento'])) . "</td>
        </tr>";
    }

    $resumen_html = "
    <div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;background:#161b22;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;'>
      <div style='background:linear-gradient(135deg,#7c3aed,#a78bfa);padding:24px 28px;'>
        <h2 style='color:#fff;margin:0;font-size:18px;'>Reparo — Resumen diario de vencimientos</h2>
        <p style='color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:13px;'>" . date('d/m/Y') . "</p>
      </div>
      <div style='padding:24px 28px;color:#e6edf3;'>
        " . ($total_pronto ? "
        <h3 style='margin:0 0 12px;font-size:14px;color:#fbbf24;'>⚠️ Por vencer ({$total_pronto})</h3>
        <table style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;'>
          <thead><tr>
            <th style='text-align:left;padding:8px 12px;background:rgba(255,255,255,0.04);color:#8b949e;font-size:11px;text-transform:uppercase;'>Empresa</th>
            <th style='text-align:left;padding:8px 12px;background:rgba(255,255,255,0.04);color:#8b949e;font-size:11px;text-transform:uppercase;'>Plan</th>
            <th style='text-align:left;padding:8px 12px;background:rgba(255,255,255,0.04);color:#8b949e;font-size:11px;text-transform:uppercase;'>Días restantes</th>
          </tr></thead>
          <tbody>{$filas_pronto}</tbody>
        </table>" : '') . "
        " . ($total_vencidas ? "
        <h3 style='margin:0 0 12px;font-size:14px;color:#f87171;'>❌ Vencidas ({$total_vencidas})</h3>
        <table style='width:100%;border-collapse:collapse;font-size:13px;'>
          <thead><tr>
            <th style='text-align:left;padding:8px 12px;background:rgba(255,255,255,0.04);color:#8b949e;font-size:11px;text-transform:uppercase;'>Empresa</th>
            <th style='text-align:left;padding:8px 12px;background:rgba(255,255,255,0.04);color:#8b949e;font-size:11px;text-transform:uppercase;'>Plan</th>
            <th style='text-align:left;padding:8px 12px;background:rgba(255,255,255,0.04);color:#8b949e;font-size:11px;text-transform:uppercase;'>Venció</th>
          </tr></thead>
          <tbody>{$filas_vencidas}</tbody>
        </table>" : '') . "
      </div>
    </div>";

    if (!$dry_run) {
        send_email($sadmin_to, $sadmin_nombre, 'Reparo — Resumen vencimientos ' . date('d/m/Y'), $resumen_html);
    }
}

// ── Respuesta ────────────────────────────────────────────────────
if (php_sapi_name() === 'cli') {
    echo ($dry_run ? '[DRY RUN] ' : '') . "Procesadas: " . count($pronto) . " por vencer, " . count($vencidas) . " vencidas.\n";
    foreach ($log as $l) {
        $icon = $l['enviado'] ? '✓' : ($dry_run ? '~' : '✗');
        echo "  $icon [{$l['tipo']}] {$l['empresa']} → {$l['correo']}\n";
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'dry_run' => $dry_run, 'log' => $log,
        'pronto' => $total_pronto, 'vencidas' => $total_vencidas]);
}
