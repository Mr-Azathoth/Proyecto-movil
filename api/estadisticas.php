<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
guard();
if (!isAdmin()) { http_response_code(403); json_err('Acceso denegado.'); }

$db  = getDB();
$eid = eid();

// ── Parámetros de fecha ───────────────────────────────────────────────────────
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

// Validar formato YYYY-MM-DD
$re = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($re, $desde)) $desde = date('Y-m-01');           // inicio de mes actual
if (!preg_match($re, $hasta)) $hasta = date('Y-m-d');            // hoy

$desde_dt = $desde . ' 00:00:00';
$hasta_dt = $hasta . ' 23:59:59';

// ── KPIs ─────────────────────────────────────────────────────────────────────
$kpi = $db->prepare("
    SELECT
        COUNT(*)                                                AS total_ordenes,
        COALESCE(SUM(valor_ingreso), 0)                         AS ingresos_totales,
        COALESCE(AVG(valor_ingreso), 0)                         AS ticket_promedio,
        COALESCE(SUM(status IN ('Reparado','Entregado')), 0)    AS ordenes_cerradas
    FROM reparaciones
    WHERE id_empresa = ? AND fecha_ingreso BETWEEN ? AND ?
");
$kpi->execute([$eid, $desde_dt, $hasta_dt]);
$kpis = $kpi->fetch();

// dias_promedio: tiempo real desde ingreso hasta primer cierre registrado en historial
$dias_q = $db->prepare("
    SELECT COALESCE(AVG(
        DATEDIFF(
            (SELECT MIN(h.fecha_cambio)
               FROM historial h
              WHERE h.id_reparacion = r.id_ingreso
                AND h.id_empresa    = r.id_empresa
                AND h.status_cambio IN ('Reparado','Entregado')),
            r.fecha_ingreso
        )
    ), 0) AS dias_promedio
    FROM reparaciones r
    WHERE r.id_empresa = ? AND r.fecha_ingreso BETWEEN ? AND ?
      AND r.status IN ('Reparado','Entregado','Garantia')
");
$dias_q->execute([$eid, $desde_dt, $hasta_dt]);
$kpis['dias_promedio'] = (float) ($dias_q->fetchColumn() ?? 0);

// ── Ingresos por mes (últimos 12 meses desde $hasta) ─────────────────────────
$ing_mes = $db->prepare("
    SELECT DATE_FORMAT(fecha_ingreso, '%Y-%m') AS mes,
           COALESCE(SUM(valor_ingreso), 0)     AS ingresos,
           COUNT(*)                             AS ordenes
    FROM reparaciones
    WHERE id_empresa = ?
      AND fecha_ingreso >= DATE_SUB(?, INTERVAL 11 MONTH)
      AND fecha_ingreso <= ?
    GROUP BY mes
    ORDER BY mes ASC
");
$ing_mes->execute([$eid, $hasta_dt, $hasta_dt]);
$por_mes = $ing_mes->fetchAll();

// ── Órdenes ingresadas vs cerradas por mes ────────────────────────────────────
$flujo = $db->prepare("
    SELECT DATE_FORMAT(fecha_ingreso, '%Y-%m') AS mes,
           COUNT(*)                             AS ingresadas,
           SUM(status IN ('Reparado','Entregado','Garantia')) AS cerradas
    FROM reparaciones
    WHERE id_empresa = ?
      AND fecha_ingreso BETWEEN ? AND ?
    GROUP BY mes
    ORDER BY mes ASC
");
$flujo->execute([$eid, $desde_dt, $hasta_dt]);
$flujo_mes = $flujo->fetchAll();

// ── Marcas más reparadas ──────────────────────────────────────────────────────
$marcas = $db->prepare("
    SELECT COALESCE(NULLIF(TRIM(marca_ingreso),''), 'Sin marca') AS marca,
           COUNT(*) AS total
    FROM reparaciones
    WHERE id_empresa = ? AND fecha_ingreso BETWEEN ? AND ?
    GROUP BY marca ORDER BY total DESC LIMIT 8
");
$marcas->execute([$eid, $desde_dt, $hasta_dt]);
$top_marcas = $marcas->fetchAll();

// ── Modelos más reparados ─────────────────────────────────────────────────────
$modelos = $db->prepare("
    SELECT CONCAT(
               COALESCE(NULLIF(TRIM(marca_ingreso),''), 'Sin marca'), ' ',
               COALESCE(NULLIF(TRIM(modelo_ingreso),''), 'Sin modelo')
           ) AS modelo,
           COUNT(*) AS total
    FROM reparaciones
    WHERE id_empresa = ? AND fecha_ingreso BETWEEN ? AND ?
      AND (marca_ingreso IS NOT NULL OR modelo_ingreso IS NOT NULL)
    GROUP BY modelo ORDER BY total DESC LIMIT 8
");
$modelos->execute([$eid, $desde_dt, $hasta_dt]);
$top_modelos = $modelos->fetchAll();

// ── Fallas más frecuentes (agrupadas por palabra clave) ───────────────────────
$fallas = $db->prepare("
    SELECT
      CASE
        WHEN LOWER(daño_ingreso) REGEXP 'pantalla|cristal|display|lcd|touch|t[aá]ctil|ghost|fisurad'
          THEN 'Pantalla / táctil'
        WHEN LOWER(daño_ingreso) REGEXP 'bater[ií]a|no carga|puerto.*carga|carga.*lenta|carga lenta'
          THEN 'Batería / carga'
        WHEN LOWER(daño_ingreso) REGEXP 'no enciende|no prende|apagado|agua|mojado|l[ií]quido'
          THEN 'No enciende / daño por agua'
        WHEN LOWER(daño_ingreso) REGEXP 'bot[oó]n|volumen|power|encendido|traba|home'
          THEN 'Botones'
        WHEN LOWER(daño_ingreso) REGEXP 'c[aá]mara|foto|lente|flash'
          THEN 'Cámara'
        WHEN LOWER(daño_ingreso) REGEXP 'altavoz|bocina|parlante|micr[oó]fono|audio|sonido'
          THEN 'Audio / micrófono'
        WHEN LOWER(daño_ingreso) REGEXP 'vidrio trasero|bisel|marco|carcasa|chasis'
          THEN 'Vidrio trasero / carcasa'
        WHEN LOWER(daño_ingreso) REGEXP 'wifi|bluetooth|se[ñn]al|red|datos|sim'
          THEN 'Conectividad'
        WHEN LOWER(daño_ingreso) REGEXP 'lento|cuelga|reinicia|virus|software|sistema|actualiz'
          THEN 'Software / sistema'
        ELSE TRIM(daño_ingreso)
      END AS falla,
      COUNT(*) AS total
    FROM reparaciones
    WHERE id_empresa = ? AND fecha_ingreso BETWEEN ? AND ?
      AND daño_ingreso IS NOT NULL AND daño_ingreso <> ''
    GROUP BY falla ORDER BY total DESC LIMIT 10
");
$fallas->execute([$eid, $desde_dt, $hasta_dt]);
$top_fallas = $fallas->fetchAll();

json_ok([
    'kpis'      => $kpis,
    'por_mes'   => $por_mes,
    'flujo_mes' => $flujo_mes,
    'marcas'    => $top_marcas,
    'modelos'   => $top_modelos,
    'fallas'    => $top_fallas,
    'filtro'    => ['desde' => $desde, 'hasta' => $hasta],
]);
