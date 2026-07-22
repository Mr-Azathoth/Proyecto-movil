<?php
// Script de uso único — eliminar después de ejecutar
require_once __DIR__ . '/includes/config.php';

// Solo admin autenticado puede ejecutar esto
requireLogin();
if (!isAdmin()) die('Sin permisos.');

$db  = getDB();
$eid = eid(); // no se usa como filtro — aplica a todas las empresas

$action = $_GET['action'] ?? '';

// ── BACKUP ────────────────────────────────────────────────────
if ($action === 'backup') {
    $filename = 'backup_tz_' . date('Ymd_His') . '.sql';
    $path     = __DIR__ . '/assets/uploads/' . $filename;

    $sql  = "-- Backup generado el " . date('Y-m-d H:i:s') . " (antes de migración UTC→Santiago)\n\n";

    // historial
    $sql .= "-- TABLA: historial\n";
    $rows = $db->query("SELECT * FROM historial")->fetchAll();
    foreach ($rows as $r) {
        $vals = implode(', ', array_map(
            fn($v) => $v === null ? 'NULL' : $db->quote((string)$v),
            array_values($r)
        ));
        $sql .= "INSERT INTO historial VALUES ($vals);\n";
    }

    $sql .= "\n-- TABLA: observaciones\n";
    $rows = $db->query("SELECT * FROM observaciones")->fetchAll();
    foreach ($rows as $r) {
        $vals = implode(', ', array_map(
            fn($v) => $v === null ? 'NULL' : $db->quote((string)$v),
            array_values($r)
        ));
        $sql .= "INSERT INTO observaciones VALUES ($vals);\n";
    }

    file_put_contents($path, $sql);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    unlink($path); // borra el archivo temporal
    exit;
}

// ── MIGRACIÓN ─────────────────────────────────────────────────
if ($action === 'migrate') {
    // 1. Verificar que CONVERT_TZ funciona con nombre de zona
    $test = $db->query("SELECT CONVERT_TZ('2026-01-01 12:00:00', '+00:00', 'America/Santiago') AS t")
               ->fetchColumn();

    if ($test === null || $test === false) {
        die('<b style="color:red">ERROR:</b> CONVERT_TZ devuelve NULL — tablas de timezone no pobladas en este MySQL. La migración fue abortada. No se modificó ningún dato.');
    }

    // 2. Contar filas que se van a actualizar
    $cutoff = '2026-07-22 23:59:59';
    $nHist  = $db->prepare("SELECT COUNT(*) FROM historial WHERE fecha_cambio < ?")->execute([$cutoff]) ? 0 : 0;
    $stH    = $db->prepare("SELECT COUNT(*) FROM historial WHERE fecha_cambio < ?");
    $stH->execute([$cutoff]);
    $nHist  = (int) $stH->fetchColumn();

    $stO    = $db->prepare("SELECT COUNT(*) FROM observaciones WHERE fecha < ?");
    $stO->execute([$cutoff]);
    $nObs   = (int) $stO->fetchColumn();

    // 3. Ejecutar migración
    $db->beginTransaction();
    try {
        $db->prepare("UPDATE historial SET fecha_cambio = CONVERT_TZ(fecha_cambio, '+00:00', 'America/Santiago') WHERE fecha_cambio < ?")
           ->execute([$cutoff]);
        $db->prepare("UPDATE observaciones SET fecha = CONVERT_TZ(fecha, '+00:00', 'America/Santiago') WHERE fecha < ?")
           ->execute([$cutoff]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        die('<b style="color:red">ERROR durante la migración (rollback aplicado):</b> ' . htmlspecialchars($e->getMessage()));
    }

    echo "<pre style='font-family:monospace;font-size:14px'>";
    echo "✔ CONVERT_TZ funciona correctamente (test: $test)\n\n";
    echo "✔ Migración completada:\n";
    echo "   historial.fecha_cambio  → $nHist filas actualizadas\n";
    echo "   observaciones.fecha     → $nObs filas actualizadas\n\n";
    echo "Todos los timestamps anteriores a $cutoff fueron convertidos de UTC a America/Santiago.\n";
    echo "\n<b>Elimina este archivo del servidor: migrate_tz.php</b>\n";
    echo "</pre>";
    exit;
}

// ── PANTALLA INICIAL ──────────────────────────────────────────
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Migración TZ</title>
<style>body{font-family:sans-serif;max-width:600px;margin:60px auto;padding:0 20px}
.btn{display:inline-block;padding:12px 24px;border-radius:6px;text-decoration:none;font-size:15px;font-weight:600}
.btn-blue{background:#2563eb;color:#fff}.btn-green{background:#16a34a;color:#fff}
.warn{background:#fef9c3;border:1px solid #ca8a04;padding:16px;border-radius:6px;margin:20px 0}</style>
</head>
<body>
<h2>Migración UTC → America/Santiago</h2>
<div class="warn">
  <b>Paso 1:</b> Descarga el backup antes de migrar. Si algo sale mal, puedes restaurar manualmente.<br><br>
  <b>Paso 2:</b> Ejecuta la migración. Solo afecta registros anteriores al 22-07-2026.
</div>
<p><a class="btn btn-blue" href="?action=backup">Descargar backup (.sql)</a></p>
<p><a class="btn btn-green" href="?action=migrate" onclick="return confirm('¿Confirmas la migración? Asegúrate de haber descargado el backup primero.')">Ejecutar migración</a></p>
</body>
</html>
<?php
