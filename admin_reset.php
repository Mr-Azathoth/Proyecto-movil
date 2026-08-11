<?php
define('SECRET', 'ct_rst_xK7m2pQ');
require_once __DIR__ . '/includes/config.php';

if (($_GET['token'] ?? '') !== SECRET) { http_response_code(403); exit('Forbidden'); }

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eid = (int)($_POST['eid'] ?? 0);
    if ($eid) {
        $db->prepare("DELETE FROM log_acciones WHERE id_empresa = ?")->execute([$eid]);
        $db->prepare("DELETE FROM empresas WHERE id_empresa = ?")->execute([$eid]);
        echo '<p style="font-family:sans-serif;color:green">✓ Empresa #'.$eid.' eliminada. <a href="?token='.SECRET.'">Volver</a></p>';
        exit;
    }
}

$empresas = $db->query("SELECT id_empresa, nombre, correo, plan_tipo, plan_estado, creada_en FROM empresas ORDER BY id_empresa DESC LIMIT 10")->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Reset empresa</title>
<style>body{font-family:sans-serif;max-width:650px;margin:40px auto;padding:20px}
table{width:100%;border-collapse:collapse}td,th{padding:8px 10px;border:1px solid #ccc;font-size:13px}
th{background:#f0f0f0}button{background:#c0392b;color:#fff;border:none;padding:6px 14px;border-radius:4px;cursor:pointer}</style>
</head><body>
<h2>Eliminar empresa de prueba</h2>
<table><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Plan</th><th>Estado</th><th>Creada</th><th></th></tr>
<?php foreach ($empresas as $e): ?>
<tr>
  <td><?= $e['id_empresa'] ?></td>
  <td><?= htmlspecialchars($e['nombre']) ?></td>
  <td><?= htmlspecialchars($e['correo']) ?></td>
  <td><?= htmlspecialchars($e['plan_tipo']) ?></td>
  <td><?= htmlspecialchars($e['plan_estado']) ?></td>
  <td><?= $e['creada_en'] ?></td>
  <td>
    <form method="post">
      <input type="hidden" name="eid" value="<?= $e['id_empresa'] ?>">
      <button onclick="return confirm('¿Eliminar empresa #<?= $e['id_empresa'] ?>?')">Eliminar</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
