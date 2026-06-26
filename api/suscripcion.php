<?php
require_once __DIR__.'/../includes/config.php';
guard();

$db  = getDB();
$eid = eid();

// Migraciones silenciosas
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS plan_tipo VARCHAR(50) NOT NULL DEFAULT 'Básico'"); }        catch(PDOException $e) {}
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS plan_estado VARCHAR(20) NOT NULL DEFAULT 'Activo'"); }      catch(PDOException $e) {}
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS plan_vencimiento DATE NULL"); }                              catch(PDOException $e) {}
try { $db->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS notif_vencimiento TINYINT(1) NOT NULL DEFAULT 1"); }        catch(PDOException $e) {}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS historial_pagos (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa    INT NOT NULL,
        fecha         DATE NOT NULL,
        monto         DECIMAL(10,2) NOT NULL DEFAULT 0,
        descripcion   VARCHAR(200) NOT NULL DEFAULT '',
        estado        VARCHAR(20) NOT NULL DEFAULT 'Pagado',
        INDEX(id_empresa, fecha)
    )");
} catch(PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $row = $db->prepare("SELECT plan_tipo, plan_estado, plan_vencimiento, notif_vencimiento FROM empresas WHERE id = ?");
    $row->execute([$eid]);
    $data = $row->fetch();

    $dias = null;
    if (!empty($data['plan_vencimiento'])) {
        $dias = (int) ceil((strtotime($data['plan_vencimiento']) - time()) / 86400);
    }

    $pagos = $db->prepare(
        "SELECT fecha, monto, descripcion, estado FROM historial_pagos
         WHERE id_empresa = ? ORDER BY fecha DESC LIMIT 24"
    );
    $pagos->execute([$eid]);

    json_ok([
        'plan_tipo'         => $data['plan_tipo']  ?? 'Básico',
        'plan_estado'       => $data['plan_estado'] ?? 'Activo',
        'plan_vencimiento'  => $data['plan_vencimiento'] ?? null,
        'notif_vencimiento' => (bool)($data['notif_vencimiento'] ?? true),
        'dias_restantes'    => $dias,
        'historial'         => $pagos->fetchAll(),
    ]);
}

if ($method === 'PUT') {
    if (!isAdmin()) json_err('Solo administradores', 403);
    csrf_check();

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (array_key_exists('notif_vencimiento', $input)) {
        $db->prepare("UPDATE empresas SET notif_vencimiento = ? WHERE id = ?")
           ->execute([$input['notif_vencimiento'] ? 1 : 0, $eid]);
        json_ok([]);
    }

    json_err('Parámetros inválidos');
}

json_err('Método no permitido', 405);
