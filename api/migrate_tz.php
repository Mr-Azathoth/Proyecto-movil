<?php
// Script de uso único — eliminar después de ejecutar
require_once __DIR__ . '/../includes/config.php';

requireLogin();
if (!isAdmin()) die('Sin permisos.');

$db     = getDB();
$action = $_GET['action'] ?? '';

// ── BACKUP ────────────────────────────────────────────────────
if ($action === 'backup') {
    $sql  = "-- Backup generado el " . date('Y-m-d H:i:s') . " (antes de migración UTC→Santiago)\n\n";

    $sql .= "-- TABLA: historial\n";
    foreach ($db->query("SELECT * FROM historial")->fetchAll() as $r) {
        $vals = implode(', ', array_map(
            fn($v) => $v === null ? 'NULL' : $db->quote((string)$v),
            array_values($r)
        ));
        $sql .= "INSERT INTO historial VALUES ($vals);\n";
    }

    $sql .= "\n-- TABLA: observaciones\n";
    foreach ($db->query("SELECT * FROM observaciones")->fetchAll() as $r) {
        $vals = implode(', ', array_map(
            fn($v) => $v === null ? 'NULL' : $db->quote((string)$v),
            array_values($r)
        ));
        $sql .= "INSERT INTO observaciones VALUES ($vals);\n";
    }

    $filename = 'backup_tz_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $sql;
    exit;
}

// ── MIGRACIÓN ─────────────────────────────────────────────────
if ($action === 'migrate') {
    $test = $db->query("SELECT CONVERT_TZ('2026-01-01 12:00:00', '+00:00', 'America/Santiago') AS t")
               ->fetchColumn();

    if ($test === null || $test === false) {
        die('<b style="color:red">ERROR:</b> CONVERT_TZ devuelve NULL — tablas de timezone no pobladas. Migración abortada, ningún dato fue modificado.');
    }

    $cutoff = '2026-07-22 23:59:59';

    $stH = $db->prepare("SELECT COUNT(*) FROM historial WHERE fecha_cambio < ?");
    $stH->execute([$cutoff]);
    $nHist = (int) $stH->fetchColumn();

    $stO = $db->prepare("SELECT COUNT(*) FROM observaciones WHERE fecha < ?");
    $stO->execute([$cutoff]);
    $nObs = (int) $stO->fetchColumn();

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE historial SET fecha_cambio = CONVERT_TZ(fecha_cambio, '+00:00', 'America/Santiago') WHERE fecha_cambio < ?")
           ->execute([$cutoff]);
        $db->prepare("UPDATE observaciones SET fecha = CONVERT_TZ(fecha, '+00:00', 'America/Santiago') WHERE fecha < ?")
           ->execute([$cutoff]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        die('<b style="color:red">ERROR (rollback aplicado):</b> ' . htmlspecialchars($e->getMessage()));
    }

    echo "<pre style='font-family:monospace;font-size:14px'>";
    echo "✔ CONVERT_TZ OK (test: $test)\n\n";
    echo "✔ Migración completada:\n";
    echo "   historial.fecha_cambio  → $nHist filas actualizadas\n";
    echo "   observaciones.fecha     → $nObs filas actualizadas\n\n";
    echo "Timestamps anteriores a $cutoff convertidos de UTC a America/Santiago.\n";
    echo "\n⚠ Elimina este archivo: api/migrate_tz.php\n";
    echo "</pre>";
    exit;
}

// ── PANTALLA INICIAL ──────────────────────────────────────────
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Migración TZ</title>
<style>
body{font-family:sans-serif;max-width:600px;margin:60px auto;padding:0 20px}
.btn{display:inline-block;padding:12px 24px;border-radius:6px;text-decoration:none;font-size:15px;font-weight:600;margin-right:12px}
.btn-blue{background:#2563eb;color:#fff}
.btn-green{background:#16a34a;color:#fff}
.warn{background:#fef9c3;border:1px solid #ca8a04;padding:16px;border-radius:6px;margin:20px 0}
</style>
</head>
<body>
<h2>Migración UTC → America/Santiago</h2>
<div class="warn">
  <b>Paso 1:</b> Descarga el backup. Si algo falla puedes restaurar con ese archivo.<br><br>
  <b>Paso 2:</b> Ejecuta la migración. Solo afecta registros anteriores al 22-07-2026.
</div>
<p>
  <a class="btn btn-blue" href="?action=backup">⬇ Descargar backup (.sql)</a>
  <a class="btn btn-green" href="?action=migrate"
     onclick="return confirm('¿Confirmas la migración? Asegúrate de haber descargado el backup primero.')">
    ▶ Ejecutar migración
  </a>
</p>
</body>
</html>
<?php
