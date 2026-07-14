<?php
require_once __DIR__.'/includes/config.php';
requireLogin();
$_SESSION['last_activity'] = time();

// Cargar datos de la empresa + migración silenciosa de columnas
$empresa = ['nombre'=>'Centrotec','logo_path'=>null,'direccion'=>'','telefono'=>'','correo'=>'','comuna'=>'','region'=>''];
try {
    $_db = getDB();
    try { $_db->exec("ALTER TABLE empresas
        ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS direccion VARCHAR(150) DEFAULT '',
        ADD COLUMN IF NOT EXISTS telefono  VARCHAR(30)  DEFAULT '',
        ADD COLUMN IF NOT EXISTS correo    VARCHAR(80)  DEFAULT '',
        ADD COLUMN IF NOT EXISTS comuna    VARCHAR(60)  DEFAULT '',
        ADD COLUMN IF NOT EXISTS region    VARCHAR(60)  DEFAULT ''");
    } catch (PDOException $ignored) {}
    $_st = $_db->prepare("SELECT nombre,logo_path,direccion,telefono,correo,comuna,region FROM empresas WHERE id_empresa=?");
    $_st->execute([eid()]);
    if ($_row = $_st->fetch()) $empresa = $_row;
} catch (PDOException $ignored) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Centrotec</title>
<style nonce="<?= CSP_NONCE ?>">html,body{background:#0d1117;margin:0}</style>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
<link rel="manifest" href="<?= BASE ?>/manifest.php">
<meta name="theme-color" content="#7c3aed">
<link rel="apple-touch-icon" href="<?= BASE ?>/assets/img/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Centrotec">
<meta name="base-path" content="<?= BASE ?>">
</head>
<body data-csrf="<?= csrf_token() ?>"
      data-role="<?= htmlspecialchars(ucargo()) ?>"
      data-user="<?= htmlspecialchars(uname()) ?>"
      data-nombre="<?= htmlspecialchars(unombre()) ?>"
      data-uid="<?= uid() ?>"
      data-base="<?= BASE ?>">

<div class="app">

  <!-- Barra superior móvil (hamburguesa + nombre app) -->
  <div class="mobile-topbar" id="mobile-topbar">
    <button class="hamburger" id="btn-hamburger" aria-label="Menú">
      <span class="material-icons-round">menu</span>
    </button>
    <span class="mobile-app-name">Centrotec</span>
    <button class="btn-pwa-install" onclick="pwaInstall()" title="Instalar app" style="display:none">
      <span class="material-icons-round">install_mobile</span>
    </button>
  </div>

  <!-- Overlay para cerrar sidebar en móvil -->
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- ── SIDEBAR ─────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">

    <!-- Logo / nombre editable (admin) -->
    <div class="sidebar-logo" id="logo-display">
      <div class="logo-icon" id="logo-icon-wrap">
        <?php if(!empty($empresa['logo_path'])): ?>
          <img class="logo-img" id="logo-img" src="<?= BASE ?>/<?=htmlspecialchars($empresa['logo_path'])?>" alt="Logo">
        <?php else: ?>
          <span id="logo-letra"><?=strtoupper(substr($empresa['nombre'],0,1))?></span>
        <?php endif; ?>
      </div>
      <span class="logo-txt" id="sidebar-nombre"><?=htmlspecialchars($empresa['nombre'])?></span>
      <?php if(isAdmin()): ?>
        <button class="btn-logo-edit" id="btn-logo-edit" title="Editar nombre y logo">
          <span class="material-icons-round">edit</span>
        </button>
      <?php endif; ?>
    </div>

    <?php if(isAdmin()): ?>
    <!-- Panel inline de edición de logo/nombre -->
    <div class="logo-edit-panel hidden" id="logo-edit-panel">
      <input class="logo-edit-input" type="text" id="inp-emp-nombre"
             value="<?=htmlspecialchars($empresa['nombre'])?>" placeholder="Nombre del negocio">
      <label class="logo-file-btn" for="inp-emp-logo">
        <span class="material-icons-round">add_photo_alternate</span>
        <span id="logo-file-lbl">Subir logo</span>
        <input type="file" id="inp-emp-logo" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden">
      </label>
      <div class="logo-edit-actions">
        <button type="button" class="btn-sm btn-primary" id="btn-logo-save">Guardar</button>
        <button type="button" class="btn-sm btn-sec"     id="btn-logo-cancel">Cancelar</button>
      </div>
    </div>
    <?php endif; ?>

    <nav class="sidebar-nav">
      <a class="nav-link active" data-view="servicios">
        <span class="material-icons-round">build</span><span>Servicios</span>
      </a>
      <a class="nav-link" data-view="inventario">
        <span class="material-icons-round">inventory_2</span><span>Inventario</span>
      </a>
      <?php if(isAdmin()): ?>
      <a class="nav-link" data-view="estadisticas">
        <span class="material-icons-round">bar_chart</span><span>Estadísticas</span>
      </a>
      <a class="nav-link" data-view="config">
        <span class="material-icons-round">settings</span><span>Configuración</span>
      </a>
      <?php endif; ?>
      <a class="nav-link" data-view="soporte">
        <span class="material-icons-round">support_agent</span><span>Soporte</span>
      </a>
    </nav>

    <div class="sidebar-bottom">
      <div class="user-chip">
        <div class="user-av"><?=strtoupper(substr(uname(),0,1))?></div>
        <div class="user-meta">
          <span class="user-name"><?=htmlspecialchars(unombre())?></span>
          <span class="user-role"><?=ucargo()?></span>
        </div>
      </div>
      <a href="<?= BASE ?>/logout.php" class="btn-logout" title="Salir">
        <span class="material-icons-round">logout</span>
      </a>
    </div>
  </aside>

  <!-- ── MAIN ───────────────────────────────────────── -->
  <main class="main">

    <!-- ═══════════════════════ VIEW: SERVICIOS ════════════════════════ -->
    <div id="view-servicios" class="view active">

      <header class="topbar">
        <div>
          <h1 class="page-title">Reparaciones</h1>
          <p class="page-sub" id="sub-servicios">Cargando...</p>
        </div>
        <div class="topbar-actions">
          <?php if(isAdmin()): ?>
          <div class="split-btn split-sec" id="split-exportar">
            <button class="split-main" id="btn-exportar-main">
              <span class="material-icons-round">download</span> Exportar vista actual
            </button>
            <button class="split-arrow" id="btn-exportar-arrow" aria-label="Más opciones de exportación">
              <span class="material-icons-round">expand_more</span>
            </button>
            <div class="split-dropdown" id="split-exportar-menu">
              <button data-split-action="exp-serv-csv">
                <span class="material-icons-round">table_view</span> Excel / CSV
              </button>
              <button data-split-action="exp-serv-pdf">
                <span class="material-icons-round">picture_as_pdf</span> PDF
              </button>
              <div class="split-dropdown-sep"></div>
              <button data-split-action="exp-serv-personalizar">
                <span class="material-icons-round">tune</span> Personalizar exportación
              </button>
            </div>
          </div>
          <?php endif; ?>
          <button class="btn-primary" id="btn-abrir-nuevo">
            <span class="material-icons-round">add</span> Ingresar equipo
          </button>
        </div>
      </header>

      <!-- Stats -->
      <div class="stats-row" id="stats-row">
        <div class="stat-card" data-filter="">
          <span class="material-icons-round stat-ic">format_list_bulleted</span>
          <div><div class="stat-val" id="st-total">0</div><div class="stat-lbl">Total</div></div>
        </div>
        <div class="stat-card" data-filter="Ingresado">
          <span class="material-icons-round stat-ic ic-ingresado">login</span>
          <div><div class="stat-val" id="st-ing">0</div><div class="stat-lbl">Ingresados</div></div>
        </div>
        <div class="stat-card" data-filter="En Reparacion">
          <span class="material-icons-round stat-ic ic-reparacion">handyman</span>
          <div><div class="stat-val" id="st-rep">0</div><div class="stat-lbl">En reparación</div></div>
        </div>
        <div class="stat-card" data-filter="Reparado">
          <span class="material-icons-round stat-ic ic-reparado">check_circle</span>
          <div><div class="stat-val" id="st-done">0</div><div class="stat-lbl">Reparados</div></div>
        </div>
        <div class="stat-card" data-filter="Entregado">
          <span class="material-icons-round stat-ic ic-entregado">assignment_turned_in</span>
          <div><div class="stat-val" id="st-entr">0</div><div class="stat-lbl">Entregados</div></div>
        </div>
        <div class="stat-card" data-filter="Garantia">
          <span class="material-icons-round stat-ic ic-garantia">verified_user</span>
          <div><div class="stat-val" id="st-gar">0</div><div class="stat-lbl">Garantía</div></div>
        </div>
      </div>

      <!-- Controles -->
      <div class="controls">
        <div class="search-wrap">
          <span class="material-icons-round search-ic">search</span>
          <input id="search-bar" type="text" class="search-input" placeholder="Buscar por cliente, modelo, # orden...">
        </div>
        <input type="hidden" id="filter-status" value="">
      </div>

      <!-- Tabla -->
      <div class="panel">
        <div class="table-scroll">
          <table class="tbl">
            <thead>
              <tr>
                <th class="th-sortable" data-sort="id"># <span class="sort-icon"></span></th>
                <th class="th-sortable" data-sort="cliente">Cliente <span class="sort-icon"></span></th>
                <th>Equipo</th>
                <th>Falla</th><th>Valor</th><th>Ingresado por</th><th>Fecha Ingreso</th><th>Estado</th><th>Acciones</th>
              </tr>
            </thead>
            <tbody id="tbl-servicios">
              <tr><td colspan="9" class="tbl-loading">Cargando servicios...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /view-servicios -->

    <!-- ═══════════════════════ VIEW: INVENTARIO ═══════════════════════ -->
    <div id="view-inventario" class="view">

      <header class="topbar">
        <div>
          <h1 class="page-title">Inventario</h1>
          <p class="page-sub">Repuestos y stock disponible</p>
        </div>
        <div class="topbar-actions">
          <button class="btn-sec" id="btn-scan-qr">
            <span class="material-icons-round">qr_code_scanner</span> Escanear QR
          </button>
          <?php if(isAdmin()): ?>
          <div class="split-btn split-sec" id="split-exportar-inv">
            <button class="split-main" id="btn-exportar-inv-main">
              <span class="material-icons-round">download</span> Exportar vista actual
            </button>
            <button class="split-arrow" id="btn-exportar-inv-arrow" aria-label="Más opciones de exportación">
              <span class="material-icons-round">expand_more</span>
            </button>
            <div class="split-dropdown" id="split-exportar-inv-menu">
              <button data-split-action="exp-inv-csv">
                <span class="material-icons-round">table_view</span> Excel / CSV
              </button>
              <button data-split-action="exp-inv-pdf">
                <span class="material-icons-round">picture_as_pdf</span> PDF
              </button>
              <div class="split-dropdown-sep"></div>
              <button data-split-action="exp-inv-personalizar">
                <span class="material-icons-round">tune</span> Personalizar exportación
              </button>
            </div>
          </div>
          <div class="split-btn split-primary" id="split-agregar-inv">
            <button class="split-main" id="btn-abrir-repuesto">
              <span class="material-icons-round">add</span> Agregar repuesto
            </button>
            <button class="split-arrow" id="btn-agregar-inv-arrow" aria-label="Más opciones">
              <span class="material-icons-round">expand_more</span>
            </button>
            <div class="split-dropdown" id="split-agregar-inv-menu">
              <button data-split-action="imp-inv-csv" class="split-item-import">
                <span class="material-icons-round">upload_file</span> Importar CSV
              </button>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </header>

      <div class="controls">
        <div class="search-wrap">
          <span class="material-icons-round search-ic">search</span>
          <input id="search-inv" type="text" class="search-input" placeholder="Buscar por nombre, marca o modelo...">
        </div>
      </div>

      <div class="panel">
        <div class="table-scroll">
          <table class="tbl">
            <thead>
              <tr>
                <th class="th-sortable" data-sort-inv="nombre">Repuesto <span class="sort-icon"></span></th>
                <th class="th-sortable" data-sort-inv="marca">Marca compatible <span class="sort-icon"></span></th>
                <th class="th-sortable" data-sort-inv="modelo">Modelo compatible <span class="sort-icon"></span></th>
                <th class="th-sortable" data-sort-inv="precio">Precio venta <span class="sort-icon"></span></th>
                <th class="th-sortable" data-sort-inv="stock">Stock <span class="sort-icon"></span></th>
                <?php if(isAdmin()): ?><th>Acciones</th><?php endif; ?>
              </tr>
            </thead>
            <tbody id="tbl-inventario">
              <tr><td colspan="6" class="tbl-loading">Carga al abrir vista</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /view-inventario -->

    <!-- ═══════════════════════════════════════════════════════
         VIEW: CONFIGURACIÓN (solo admin)
    ════════════════════════════════════════════════════════ -->
    <?php if(isAdmin()): ?>
    <div id="view-config" class="view">
      <header class="topbar">
        <div><h1 class="page-title">Configuración</h1></div>
      </header>

      <!-- Tabs -->
      <div class="cfg-tabs">
        <button class="cfg-tab active" data-tab="mi-cuenta">Mi cuenta</button>
        <button class="cfg-tab" data-tab="usuarios">Usuarios</button>
        <button class="cfg-tab" data-tab="suscripcion">Mi suscripción</button>
      </div>

      <!-- Panel: Mi cuenta -->
      <div class="cfg-panel active" id="cfg-mi-cuenta">
        <div class="cfg-section">
          <h3 class="cfg-section-title">Datos de contacto del negocio</h3>
          <div class="form-grid2">
            <div class="fg"><label>Dirección</label><input type="text" id="cfg-dir" placeholder="Calle 123, Local 4"></div>
            <div class="fg"><label>Teléfono</label><input type="text" id="cfg-tel" placeholder="+56 9 1234 5678"></div>
            <div class="fg"><label>Correo de contacto</label><input type="email" id="cfg-mail" placeholder="contacto@empresa.cl"></div>
            <div class="fg"><label>Comuna</label><input type="text" id="cfg-comuna" placeholder="Providencia"></div>
            <div class="fg fg-wide"><label>Región</label><input type="text" id="cfg-region" placeholder="Región Metropolitana"></div>
          </div>
          <button type="button" class="btn-primary" id="btn-cfg-empresa">
            <span class="material-icons-round">save</span> Guardar datos
          </button>
        </div>

        <div class="cfg-section">
          <h3 class="cfg-section-title">Cambiar contraseña</h3>
          <div class="form-grid2 modal-w520">
            <div class="fg fg-wide"><label>Contraseña actual</label><input type="password" id="cfg-pass-actual" placeholder="••••••••" autocomplete="current-password"></div>
            <div class="fg"><label>Nueva contraseña</label><input type="password" id="cfg-pass-nueva" placeholder="••••••••" autocomplete="new-password"></div>
            <div class="fg"><label>Confirmar nueva</label><input type="password" id="cfg-pass-confirm" placeholder="••••••••" autocomplete="new-password"></div>
          </div>
          <button type="button" class="btn-primary" id="btn-cfg-pass">
            <span class="material-icons-round">lock</span> Cambiar contraseña
          </button>
        </div>
      </div>

      <!-- Panel: Usuarios -->
      <div class="cfg-panel hidden" id="cfg-usuarios">
        <div class="cfg-section">
          <div class="cfg-usuarios-header">
            <h3 class="cfg-section-title">Usuarios de la cuenta</h3>
            <div class="cfg-usuarios-actions">
              <span class="cfg-tecnicos-count hidden" id="cfg-tecnicos-count"></span>
              <button type="button" class="btn-primary btn-sm" id="btn-nuevo-tecnico">
                <span class="material-icons-round">person_add</span> Agregar técnico
              </button>
            </div>
          </div>
          <table class="tbl mt-8">
            <thead>
              <tr><th>Nombre</th><th>Usuario</th><th>Cargo</th><th>Acciones</th></tr>
            </thead>
            <tbody id="tbl-usuarios">
              <tr><td colspan="4" class="tbl-loading"><span class="material-icons-round spin">sync</span> Cargando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal: Nuevo técnico -->
      <div class="modal-bg" id="modal-nuevo-tecnico">
        <div class="modal-box modal-sm">
          <div class="modal-header">
            <span class="modal-title">Agregar técnico</span>
            <button type="button" class="modal-close" id="modal-tecnico-close" aria-label="Cerrar">
              <span class="material-icons-round">close</span>
            </button>
          </div>
          <div class="modal-body">
            <label class="form-label">Nombre completo</label>
            <input type="text" id="tecnico-nombre" class="form-inp" placeholder="Ej: Juan Pérez" maxlength="80" autocomplete="off">
            <label class="form-label mt-8">Nombre de usuario</label>
            <input type="text" id="tecnico-user" class="form-inp" placeholder="Ej: juan.perez" maxlength="40" autocomplete="off">
            <label class="form-label mt-8">Contraseña</label>
            <input type="password" id="tecnico-pass" class="form-inp" placeholder="Mínimo 6 caracteres" maxlength="60">
            <label class="form-label mt-8">Confirmar contraseña</label>
            <input type="password" id="tecnico-pass2" class="form-inp" placeholder="Repetir contraseña" maxlength="60">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-sec" id="modal-tecnico-cancel">Cancelar</button>
            <button type="button" class="btn-primary" id="btn-tecnico-guardar">
              <span class="material-icons-round">save</span> Guardar
            </button>
          </div>
        </div>
      </div>

      <!-- Panel: Suscripción -->
      <div class="cfg-panel hidden" id="cfg-suscripcion">

        <!-- Plan actual -->
        <div class="cfg-section">
          <h3 class="cfg-section-title">Plan actual</h3>
          <div class="subs-card">
            <div class="subs-card-left">
              <div class="subs-plan-name" id="subs-plan-nombre">–</div>
              <div class="subs-meta">
                <span class="pill" id="subs-estado-badge">–</span>
                <span class="subs-vence-txt" id="subs-vence-txt">–</span>
              </div>
            </div>
            <div class="subs-dias-wrap" id="subs-dias-wrap">
              <span class="subs-dias-num" id="subs-dias-num">–</span>
              <span class="subs-dias-lbl" id="subs-dias-lbl">días restantes</span>
            </div>
          </div>
        </div>

        <!-- Notificación por correo -->
        <div class="cfg-section">
          <h3 class="cfg-section-title">Notificaciones</h3>
          <div class="subs-notif-row">
            <div class="subs-notif-text">
              <div class="subs-notif-title">Aviso por correo al administrador</div>
              <div class="subs-notif-sub">Notifica al correo registrado cuando queden 30, 15 y 7 días para el vencimiento</div>
            </div>
            <label class="toggle-sw">
              <input type="checkbox" id="subs-notif-chk">
              <span class="toggle-sw-track"><span class="toggle-sw-thumb"></span></span>
            </label>
          </div>
        </div>

        <!-- Planes de suscripción -->
        <div class="cfg-section">
          <h3 class="cfg-section-title">Seleccionar plan</h3>
          <p class="cfg-section-sub">Todas las funciones incluidas en cada plan. Suscripción recurrente vía Mercado Pago — cancela cuando quieras.</p>
          <div class="plan-grid">
            <?php
            $planes         = defined('MP_PLANES') ? MP_PLANES : [];
            $precioMensual  = $planes['1mes']['precio'] ?? 4990;
            foreach ($planes as $key => $plan):
                $porMes   = (int) round($plan['precio'] / $plan['meses']);
                $ahorro   = $plan['meses'] > 1 ? (int) round((1 - $porMes / $precioMensual) * 100) : 0;
                $featured = ($key === '12meses');
            ?>
            <div class="plan-card <?= $featured ? 'plan-card-featured' : '' ?>" data-plan="<?= $key ?>">
              <?php if ($featured): ?>
                <div class="plan-badge plan-badge-popular">Mejor valor</div>
              <?php elseif ($ahorro > 0): ?>
                <div class="plan-badge plan-badge-ahorro">Ahorra <?= $ahorro ?>%</div>
              <?php endif; ?>
              <div class="plan-nombre"><?= $plan['nombre'] ?></div>
              <div class="plan-precio">$<?= number_format($plan['precio'], 0, ',', '.') ?></div>
              <div class="plan-por-mes">$<?= number_format($porMes, 0, ',', '.') ?> / mes</div>
              <button type="button" class="btn-plan" data-plan="<?= $key ?>">
                <span class="material-icons-round">shopping_cart</span> Suscribirse
              </button>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="subs-pay-note">
            <span class="material-icons-round ic-inline">lock</span>
            Pago seguro vía Mercado Pago. Tus datos de tarjeta son gestionados por MP, nunca por nosotros.
          </p>
        </div>

        <!-- Historial de pagos -->
        <div class="cfg-section">
          <h3 class="cfg-section-title">Historial de pagos</h3>
          <div id="subs-historial-wrap">
            <p class="tbl-loading"><span class="material-icons-round spin">sync</span> Cargando...</p>
          </div>
        </div>

      </div><!-- /cfg-suscripcion -->

    </div><!-- /view-config -->

    <!-- ══════════════════════ VIEW: ESTADÍSTICAS ══════════════════════ -->
    <div id="view-estadisticas" class="view">
      <header class="topbar">
        <div>
          <h1 class="page-title">Estadísticas</h1>
          <p class="page-sub">Métricas financieras y operacionales</p>
        </div>
      </header>

      <!-- Filtros de fecha -->
      <div class="est-filtros">
        <div class="est-atajos">
          <button class="est-atajo active" data-rango="mes">Este mes</button>
          <button class="est-atajo" data-rango="trim">3 meses</button>
          <button class="est-atajo" data-rango="anio">Este año</button>
          <button class="est-atajo" data-rango="todo">Todo</button>
        </div>
        <div class="est-rango-custom">
          <input type="date" id="est-desde" class="est-date-input">
          <span class="est-rango-sep">→</span>
          <input type="date" id="est-hasta" class="est-date-input">
          <button class="btn-primary btn-sm" id="est-btn-aplicar">Aplicar</button>
        </div>
      </div>

      <!-- KPIs -->
      <div class="est-kpis" id="est-kpis">
        <div class="est-kpi">
          <div class="est-kpi-icon est-kpi-blue"><span class="material-icons-round">receipt_long</span></div>
          <div class="est-kpi-val" id="est-k-ordenes">—</div>
          <div class="est-kpi-lbl">Órdenes en período</div>
        </div>
        <div class="est-kpi">
          <div class="est-kpi-icon est-kpi-green"><span class="material-icons-round">attach_money</span></div>
          <div class="est-kpi-val" id="est-k-ingresos">—</div>
          <div class="est-kpi-lbl">Ingresos totales</div>
        </div>
        <div class="est-kpi">
          <div class="est-kpi-icon est-kpi-purple"><span class="material-icons-round">show_chart</span></div>
          <div class="est-kpi-val" id="est-k-ticket">—</div>
          <div class="est-kpi-lbl">Ticket promedio</div>
        </div>
        <div class="est-kpi">
          <div class="est-kpi-icon est-kpi-orange"><span class="material-icons-round">check_circle</span></div>
          <div class="est-kpi-val" id="est-k-cerradas">—</div>
          <div class="est-kpi-lbl">Órdenes cerradas</div>
        </div>
        <div class="est-kpi">
          <div class="est-kpi-icon est-kpi-gray"><span class="material-icons-round">schedule</span></div>
          <div class="est-kpi-val" id="est-k-dias">—</div>
          <div class="est-kpi-lbl">Días prom. reparación</div>
        </div>
      </div>

      <!-- Gráficos fila 1 -->
      <div class="est-charts-row">
        <div class="est-chart-card est-chart-wide">
          <div class="est-chart-title">Ingresos por mes</div>
          <canvas id="chart-ingresos" height="90"></canvas>
        </div>
        <div class="est-chart-card">
          <div class="est-chart-title">Órdenes: ingresadas vs cerradas</div>
          <canvas id="chart-flujo" height="90"></canvas>
        </div>
      </div>

      <!-- Gráficos fila 2 -->
      <div class="est-charts-row">
        <div class="est-chart-card">
          <div class="est-chart-title">Marcas más reparadas</div>
          <canvas id="chart-marcas" height="160"></canvas>
        </div>
        <div class="est-chart-card">
          <div class="est-chart-title">Fallas más frecuentes</div>
          <div class="est-fallas-list" id="est-fallas-list"></div>
        </div>
      </div>

      <!-- Gráficos fila 3 -->
      <div class="est-charts-row">
        <div class="est-chart-card est-chart-wide">
          <div class="est-chart-title">Modelos más reparados</div>
          <div class="chart-modelos-wrap"><canvas id="chart-modelos"></canvas></div>
        </div>
      </div>

    </div><!-- /view-estadisticas -->

    <!-- ═══════════════════════ VIEW: SOPORTE ══════════════════════════ -->
    <div id="view-soporte" class="view">
      <header class="topbar">
        <div>
          <h1 class="page-title">Soporte</h1>
          <p class="page-sub">Envía consultas o reporta problemas al equipo Centrotec</p>
        </div>
        <button class="btn btn-primary" id="btn-nuevo-ticket">
          <span class="material-icons-round">add</span>Nuevo ticket
        </button>
      </header>

      <div id="soporte-ticket-list">
        <div class="soporte-empty">
          <span class="material-icons-round">support_agent</span>
          <p>Cargando tickets...</p>
        </div>
      </div>
    </div><!-- /view-soporte -->

    <!-- Modal: nuevo ticket de soporte -->
    <div class="modal-bg" id="modal-soporte">
      <div class="modal-box modal-box-sm">
        <div class="modal-hd">
          <h3>Nuevo ticket de soporte</h3>
          <button class="modal-close" id="btn-sop-close"><span class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body">
          <div class="fg">
            <label>Asunto</label>
            <input type="text" id="sop-asunto" placeholder="Describe brevemente el problema" maxlength="200">
          </div>
          <div class="fg">
            <label>Mensaje</label>
            <textarea id="sop-mensaje" rows="5" placeholder="Detalla tu consulta o el problema que estás experimentando..."></textarea>
          </div>
          <p id="sop-error" style="color:#f87171;font-size:13px;min-height:18px;"></p>
        </div>
        <div class="modal-ft">
          <button class="btn btn-sec" id="btn-sop-close-ft">Cancelar</button>
          <button class="btn btn-primary" id="btn-sop-enviar">
            <span class="material-icons-round">send</span>Enviar
          </button>
        </div>
      </div>
    </div><!-- /modal-soporte -->

    <!-- Modal: resetear contraseña de usuario -->
    <div class="modal-bg" id="modal-reset-pass">
      <div class="modal-box modal-box-sm">
        <div class="modal-hd">
          <h3>Cambiar contraseña de <span id="reset-pass-nombre"></span></h3>
          <button class="modal-close" data-modal="modal-reset-pass"><span class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="reset-pass-uid">
          <div class="form-grid2 w-full">
            <div class="fg"><label>Nueva contraseña</label><input type="password" id="reset-pass-nueva" placeholder="••••••••" autocomplete="new-password"></div>
            <div class="fg"><label>Confirmar</label><input type="password" id="reset-pass-confirm" placeholder="••••••••" autocomplete="new-password"></div>
          </div>
        </div>
        <div class="modal-ft">
          <button type="button" class="btn-sec" data-modal="modal-reset-pass">Cancelar</button>
          <button type="button" class="btn-primary" id="btn-reset-pass-save">
            <span class="material-icons-round">lock_reset</span> Guardar contraseña
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: Nuevo servicio
══════════════════════════════════════════════════ -->
<div class="modal-bg" id="modal-nuevo">
  <div class="modal-box">
    <div class="modal-hd">
      <h3>Registrar ingreso de equipo</h3>
      <button class="modal-close" data-modal="modal-nuevo"><span class="material-icons-round">close</span></button>
    </div>
    <!-- Panel post-guardado (oculto hasta guardar exitosamente) -->
    <div id="nuevo-post-save" class="hidden">
      <div class="post-save-body">
        <span class="material-icons-round post-save-icon">check_circle</span>
        <h3 class="post-save-title">Servicio <span id="ps-num"></span> registrado</h3>

        <!-- Código de seguimiento -->
        <div class="ps-codigo-wrap">
          <p class="ps-codigo-label">Código de seguimiento del cliente</p>
          <div class="ps-codigo-chip"><span id="ps-codigo">–</span></div>
          <a id="ps-wa-btn" href="#" target="_blank" rel="noopener" class="btn-wa ps-wa-btn">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Enviar código por WhatsApp
          </a>
        </div>

        <p class="post-save-sub">¿Qué deseas hacer?</p>
        <div class="post-save-actions">
          <button type="button" class="btn-sec" id="ps-nuevo">
            <span class="material-icons-round">add</span> Ingresar otro
          </button>
          <button type="button" class="btn-sec" id="ps-editar">
            <span class="material-icons-round">edit</span>Me equivoqué, corregir servicio ingresado
          </button>
          <button type="button" class="btn-sec" id="ps-boleta">
            <span class="material-icons-round">print</span> Orden de servicio
          </button>
          <button type="button" class="btn-primary" id="ps-cerrar">
            <span class="material-icons-round">done_all</span> Listo
          </button>
        </div>
      </div>
    </div>

    <form id="form-nuevo">
      <div class="modal-body">
        <p class="section-label">Datos del cliente</p>
        <div class="form-grid4">
          <div class="fg"><label>Nombre <span class="req">*</span></label><input type="text" name="nombre_cliente" placeholder="Juan Pérez" required></div>
          <div class="fg"><label>Teléfono <span class="req">*</span></label><input type="text" name="telefono_cliente" placeholder="+56 9 XXXX XXXX" value="+56 " required></div>
          <div class="fg"><label>RUT</label><input type="text" name="rut_cliente" placeholder="12.345.678-9"></div>
          <div class="fg"><label>Tipo</label>
            <select name="tipo_ingreso">
              <option>Telefono</option><option>Tablet</option>
              <option>Notebook</option><option>Televisor</option><option>Otro</option>
            </select>
          </div>
        </div>
        <p class="section-label">Datos del equipo</p>
        <div class="form-grid4">
          <div class="fg">
            <label>Marca <span class="req">*</span></label>
            <div id="sel-marca-nuevo"></div>
            <input type="text" id="inp-marca-nueva-nuevo" class="inp-nueva-item"
                   placeholder="Ej: OnePlus, Nothing...">
            <input type="hidden" name="marca_ingreso" id="hid-marca-nuevo">
          </div>
          <div class="fg">
            <label>Modelo <span class="req">*</span></label>
            <div id="sel-modelo-nuevo"></div>
            <input type="text" id="inp-modelo-nuevo-nuevo" class="inp-nueva-item"
                   placeholder="Ej: Galaxy A55, iPhone 16...">
            <input type="hidden" name="modelo_ingreso" id="hid-modelo-nuevo">
          </div>
          <div class="fg"><label>IMEI / N° serie</label><input type="text" name="imei" placeholder="352981..."></div>
          <div class="fg"><label>Contraseña equipo</label><input type="text" name="pass_ingreso" placeholder="Sin contraseña"></div>
        </div>
        <p class="section-label">Servicio</p>
        <div class="form-grid2">
          <div class="fg fg-full"><label>Falla / Daño <span class="req">*</span></label><input type="text" name="dano_ingreso" placeholder="Cambio de pantalla, revisión..." required></div>
          <div class="fg"><label>Valor ($)</label><input type="number" name="valor_ingreso" placeholder="0" value="0" min="0"></div>
          <div class="fg"><label>Estado inicial</label>
            <select name="status">
              <option value="Ingresado">Ingresado</option>
              <option value="En Reparacion">En Reparación</option>
            </select>
          </div>
          <div class="fg fg-full"><label>Observación inicial</label><textarea name="obs" placeholder="Sin Observaciones" class="textarea-compact"></textarea></div>
          <div class="fg fg-full">
            <label>Repuesto a utilizar <span class="lbl-opt">(opcional)</span></label>
            <div id="sel-rep-nuevo"></div>
            <input type="hidden" id="hid-rep-nuevo">
          </div>
        </div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn-sec" data-modal="modal-nuevo">Cancelar</button>
        <button type="submit" class="btn-primary" id="btn-submit-nuevo">
          <span class="material-icons-round">save</span> Registrar ingreso
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: Detalle / gestión de servicio
══════════════════════════════════════════════════ -->
<div class="modal-bg" id="modal-detalle">
  <div class="modal-box modal-box-lg">
    <div class="modal-hd">
      <h3>Orden #<span id="det-id"></span></h3>
      <button class="modal-close" data-modal="modal-detalle"><span class="material-icons-round">close</span></button>
    </div>

    <form id="form-actualizar">
      <input type="hidden" id="det-hidden-id">
      <div class="modal-body det-grid">

        <!-- Izquierda: info + repuestos + timeline -->
        <div class="det-left">
          <div class="info-block">
            <p><span class="info-lbl">Cliente</span><span class="info-val" id="det-cliente"></span></p>
            <p><span class="info-lbl">Teléfono</span><span class="info-val" id="det-tel"></span></p>
            <p><span class="info-lbl">Equipo</span><span class="info-val" id="det-equipo"></span></p>
            <p><span class="info-lbl">Ingresado por</span><span class="info-val" id="det-tecnico"></span></p>
            <p class="info-full"><span class="info-lbl">Falla reportada</span><span class="info-val" id="det-daño"></span></p>
            <p class="info-full"><span class="info-lbl">IMEI / Clave</span><span class="info-val" id="det-imei"></span></p>
          </div>

          <!-- Sección repuestos -->
          <div class="rep-section" id="det-rep-section">
            <p class="section-label">Repuestos</p>
            <div id="det-rep-list"></div>
            <div class="rep-add-row" id="rep-add-row">
              <div id="sel-rep-adicional" class="flex-grow"></div>
              <input type="hidden" id="hid-rep-adicional">
              <input type="number" id="inp-rep-cant" min="1" value="1" title="Cantidad">
              <button type="button" class="btn-sec btn-sm" id="btn-agregar-rep">+ Agregar</button>
            </div>
          </div>

          <div class="timeline-wrap">
            <p class="section-label">Línea de tiempo</p>
            <div id="timeline" class="timeline-list">Cargando...</div>
          </div>
        </div>

        <!-- Derecha: controles de edición -->
        <div class="det-right">
          <div class="fg"><label>Estado</label>
            <select id="det-status" class="form-sel">
              <option value="Ingresado">Ingresado</option>
              <option value="En Reparacion">En Reparación</option>
              <option value="Reparado">Reparado</option>
              <option value="Entregado">Entregado</option>
              <option value="Garantia">Garantía</option>
            </select>
          </div>
          <div class="fg" id="grp-valor">
            <label>Valor ($)</label>
            <input type="number" id="det-valor" min="0" placeholder="0">
          </div>
          <p class="hint-txt" id="hint-entregado">
            <span class="material-icons-round ic-xs">info</span>
            Al guardar como Entregado se descontarán los repuestos del stock
          </p>
          <div class="fg">
            <label>Nota técnica</label>
            <textarea id="det-obs" rows="3" placeholder="Avance, resultado, observación..." class="textarea-fixed"></textarea>
          </div>
        </div>

      </div>
      <div class="modal-ft">
        <a id="det-wa-link" href="#" target="_blank" class="btn-wa mr-auto" title="Abrir chat WhatsApp">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          WhatsApp
        </a>
        <button type="button" class="btn-sec" id="det-boleta-btn" title="Imprimir orden de servicio técnico">
          <span class="material-icons-round">print</span> Orden de servicio
        </button>
        <button type="button" class="btn-sec" data-modal="modal-detalle">Cancelar</button>
        <button type="submit" class="btn-primary"><span class="material-icons-round">save</span> Guardar</button>
      </div>
    </form>

  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: Nuevo repuesto (Inventario)
══════════════════════════════════════════════════ -->
<div class="modal-bg" id="modal-repuesto">
  <div class="modal-box">
    <div class="modal-hd">
      <h3>Agregar repuesto</h3>
      <button class="modal-close" data-modal="modal-repuesto"><span class="material-icons-round">close</span></button>
    </div>
    <form id="form-repuesto">
      <div class="modal-body">
        <div class="form-grid2">
          <div class="fg"><label>Nombre <span class="req">*</span></label><input type="text" name="nombre" placeholder="Pantalla Samsung A54" required></div>
          <div class="fg"><label>Marca compatible</label><input type="text" name="marca_compatible" placeholder="Samsung" list="dl-marcas-inv"></div>
          <div class="fg fg-wide"><label>Modelos compatibles</label>
            <div class="tag-input-wrap" id="tag-nuevo-modelo"></div>
            <input type="hidden" name="modelo_compatible" id="hid-nuevo-modelo">
          </div>
          <div class="fg"><label>Precio venta ($)</label><input type="number" name="precio_venta" placeholder="0" value="0" min="0"></div>
          <div class="fg"><label>Stock inicial</label><input type="number" name="cantidad" placeholder="0" value="0" min="0"></div>
        </div>
        <datalist id="dl-marcas-inv"></datalist>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn-sec" data-modal="modal-repuesto">Cancelar</button>
        <button type="submit" class="btn-primary"><span class="material-icons-round">save</span> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: Confirmación genérica (eliminar)
══════════════════════════════════════════════════ -->
<div class="modal-bg" id="modal-confirm">
  <div class="modal-box modal-sm">
    <div class="modal-hd">
      <h3 id="confirm-title">Confirmar acción</h3>
    </div>
    <div class="modal-body">
      <p id="confirm-msg" class="modal-msg"></p>
    </div>
    <div class="modal-ft">
      <button type="button" class="btn-sec" data-modal="modal-confirm">Cancelar</button>
      <button type="button" class="btn-danger" id="confirm-ok">Eliminar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: Importar inventario CSV
══════════════════════════════════════════════════ -->
<div class="modal-bg" id="modal-importar-inv">
  <div class="modal-box modal-importar-box">
    <div class="qr-modal-topbar">
      <button type="button" class="modal-close" id="modal-importar-close" aria-label="Cerrar">
        <span class="material-icons-round">close</span>
      </button>
    </div>
    <div class="modal-body">

      <div class="imp-template-row">
        <span class="material-icons-round imp-info-icon">info</span>
        <span class="imp-info-txt">El CSV debe tener las columnas: <strong>nombre</strong>, marca_compatible, modelo_compatible, precio_venta, cantidad.</span>
        <a class="imp-template-link" id="btn-descargar-plantilla" href="#">
          <span class="material-icons-round">download</span> Descargar plantilla
        </a>
      </div>

      <div class="imp-dropzone" id="imp-dropzone">
        <span class="material-icons-round imp-upload-icon">upload_file</span>
        <p class="imp-drop-txt">Arrastra tu CSV aquí o <label class="imp-file-label" for="imp-file-input">selecciona archivo</label></p>
        <input type="file" id="imp-file-input" accept=".csv,.txt" class="imp-file-hidden">
        <p class="imp-drop-hint">Solo archivos .csv — máximo 2 MB</p>
      </div>

      <div class="imp-preview-wrap hidden" id="imp-preview-wrap">
        <p class="imp-preview-title" id="imp-preview-title"></p>
        <div class="table-scroll">
          <table class="tbl imp-preview-tbl" id="imp-preview-tbl"></table>
        </div>
      </div>

      <div class="imp-result hidden" id="imp-result"></div>

    </div>
    <div class="modal-footer">
      <button type="button" class="btn-primary" id="btn-importar-confirm" disabled>
        <span class="material-icons-round">cloud_upload</span> Importar
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: Editar repuesto de inventario
══════════════════════════════════════════════════ -->
<div class="modal-bg" id="modal-edit-repuesto">
  <div class="modal-box modal-box-sm">
    <div class="modal-hd">
      <h3>Editar repuesto</h3>
      <button class="modal-close" data-modal="modal-edit-repuesto"><span class="material-icons-round">close</span></button>
    </div>
    <form id="form-edit-repuesto">
      <input type="hidden" id="edit-rep-id">
      <div class="modal-body">
        <div class="form-grid2" id="edit-rep-admin-fields">
          <div class="fg"><label>Nombre <span class="req">*</span></label><input type="text" id="edit-rep-nombre" placeholder="Pantalla Samsung A54" required></div>
          <div class="fg"><label>Marca compatible</label><input type="text" id="edit-rep-marca" placeholder="Samsung" list="dl-marcas-inv"></div>
          <div class="fg fg-wide"><label>Modelos compatibles</label>
            <div class="tag-input-wrap" id="tag-edit-modelo"></div>
          </div>
          <div class="fg"><label>Precio venta ($)</label><input type="number" id="edit-rep-precio" placeholder="0" min="0"></div>
        </div>
        <div class="fg w-half-mt"><label>Stock</label><input type="number" id="edit-rep-cantidad" placeholder="0" min="0"></div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn-sec" data-modal="modal-edit-repuesto">Cancelar</button>
        <button type="submit" class="btn-primary"><span class="material-icons-round">save</span> Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php if(isAdmin()): ?>
<!-- ══════════════════ MODAL: EXPORTAR ══════════════════════════════════════ -->
<div class="modal-bg" id="modal-exportar">
  <div class="modal-box modal-md">
    <div class="modal-hd">
      <h3>Exportar servicios</h3>
      <button class="modal-close" data-modal="modal-exportar"><span class="material-icons-round">close</span></button>
    </div>
    <div class="modal-body exp-body">

      <div class="exp-vista-wrap hidden" id="exp-vista-wrap">
        <div class="exp-fs mb-0">
          <div class="exp-vista-meta">
            <span class="material-icons-round">filter_list</span>
            <span>Vista actual: </span><span id="exp-vista-info" class="exp-vista-val"></span>
          </div>
        </div>
      </div>

      <fieldset class="exp-fs">
        <legend>Rango de fechas</legend>
        <div class="fg-row">
          <div class="fg">
            <label>Desde</label>
            <input type="date" id="exp-f-desde">
          </div>
          <div class="fg">
            <label>Hasta</label>
            <input type="date" id="exp-f-hasta">
          </div>
        </div>
      </fieldset>

      <fieldset class="exp-fs">
        <legend>Estado</legend>
        <div class="exp-checks">
          <label><input type="checkbox" class="exp-status" value="Ingresado"            checked> Ingresado</label>
          <label><input type="checkbox" class="exp-status" value="En Reparacion"        checked> En Reparación</label>
          <label><input type="checkbox" class="exp-status" value="Reparado"             checked> Reparado</label>
          <label><input type="checkbox" class="exp-status" value="Entregado"            checked> Entregado</label>
          <label><input type="checkbox" class="exp-status" value="No tiene reparacion"  checked> Sin reparación</label>
        </div>
      </fieldset>

      <fieldset class="exp-fs">
        <legend>Valor del servicio</legend>
        <div class="fg-row">
          <div class="fg">
            <label>Mínimo ($)</label>
            <input type="number" id="exp-p-min" min="0" placeholder="Sin límite">
          </div>
          <div class="fg">
            <label>Máximo ($)</label>
            <input type="number" id="exp-p-max" min="0" placeholder="Sin límite">
          </div>
        </div>
      </fieldset>

      <fieldset class="exp-fs">
        <legend>Repuesto utilizado</legend>
        <select id="exp-repuesto" class="sel-native">
          <option value="">— Todos —</option>
        </select>
      </fieldset>

    </div>
    <div class="modal-ft gap-10">
      <button type="button" class="btn-sec" data-modal="modal-exportar">Cancelar</button>
      <button type="button" class="btn-sec" id="btn-exp-csv">
        <span class="material-icons-round">table_view</span> Excel / CSV
      </button>
      <button type="button" class="btn-primary" id="btn-exp-pdf">
        <span class="material-icons-round">picture_as_pdf</span> PDF
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════ MODAL: EXPORTAR INVENTARIO ═══════════════════════════ -->
<div class="modal-bg" id="modal-exportar-inv">
  <div class="modal-box modal-sm">
    <div class="modal-hd">
      <h3>Exportar inventario</h3>
      <button class="modal-close" data-modal="modal-exportar-inv"><span class="material-icons-round">close</span></button>
    </div>
    <div class="modal-body exp-body">
      <div class="exp-fs">
        <div class="exp-vista-meta">
          <span class="material-icons-round">filter_list</span>
          <span>Vista actual: </span><span id="exp-inv-vista-info" class="exp-vista-val">Sin filtros</span>
        </div>
      </div>
      <p class="exp-note">Se exportará el inventario con los filtros y el orden de la vista actual.</p>
    </div>
    <div class="modal-ft gap-10">
      <button type="button" class="btn-sec" data-modal="modal-exportar-inv">Cancelar</button>
      <button type="button" class="btn-sec" id="btn-exp-inv-csv">
        <span class="material-icons-round">table_view</span> Excel / CSV
      </button>
      <button type="button" class="btn-primary" id="btn-exp-inv-pdf">
        <span class="material-icons-round">picture_as_pdf</span> PDF
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div id="toast" class="toast"></div>

<!-- Modal: Ver QR de repuesto -->
<div class="modal-bg" id="modal-qr">
  <div class="modal-box modal-qr-box">
    <div class="qr-modal-topbar">
      <button type="button" class="modal-close" id="modal-qr-close" aria-label="Cerrar">
        <span class="material-icons-round">close</span>
      </button>
    </div>
    <div class="modal-body qr-modal-body">
      <div id="qr-canvas-wrap"></div>
      <p class="qr-item-nombre" id="qr-item-nombre"></p>
      <p class="qr-item-meta" id="qr-item-meta"></p>
      <button type="button" class="btn-primary w-full" id="btn-qr-print">
        <span class="material-icons-round">print</span> Imprimir
      </button>
    </div>
  </div>
</div>

<!-- Modal: Escáner QR -->
<div class="modal-bg" id="modal-scanner">
  <div class="modal-box modal-sm">
    <div class="qr-modal-topbar">
      <button type="button" class="modal-close" id="modal-scanner-close" aria-label="Cerrar">
        <span class="material-icons-round">close</span>
      </button>
    </div>
    <div class="modal-body">
      <div class="scanner-wrap" id="scanner-wrap">
        <video id="scanner-video" class="scanner-video" autoplay muted playsinline></video>
        <div class="scanner-overlay">
          <div class="scanner-frame"></div>
          <p class="scanner-hint">Apunta al código QR del repuesto</p>
        </div>
      </div>
      <div class="scanner-fallback hidden" id="scanner-fallback">
        <span class="material-icons-round scanner-mobile-icon">smartphone</span>
        <p class="scanner-fallback-msg">No se pudo acceder a la cámara. Verifica que el sitio use HTTPS y que hayas dado permiso de cámara al navegador.</p>
      </div>
      <div class="scanner-result hidden" id="scanner-result">
        <span class="material-icons-round scanner-ok-icon">check_circle</span>
        <p class="scanner-ok-text" id="scanner-ok-text"></p>
      </div>
    </div>
  </div>
</div>

<script src="<?= BASE ?>/assets/js/sw-register.js"></script>
<script src="<?= BASE ?>/assets/js/chart.umd.min.js"></script>
<script src="<?= BASE ?>/assets/js/qrcode.min.js"></script>
<script src="<?= BASE ?>/assets/js/jsqr.min.js"></script>
<script src="<?= BASE ?>/assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
<script src="<?= BASE ?>/assets/js/soporte.js?v=<?= filemtime(__DIR__.'/assets/js/soporte.js') ?>"></script>
</body>
</html>
