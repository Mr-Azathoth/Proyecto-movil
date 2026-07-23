<?php
require_once __DIR__ . '/../includes/config.php';
guard();

$checks = [];
$allOk  = true;

function chk(string $name, callable $fn, array &$checks, bool &$allOk): void {
    try {
        $result   = $fn();
        $checks[] = ['name' => $name, 'ok' => true, 'msg' => $result ?: 'OK'];
    } catch (Throwable $e) {
        $checks[] = ['name' => $name, 'ok' => false, 'msg' => $e->getMessage()];
        $allOk    = false;
    }
}

$db = getDB();

chk('DB conexión', fn() => $db->query('SELECT 1')->fetch() ? null : throw new \Exception('Sin respuesta'), $checks, $allOk);

foreach (['reparaciones','historial','observaciones','inventario','reparacion_repuestos','empresas','usuarios'] as $t) {
    chk("Tabla · $t", function() use ($db, $t) {
        if (!$db->query("SHOW TABLES LIKE '$t'")->fetch()) throw new \Exception('No existe');
    }, $checks, $allOk);
}

foreach ([
    ['reparaciones','codigo_seguimiento'],
    ['reparaciones','deleted_at'],
    ['historial','detalle'],
    ['inventario','deleted_at'],
    ['reparacion_repuestos','stock_desc'],
] as [$tabla, $col]) {
    chk("Columna · $tabla.$col", function() use ($db, $tabla, $col) {
        if (!$db->query("SHOW COLUMNS FROM `$tabla` LIKE '$col'")->fetch()) throw new \Exception('Falta la columna');
    }, $checks, $allOk);
}

chk('Sesión activa', fn() => logueado() ? null : throw new \Exception('Sin sesión'), $checks, $allOk);

// Si el cliente pide JSON (ej. curl), retornar JSON
if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    header('Content-Type: application/json; charset=utf-8');
    json_ok(['checks' => $checks, 'all_ok' => $allOk, 'env' => APP_ENV]);
}

$ts    = date('d/m/Y H:i:s');
$total = count($checks);
$ok    = count(array_filter($checks, fn($c) => $c['ok']));
$fail  = $total - $ok;
$badge = $allOk ? '#22c55e' : '#f87171';
$label = $allOk ? 'SISTEMA OK' : "$fail FALLO" . ($fail > 1 ? 'S' : '');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Health Check — Centrotec</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#0d1117;color:#c9d1d9;font-family:ui-monospace,monospace;font-size:14px;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 16px}
  h1{font-size:18px;font-weight:700;color:#e6edf3;letter-spacing:.04em;margin-bottom:4px}
  .sub{font-size:12px;color:#6e7681;margin-bottom:28px}
  .badge{display:inline-block;padding:6px 18px;border-radius:6px;font-size:13px;font-weight:700;letter-spacing:.06em;color:#fff;margin-bottom:28px;background:<?= $badge ?>}
  table{width:100%;max-width:560px;border-collapse:collapse}
  tr{border-bottom:1px solid #21262d}
  td{padding:10px 12px;vertical-align:middle}
  td:first-child{color:#8b949e;font-size:12px;width:24px;text-align:center}
  td:nth-child(2){color:#e6edf3}
  td:last-child{color:#8b949e;font-size:12px;text-align:right}
  .ok-ic{color:#22c55e}
  .err-ic{color:#f87171}
  .err-name{color:#f87171}
  .err-msg{color:#f87171}
  .footer{margin-top:28px;font-size:11px;color:#484f58}
</style>
</head>
<body>
<h1>Health Check</h1>
<p class="sub"><?= $ts ?> &nbsp;·&nbsp; Entorno: <strong><?= htmlspecialchars(APP_ENV) ?></strong> &nbsp;·&nbsp; <?= $ok ?>/<?= $total ?> checks</p>
<span class="badge"><?= $label ?></span>
<table>
<?php foreach ($checks as $c): ?>
<tr>
  <td class="<?= $c['ok'] ? 'ok-ic' : 'err-ic' ?>"><?= $c['ok'] ? '✓' : '✗' ?></td>
  <td class="<?= $c['ok'] ? '' : 'err-name' ?>"><?= htmlspecialchars($c['name']) ?></td>
  <td class="<?= $c['ok'] ? '' : 'err-msg' ?>"><?= htmlspecialchars($c['msg']) ?></td>
</tr>
<?php endforeach ?>
</table>
<p class="footer">Solo visible para usuarios con sesión activa.</p>
</body>
</html>
