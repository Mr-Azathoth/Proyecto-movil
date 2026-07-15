<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_config.php';
require_once __DIR__ . '/../includes/sanitize_ticket.php';
sadmin_guard();
sadmin_csrf_check();

$db = getDB();

// Migración one-shot: solo corre una vez (flag en disco)
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

// GET — listar todos los tickets (opcionalmente filtrar por empresa o estado)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $where = ['1=1'];
    $params = [];
    if (!empty($_GET['id_empresa'])) {
        $where[] = 't.id_empresa = ?';
        $params[] = (int)$_GET['id_empresa'];
    }
    if (!empty($_GET['estado'])) {
        $where[] = 't.estado = ?';
        $params[] = $_GET['estado'];
    }
    $sql = "SELECT t.id_ticket, t.id_empresa, e.nombre AS empresa,
                   t.id_usuario, t.usuario_nombre, t.asunto, t.mensaje,
                   t.estado, t.respuesta, t.respondido_por,
                   t.created_at, t.updated_at
              FROM tickets t
              JOIN empresas e ON e.id_empresa = t.id_empresa
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.created_at DESC";
    $st = $db->prepare($sql);
    $st->execute($params);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $st->fetchAll()]);
    exit;
}

// POST — responder/actualizar estado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id_ticket']  ?? 0);
    $estado   = trim($_POST['estado']      ?? '');
    $respuesta = sanitize_ticket_html($_POST['respuesta'] ?? '');

    $estados_validos = ['Abierto', 'En revision', 'Resuelto'];
    if (!$id || !in_array($estado, $estados_validos, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Datos inválidos.']);
        exit;
    }

    // Actualizar estado (y respuesta si se proporcionó — nunca borrar respuesta existente)
    if ($respuesta !== '') {
        $st = $db->prepare(
            "UPDATE tickets SET estado = ?, respuesta = ?, respondido_por = ?, visto = 0, updated_at = NOW() WHERE id_ticket = ?"
        );
        $st->execute([$estado, $respuesta, sadmin_nombre(), $id]);
    } else {
        $st = $db->prepare(
            "UPDATE tickets SET estado = ?, updated_at = NOW() WHERE id_ticket = ?"
        );
        $st->execute([$estado, $id]);
    }

    // Notificar al cliente si el ticket fue resuelto
    if ($estado === 'Resuelto' && $respuesta && SMTP_USER) {
        try {
            $row = $db->prepare(
                "SELECT t.asunto, t.id_empresa, e.correo, t.usuario_nombre
                   FROM tickets t JOIN empresas e ON e.id_empresa = t.id_empresa
                  WHERE t.id_ticket = ? LIMIT 1"
            );
            $row->execute([$id]);
            $t = $row->fetch();
            if ($t && $t['correo']) {
                require_once __DIR__ . '/../includes/mailer.php';
                send_email(
                    $t['correo'], $t['usuario_nombre'],
                    "[Ticket #{$id} Resuelto] " . $t['asunto'],
                    "<p>Hola <b>" . htmlspecialchars($t['usuario_nombre']) . "</b>,</p>
                     <p>Tu solicitud de soporte <b>\"" . htmlspecialchars($t['asunto']) . "\"</b> ha sido resuelta.</p>
                     <p><b>Respuesta:</b><br>" . $respuesta . "</p>
                     <p>Saludos,<br>Equipo Centrotec</p>"
                );
            }
        } catch (Exception $e) {}
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => ['msg' => 'Ticket actualizado.']]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
