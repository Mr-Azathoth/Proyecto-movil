<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();

$db     = getDB();
$eid    = eid();
$method = $_SERVER['REQUEST_METHOD'];

// Migración silenciosa: añadir columnas si no existen
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN id_repuesto_usado INT NULL"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN stock_descontado TINYINT(1) NOT NULL DEFAULT 0"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN codigo_seguimiento VARCHAR(6) NULL"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD UNIQUE KEY uq_codigo_seguimiento (codigo_seguimiento)"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE reparaciones ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch(PDOException $e) {}
try { $db->exec("ALTER TABLE historial ADD COLUMN detalle TEXT NULL DEFAULT NULL"); } catch(PDOException $e) {}
try { $db->exec("CREATE TABLE IF NOT EXISTS reparacion_fotos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa INT NOT NULL,
  id_reparacion INT NOT NULL,
  url VARCHAR(500) NOT NULL,
  etiqueta VARCHAR(50) NOT NULL DEFAULT 'Reparación',
  subida_por VARCHAR(100) NOT NULL DEFAULT '',
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rrf (id_reparacion, id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(PDOException $e) {}

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

    $suc = sucursal_filtro($db, $eid);

    $sql = "SELECT r.*, i.nombre AS nombre_repuesto_usado, su.nombre AS nombre_sucursal
              FROM reparaciones r
              LEFT JOIN inventario i
                     ON i.id_repuesto = r.id_repuesto_usado AND i.id_empresa = r.id_empresa
              LEFT JOIN sucursales su
                     ON su.id_sucursal = r.id_sucursal AND su.id_empresa = r.id_empresa
             WHERE r.id_empresa = ? AND r.deleted_at IS NULL";
    $p   = [$eid];

    if ($suc !== null) {
        $sql .= " AND r.id_sucursal = ?";
        $p[]  = $suc;
    }

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
        'tipo_ingreso'     => trim($_POST['tipo_ingreso']     ?? '') ?: 'Telefono',
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

    // Validar tipo_ingreso contra categorías conocidas del picker
    $tipos_validos = [
        'Computación / Notebook','Computación / Desktop / PC','Computación / Placa madre',
        'Computación / Fuente de poder','Computación / RAM','Computación / Disco duro',
        'Computación / Tarjeta de video','Computación / Otro componente PC',
        'Móviles / Smartphone','Móviles / Tablet','Móviles / Smartwatch',
        'Gaming / Control / Joystick','Gaming / Consola','Gaming / Volante / Periférico','Gaming / Auriculares gaming',
        'Audio / Auriculares','Audio / Parlante / Soundbar','Audio / Micrófono',
        'Periféricos / Teclado / Mouse','Periféricos / Monitor','Periféricos / Impresora','Periféricos / Scanner',
        'Otro / Especificar en descripción',
        // Valores legacy anteriores al picker
        'Telefono','Tablet','Notebook','Televisor','Otro',
    ];
    if (!in_array($f['tipo_ingreso'], $tipos_validos, true)) {
        if (strlen($f['tipo_ingreso']) > 100) $f['tipo_ingreso'] = 'Otro';
        // Aceptar valor desconocido pero truncar (compatibilidad datos existentes)
        else if (empty($f['tipo_ingreso'])) $f['tipo_ingreso'] = 'Otro';
    }

    // Sucursal: la indicada o la base del usuario; con permiso de escritura, activa y que atienda servicios.
    $id_sucursal = sucursal_para_escritura($db, $eid, $_POST['id_sucursal'] ?? null, true);

    // Repuesto inicial opcional: se reserva del stock de la sucursal donde se ingresa el servicio.
    $id_repuesto_inicial = null;
    if (!empty($_POST['id_repuesto_usado'])) {
        $id_rp = (int) $_POST['id_repuesto_usado'];
        $chkRp = $db->prepare("SELECT 1 FROM inventario WHERE id_repuesto = ? AND id_empresa = ? AND deleted_at IS NULL");
        $chkRp->execute([$id_rp, $eid]);
        if ($chkRp->fetchColumn()) {
            if (stock_disponible($db, $eid, $id_rp, $id_sucursal) <= 0) {
                json_err('Sin stock disponible en esta sucursal — el repuesto está agotado o reservado para otro trabajo.');
            }
            $id_repuesto_inicial = $id_rp;
        }
    }

    $codigo = generar_codigo_seguimiento($db);

    // Reserva del repuesto y creacion de la reparacion en una sola transaccion: si la
    // reserva falla (otro ingreso en paralelo se quedo con la ultima unidad) no se crea
    // la reparacion, y si la reparacion fallara por otro motivo, la reserva se revierte —
    // ninguna reparacion queda apuntando a un repuesto que en realidad no se reservo.
    $db->beginTransaction();
    try {
        if ($id_repuesto_inicial && !stock_reservar($db, $eid, $id_repuesto_inicial, $id_sucursal, 1)) {
            $db->rollBack();
            json_err('Sin stock disponible en esta sucursal — el repuesto está reservado para otro trabajo.');
        }

        $db->prepare("INSERT INTO reparaciones
            (id_empresa, nombre_cliente, telefono_cliente, rut_cliente, tipo_ingreso,
             marca_ingreso, modelo_ingreso, imei, pass_ingreso, dano_ingreso,
             valor_ingreso, status, obs, ingresado_por, id_repuesto_usado, codigo_seguimiento, id_sucursal)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([
                $eid, $f['nombre_cliente'], $f['telefono_cliente'], $f['rut_cliente'],
                $f['tipo_ingreso'], $f['marca_ingreso'], $f['modelo_ingreso'], $f['imei'],
                $f['pass_ingreso'], $f['dano_ingreso'], $f['valor_ingreso'],
                $f['status'], $f['obs'], uname(), $id_repuesto_inicial, $codigo, $id_sucursal,
            ]);
        $newId = (int) $db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    log_accion($db, 'nueva_reparacion', $newId, $f, ['id' => $newId, 'codigo_seguimiento' => $codigo]);

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

    // Un tecnico solo modifica reparaciones de las sucursales donde esta habilitado.
    if ($row['id_sucursal'] !== null && !puede_escribir_sucursal((int)$row['id_sucursal'])) {
        json_err('Esta reparación pertenece a otra sucursal: solo lectura.', 403);
    }

    // Traslado de la reparacion a otra sucursal (solo admin).
    $nueva_sucursal = $row['id_sucursal'] !== null ? (int)$row['id_sucursal'] : null;
    $traslado_txt   = '';
    if (isset($in['id_sucursal']) && $in['id_sucursal'] !== '' && (int)$in['id_sucursal'] !== $nueva_sucursal) {
        if (!isAdmin()) json_err('Solo un administrador puede trasladar una reparación de sucursal.', 403);
        $destino = (int) $in['id_sucursal'];
        $dest = sucursal_de_empresa($db, $eid, $destino);
        if (!$dest || !(int)$dest['activa'])  json_err('Sucursal inválida.');
        if ((int)$dest['es_bodega'])          json_err('Una bodega no atiende servicios técnicos.');
        $nueva_sucursal = $destino;
        $traslado_txt   = "Trasladado a la sucursal: {$dest['nombre']}";
    }

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

    // Validar pertenencia del repuesto a esta empresa ANTES de guardarlo — de lo contrario un
    // id_repuesto de otra empresa quedaba escrito en reparaciones.id_repuesto_usado cuando el
    // estado nuevo era 'Reparado' (esa rama salta el chequeo de reserva/descuento mas abajo).
    if ($id_repuesto_nuevo !== null) {
        $chkRep = $db->prepare("SELECT 1 FROM inventario WHERE id_repuesto = ? AND id_empresa = ?");
        $chkRep->execute([$id_repuesto_nuevo, $eid]);
        if (!$chkRep->fetch()) json_err('Repuesto no encontrado.', 404);
    }

    $obs_txt = trim($in['obs'] ?? '');

    $nuevo_tel = null;
    if (array_key_exists('telefono_cliente', $in)) {
        $t = trim((string)($in['telefono_cliente'] ?? ''));
        if (strlen($t) > 30) json_err('Teléfono demasiado largo.');
        $nuevo_tel = $t ?: $row['telefono_cliente'];
    } else {
        $nuevo_tel = $row['telefono_cliente'];
    }

    $ya_descontado = (bool) ($row['stock_descontado'] ?? 0);
    $stock_dec = 0;

    // Las reservas y descuentos de stock viven en la sucursal de la reparacion. Un traslado solo
    // se permite sin reservas pendientes (no se mueven reservas entre sucursales).
    $suc_actual = reparacion_sucursal($db, $eid, $row['id_sucursal'] !== null ? (int)$row['id_sucursal'] : null);
    $suc_stock = $traslado_txt ? $nueva_sucursal : $suc_actual;
    if ($nueva_sucursal === null) $nueva_sucursal = $suc_actual;

    $db->beginTransaction();
    try {
        // Se bloquea la fila de la reparacion: rep_servicio.php hace lo mismo antes de reservar, asi que
        // un traslado y la reserva de un repuesto adicional no pueden cruzarse. Si otra peticion cambio
        // la sucursal o los repuestos desde que se leyo la fila, se aborta en vez de contabilizar mal.
        $lk = $db->prepare("SELECT id_sucursal, id_repuesto_usado, stock_descontado FROM reparaciones
                             WHERE id_ingreso = ? AND id_empresa = ? FOR UPDATE");
        $lk->execute([$id, $eid]);
        $lock = $lk->fetch();
        $mismoRep = fn($a, $b) => ($a === null ? null : (int)$a) === ($b === null ? null : (int)$b);
        if (!$lock || !$mismoRep($lock['id_sucursal'], $row['id_sucursal'])
                   || !$mismoRep($lock['id_repuesto_usado'], $row['id_repuesto_usado'])
                   || (int)$lock['stock_descontado'] !== (int)($row['stock_descontado'] ?? 0)) {
            $db->rollBack();
            json_err('La reparación fue modificada por otra persona mientras se guardaba. Recarga e inténtalo de nuevo.', 409);
        }
        if ($traslado_txt) {
            $pend = ($row['id_repuesto_usado'] && !$ya_descontado) ? 1 : 0;
            if (!$pend) {
                $pq = $db->prepare("SELECT COUNT(*) FROM reparacion_repuestos WHERE id_reparacion = ? AND id_empresa = ? AND stock_desc = 0");
                $pq->execute([$id, $eid]);
                $pend = (int) $pq->fetchColumn();
            }
            if ($pend) {
                $db->rollBack();
                json_err('Esta reparación tiene repuestos reservados en el stock de su sucursal actual. Quítalos antes de trasladarla.');
            }
        }

        $db->prepare("UPDATE reparaciones
                      SET status = ?, valor_ingreso = ?, id_repuesto_usado = ?, telefono_cliente = ?, id_sucursal = ?
                      WHERE id_ingreso = ? AND id_empresa = ?")
           ->execute([$nuevo_status, $nuevo_valor, $id_repuesto_nuevo, $nuevo_tel, $nueva_sucursal, $id, $eid]);

        // Gestión de reservas al cambiar el repuesto inicial
        $id_rep_ant_v            = $row['id_repuesto_usado'] !== null ? (int)$row['id_repuesto_usado'] : null;
        $repuesto_changed        = isset($in['id_repuesto_usado']) && $id_repuesto_nuevo !== $id_rep_ant_v;
        $repuesto_ini_reservado  = !$repuesto_changed; // true = este trabajo tiene reserva activa sobre id_repuesto_nuevo
        if ($repuesto_changed) {
            // Liberar reserva anterior si el stock aún no fue consumido
            if ($id_rep_ant_v && !$ya_descontado) {
                stock_liberar($db, $eid, $id_rep_ant_v, $suc_stock, 1);
            }
            // Reservar nuevo repuesto (si no va directo a Reparado, que se descuenta abajo)
            if ($id_repuesto_nuevo && $nuevo_status !== 'Reparado') {
                if (!stock_reservar($db, $eid, $id_repuesto_nuevo, $suc_stock, 1)) {
                    $db->rollBack();
                    json_err('Sin stock disponible en esta sucursal — el repuesto está reservado para otro trabajo.');
                }
                $repuesto_ini_reservado = true; // reserva creada en este mismo PUT
            }
        }

        // Descuento de stock al pasar a Reparado o Entregado.
        // !$ya_descontado garantiza que nunca se rebaje dos veces (ej: Reparado → Entregado).
        if (in_array($nuevo_status, ['Reparado', 'Entregado'], true)) {
            // Descontar repuesto inicial
            if ($id_repuesto_nuevo && !$ya_descontado) {
                $chk = $db->prepare("SELECT i.nombre FROM inventario i
                                       JOIN inventario_stock s ON s.id_repuesto = i.id_repuesto AND s.id_sucursal = ?
                                      WHERE i.id_repuesto = ? AND i.id_empresa = ? AND s.cantidad > 0");
                $chk->execute([$suc_stock, $id_repuesto_nuevo, $eid]);
                $rep_row = $chk->fetch();
                if ($rep_row) {
                    stock_consumir($db, $eid, $id_repuesto_nuevo, $suc_stock, 1, $repuesto_ini_reservado);
                    $db->prepare("UPDATE reparaciones SET stock_descontado = 1 WHERE id_ingreso = ? AND id_empresa = ?")
                       ->execute([$id, $eid]);
                    $db->prepare("INSERT INTO observaciones (id_empresa, id_registro, obs, user) VALUES (?,?,?,?)")
                       ->execute([$eid, $id, "Repuesto descontado del inventario: {$rep_row['nombre']}", uname()]);
                    $stock_dec = 1;
                }
            }
            // Descontar repuestos adicionales (reparacion_repuestos) — solo los no descontados aún
            $adicionales = $db->prepare(
                "SELECT * FROM reparacion_repuestos WHERE id_reparacion = ? AND id_empresa = ? AND stock_desc = 0"
            );
            $adicionales->execute([$id, $eid]);
            foreach ($adicionales->fetchAll() as $ar) {
                // Solo marcar como descontado si la UPDATE afectó filas (había stock)
                if (stock_consumir($db, $eid, (int)$ar['id_repuesto'], $suc_stock, (int)$ar['cantidad'], true)) {
                    $db->prepare("UPDATE reparacion_repuestos SET stock_desc = 1 WHERE id = ?")
                       ->execute([(int)$ar['id']]);
                    $db->prepare("INSERT INTO observaciones (id_empresa, id_registro, obs, user) VALUES (?,?,?,?)")
                       ->execute([$eid, $id, "Repuesto descontado: {$ar['nombre_snap']} x{$ar['cantidad']}", uname()]);
                    $stock_dec = 1;
                }
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
                log_accion($db, 'cambio_valor', $id, ['valor_anterior' => $base_valor, 'valor_nuevo' => $nuevo_valor]);
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
        if ($traslado_txt) {
            $rep_txt = $rep_txt ? $rep_txt . "\n" . $traslado_txt : $traslado_txt;
            log_accion($db, 'cambio_sucursal', $id, ['sucursal_anterior' => $row['id_sucursal'], 'sucursal_nueva' => $nueva_sucursal]);
        }

        if ($nuevo_status !== $row['status']) {
            // Consolidar valor, repuesto y nota en el mismo registro de historial
            $partes  = array_filter([$val_txt, $rep_txt, $obs_txt ? "Nota: {$obs_txt}" : '']);
            $detalle = $partes ? implode("\n", $partes) : null;
            $db->prepare("INSERT INTO historial (id_empresa, id_reparacion, status_anterior, status_cambio, user, detalle)
                          VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$eid, $id, $row['status'], $nuevo_status, uname(), $detalle]);
            log_accion($db, 'cambio_status', $id, ['status_anterior' => $row['status'], 'status_nuevo' => $nuevo_status]);
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

    $cur = $db->prepare("SELECT id_ingreso, id_repuesto_usado, stock_descontado, id_sucursal FROM reparaciones WHERE id_ingreso = ? AND id_empresa = ? AND deleted_at IS NULL");
    $cur->execute([$id, $eid]);
    $rep_del = $cur->fetch();
    if (!$rep_del) json_err('Registro no encontrado.', 404);
    $suc_del = reparacion_sucursal($db, $eid, $rep_del['id_sucursal'] !== null ? (int)$rep_del['id_sucursal'] : null);

    // Liberar reservas pendientes (repuesto no consumido aún) en el stock de su sucursal
    if ($rep_del['id_repuesto_usado'] && !(int)$rep_del['stock_descontado']) {
        stock_liberar($db, $eid, (int)$rep_del['id_repuesto_usado'], $suc_del, 1);
    }
    $adic_del = $db->prepare("SELECT id_repuesto, cantidad FROM reparacion_repuestos WHERE id_reparacion = ? AND id_empresa = ? AND stock_desc = 0");
    $adic_del->execute([$id, $eid]);
    foreach ($adic_del->fetchAll() as $ar_del) {
        stock_liberar($db, $eid, (int)$ar_del['id_repuesto'], $suc_del, (int)$ar_del['cantidad']);
    }

    // Eliminar fotos físicas + registros de BD antes del soft delete
    $fotos_del = $db->prepare("SELECT url FROM reparacion_fotos WHERE id_reparacion = ? AND id_empresa = ?");
    $fotos_del->execute([$id, $eid]);
    $upload_dir = realpath(__DIR__ . '/../assets/uploads/reparaciones');
    foreach ($fotos_del->fetchAll() as $foto_row) {
        $url_path  = parse_url($foto_row['url'], PHP_URL_PATH);
        $base_path = parse_url(BASE, PHP_URL_PATH);
        $rel       = ($base_path && strncmp($url_path, $base_path, strlen($base_path)) === 0)
                       ? substr($url_path, strlen($base_path)) : $url_path;
        $file_path = realpath(__DIR__ . '/..') . $rel;
        $real_path = $file_path ? realpath($file_path) : false;
        if ($real_path && $upload_dir && strncmp($real_path, $upload_dir, strlen($upload_dir)) === 0) {
            @unlink($real_path);
        }
    }
    $db->prepare("DELETE FROM reparacion_fotos WHERE id_reparacion = ? AND id_empresa = ?")->execute([$id, $eid]);

    $db->prepare("UPDATE reparaciones SET deleted_at = NOW() WHERE id_ingreso = ? AND id_empresa = ?")
       ->execute([$id, $eid]);

    log_accion($db, 'eliminacion', $id, ['id' => $id, 'cliente' => $rep_del['id_ingreso'] ?? $id]);
    json_ok(['msg' => "Servicio #{$id} eliminado."]);
}
