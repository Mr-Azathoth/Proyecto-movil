<?php
// Script de limpieza de UN SOLO USO — eliminar reparación de prueba
// Solo accesible desde localhost. Eliminar después de ejecutar.
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403); exit('Acceso denegado.');
}

require_once __DIR__ . '/includes/config.php';
$db = getDB();

$id = (int)($_POST['id'] ?? 0);

// Sin POST: mostrar formulario de confirmación con datos del registro
if (!$id || !isset($_POST['confirmar'])) {
    $preview_id = (int)($_GET['id'] ?? 11);
    $st = $db->prepare("SELECT id_ingreso, nombre_cliente, marca_ingreso, modelo_ingreso, status, created_at FROM reparaciones WHERE id_ingreso = ? LIMIT 1");
    $st->execute([$preview_id]);
    $rep = $st->fetch();

    $hist_count = 0; $obs_count = 0;
    if ($rep) {
        $hist_count = (int)$db->query("SELECT COUNT(*) FROM historial WHERE id_reparacion = $preview_id")->fetchColumn();
        $obs_count  = (int)$db->query("SELECT COUNT(*) FROM observaciones WHERE id_registro = $preview_id")->fetchColumn();
    }
    ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Eliminar reparación de prueba</title>
<style>body{font-family:monospace;background:#0d1117;color:#e6edf3;padding:32px}
h2{color:#f85149}.card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:20px;max-width:500px}
table{border-collapse:collapse;width:100%}td{padding:6px 12px;border-bottom:1px solid #21262d}
td:first-child{color:#8b949e;width:160px}.btn{padding:12px 24px;border:none;border-radius:6px;font-size:14px;cursor:pointer;margin-top:20px;margin-right:8px}
.btn-del{background:#f85149;color:#fff}.btn-cancel{background:#21262d;color:#e6edf3}
.warn{background:#3d1f00;border:1px solid #f0883e;border-radius:6px;padding:12px;margin-top:16px;color:#f0883e}
</style></head><body>
<h2>Eliminar reparación de prueba</h2>
<?php if (!$rep): ?>
  <p style="color:#f85149">No existe reparación con ID <?= $preview_id ?>.</p>
<?php else: ?>
  <div class="card">
    <table>
      <tr><td>ID</td><td>#<?= $rep['id_ingreso'] ?></td></tr>
      <tr><td>Cliente</td><td><?= htmlspecialchars($rep['nombre_cliente']) ?></td></tr>
      <tr><td>Equipo</td><td><?= htmlspecialchars($rep['marca_ingreso'].' '.$rep['modelo_ingreso']) ?></td></tr>
      <tr><td>Estado</td><td><?= htmlspecialchars($rep['status']) ?></td></tr>
      <tr><td>Creado</td><td><?= $rep['created_at'] ?></td></tr>
      <tr><td>Historial</td><td><?= $hist_count ?> registros (CASCADE)</td></tr>
      <tr><td>Observaciones</td><td><?= $obs_count ?> registros (CASCADE)</td></tr>
    </table>
    <div class="warn">Esta acción es irreversible. Se eliminará la reparación y sus registros asociados.</div>
    <form method="POST">
      <input type="hidden" name="id" value="<?= $rep['id_ingreso'] ?>">
      <input type="hidden" name="confirmar" value="1">
      <button class="btn btn-del" type="submit">Eliminar definitivamente</button>
      <a href="/" class="btn btn-cancel" style="text-decoration:none">Cancelar</a>
    </form>
  </div>
<?php endif; ?>
</body></html>
<?php
    exit;
}

// POST confirmado: eliminar
$st = $db->prepare("DELETE FROM reparaciones WHERE id_ingreso = ?");
$st->execute([$id]);
$deleted = $st->rowCount();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Eliminado</title>
<style>body{font-family:monospace;background:#0d1117;color:#e6edf3;padding:32px}
.ok{color:#3fb950}.btn{display:inline-block;margin-top:20px;padding:12px 24px;background:#238636;color:#fff;text-decoration:none;border-radius:6px}</style>
</head><body>
<?php if ($deleted): ?>
  <p class="ok">Reparación #<?= $id ?> eliminada (+ registros en cascade).</p>
  <p style="color:#8b949e">Recuerda eliminar este archivo del servidor: <code>delete_test_rep.php</code></p>
<?php else: ?>
  <p style="color:#f85149">No se encontró la reparación #<?= $id ?> o ya fue eliminada.</p>
<?php endif; ?>
<a class="btn" href="/app.php">Volver a la app</a>
</body></html>
