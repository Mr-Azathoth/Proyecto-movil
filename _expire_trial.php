<?php
require_once __DIR__.'/includes/config.php';

if (($_GET['token'] ?? '') !== 'exp2025') {
    http_response_code(403); die('Forbidden');
}

$db = getDB();

// Mostrar qué hay en la DB para debug
$rows = $db->query("SELECT e.id_empresa, e.nombre, e.plan_estado, e.plan_vencimiento, u.user
    FROM empresas e JOIN usuarios u ON u.id_empresa = e.id_empresa
    ORDER BY e.id_empresa DESC LIMIT 10")->fetchAll();
echo '<pre>'; print_r($rows); echo '</pre>';

$stmt = $db->prepare("
    UPDATE empresas e
    JOIN usuarios u ON u.id_empresa = e.id_empresa
    SET e.plan_vencimiento = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    WHERE u.user = ?
");
$stmt->execute(['demianlaprofecia']);

echo "Trial vencido. Filas afectadas: " . $stmt->rowCount();
