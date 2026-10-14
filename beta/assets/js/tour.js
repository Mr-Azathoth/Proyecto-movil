// Tour guiado de la plataforma (usa Driver.js). Se descarga bajo demanda: ver iniciarTour() / cargarLib() en app.js.
//
// Un tour es una lista de pasos. Cada paso dice QUE resaltar (selector), en QUE pantalla esta (vista), si esta en el
// menu lateral (menu: en el celular el menu esta oculto y se abre solo), para QUE cargos aplica (solo) y el texto.
// Los pasos que no corresponden al usuario (otro cargo, o algo que su cuenta no tiene, como el selector de sucursal
// con una sola sucursal) se omiten solos.
(function () {
  'use strict';

  const BASE = document.body.dataset.base || '';
  const CSRF = document.body.dataset.csrf || '';
  const ROL  = document.body.dataset.role || 'Tecnico';

  // Sube el numero de un tour cuando cambia mucho: queda registrado con la version que el usuario vio.
  const VERSION = { general: 1, servicios: 1, inventario: 1, config: 1, estadisticas: 1, soporte: 1, ingreso: 1, detalle: 1, repuesto: 1, editrepuesto: 1 };

  const SOLO_ADMIN = ['Admin'];

  // ── Contenido ────────────────────────────────────────────────────────────────────────────────────────────────
  // texto puede ser un string o una funcion (rol) => string, para adaptar la explicacion al cargo.
  const POR_ROL = (a, t, v) => rol => (rol === 'Admin' ? a : rol === 'Vendedor' ? v : t);

  const PASOS = {
    bienvenida: {
      titulo: '¡Te damos la bienvenida a Centrotec!',
      texto: 'Te mostramos lo básico en un minuto. Puedes salir cuando quieras.',
    },
    menu: {
      el: '.sidebar-nav', menu: true, lado: 'right',
      titulo: 'Tu menú',
      texto: POR_ROL(
        'Aquí te mueves entre Servicios, Inventario, Estadísticas y Configuración.',
        'Aquí te mueves entre Servicios, Inventario y Soporte.',
        'Aquí te mueves entre Servicios, Inventario y Soporte.'),
    },
    sucursal: {
      el: '#suc-switch', menu: true, lado: 'right',
      titulo: 'Tus sucursales',
      texto: POR_ROL(
        'Elige una sucursal para ver solo lo suyo, o "Todas" para verlo junto.',
        'Puedes ver todas las sucursales, pero solo modificas las tuyas.',
        'Puedes ver todas las sucursales, pero solo ingresas y entregas equipos en las tuyas.'),
    },
    nuevo: {
      el: '#btn-abrir-nuevo', vista: 'servicios', lado: 'bottom', alinear: 'end',
      titulo: 'Ingresar un equipo',
      texto: POR_ROL(
        'Cuando llegue un equipo, regístralo aquí. Se crea un código de seguimiento para el cliente.',
        'Cuando llegue un equipo, regístralo aquí. Se crea un código de seguimiento para el cliente.',
        'Cuando un cliente deje un equipo, regístralo aquí. El técnico se encarga de repararlo.'),
    },
    estados: {
      el: '#stats-row', vista: 'servicios', lado: 'bottom',
      titulo: 'Servicios por estado',
      texto: 'Cada tarjeta cuenta los servicios de un estado. Haz clic en una para filtrar la lista.',
    },
    lista: {
      el: '#view-servicios .panel', vista: 'servicios', lado: 'top',
      titulo: 'Tus servicios',
      texto: POR_ROL(
        'Haz doble clic en un servicio para abrirlo. Los botones de la fila imprimen la orden o escriben por WhatsApp.',
        'Haz doble clic en un servicio para cambiar su estado, anotar avances o subir fotos.',
        'Haz doble clic en un servicio para anotar algo o entregarlo cuando el cliente lo retire.'),
    },
    exportar: {
      el: '#split-exportar', vista: 'servicios', solo: SOLO_ADMIN, lado: 'bottom', alinear: 'end',
      titulo: 'Exportar',
      texto: 'Descarga esta lista en Excel o PDF, con los filtros que tengas puestos.',
    },
    inventarioBuscar: {
      el: '#search-inv', vista: 'inventario', lado: 'bottom', alinear: 'start',
      titulo: 'Buscar un repuesto',
      texto: 'Escribe el nombre, la marca o el modelo y la lista se filtra sola.',
    },
    inventarioStock: {
      el: '#inv-stock-filtro-wrap', vista: 'inventario', lado: 'bottom',
      titulo: 'Qué se está acabando',
      texto: 'Filtra por stock bajo o agotado para saber qué te conviene reponer.',
    },
    inventarioSucursal: {
      el: '#suc-inv-wrap', vista: 'inventario', lado: 'bottom',
      titulo: 'Stock por sucursal',
      texto: POR_ROL(
        'Cada sucursal tiene su propio stock. Elige una para verlo o modificarlo.',
        'Cada sucursal tiene su propio stock. Elige una para ver cuánto hay allí.',
        'Cada sucursal tiene su propio stock. Elige una para saber si hay un repuesto disponible.'),
    },
    inventarioAgregar: {
      el: '#btn-abrir-repuesto', vista: 'inventario', solo: SOLO_ADMIN, lado: 'bottom', alinear: 'end',
      titulo: 'Agregar repuestos',
      texto: 'Suma un repuesto con su precio y stock, o importa muchos de una vez desde Excel (menú ▾).',
    },
    inventarioEscaner: {
      el: '#btn-scan-qr', vista: 'inventario', lado: 'bottom', alinear: 'end',
      titulo: 'Escanear QR',
      texto: 'Apunta la cámara a la etiqueta de un repuesto y se abre su ficha al instante.',
    },
    inventarioQR: {
      el: '#view-inventario .btn-inv-qr', vista: 'inventario', lado: 'left',
      titulo: 'El QR de cada repuesto',
      texto: 'Imprime su etiqueta y pégala en el repuesto. Después, escaneándola, lo encuentras al instante.',
    },
    inventario: {
      el: '#view-inventario .panel', vista: 'inventario', lado: 'top',
      titulo: 'Tus repuestos',
      texto: POR_ROL(
        'Ajusta el stock con + y −, edita con el lápiz y traspasa entre sucursales con las flechas.',
        'Para corregir el stock de tu sucursal, haz doble clic en el repuesto.',
        'Aquí consultas precios y stock. Es solo de consulta: lo modifica el técnico o el administrador.'),
    },
    estadisticasFiltros: {
      el: '.est-atajos', vista: 'estadisticas', solo: SOLO_ADMIN, lado: 'bottom', alinear: 'start',
      titulo: 'El período',
      texto: 'Elige qué período quieres analizar: este mes, 3 meses, este año o fechas a tu medida.',
    },
    estadisticasKpis: {
      el: '#est-kpis', vista: 'estadisticas', solo: SOLO_ADMIN, lado: 'bottom',
      titulo: 'Las cifras clave',
      texto: 'Órdenes, ingresos, ticket promedio y cuánto demora una reparación. Más abajo hay gráficos.',
    },
    configTabs: {
      el: '.cfg-tabs', vista: 'config', solo: SOLO_ADMIN, lado: 'bottom', alinear: 'start',
      titulo: 'Configuración',
      texto: 'Aquí creas a tu equipo y tus sucursales, y ves tu suscripción.',
    },
    configUsuarios: {
      el: '.cfg-tab[data-tab="usuarios"]', vista: 'config', solo: SOLO_ADMIN, lado: 'bottom',
      titulo: 'Usuarios',
      texto: 'El técnico repara; el vendedor atiende el mostrador. Cada uno ve solo lo suyo.',
    },
    configSucursales: {
      el: '.cfg-tab[data-tab="sucursales"]', vista: 'config', solo: SOLO_ADMIN, lado: 'bottom',
      titulo: 'Sucursales',
      texto: 'Si tienes más de un local, créalos aquí. Una bodega guarda stock pero no atiende servicios.',
    },
    configSuscripcion: {
      el: '.cfg-tab[data-tab="suscripcion"]', vista: 'config', solo: SOLO_ADMIN, lado: 'bottom',
      titulo: 'Tu suscripción',
      texto: 'Aquí ves tu plan, cuándo vence y puedes renovarlo.',
    },
    soporteNuevo: {
      el: '#btn-nuevo-ticket', vista: 'soporte', lado: 'bottom', alinear: 'end',
      titulo: 'Escribe a Soporte',
      texto: 'Si algo no funciona o tienes una duda, envíanos un ticket. Puedes pegar imágenes.',
    },
    soporteLista: {
      el: '#soporte-ticket-list', vista: 'soporte', lado: 'top',
      titulo: 'Tus tickets',
      texto: POR_ROL(
        'Aquí quedan los tickets de tu empresa. Haz doble clic en uno para ver la conversación.',
        'Aquí quedan tus tickets. Haz doble clic en uno para ver la conversación.',
        'Aquí quedan tus tickets. Haz doble clic en uno para ver la conversación.'),
    },
    // ── Ventanas (se muestran con la ventana ya abierta: sirven aunque aun no haya datos) ─────────────────────
    ingresoCliente: {
      el: '#modal-nuevo .form-grid-cliente', lado: 'bottom',
      titulo: 'Datos del cliente',
      texto: 'Nombre y teléfono son obligatorios; el teléfono sirve para avisarle por WhatsApp.',
    },
    ingresoEquipo: {
      el: '#modal-nuevo .form-grid4:not(.form-grid-cliente)', lado: 'bottom',
      titulo: 'Datos del equipo',
      texto: 'Elige marca y modelo de la lista, o escribe uno nuevo. IMEI y contraseña son opcionales.',
    },
    ingresoFalla: {
      el: '#modal-nuevo input[name="dano_ingreso"]', lado: 'bottom',
      titulo: 'La falla',
      texto: 'Cuenta qué le pasa al equipo, con las palabras del cliente.',
    },
    ingresoServicio: {
      el: '#modal-nuevo input[name="valor_ingreso"]', lado: 'top',
      titulo: 'Valor y estado',
      texto: POR_ROL(
        'Anota el valor acordado y el estado de entrada. Más abajo puedes elegir el repuesto (opcional).',
        'Anota el valor acordado y el estado de entrada. Más abajo puedes elegir el repuesto (opcional).',
        'Anota el valor acordado. El equipo entra como "Ingresado" y el técnico lo toma desde ahí.'),
    },
    ingresoFoto: {
      el: '#nuevo-foto-drop', lado: 'top',
      titulo: 'Foto del equipo',
      texto: 'Opcional: una foto al recibirlo deja constancia de cómo llegó.',
    },
    ingresoGuardar: {
      el: '#btn-submit-nuevo', lado: 'top', alinear: 'end',
      titulo: 'Registrar el ingreso',
      texto: 'Se crea el código de seguimiento y podrás enviarlo por WhatsApp o imprimir la orden.',
    },

    detInfo: {
      el: '#modal-detalle .info-block', lado: 'bottom',
      titulo: 'Datos del servicio',
      texto: 'Lo registrado al ingreso, con el código que ve el cliente. ¿Teléfono mal? Corrígelo con el lápiz.',
    },
    detEstado: {
      el: '#det-status', lado: 'left',
      titulo: 'Estado del servicio',
      texto: POR_ROL(
        'Cámbialo según avanza el trabajo. Al marcarlo Entregado se descuentan del stock los repuestos usados.',
        'Cámbialo según avanza el trabajo. Al marcarlo Entregado se descuentan del stock los repuestos usados.',
        'Puedes marcarlo como Entregado cuando el cliente lo retire, o en Garantía. Los demás estados los cambia el técnico.'),
    },
    detValor: {
      el: '#grp-valor', lado: 'left',
      titulo: 'Valor',
      texto: 'El valor del servicio. Solo el administrador puede cambiarlo.',
    },
    detRepuestos: {
      el: '#det-rep-section', lado: 'right',
      titulo: 'Repuestos usados',
      texto: POR_ROL(
        'Agrega aquí los repuestos que usaste. Se descuentan del stock al marcar Entregado.',
        'Estos son los repuestos de este servicio. Se descuentan del stock al marcar Entregado.',
        'Estos son los repuestos de este servicio. Se descuentan del stock al marcar Entregado.'),
    },
    detNota: {
      el: '#det-obs', lado: 'left',
      titulo: 'Nota técnica',
      texto: 'Anota el avance o lo que encontraste. Queda en la línea de tiempo.',
    },
    detFotos: {
      el: '#det-foto-thumbs', lado: 'left',
      titulo: 'Fotos del servicio',
      texto: 'Sube hasta 3 fotos, por ejemplo del antes y el después.',
    },
    detTimeline: {
      el: '#modal-detalle .timeline-wrap', lado: 'right',
      titulo: 'Línea de tiempo',
      texto: 'El historial del servicio: estados y notas, con su fecha.',
    },
    detPie: {
      el: '#modal-detalle .modal-ft', lado: 'top',
      titulo: 'Avisar, imprimir y guardar',
      texto: 'Avisa al cliente por WhatsApp, imprime la orden y pulsa "Guardar" para aplicar tus cambios.',
    },

    repNombre: {
      el: '#modal-repuesto input[name="nombre"]', lado: 'bottom',
      titulo: 'Nombre del repuesto',
      texto: 'Un nombre que lo distinga, por ejemplo "Pantalla Samsung A54".',
    },
    repModelos: {
      el: '#tag-nuevo-modelo', lado: 'bottom',
      titulo: 'Modelos compatibles',
      texto: 'Escribe un modelo y pulsa Enter. Puedes agregar varios.',
    },
    repPrecio: {
      el: '#modal-repuesto input[name="precio_venta"]', lado: 'top',
      titulo: 'Precio de venta',
      texto: 'El precio al que cobras este repuesto.',
    },
    repStock: {
      el: '#modal-repuesto input[name="cantidad"]', lado: 'top',
      titulo: 'Stock inicial',
      texto: 'Cuántas unidades tienes hoy. Luego lo ajustas con + y −.',
    },
    repMinimo: {
      el: '#rep-nuevo-minimo', lado: 'top',
      titulo: 'Stock mínimo',
      texto: 'Si el stock baja de este número, te lo marcamos para reponer. Déjalo en 0 si no quieres aviso.',
    },

    editDatos: {
      el: '#edit-rep-admin-fields', lado: 'bottom',
      titulo: 'Datos del repuesto',
      texto: 'Cambia nombre, marca, modelos, precio y stock mínimo. Vale para todas las sucursales.',
    },
    editStock: {
      el: '#edit-rep-cantidad', lado: 'top',
      titulo: 'Corregir el stock',
      texto: POR_ROL(
        'Corrige la cantidad si el conteo real es distinto. Con varias sucursales, elige antes una en "Stock de".',
        'Corrige la cantidad de tu sucursal si el conteo real es distinto.',
        'Corrige la cantidad de tu sucursal si el conteo real es distinto.'),
    },

    ayuda: {
      el: '#btn-ayuda', menu: true, vista: 'servicios', lado: 'right',
      titulo: 'Ayuda cuando la necesites',
      texto: 'Con este botón repites el tour o escribes a Soporte. ¡Ya estás listo!',
    },
  };

  const TOURS = {
    general: ['bienvenida', 'menu', 'sucursal', 'nuevo', 'estados', 'lista', 'inventario', 'estadisticasKpis', 'configTabs', 'soporteNuevo', 'ayuda'],
    servicios: ['nuevo', 'estados', 'lista', 'exportar'],
    inventario: ['inventarioBuscar', 'inventarioStock', 'inventarioSucursal', 'inventarioEscaner', 'inventarioAgregar', 'inventario', 'inventarioQR'],
    estadisticas: ['estadisticasFiltros', 'estadisticasKpis'],
    config: ['configTabs', 'configUsuarios', 'configSucursales', 'configSuscripcion'],
    soporte: ['soporteNuevo', 'soporteLista'],
    // Ventanas: se lanzan con la ventana abierta (la primera vez que se abre, o con el "?" de su encabezado)
    ingreso: ['ingresoCliente', 'ingresoEquipo', 'ingresoFalla', 'ingresoServicio', 'ingresoFoto', 'ingresoGuardar'],
    detalle: ['detInfo', 'detEstado', 'detValor', 'detRepuestos', 'detNota', 'detFotos', 'detTimeline', 'detPie'],
    repuesto: ['repNombre', 'repModelos', 'repPrecio', 'repStock', 'repMinimo'],
    editrepuesto: ['editDatos', 'editStock'],
  };

  // ── Utilidades ───────────────────────────────────────────────────────────────────────────────────────────────
  const $ = sel => document.querySelector(sel);
  const pausa = ms => new Promise(r => setTimeout(r, ms));
  const visible = el => !!el && el.getClientRects().length > 0;
  const textoDe = (v, rol) => (typeof v === 'function' ? v(rol) : v) || '';

  // Un elemento de otra pantalla no esta visible aunque exista: se comprueba que ninguno de sus contenedores
  // (hasta la pantalla) tenga la clase "hidden" (ej. el selector de sucursal cuando hay una sola).
  function ocultoPorClase(el) {
    for (let n = el; n && !n.classList?.contains('view'); n = n.parentElement) {
      if (n.classList?.contains('hidden') || n.style?.display === 'none') return true;   // ej. el boton de escanear QR solo existe en el celular
    }
    return false;
  }

  function aplica(p, rol) {
    if (p.solo && !p.solo.includes(rol)) return false;
    if (!p.el) return true;
    const el = $(p.el);
    if (!el) return false;
    return p.vista ? !ocultoPorClase(el) : visible(el);
  }

  // Los pasos de un tour que corresponden a este usuario.
  function construir(id, rol) {
    return (TOURS[id] || []).map(k => ({ id: k, ...PASOS[k] })).filter(p => aplica(p, rol));
  }

  // ── Preparacion de cada paso (cambiar de pantalla, abrir el menu del celular) ─────────────────────────────────
  const enMovil = () => visible($('#btn-hamburger'));

  function menuMovil(abrir) {
    if (!enMovil()) return;
    const abierto = !!$('#sidebar')?.classList.contains('open');
    if (abierto !== abrir) $('#btn-hamburger')?.click();
  }

  function cambiarVista(v) {
    if ($('.view.active')?.id === 'view-' + v || typeof window.switchView !== 'function') return;
    window.switchView(v, document.querySelector('.nav-link[data-view="' + v + '"]'));
  }

  async function esperarVisible(sel, ms) {
    const t0 = performance.now();
    while (performance.now() - t0 < ms) { const el = $(sel); if (visible(el)) return el; await pausa(80); }
    return null;
  }

  async function preparar(p) {
    if (p.vista) cambiarVista(p.vista);
    menuMovil(!!p.menu);
    if (!p.el) return true;
    if (p.menu && enMovil()) await pausa(280);          // el menu se desliza al abrirse
    return !!(await esperarVisible(p.el, p.vista ? 4000 : 1500));
  }

  // ── Ejecucion ─────────────────────────────────────────────────────────────────────────────────────────────────
  const T = { vistos: null, activo: null };
  let drv = null, lista = [], idTour = '', volverA = '', ocupado = false, cerrando = false;

  function guardar(id, estado) {
    fetch(BASE + '/api/tour.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ tour: id, version: VERSION[id] || 1, estado }),
    }).catch(() => { /* si falla, simplemente se volvera a ofrecer */ });
    try { const el = document.getElementById('boot-tour'); const v = JSON.parse(el?.textContent || '{}'); v[id] = { version: VERSION[id] || 1, estado }; if (el) el.textContent = JSON.stringify(v); } catch (e) {}
  }

  async function terminar(estado) {
    if (cerrando || !drv) return;
    cerrando = true;
    const id = idTour, d = drv;
    drv = null;
    try { d.destroy(); } catch (e) {}
    menuMovil(false);
    if (volverA) cambiarVista(volverA);                 // se devuelve al usuario a la pantalla donde estaba
    // Si ya lo habia completado y ahora solo lo repite y lo cierra a medias, no se pisa el "completado".
    if (!(estado === 'saltado' && T.vistos?.()[id]?.estado === 'completado')) guardar(id, estado);
    T.activo = null;
    cerrando = false;
  }

  // Avanza (dir = 1) o retrocede (dir = -1) al siguiente paso que se pueda mostrar.
  async function ir(dir) {
    if (ocupado || !drv) return;
    ocupado = true;
    try {
      let i = drv.getActiveIndex() + dir;
      while (i >= 0 && i < lista.length) {
        if (await preparar(lista[i])) { drv.moveTo(i); return; }
        i += dir;
      }
      if (dir > 0) await terminar('completado');
    } finally { ocupado = false; }
  }

  async function iniciar(id, opciones) {
    if (!window.driver?.js?.driver) return false;
    if (drv) { try { drv.destroy(); } catch (e) {} drv = null; }
    const rol = ROL;
    lista = construir(id, rol);
    if (!lista.length) return false;
    idTour  = id;
    volverA = ($('.view.active')?.id || 'view-servicios').replace(/^view-/, '');
    cerrando = false; ocupado = false;

    drv = window.driver.js.driver({
      showProgress: true,
      progressText: '{{current}} de {{total}}',
      nextBtnText: 'Siguiente', prevBtnText: 'Atrás', doneBtnText: 'Terminar',
      allowClose: true, allowKeyboardControl: true, smoothScroll: true, animate: true,
      overlayColor: '#000', overlayOpacity: 0.62, stagePadding: 6, stageRadius: 10, popoverOffset: 12,
      popoverClass: 'ct-tour',
      overlayClickBehavior: () => {},                    // un clic fuera no cierra por accidente
      steps: lista.map(p => ({
        element: p.el ? () => $(p.el) : undefined,
        popover: { title: p.titulo, description: textoDe(p.texto, rol), side: p.lado || 'bottom', align: p.alinear || 'center' },
      })),
      onNextClick: () => ir(1),
      onPrevClick: () => ir(-1),
      onDestroyStarted: () => { terminar('saltado'); },   // X o Escape
      onPopoverRender: (pop) => {
        pop.wrapper.setAttribute('role', 'dialog');
        pop.wrapper.setAttribute('aria-label', 'Tour guiado');
        const pie = pop.footer;
        if (pie && !pie.querySelector('.ct-tour-saltar')) {
          const b = document.createElement('button');
          b.type = 'button'; b.className = 'ct-tour-saltar'; b.textContent = 'Saltar tour';
          b.addEventListener('click', () => terminar('saltado'));
          pie.insertBefore(b, pie.firstChild);
        }
      },
    });
    T.activo = id;

    let i = 0;
    while (i < lista.length && !(await preparar(lista[i]))) i++;
    if (i >= lista.length) { drv = null; T.activo = null; return false; }
    // El tour que se ofrece solo queda anotado en cuanto aparece: si el usuario recarga o cierra la pagina a la mitad,
    // no vuelve a salirle en el siguiente ingreso. Al terminarlo, queda como "completado".
    if (opciones && opciones.auto) guardar(id, 'saltado');
    drv.drive(i);
    return true;
  }

  T.vistos = () => { try { return JSON.parse($('#boot-tour')?.textContent || '{}') || {}; } catch (e) { return {}; } };
  T.iniciar = iniciar;
  T.construir = construir;           // para las pruebas
  T.PASOS = PASOS; T.TOURS = TOURS; T.VERSION = VERSION;
  window.TOUR = T;
})();
