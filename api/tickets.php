<?php
require_once __DIR__ . '/../includes/config.php';
guard();
csrf_check();

$db  = getDB();
$eid = eid();
$uid = uid();

// Auto-migrate
try {
    $db->exec("CREATE TABLE IF NOT EXISTS tickets (
        id_ticket      INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa     INT NOT NULL,
        id_usuario     INT NOT NULL,
        usuario_nombre VARCHAR(100) NOT NULL,
        asunto         VARCHAR(200) NOT NULL,
        mensaje        TEXT NOT NULL,
        estado         ENUM('Abierto','En revision','Resuelto') NOT NULL DEFAULT 'Abierto',
        respuesta      TEXT NULL,
        respondido_por VARCHAR(100) NULL,
        created_at     DATETIME NOT NULL DEFAULT NOW(),
        updated_at     DATETIME NULL,
        KEY idx_empresa (id_empresa),
        KEY idx_estado  (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'];

// GET — listar tickets de la empresa
if ($method === 'GET') {
    $st = $db->prepare(
        "SELECT id_ticket, id_usuario, usuario_nombre, asunto,
                LEFT(mensaje,120) AS mensaje_preview,
                estado, respuesta, created_at, updated_at
           FROM tickets
          WHERE id_empresa = ?
          ORDER BY created_at DESC"
    );
    $st->execute([$eid]);
    json_ok($st->fetchAll());
}

// POST — crear ticket
if ($method === 'POST') {
    $asunto  = trim($_POST['asunto']  ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    if (strlen($asunto) < 3 || strlen($asunto) > 200) json_err('Asunto inválido.');
    if (strlen($mensaje) < 10)                          json_err('Mensaje demasiado corto.');

    $st = $db->prepare(
        "INSERT INTO tickets (id_empresa, id_usuario, usuario_nombre, asunto, mensaje)
         VALUES (?, ?, ?, ?, ?)"
    );
    $st->execute([$eid, $uid, unombre(), $asunto, $mensaje]);
    $id = $db->lastInsertId();

    // Notificar a soporte
    if (SMTP_USER) {
        require_once __DIR__ . '/../includes/mailer.php';
        $empresa = $db->prepare("SELECT nombre FROM empresas WHERE id_empresa=? LIMIT 1");
        $empresa->execute([$eid]);
        $emp_nombre = $empresa->fetchColumn() ?: "Empresa #{$eid}";
        send_email(
            'soporte@centrotec.cl', 'Soporte Centrotec',
            "[Ticket #{$id}] {$asunto}",
            "<p><b>Empresa:</b> " . htmlspecialchars($emp_nombre) . "<br>
             <b>Usuario:</b> " . htmlspecialchars(unombre()) . "<br>
             <b>Asunto:</b> " . htmlspecialchars($asunto) . "</p>
             <p>" . nl2br(htmlspecialchars($mensaje)) . "</p>"
        );
    }

    json_ok(['id_ticket' => (int)$id, 'msg' => 'Ticket enviado correctamente.']);
}

json_err('Método no permitido.', 405);
