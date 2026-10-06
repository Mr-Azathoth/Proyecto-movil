<?php
// Esquema de sucursales y stock por sucursal. Es idempotente y se aplica solo: guard() llama a
// schema_sucursales_asegurar() y, si falta algo, ejecuta schema_sucursales_migrar() bajo un lock
// (igual que el resto de migraciones silenciosas del proyecto). Los scripts de cron/ solo lo invocan.

function schema_inventario_base(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS inventario (
        id_repuesto       INT NOT NULL AUTO_INCREMENT,
        id_empresa        INT NOT NULL,
        codigo            VARCHAR(30)  NOT NULL,
        nombre            VARCHAR(100) NOT NULL,
        marca_compatible  VARCHAR(40)  DEFAULT '',
        modelo_compatible VARCHAR(60)  DEFAULT '',
        precio_venta      INT          DEFAULT 0,
        cantidad          INT          DEFAULT 0,
        PRIMARY KEY (id_repuesto),
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $db->exec("ALTER TABLE inventario ADD UNIQUE KEY uq_inv_codigo (id_empresa, codigo)"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE inventario ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (PDOException $e) {}
}

function schema_sucursales_completo(PDO $db): bool {
    $r = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name IN ('sucursales','usuario_sucursales','inventario_stock','traspasos')) AS t,
            (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND ((table_name = 'reparaciones' AND column_name = 'id_sucursal')
                  OR (table_name = 'usuarios'     AND column_name = 'id_sucursal')
                  OR (table_name = 'sucursales'   AND column_name = 'telefono'))) AS c"
    )->fetch();
    return (int) $r['t'] === 4 && (int) $r['c'] === 3;
}

function schema_sucursales_asegurar(PDO $db): void {
    if (!empty($_SESSION['_schema_suc_ok'])) return;
    if (!schema_sucursales_completo($db)) {
        $lock = (bool) $db->query("SELECT GET_LOCK('centrotec_schema_sucursales', 60)")->fetchColumn();
        try {
            if (!schema_sucursales_completo($db)) schema_sucursales_migrar($db);
        } finally {
            if ($lock) $db->query("SELECT RELEASE_LOCK('centrotec_schema_sucursales')");
        }
    }
    $_SESSION['_schema_suc_ok'] = 1;
}

function schema_sucursales_migrar(PDO $db): array {
    $log = [];
    schema_inventario_base($db);

    $db->exec("CREATE TABLE IF NOT EXISTS sucursales (
        id_sucursal INT NOT NULL AUTO_INCREMENT,
        id_empresa  INT NOT NULL,
        nombre      VARCHAR(80)  NOT NULL,
        direccion   VARCHAR(150) NOT NULL DEFAULT '',
        telefono    VARCHAR(30)  NOT NULL DEFAULT '',
        es_bodega   TINYINT(1)   NOT NULL DEFAULT 0,
        activa      TINYINT(1)   NOT NULL DEFAULT 1,
        creada_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_sucursal),
        UNIQUE KEY uq_suc_nombre (id_empresa, nombre),
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS usuario_sucursales (
        id_usuario  INT NOT NULL,
        id_sucursal INT NOT NULL,
        PRIMARY KEY (id_usuario, id_sucursal),
        FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario)    ON DELETE CASCADE,
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        "ALTER TABLE sucursales ADD COLUMN telefono VARCHAR(30) NOT NULL DEFAULT '' AFTER direccion",
        "ALTER TABLE reparaciones ADD COLUMN id_sucursal INT NULL",
        "ALTER TABLE reparaciones ADD KEY idx_rep_sucursal (id_empresa, id_sucursal)",
        "ALTER TABLE usuarios     ADD COLUMN id_sucursal INT NULL",
    ] as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) {}
    }

    $n = 0;
    foreach ($db->query("SELECT id_empresa FROM empresas")->fetchAll(PDO::FETCH_COLUMN) as $eid) {
        $eid = (int) $eid;
        $s = $db->prepare("SELECT id_sucursal FROM sucursales WHERE id_empresa = ? AND es_bodega = 0 ORDER BY id_sucursal LIMIT 1");
        $s->execute([$eid]);
        $sid = (int) $s->fetchColumn();
        if (!$sid) {
            $db->prepare("INSERT INTO sucursales (id_empresa, nombre) VALUES (?, 'Principal')")->execute([$eid]);
            $sid = (int) $db->lastInsertId();
            $n++;
        }
        $db->prepare("UPDATE reparaciones SET id_sucursal = ? WHERE id_empresa = ? AND id_sucursal IS NULL")->execute([$sid, $eid]);
        $db->prepare("UPDATE usuarios     SET id_sucursal = ? WHERE id_empresa = ? AND id_sucursal IS NULL")->execute([$sid, $eid]);
    }
    $log[] = "Sucursales 'Principal' creadas: $n";

    $db->exec("CREATE TABLE IF NOT EXISTS inventario_stock (
        id_repuesto        INT NOT NULL,
        id_sucursal        INT NOT NULL,
        id_empresa         INT NOT NULL,
        cantidad           INT NOT NULL DEFAULT 0,
        cantidad_reservada INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id_repuesto, id_sucursal),
        KEY idx_stock_emp (id_empresa, id_sucursal),
        FOREIGN KEY (id_repuesto)  REFERENCES inventario(id_repuesto)  ON DELETE CASCADE,
        FOREIGN KEY (id_sucursal)  REFERENCES sucursales(id_sucursal)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS traspasos (
        id          INT NOT NULL AUTO_INCREMENT,
        id_empresa  INT NOT NULL,
        id_repuesto INT NOT NULL,
        id_origen   INT NOT NULL,
        id_destino  INT NOT NULL,
        cantidad    INT NOT NULL,
        id_usuario  INT NULL,
        usuario     VARCHAR(100) NOT NULL DEFAULT '',
        nota        VARCHAR(200) NOT NULL DEFAULT '',
        fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_tr_rep (id_empresa, id_repuesto),
        FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Todo el stock anterior pasa a la primera sucursal con atencion al publico de cada empresa.
    // INSERT IGNORE: nunca pisa filas ya migradas ni cambios posteriores. La columna de reservas
    // antigua puede no existir en instalaciones nuevas.
    $tieneRes = (bool) $db->query("SHOW COLUMNS FROM inventario LIKE 'cantidad_reservada'")->fetch();
    $resExpr  = $tieneRes ? 'i.cantidad_reservada' : '0';
    $f = $db->exec("INSERT IGNORE INTO inventario_stock (id_repuesto, id_sucursal, id_empresa, cantidad, cantidad_reservada)
        SELECT i.id_repuesto, d.sid, i.id_empresa, i.cantidad, $resExpr
          FROM inventario i
          JOIN (SELECT id_empresa, MIN(id_sucursal) AS sid FROM sucursales WHERE es_bodega = 0 GROUP BY id_empresa) d
            ON d.id_empresa = i.id_empresa
         WHERE NOT EXISTS (SELECT 1 FROM inventario_stock s WHERE s.id_repuesto = i.id_repuesto)");
    $log[] = "Filas de stock migradas: $f";
    return $log;
}
