<?php
require_once __DIR__.'/includes/config.php';
requireLogin();
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reparo</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
</head>
<body data-csrf="<?= csrf_token() ?>"
      data-role="<?= htmlspecialchars(ucargo()) ?>"
      data-user="<?= htmlspecialchars(uname()) ?>"
      data-nombre="<?= htmlspecialchars(unombre()) ?>">

<div class="app">

  <!-- ── SIDEBAR ─────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">R</div>
      <span class="logo-txt">Reparo</span>
    </div>

    <nav class="sidebar-nav">
      <a class="nav-link active" data-view="servicios">
        <span class="material-icons-round">build</span><span>Servicios</span>
      </a>
      <a class="nav-link" data-view="inventario">
        <span class="material-icons-round">inventory_2</span><span>Inventario</span>
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
      <a href="/reparo/logout.php" class="btn-logout" title="Salir">
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
          <button class="btn-sec" id="btn-exportar">
            <span class="material-icons-round">download</span> Exportar
          </button>
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
          <span class="material-icons-round stat-ic" style="color:#60a5fa">login</span>
          <div><div class="stat-val" id="st-ing">0</div><div class="stat-lbl">Ingresados</div></div>
        </div>
        <div class="stat-card" data-filter="En Reparacion">
          <span class="material-icons-round stat-ic" style="color:#fb923c">handyman</span>
          <div><div class="stat-val" id="st-rep">0</div><div class="stat-lbl">En reparación</div></div>
        </div>
        <div class="stat-card" data-filter="Reparado">
          <span class="material-icons-round stat-ic" style="color:#4ade80">check_circle</span>
          <div><div class="stat-val" id="st-done">0</div><div class="stat-lbl">Reparados</div></div>
        </div>
        <div class="stat-card" data-filter="Entregado">
          <span class="material-icons-round stat-ic" style="color:#94a3b8">assignment_turned_in</span>
          <div><div class="stat-val" id="st-entr">0</div><div class="stat-lbl">Entregados</div></div>
        </div>
      </div>

      <!-- Controles -->
      <div class="controls">
        <div class="search-wrap">
          <span class="material-icons-round search-ic">search</span>
          <input id="search-bar" type="text" class="search-input" placeholder="Buscar por cliente, modelo, # orden...">
        </div>
        <select id="filter-status" class="filter-sel">
          <option value="">Todos los estados</option>
          <option value="Ingresado">Ingresado</option>
          <option value="En Reparacion">En Reparación</option>
          <option value="Reparado">Reparado</option>
          <option value="Entregado">Entregado</option>
          <option value="Garantia">Garantía</option>
        </select>
      </div>

      <!-- Tabla -->
      <div class="panel">
        <div class="table-scroll">
          <table class="tbl">
            <thead>
              <tr>
                <th>#</th><th>Cliente</th><th>Equipo</th>
                <th>Falla</th><th>Valor</th><th>Técnico</th><th>Fecha</th><th>Estado</th><th>Acciones</th>
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
        <?php if(isAdmin()): ?>
        <button class="btn-primary" id="btn-abrir-repuesto">
          <span class="material-icons-round">add</span> Agregar repuesto
        </button>
        <?php endif; ?>
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
                <th>Código</th><th>Repuesto</th><th>Marca compatible</th>
                <th>Modelo compatible</th><th>Precio venta</th><th>Stock</th>
                <?php if(isAdmin()): ?><th>Acciones</th><?php endif; ?>
              </tr>
            </thead>
            <tbody id="tbl-inventario">
              <tr><td colspan="7" class="tbl-loading">Carga al abrir vista</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /view-inventario -->

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
    <div id="nuevo-post-save" style="display:none">
      <div class="post-save-body">
        <span class="material-icons-round post-save-icon">check_circle</span>
        <h3 class="post-save-title">Servicio <span id="ps-num"></span> registrado</h3>
        <p class="post-save-sub">¿Qué deseas hacer?</p>
        <div class="post-save-actions">
          <button type="button" class="btn-sec" id="ps-nuevo">
            <span class="material-icons-round">add</span> Ingresar otro
          </button>
          <button type="button" class="btn-sec" id="ps-editar">
            <span class="material-icons-round">edit</span> Corregir datos
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
          <div class="fg"><label>Teléfono <span class="req">*</span></label><input type="text" name="telefono_cliente" placeholder="+56 9 XXXX XXXX" required></div>
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
          <div class="fg fg-full"><label>Falla / Daño <span class="req">*</span></label><input type="text" name="daño_ingreso" placeholder="Cambio de pantalla, revisión..." required></div>
          <div class="fg"><label>Valor ($)</label><input type="number" name="valor_ingreso" placeholder="0" value="0" min="0"></div>
          <div class="fg"><label>Estado inicial</label>
            <select name="status">
              <option value="Ingresado">Ingresado</option>
              <option value="En Reparacion">En Reparación</option>
            </select>
          </div>
          <div class="fg fg-full"><label>Observación inicial</label><textarea name="obs" placeholder="Sin Observaciones" style="resize:none;height:40px;min-height:40px;overflow:hidden"></textarea></div>
          <div class="fg fg-full">
            <label>Repuesto a utilizar <span style="font-size:10.5px;color:var(--txt3);font-weight:400">(opcional)</span></label>
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
            <p class="section-label" style="margin-top:8px">Repuestos</p>
            <div id="det-rep-list"></div>
            <div class="rep-add-row" id="rep-add-row">
              <div id="sel-rep-adicional" style="flex:1;min-width:0"></div>
              <input type="hidden" id="hid-rep-adicional">
              <input type="number" id="inp-rep-cant" min="1" value="1" title="Cantidad">
              <button type="button" class="btn-sec btn-sm" id="btn-agregar-rep">+ Agregar</button>
            </div>
          </div>

          <div class="timeline-wrap">
            <p class="section-label" style="margin-top:8px">Línea de tiempo</p>
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
          <p class="hint-txt" id="hint-entregado" style="display:none;margin-top:-4px">
            <span class="material-icons-round" style="font-size:12px;vertical-align:middle">info</span>
            Al guardar como Entregado se descontarán los repuestos del stock
          </p>
          <div class="fg">
            <label>Nota técnica</label>
            <textarea id="det-obs" rows="3" placeholder="Avance, resultado, observación..." style="resize:none"></textarea>
          </div>
        </div>

      </div>
      <div class="modal-ft">
        <a id="det-wa-link" href="#" target="_blank" class="btn-wa" title="Abrir chat WhatsApp" style="margin-right:auto">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          WhatsApp
        </a>
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
          <div class="fg"><label>Código <span class="req">*</span></label><input type="text" name="codigo" placeholder="REP-001" required></div>
          <div class="fg"><label>Nombre <span class="req">*</span></label><input type="text" name="nombre" placeholder="Pantalla Samsung A54" required></div>
          <div class="fg"><label>Marca compatible</label><input type="text" name="marca_compatible" placeholder="Samsung" list="dl-marcas-inv"></div>
          <div class="fg"><label>Modelo compatible</label><input type="text" name="modelo_compatible" placeholder="A54, A53, A52..."></div>
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
  <div class="modal-box" style="max-width:420px">
    <div class="modal-hd">
      <h3 id="confirm-title">Confirmar acción</h3>
    </div>
    <div class="modal-body">
      <p id="confirm-msg" style="font-size:14px;line-height:1.6;margin:0"></p>
    </div>
    <div class="modal-ft">
      <button type="button" class="btn-sec" data-modal="modal-confirm">Cancelar</button>
      <button type="button" class="btn-danger" id="confirm-ok">Eliminar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL: Editar repuesto de inventario
