<?php
// Script de un solo uso — eliminar empresa por ID
// BORRAR este archivo inmediatamente después de usarlo
define('SECRET', 'ct_del_xK7m2pQ');
define('EMPRESA_TARGET', 6);

require_once __DIR__ . '/includes/config.php';

if (($_GET['token'] ?? '') !== SECRET) {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Mostrar confirmación
    $db = getDB();
    $emp = $db->prepare("SELECT nombre, correo, plan_tipo, creada_en FROM empresas WHERE id_empresa = ?");
    $emp->execute([EMPRESA_TARGET]);
    $row = $emp->fetch();
    if (!$row) exit('Empresa ' . EMPRESA_TARGET . ' no encontrada.');
    ?>
    <!doctype html><html lang="es"><head><meta charset="utf-8">
    <title>Eliminar empresa</title>
    <style>body{font-family:sans-serif;max-width:500px;margin:60px auto;padding:20px}
    .warn{background:#fff3cd;border:1px solid #ffc107;padding:16px;border-radius:8px;margin:20px 0}
    button{background:#dc3545;color:#fff;border:none;padding:12px 28px;font-size:16px;border-radius:6px;cursor:pointer}
    dt{font-weight:bold;margin-top:8px}dd{margin:0 0 4px 16px}</style>
    </head><body>
    <h2>⚠️ Eliminar empresa #<?= EMPRESA_TARGET ?></h2>
    <div class="warn">
    <dl>
    <dt>Nombre</dt><dd><?= htmlspecialchars($row['nombre']) ?></dd>
    <dt>Correo</dt><dd><?= htmlspecialchars($row['correo']) ?></dd>
    <dt>Plan</dt><dd><?= htmlspecialchars($row['plan_tipo']) ?></dd>
    <dt>Creada</dt><dd><?= $row['creada_en'] ?></dd>
    </dl>
    <p><strong>Se eliminará la empresa y todos sus datos (usuarios, reparaciones, historial, logs).</strong></p>
    </div>
    <form method="post">
    <button type="submit" onclick="return confirm('¿Seguro? Esta acción no se puede deshacer.')">
        Eliminar empresa #<?= EMPRESA_TARGET ?> y todos sus datos
    </button>
    </form>
    </body></html>
    <?php
    exit;
}

// POST — ejecutar el borrado
$db = getDB();
$db->beginTransaction();
try {
    $db->prepare("DELETE FROM log_acciones WHERE id_empresa = ?")->execute([EMPRESA_TARGET]);
    $rows = $db->prepare("DELETE FROM empresas WHERE id_empresa = ?");
    $rows->execute([EMPRESA_TARGET]);
    $deleted = $rows->rowCount();
    $db->commit();
    echo '<h2 style="font-family:sans-serif;color:green">✓ Listo</h2>';
    echo '<p style="font-family:sans-serif">Empresa ' . EMPRESA_TARGET . ' eliminada (' . $deleted . ' fila). Todos sus datos fueron borrados en cascada.</p>';
    echo '<p style="font-family:sans-serif;color:red"><strong>Borra este archivo del servidor ahora.</strong></p>';
} catch (Throwable $e) {
    $db->rollBack();
    echo '<h2 style="color:red">Error</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
