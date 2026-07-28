<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

// Migración silenciosa: añadir columnas si no existen
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN id_repuesto_usado INT NULL"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE inventario ADD COLUMN cantidad_reservada INT NOT NULL DEFAULT 0"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN stock_descontado TINYINT(1) NOT NULL DEFAULT 0"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN codigo_seguimiento VARCHAR(6) NULL"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD UNIQUE KEY uq_codigo_seguimiento (codigo_seguimiento)"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE historial ADD COLUMN detalle TEXT NULL DEFAULT NULL"); } catch(PDOException $e) {}

// Backfill: sincronizar cantidad_reservada para trabajos activos creados antes del sistema de reservas.
// Calcula el total correcto (inicial + adicionales) y hace SET solo donde cantidad_reservada = 0.
// Idempotente: tras la primera corrida los repuestos afectados quedan con valor > 0 y el INNER JOIN
// no los vuelve a tocar (WHERE cantidad_reservada = 0 no aplica).
try {
    $db->exec("
        UPDATE inventario i
        INNER JOIN (
            SELECT id_repuesto, id_empresa, SUM(total) AS cnt
            FROM (
                SELECT id_repuesto_usado AS id_repuesto, id_empresa, COUNT(*) AS total
                FROM reparaciones
                WHERE id_repuesto_usado IS NOT NULL
                  AND stock_descontado = 0
                  AND deleted_at IS NULL
                  AND status NOT IN ('Reparado','Entregado')
                GROUP BY id_repuesto_usado, id_empresa
                UNION ALL
                SELECT rr.id_repuesto, rr.id_empresa, SUM(rr.cantidad)
                FROM reparacion_repuestos rr
                JOIN reparaciones r ON r.id_ingreso = rr.id_reparacion
                WHERE rr.stock_desc = 0
                  AND r.deleted_at IS NULL
                  AND r.status NOT IN ('Reparado','Entregado')
                GROUP BY rr.id_repuesto, rr.id_empresa
            ) combined
            GROUP BY id_repuesto, id_empresa
        ) x ON i.id_repuesto = x.id_repuesto AND i.id_empresa = x.id_empresa
        SET i.cantidad_reservada = LEAST(i.cantidad, x.cnt)
        WHERE i.cantidad_reservada = 0
    ");
} catch(PDOException $e) {}

function generar_codigo_seguimiento(PDO $db): string {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXY3456789';
    $len   = strlen($chars);
    for ($try = 0; $try < 30; $try++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, $len - 1)];
        $st = $db->prepare("SELECT 1 FROM reparaciones WHERE codigo_seguimiento = ?");
        $st->execute([$code]);
        if (!$st->fetch()) return $code;
    }
    return strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

if ($method === 'GET') {
    $q  = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
    $st = trim($_GET['status'] ?? '');

    // Validar status si viene filtro
    if ($st && !in_array($st, VALID_STATUS, true)) {
        json_err('Estado inválido.');
    }

    $sql = "SELECT r.*, i.nombre AS nombre_repuesto_usado
              FROM reparaciones r
              LEFT JOIN inventario i
                     ON i.id_repuesto = r.id_repuesto_usado AND i.id_empresa = r.id_empresa
             WHERE r.id_empresa = ? AND r.deleted_at IS NULL";
    $p   = [$eid];

    if ($q) {
        $sql .= " AND (r.nombre_cliente LIKE ? OR r.marca_ingreso LIKE ? OR r.modelo_ingreso LIKE ? OR r.id_ingreso = ?)";
        $like = "%" . $q . "%";
        $p    = array_merge($p, [$like, $like, $like, (int) $q]);
    }
    if ($st) {
        $sql .= " AND r.status = ?";
        $p[] = $st;
    }
    $sql .= " ORDER BY r.id_ingreso DESC";

    $s = $db->prepare($sql);
    $s->execute($p);
    json_ok($s->fetchAll());
}

