<?php
require_once __DIR__.'/includes/config.php';

if (($_GET['token'] ?? '') !== 'del2025') {
    http_response_code(403); die('Forbidden');
}

$db = getDB();

$stmt = $db->prepare("
    SELECT e.id_empresa, e.nombre
    FROM empresas e
    JOIN usuarios u ON u.id_empresa = e.id_empresa
    WHERE u.user = ? OR e.nombre = ?
    LIMIT 1
");
$stmt->execute(['demian_laprofecia@hotmail.com', 'Tecnico prueba pagos']);
$row = $stmt->fetch();

if (!$row) { die('No encontrada.'); }

$db->prepare("DELETE FROM empresas WHERE id_empresa = ?")->execute([$row['id_empresa']]);

echo "Eliminada: [{$row['id_empresa']}] {$row['nombre']}";
