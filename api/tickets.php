<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/sanitize_ticket.php';
guard();

define('TICKET_GRACE_HOURS', 72);

$db     = getDB();
$eid    = eid();
$uid    = uid();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') csrf_check();

// Auto-migrate tickets
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
        visto          TINYINT NOT NULL DEFAULT 0,
        created_at     DATETIME NOT NULL DEFAULT NOW(),
        updated_at     DATETIME NULL,
        KEY idx_empresa (id_empresa),
        KEY idx_estado  (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

// Auto-migrate ticket_mensajes
try {
    $db->exec("CREATE TABLE IF NOT EXISTS ticket_mensajes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        id_ticket  INT NOT NULL,
        tipo       ENUM('cliente','admin') NOT NULL,
        autor      VARCHAR(100) NOT NULL,
        mensaje    TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT NOW(),
        KEY idx_ticket (id_ticket)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

// Migración one-shot: columna visto
$_mig_flag = __DIR__ . '/../.migration_visto_done';
if (!file_exists($_mig_flag)) {
    try {
        $col = $db->query("SHOW COLUMNS FROM tickets LIKE 'visto'");
        if ($col->rowCount() === 0) {
            $db->exec("ALTER TABLE tickets ADD COLUMN visto TINYINT NOT NULL DEFAULT 0");
        }
        @file_put_contents($_mig_flag, '1');
    } catch (PDOException $e) {}
}

// GET — listar tickets de la empresa (incluye hilo de mensajes)
if ($method === 'GET') {
    $st = $db->prepare(
        "SELECT id_ticket, id_usuario, usuario_nombre, asunto, mensaje,
                estado, respuesta, visto, created_at, updated_at
           FROM tickets
          WHERE id_empresa = ?
          ORDER BY created_at DESC"
    );
    $st->execute([$eid]);
    $tickets = $st->fetchAll();

    if ($tickets) {
        $ids = array_column($tickets, 'id_ticket');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $msj = $db->prepare(
            "SELECT id_ticket, id, tipo, autor, mensaje, created_at
               FROM ticket_mensajes
              WHERE id_ticket IN ($in)
              ORDER BY created_at ASC"
        );
        $msj->execute($ids);
        $byTicket = [];
        foreach ($msj->fetchAll() as $m) {
            $byTicket[$m['id_ticket']][] = $m;
        }
        foreach ($tickets as &$t) {
            $t['mensajes'] = $byTicket[$t['id_ticket']] ?? [];
        }
        unset($t);
    }

    json_ok($tickets);
}

if ($method === 'POST') {
    $action = trim($_POST['action'] ?? 'nuevo');

    // Marcar ticket como visto
    if ($action === 'marcar_visto') {
        $id = (int)($_POST['id_ticket'] ?? 0);
        if (!$id) json_err('ID inválido.');
        $db->prepare("UPDATE tickets SET visto=1 WHERE id_ticket=? AND id_empresa=?")
           ->execute([$id, $eid]);
        json_ok([]);
    }

    // Responder a ticket existente
    if ($action === 'reply') {
        $id      = (int)($_POST['id_ticket'] ?? 0);
        $mensaje = sanitize_ticket_html($_POST['mensaje'] ?? '');

        if (!$id) json_err('ID de ticket inválido.');
        if (mb_strlen(strip_tags($mensaje)) < 2) json_err('El mensaje es demasiado corto.');

        // Verificar que el ticket pertenece a la empresa
        $st = $db->prepare(
            "SELECT id_ticket, estado, updated_at FROM tickets WHERE id_ticket=? AND id_empresa=?"
        );
        $st->execute([$id, $eid]);
        $ticket = $st->fetch();
        if (!$ticket) json_err('Ticket no encontrado.');

        if ($ticket['estado'] === 'Resuelto') {
            $resolvedAt = strtotime($ticket['updated_at'] ?: $ticket['created_at']);
            $graceEnd   = $resolvedAt + TICKET_GRACE_HOURS * 3600;
            if (time() > $graceEnd) {
                json_err('El plazo de ' . TICKET_GRACE_HOURS . ' horas para responder este ticket ha vencido. Si necesitas más ayuda, abre un nuevo ticket.');
            }
            // Reabrir ticket
            $db->prepare(
                "UPDATE tickets SET estado='Abierto', visto=0, updated_at=NOW() WHERE id_ticket=?"
            )->execute([$id]);
        } else {
            // Marcar como no leído para el admin
            $db->prepare(
                "UPDATE tickets SET visto=0, updated_at=NOW() WHERE id_ticket=?"
            )->execute([$id]);
        }

        // Insertar mensaje en el hilo
        $db->prepare(
            "INSERT INTO ticket_mensajes (id_ticket, tipo, autor, mensaje) VALUES (?,?,?,?)"
        )->execute([$id, 'cliente', unombre(), $mensaje]);

        // Notificar al admin por correo
        if (SMTP_USER) {
            try {
                $row = $db->prepare(
                    "SELECT t.asunto, e.nombre AS empresa
                       FROM tickets t JOIN empresas e ON e.id_empresa = t.id_empresa
                      WHERE t.id_ticket = ? LIMIT 1"
                );
                $row->execute([$id]);
                $tk = $row->fetch();
                require_once __DIR__ . '/../includes/mailer.php';
                send_email(
                    'soporte@centrotec.cl', 'Soporte Centrotec',
                    "[Ticket #{$id} — Respuesta cliente] " . ($tk['asunto'] ?? ''),
                    "<p><b>Empresa:</b> " . htmlspecialchars($tk['empresa'] ?? '') . "<br>
                     <b>Usuario:</b> " . htmlspecialchars(unombre()) . "</p>
                     <p>" . $mensaje . "</p>"
                );
            } catch (Exception $e) {}
        }

        json_ok(['msg' => 'Respuesta enviada.']);
    }

    // Crear nuevo ticket
    $asunto  = trim($_POST['asunto']  ?? '');
    $mensaje = sanitize_ticket_html($_POST['mensaje'] ?? '');

    if (strlen($asunto) < 3 || strlen($asunto) > 200) json_err('Asunto inválido.');
    if (mb_strlen(strip_tags($mensaje)) < 10)          json_err('Mensaje demasiado corto.');

    $st = $db->prepare(
        "INSERT INTO tickets (id_empresa, id_usuario, usuario_nombre, asunto, mensaje)
         VALUES (?, ?, ?, ?, ?)"
    );
    $st->execute([$eid, $uid, unombre(), $asunto, $mensaje]);
    $id = $db->lastInsertId();

    if (SMTP_USER) {
        try {
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
                 <p>" . $mensaje . "</p>"
            );
        } catch (Exception $e) {}
    }

    json_ok(['id_ticket' => (int)$id, 'msg' => 'Ticket enviado correctamente.']);
}

json_err('Método no permitido.', 405);