if ($method === 'POST') {
    csrf_check();

    $f = [
        'nombre_cliente'   => trim($_POST['nombre_cliente']   ?? ''),
        'telefono_cliente' => trim($_POST['telefono_cliente'] ?? ''),
        'rut_cliente'      => trim($_POST['rut_cliente']      ?? ''),
        'tipo_ingreso'     => trim($_POST['tipo_ingreso']     ?? 'Telefono'),
        'marca_ingreso'    => trim($_POST['marca_ingreso']    ?? ''),
        'modelo_ingreso'   => trim($_POST['modelo_ingreso']   ?? ''),
        'imei'             => trim($_POST['imei']             ?? ''),
        'pass_ingreso'     => trim($_POST['pass_ingreso']     ?? 'Sin contraseña'),
        'dano_ingreso'     => trim($_POST['dano_ingreso']     ?? ''),
        'valor_ingreso'    => max(0, (int) ($_POST['valor_ingreso'] ?? 0)),
        'status'           => trim($_POST['status']           ?? 'Ingresado'),
        'obs'              => trim($_POST['obs']              ?? ''),
    ];

    // Validaciones
    if (!$f['nombre_cliente'])                            json_err('El nombre del cliente es obligatorio.');
    if (strlen($f['nombre_cliente']) > 120)               json_err('Nombre demasiado largo.');
    if (!$f['telefono_cliente'])                          json_err('El teléfono del cliente es obligatorio.');
    if (strlen($f['telefono_cliente']) > 30)              json_err('Teléfono demasiado largo.');
    if (strlen($f['rut_cliente'])      > 15)              json_err('RUT demasiado largo.');
    if (strlen($f['imei'])             > 20)              json_err('IMEI demasiado largo.');
    if (!$f['dano_ingreso'])                              json_err('La descripción de la falla es obligatoria.');
    if (strlen($f['dano_ingreso'])     > 2000)            json_err('Descripción de falla demasiado larga (máx. 2000 caracteres).');
    if (strlen($f['obs'])              > 2000)            json_err('Observación demasiado larga (máx. 2000 caracteres).');
    if (!in_array($f['status'], VALID_STATUS, true))      json_err('Estado inicial inválido.');

    $tipos_validos = ['Telefono', 'Tablet', 'Notebook', 'Televisor', 'Otro'];
    if (!in_array($f['tipo_ingreso'], $tipos_validos, true)) $f['tipo_ingreso'] = 'Otro';

    // Repuesto inicial opcional
    $id_repuesto_inicial = null;
    if (!empty($_POST['id_repuesto_usado'])) {
        $id_rp = (int) $_POST['id_repuesto_usado'];
        $chkRp = $db->prepare(
            "SELECT id_repuesto, cantidad, cantidad_reservada
               FROM inventario WHERE id_repuesto = ? AND id_empresa = ? AND deleted_at IS NULL"
        );
        $chkRp->execute([$id_rp, $eid]);
        $inv_row = $chkRp->fetch();
        if ($inv_row) {
            $disp = (int)$inv_row['cantidad'] - (int)$inv_row['cantidad_reservada'];
            if ($disp <= 0) json_err('Sin stock disponible — el repuesto está reservado para otro trabajo.');
            $id_repuesto_inicial = $id_rp;
        }
    }

    $codigo = generar_codigo_seguimiento($db);

    $db->prepare("INSERT INTO reparaciones
        (id_empresa, nombre_cliente, telefono_cliente, rut_cliente, tipo_ingreso,
         marca_ingreso, modelo_ingreso, imei, pass_ingreso, dano_ingreso,
         valor_ingreso, status, obs, ingresado_por, id_repuesto_usado, codigo_seguimiento)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([
            $eid, $f['nombre_cliente'], $f['telefono_cliente'], $f['rut_cliente'],
            $f['tipo_ingreso'], $f['marca_ingreso'], $f['modelo_ingreso'], $f['imei'],
            $f['pass_ingreso'], $f['dano_ingreso'], $f['valor_ingreso'],
            $f['status'], $f['obs'], uname(), $id_repuesto_inicial, $codigo,
        ]);
    $newId = (int) $db->lastInsertId();
    if ($id_repuesto_inicial) {
        $db->prepare("UPDATE inventario SET cantidad_reservada = cantidad_reservada + 1
                       WHERE id_repuesto = ? AND id_empresa = ? AND (cantidad - cantidad_reservada) > 0")
           ->execute([$id_repuesto_inicial, $eid]);
    }
    log_accion($db, 'nueva_reparacion', $newId);

    $db->prepare("INSERT INTO historial (id_empresa, id_reparacion, status_anterior, status_cambio, user)
                  VALUES (?, ?, '', ?, ?)")
       ->execute([$eid, $newId, $f['status'], uname()]);

    if ($f['obs']) {
        $db->prepare("INSERT INTO observaciones (id_empresa, id_registro, obs, user)
                      VALUES (?, ?, ?, ?)")
           ->execute([$eid, $newId, $f['obs'], uname()]);
    }

    // Recuperar el código generado para mostrarlo al frontend
    $sc = $db->prepare("SELECT codigo_seguimiento FROM reparaciones WHERE id_ingreso = ?");
    $sc->execute([$newId]);
    $codigo = $sc->fetchColumn() ?? '';

    json_ok(['id' => $newId, 'codigo_seguimiento' => $codigo, 'msg' => "Servicio #{$newId} registrado."]);
}

if ($method === 'PUT') {
    csrf_check();

    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($in['id'] ?? 0);
    if (!$id) json_err('ID inválido.');

    $cur = $db->prepare("SELECT * FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ?");
    $cur->execute([$id, $eid]);
    $row = $cur->fetch();
    if (!$row) json_err('Registro no encontrado.', 404);

    $nuevo_status = $in['status'] ?? $row['status'];
    if (!in_array($nuevo_status, VALID_STATUS, true)) json_err('Estado inválido.');

    $nuevo_valor = $row['valor_ingreso'];
    if (isAdmin() && isset($in['valor'])) {
        $nuevo_valor = max(0, (int) $in['valor']);
    }

    // Repuesto usado (opcional, null = sin repuesto)
    $id_repuesto_nuevo = isset($in['id_repuesto_usado'])
        ? ($in['id_repuesto_usado'] ? (int) $in['id_repuesto_usado'] : null)
        : ($row['id_repuesto_usado'] ?? null);

    $obs_txt = trim($in['obs'] ?? '');

    $ya_descontado = (bool) ($row['stock_descontado'] ?? 0);
    $stock_dec = 0;

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE reparaciones
                      SET status = ?, valor_ingreso = ?, id_repuesto_usado = ?
                      WHERE id_ingreso = ? AND id_empresa = ?")
           ->execute([$nuevo_status, $nuevo_valor, $id_repuesto_nuevo, $id, $eid]);

        // Gestión de reservas al cambiar el repuesto inicial
        $id_rep_ant_v            = $row['id_repuesto_usado'] !== null ? (int)$row['id_repuesto_usado'] : null;
        $repuesto_changed        = isset($in['id_repuesto_usado']) && $id_repuesto_nuevo !== $id_rep_ant_v;
        $repuesto_ini_reservado  = !$repuesto_changed; // true = este trabajo tiene reserva activa sobre id_repuesto_nuevo
        if ($repuesto_changed) {
            // Liberar reserva anterior si el stock aún no fue consumido
            if ($id_rep_ant_v && !$ya_descontado) {
                $db->prepare("UPDATE inventario SET cantidad_reservada = GREATEST(0, cantidad_reservada - 1)
                               WHERE id_repuesto = ? AND id_empresa = ?")
                   ->execute([$id_rep_ant_v, $eid]);
            }
            // Reservar nuevo repuesto (si no va directo a Reparado, que se descuenta abajo)
            if ($id_repuesto_nuevo && $nuevo_status !== 'Reparado') {
                $resv = $db->prepare("UPDATE inventario SET cantidad_reservada = cantidad_reservada + 1
                                       WHERE id_repuesto = ? AND id_empresa = ? AND (cantidad - cantidad_reservada) > 0");
                $resv->execute([$id_repuesto_nuevo, $eid]);
                if ($resv->rowCount() === 0) {
                    $db->rollBack();
                    json_err('Sin stock disponible — el repuesto está reservado para otro trabajo.');
                }
                $repuesto_ini_reservado = true; // reserva creada en este mismo PUT
            }
        }

        // Descuento de stock cuando el servicio pasa a Reparado (el repuesto ya fue usado)
        if ($nuevo_status === 'Reparado') {
            // Descontar repuesto inicial
            if ($id_repuesto_nuevo && !$ya_descontado) {
                $chk = $db->prepare("SELECT nombre FROM inventario WHERE id_repuesto = ? AND id_empresa = ? AND cantidad > 0");
                $chk->execute([$id_repuesto_nuevo, $eid]);
                $rep_row = $chk->fetch();
                if ($rep_row) {
                    // Solo decrementar cantidad_reservada si este trabajo tenía una reserva activa
                    $sql_desc = $repuesto_ini_reservado
                        ? "UPDATE inventario SET cantidad = cantidad - 1, cantidad_reservada = GREATEST(0, cantidad_reservada - 1) WHERE id_repuesto = ? AND id_empresa = ? AND cantidad > 0"
                        : "UPDATE inventario SET cantidad = cantidad - 1 WHERE id_repuesto = ? AND id_empresa = ? AND cantidad > 0";
                    $db->prepare($sql_desc)->execute([$id_repuesto_nuevo, $eid]);
                    $db->prepare("UPDATE reparaciones SET stock_descontado = 1 WHERE id_ingreso = ? AND id_empresa = ?")
                       ->execute([$id, $eid]);
                    $db->prepare("INSERT INTO observaciones (id_empresa, id_registro, obs, user) VALUES (?,?,?,?)")
                       ->execute([$eid, $id, "Repuesto descontado del inventario: {$rep_row['nombre']}", uname()]);
                    $stock_dec = 1;
                }
            }
            // Descontar repuestos adicionales (reparacion_repuestos)
            $adicionales = $db->prepare(
                "SELECT * FROM reparacion_repuestos WHERE id_reparacion = ? AND id_empresa = ? AND stock_desc = 0"
            );
            $adicionales->execute([$id, $eid]);
            foreach ($adicionales->fetchAll() as $ar) {
                $db->prepare("UPDATE inventario
                                SET cantidad = GREATEST(0, cantidad - ?),
                                    cantidad_reservada = GREATEST(0, cantidad_reservada - ?)
                              WHERE id_repuesto = ? AND id_empresa = ? AND cantidad > 0")
                   ->execute([(int)$ar['cantidad'], (int)$ar['cantidad'], (int)$ar['id_repuesto'], $eid]);
                $db->prepare("UPDATE reparacion_repuestos SET stock_desc = 1 WHERE id = ?")
                   ->execute([(int)$ar['id']]);
                $db->prepare("INSERT INTO observaciones (id_empresa, id_registro, obs, user) VALUES (?,?,?,?)")
                   ->execute([$eid, $id, "Repuesto descontado: {$ar['nombre_snap']} x{$ar['cantidad']}", uname()]);
                $stock_dec = 1;
            }
        }

        // Preparar texto de cambio de valor (si aplica)
        // Usar valor_original (al abrir modal) como base para detectar el cambio total
        $val_txt = '';
        if (isAdmin() && isset($in['valor'])) {
            $base_valor = isset($in['valor_original']) ? (int)$in['valor_original'] : (int)$row['valor_ingreso'];
            if ($nuevo_valor !== $base_valor) {
                $v_ant   = '$' . number_format($base_valor, 0, ',', '.');
                $v_new   = '$' . number_format($nuevo_valor, 0, ',', '.');
                $val_txt = "Valor modificado: {$v_ant} → {$v_new}";
                log_accion($db, 'cambio_valor', $id);
            }
        }

        // Preparar texto de cambio de repuesto (si aplica)
        $rep_txt   = '';
        $id_rep_ant = $row['id_repuesto_usado'] !== null ? (int)$row['id_repuesto_usado'] : null;
        if (isset($in['id_repuesto_usado']) && $id_repuesto_nuevo !== $id_rep_ant) {
            $nombre_ant = '';
            $nombre_new = '';
            if ($id_rep_ant) {
                $st = $db->prepare("SELECT nombre FROM inventario WHERE id_repuesto=? AND id_empresa=?");
                $st->execute([$id_rep_ant, $eid]);
                $nombre_ant = $st->fetchColumn() ?: "ID {$id_rep_ant}";
            }
            if ($id_repuesto_nuevo) {
                $st = $db->prepare("SELECT nombre FROM inventario WHERE id_repuesto=? AND id_empresa=?");
                $st->execute([$id_repuesto_nuevo, $eid]);
                $nombre_new = $st->fetchColumn() ?: "ID {$id_repuesto_nuevo}";
            }
            if ($id_rep_ant && $id_repuesto_nuevo) {
                $rep_txt = "Repuesto cambiado: {$nombre_ant} → {$nombre_new}";
            } elseif ($id_repuesto_nuevo) {
                $rep_txt = "Repuesto asignado: {$nombre_new}";
            } else {
                $rep_txt = "Repuesto removido: {$nombre_ant}";
            }
        }

        // Cambios de repuestos adicionales enviados desde el frontend
        $rep_cambios = is_array($in['rep_cambios'] ?? null) ? $in['rep_cambios'] : [];
        $rep_add_txt = [];
        foreach ($rep_cambios as $rc) {
            $accion  = ($rc['accion'] ?? '') === 'removido' ? 'Repuesto removido' : 'Repuesto agregado';
            $nombre  = substr(trim($rc['nombre'] ?? ''), 0, 120);
            $cant    = max(1, (int)($rc['cantidad'] ?? 1));
            if ($nombre) $rep_add_txt[] = $accion . ': ' . $nombre . ($cant > 1 ? " ×{$cant}" : '');
        }
        if ($rep_add_txt) {
            $rep_txt = $rep_txt ? $rep_txt . "\n" . implode("\n", $rep_add_txt) : implode("\n", $rep_add_txt);
        }

        if ($nuevo_status !== $row['status']) {
            // Consolidar valor, repuesto y nota en el mismo registro de historial
            $partes  = array_filter([$val_txt, $rep_txt, $obs_txt ? "Nota: {$obs_txt}" : '']);
            $detalle = $partes ? implode("\n", $partes) : null;
            $db->prepare("INSERT INTO historial (id_empresa, id_reparacion, status_anterior, status_cambio, user, detalle)
                          VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$eid, $id, $row['status'], $nuevo_status, uname(), $detalle]);
            log_accion($db, 'cambio_status', $id);
            $val_txt = '';
            $rep_txt = '';
            $obs_txt = '';
        }

        // Sin cambio de estado: valor, repuesto y nota van juntos a observaciones
        if ($val_txt || $rep_txt) {
            $extra   = implode("\n", array_filter([$val_txt, $rep_txt]));
            $obs_txt = $obs_txt ? "{$extra}\nNota: {$obs_txt}" : $extra;
        }

        if ($obs_txt) {
            $db->prepare("INSERT INTO observaciones (id_empresa, id_registro, obs, user)
                          VALUES (?, ?, ?, ?)")
               ->execute([$eid, $id, $obs_txt, uname()]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        json_err('Error al guardar. Intente nuevamente.', 500);
    }

    json_ok(['msg' => 'Guardado.', 'stock_descontado' => $stock_dec]);
}

if ($method === 'DELETE') {
    if (!isAdmin()) json_err('Sin permisos.', 403);
    csrf_check();

    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($in['id'] ?? $_GET['id'] ?? 0);
    if (!$id) json_err('ID inválido.');

    $cur = $db->prepare("SELECT id_ingreso, id_repuesto_usado, stock_descontado FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ? AND deleted_at IS NULL");
    $cur->execute([$id, $eid]);
    $rep_del = $cur->fetch();
    if (!$rep_del) json_err('Registro no encontrado.', 404);

    // Liberar reservas pendientes (repuesto no consumido aún)
    if ($rep_del['id_repuesto_usado'] && !(int)$rep_del['stock_descontado']) {
        $db->prepare("UPDATE inventario SET cantidad_reservada = GREATEST(0, cantidad_reservada - 1)
                       WHERE id_repuesto = ? AND id_empresa = ?")
           ->execute([(int)$rep_del['id_repuesto_usado'], $eid]);
    }
    $adic_del = $db->prepare("SELECT id_repuesto, cantidad FROM reparacion_repuestos WHERE id_reparacion = ? AND id_empresa = ? AND stock_desc = 0");
    $adic_del->execute([$id, $eid]);
    foreach ($adic_del->fetchAll() as $ar_del) {
        $db->prepare("UPDATE inventario SET cantidad_reservada = GREATEST(0, cantidad_reservada - ?)
                       WHERE id_repuesto = ? AND id_empresa = ?")
           ->execute([(int)$ar_del['cantidad'], (int)$ar_del['id_repuesto'], $eid]);
    }

    $db->prepare("UPDATE reparaciones SET deleted_at = NOW() WHERE id_ingreso = ? AND id_empresa = ?")
       ->execute([$id, $eid]);

    log_accion($db, 'eliminacion', $id);
    json_ok(['msg' => "Servicio #{$id} eliminado."]);
}
