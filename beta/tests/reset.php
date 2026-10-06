<?php
// Borra las empresas de prueba (testsuca / testsucb) y todo lo suyo. SOLO consola y SOLO contra centrotec_beta.
// Uso: php tests/reset.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/config.php';

$db = getDB();
if ($db->query("SELECT DATABASE()")->fetchColumn() !== 'centrotec_beta') exit("Abortado: esta herramienta solo corre contra centrotec_beta.\n");

$ids = $db->query("SELECT id_empresa FROM empresas WHERE subdominio IN ('testsuca','testsucb')")->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $id) {
    $id = (int) $id;
    // Mismo orden que api/admin_borrar_empresa.php (tablas sin FK en cascada primero)
    $db->prepare("DELETE tm FROM ticket_mensajes tm INNER JOIN tickets t ON t.id_ticket = tm.id_ticket WHERE t.id_empresa = ?")->execute([$id]);
    foreach (['tickets', 'historial_pagos', 'inventario', 'log_acciones'] as $t) {
        $db->prepare("DELETE FROM $t WHERE id_empresa = ?")->execute([$id]);
    }
    $db->prepare("DELETE FROM empresas WHERE id_empresa = ?")->execute([$id]);
}
@unlink(__DIR__ . '/.creds.json');
echo "Empresas de prueba borradas: " . count($ids) . "\n";
