<?php
require_once __DIR__.'/includes/config.php';
$db = getDB();
$affected = $db->exec("UPDATE historial_pagos SET estado='Pagado' WHERE descripcion LIKE '%1 mes%Mercado Pago%' AND estado='Pendiente'");
echo "Filas actualizadas: " . $affected;
