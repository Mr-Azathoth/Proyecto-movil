<?php
// Aplica a mano el esquema de sucursales y stock por sucursal (idempotente). Normalmente no hace falta:
// guard() lo aplica solo la primera vez. Uso: php cron/migrate_sucursales.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../includes/config.php';
$db = getDB();
echo "BD: " . $db->query("SELECT DATABASE()")->fetchColumn() . PHP_EOL;
foreach (schema_sucursales_migrar($db) as $linea) echo $linea . PHP_EOL;
echo "Esquema completo: " . (schema_sucursales_completo($db) ? 'si' : 'NO') . PHP_EOL;