══════════════════════════════════════════════════ -->
<div class="modal-bg" id="modal-edit-repuesto">
  <div class="modal-box">
    <div class="modal-hd">
      <h3>Editar repuesto</h3>
      <button class="modal-close" data-modal="modal-edit-repuesto"><span class="material-icons-round">close</span></button>
    </div>
    <form id="form-edit-repuesto">
      <input type="hidden" id="edit-rep-id">
      <div class="modal-body">
        <div class="form-grid2" id="edit-rep-admin-fields">
          <div class="fg"><label>Código <span class="req">*</span></label><input type="text" id="edit-rep-codigo" placeholder="REP-001" required></div>
          <div class="fg"><label>Nombre <span class="req">*</span></label><input type="text" id="edit-rep-nombre" placeholder="Pantalla Samsung A54" required></div>
          <div class="fg"><label>Marca compatible</label><input type="text" id="edit-rep-marca" placeholder="Samsung" list="dl-marcas-inv"></div>
          <div class="fg"><label>Modelo compatible</label><input type="text" id="edit-rep-modelo" placeholder="A54, A53..."></div>
          <div class="fg"><label>Precio venta ($)</label><input type="number" id="edit-rep-precio" placeholder="0" min="0"></div>
        </div>
        <div class="fg" style="margin-top:4px"><label>Stock</label><input type="number" id="edit-rep-cantidad" placeholder="0" min="0"></div>
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
  <div class="modal-box" style="max-width:500px">
    <div class="modal-hd">
      <h3>Exportar servicios</h3>
      <button class="modal-close" data-modal="modal-exportar"><span class="material-icons-round">close</span></button>
    </div>
    <div class="modal-body exp-body">

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
    <div class="modal-ft" style="gap:10px">
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
<?php endif; ?>

<!-- Toast -->
<div id="toast" class="toast"></div>

<script src="/reparo/assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
</body>
</html>
