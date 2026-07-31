<?php
require_once __DIR__.'/includes/config.php';

if (($_GET['token'] ?? '') !== 'exp2025') {
    http_response_code(403); die('Forbidden');
}

$db = getDB();

$stmt = $db->prepare("
    UPDATE empresas
    SET plan_vencimiento = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    WHERE nombre = ?
");
$stmt->execute(['Tecnico prueba pagos']);

echo "Trial vencido. Filas afectadas: " . $stmt->rowCount();
