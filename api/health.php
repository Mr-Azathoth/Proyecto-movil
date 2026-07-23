<?php
/**
 * Health-check del sistema.
 * Llama a este endpoint después de cada deploy para confirmar que todo está en orden.
 * Solo accesible para usuarios con sesión activa (admin o técnico).
 *
 * GET /api/health.php
 * Retorna: { ok: true, checks: [{name, ok, msg}], ts: "..." }
 */
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$checks = [];
$allOk  = true;

function chk(string $name, callable $fn, array &$checks, bool &$allOk): void {
    try {
        $result = $fn();
        $checks[] = ['name' => $name, 'ok' => true,  'msg' => $result ?: 'OK'];
    } catch (Throwable $e) {
        $checks[]  = ['name' => $name, 'ok' => false, 'msg' => $e->getMessage()];
        $allOk = false;
    }
}

$db = getDB();

// ── Base de datos ────────────────────────────────────────────
chk('DB conexión', fn() => $db->query('SELECT 1')->fetch() ? null : throw new \Exception('Sin respuesta'), $checks, $allOk);

// ── Tablas críticas ──────────────────────────────────────────
$tablas = ['reparaciones', 'historial', 'observaciones', 'inventario',
           'reparacion_repuestos', 'empresas', 'usuarios'];
foreach ($tablas as $t) {
    chk("Tabla: $t", function() use ($db, $t) {
        $r = $db->query("SHOW TABLES LIKE '$t'")->fetch();
        if (!$r) throw new \Exception("No existe");
        return null;
    }, $checks, $allOk);
}

// ── Columnas agregadas recientemente ────────────────────────
$colChecks = [
    ['reparaciones', 'codigo_seguimiento'],
    ['reparaciones', 'deleted_at'],
    ['historial',    'detalle'],
    ['inventario',   'deleted_at'],
    ['reparacion_repuestos', 'stock_desc'],
];
foreach ($colChecks as [$tabla, $col]) {
    chk("Columna: $tabla.$col", function() use ($db, $tabla, $col) {
        $r = $db->query("SHOW COLUMNS FROM `$tabla` LIKE '$col'")->fetch();
        if (!$r) throw new \Exception("Falta la columna");
        return null;
    }, $checks, $allOk);
}

// ── Sesión y autenticación ───────────────────────────────────
chk('Sesión activa', fn() => logueado() ? null : throw new \Exception('Sin sesión'), $checks, $allOk);

json_ok(['checks' => $checks, 'all_ok' => $allOk, 'env' => APP_ENV]);
