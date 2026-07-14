<?php
// Reset de contraseña — SOLO localhost. Eliminar después de usar.
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403); exit('Acceso denegado.');
}
require_once __DIR__ . '/includes/config.php';
$db = getDB();

// Listar usuarios
$usuarios = $db->query("SELECT u.id_usuario, u.user, u.nombre, u.cargo, e.nombre AS empresa, e.activa FROM usuarios u LEFT JOIN empresas e ON e.id_empresa = u.id_empresa ORDER BY u.id_empresa, u.cargo")->fetchAll();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $pass = $_POST['nueva_pass'] ?? '';
    if ($id && strlen($pass) >= 6) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $db->prepare("UPDATE usuarios SET pass = ? WHERE id_usuario = ?")->execute([$hash, $id]);
        $msg = "✔ Contraseña actualizada para usuario #$id.";
    } else {
        $msg = "✘ La contraseña debe tener al menos 6 caracteres.";
    }
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Reset contraseña — Centrotec</title>
<style>body{font-family:monospace;background:#0d1117;color:#e6edf3;padding:32px}
h2{color:#2f81f7}.card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:20px;max-width:560px;margin-bottom:16px}
table{border-collapse:collapse;width:100%}td,th{padding:8px 12px;border-bottom:1px solid #21262d;text-align:left}th{color:#8b949e;font-size:11px;text-transform:uppercase}
.activa{color:#3fb950}.inactiva{color:#f85149}
select,input[type=text],input[type=password]{width:100%;padding:8px;background:#0d1117;border:1px solid #30363d;color:#e6edf3;border-radius:6px;margin-top:4px}
.btn{padding:10px 20px;background:#2f81f7;color:#fff;border:none;border-radius:6px;cursor:pointer;margin-top:12px}
.ok{color:#3fb950;margin-bottom:12px}.err{color:#f85149;margin-bottom:12px}
label{display:block;margin-top:12px;color:#8b949e;font-size:12px}
</style></head><body>
<h2>Reset de contraseña — Centrotec</h2>
<?php if ($msg): ?>
  <p class="<?= str_starts_with($msg,'✔') ? 'ok' : 'err' ?>"><?= htmlspecialchars($msg) ?></p>
<?php endif; ?>

<div class="card">
  <h3 style="margin:0 0 12px;font-size:14px">Usuarios en el sistema</h3>
  <table>
    <thead><tr><th>#</th><th>Usuario</th><th>Nombre</th><th>Cargo</th><th>Empresa</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><?= $u['id_usuario'] ?></td>
        <td><?= htmlspecialchars($u['user']) ?></td>
        <td><?= htmlspecialchars($u['nombre']) ?></td>
        <td><?= htmlspecialchars($u['cargo']) ?></td>
        <td><?= htmlspecialchars($u['empresa'] ?? '—') ?></td>
        <td class="<?= $u['activa'] ? 'activa' : 'inactiva' ?>"><?= $u['activa'] ? 'Activa' : 'Suspendida' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3 style="margin:0 0 12px;font-size:14px">Cambiar contraseña</h3>
  <form method="POST">
    <label>Usuario</label>
    <select name="id">
      <?php foreach ($usuarios as $u): ?>
        <option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['user'].' — '.$u['nombre'].' ('.$u['cargo'].')') ?></option>
      <?php endforeach; ?>
    </select>
    <label>Nueva contraseña (mínimo 6 caracteres)</label>
    <input type="password" name="nueva_pass" placeholder="Nueva contraseña" required minlength="6">
    <button class="btn" type="submit">Actualizar contraseña</button>
  </form>
</div>

<p style="color:#f85149;margin-top:16px;font-size:13px">⚠ Elimina este archivo del servidor después de usarlo:<br>
<code>sudo rm /var/www/centrotec/reset_pass.php</code></p>
</body></html>
