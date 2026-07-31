<?php
require_once __DIR__.'/includes/config.php';

if (($_GET['token'] ?? '') !== 'exp2025') {
    http_response_code(403); die('Forbidden');
}

$db = getDB();

$stmt = $db->prepare("
    UPDATE empresas e
    JOIN usuarios u ON u.id_empresa = e.id_empresa
    SET e.plan_vencimiento = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    WHERE u.user = ?
");
$stmt->execute(['demian_laprofecia@hotmail.com']);

echo "Trial vencido. Filas afectadas: " . $stmt->rowCount();
