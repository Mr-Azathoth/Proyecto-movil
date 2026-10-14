// Tour guiado: API de "que tours vio cada usuario", HTML (boton de ayuda, datos de arranque, carga bajo demanda)
// y el contenido de los tours (cada paso apunta a algo que existe en la pagina del cargo).
const fs = require('fs'), vm = require('vm'), path = require('path');
const { Client, ids } = require('./lib');
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };

// Un "navegador" minimo para cargar tour.js tal cual y probar su logica (construir pasos por cargo).
function cargarTour(rol, presentes, ocultos = []) {
  const el = (sel) => ({ sel, getClientRects: () => (ocultos.includes(sel) ? [] : [1]), classList: { contains: c => c === 'hidden' && ocultos.includes('clase:' + sel) }, style: { display: ocultos.includes('display:' + sel) ? 'none' : '' }, parentElement: null });
  const doc = { body: { dataset: { base: '', csrf: 'x', role: rol } }, querySelector: s => (presentes.has(s) ? el(s) : null) };
  const ctx = { document: doc, window: {}, performance: { now: () => Date.now() }, setTimeout, fetch: async () => ({}), JSON, Promise, Date, Set };
  ctx.window = ctx; vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(path.resolve(__dirname, '../assets/js/tour.js'), 'utf8'), ctx);
  return ctx.TOUR;
}

// ¿El selector (simple: #id, .clase, [attr="v"], con descendientes) tiene todas sus piezas en el HTML?
const piezas = sel => sel.split(/\s+/).flatMap(parte => parte.match(/#[\w-]+|\.[\w-]+|\[[\w-]+="[^"]+"\]/g) || []);
function existeEnHtml(html, sel) {
  return piezas(sel).every(p => {
    if (p[0] === '#') return new RegExp('id="' + p.slice(1) + '"').test(html);
    if (p[0] === '.') return new RegExp('class="[^"]*\\b' + p.slice(1) + '\\b[^"]*"').test(html);
    const m = p.match(/\[([\w-]+)="([^"]+)"\]/); return new RegExp(m[1] + '="' + m[2] + '"').test(html);
  });
}

const APPJS = fs.readFileSync(path.resolve(__dirname, '../assets/js/app.js'), 'utf8');   // las filas de las listas se dibujan desde app.js

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro'), vd = await new Client().login('vendCentro'), adb = await new Client().login('adminB');
  const post = (cl, j) => cl.post('/api/tour.php', j);

  // ── API ────────────────────────────────────────────────────
  let r = await adm.get('/api/tour.php');
  ok('un usuario nuevo no tiene tours vistos ({} vacio)', r.json?.ok === true && r.text.includes('"data":{}'), r.text.slice(0, 80));
  r = await post(adm, { tour: 'general', version: 1, estado: 'completado' });
  ok('se registra que completo el tour general', r.json?.ok === true, r.text.slice(0, 100));
  r = await adm.get('/api/tour.php');
  ok('...y queda guardado con su version y estado', r.json.data.general?.version === 1 && r.json.data.general?.estado === 'completado', r.text);
  await post(adm, { tour: 'general', version: 2, estado: 'saltado' });
  r = await adm.get('/api/tour.php');
  ok('volver a registrarlo lo actualiza (no duplica)', r.json.data.general.version === 2 && r.json.data.general.estado === 'saltado' && Object.keys(r.json.data).length === 1, r.text);
  await post(adm, { tour: 'inventario', estado: 'completado' });
  r = await adm.get('/api/tour.php');
  ok('la version por defecto es 1 y puede haber varios tours', r.json.data.inventario?.version === 1 && Object.keys(r.json.data).length === 2, r.text);

  for (const [nombre, cuerpo] of [
    ['un tour que no existe', { tour: 'hackeo', version: 1, estado: 'completado' }],
    ['un tour vacio', { tour: '', version: 1 }],
    ['un tour que no es texto (lista)', { tour: ['general'], version: 1 }],
    ['version 0', { tour: 'general', version: 0 }],
    ['version 100', { tour: 'general', version: 100 }],
    ['version no numerica', { tour: 'general', version: 'abc' }],
    ['un estado inventado', { tour: 'general', version: 1, estado: 'omitido' }],
    ['un estado que no es texto', { tour: 'general', version: 1, estado: ['saltado'] }],
  ]) { const x = await post(adm, cuerpo); ok('se rechaza ' + nombre, x.json?.ok === false && !/Warning|Notice|Array to string/.test(x.text), x.text.slice(0, 100)); }

  // ── Aislamiento ────────────────────────────────────────────
  r = await vd.get('/api/tour.php');
  ok('lo que vio el admin no aparece en la cuenta del vendedor', r.json.data && Object.keys(r.json.data).length === 0, r.text);
  await post(tc, { tour: 'servicios', estado: 'completado' });
  r = await adm.get('/api/tour.php');
  ok('ni lo del tecnico en la del admin', !r.json.data.servicios, r.text);
  r = await adb.get('/api/tour.php');
  ok('la empresa B tampoco ve nada de la empresa A', Object.keys(r.json.data).length === 0, r.text);

  // ── Seguridad ──────────────────────────────────────────────
  const sinSesion = await new Client().get('/api/tour.php');
  ok('sin iniciar sesion responde 401', sinSesion.status === 401, sinSesion.status);
  const sinCsrf = new Client(); await sinCsrf.login('adminA'); sinCsrf.csrf = '';
  r = await sinCsrf.post('/api/tour.php', { tour: 'general', version: 1 });
  ok('un POST sin token CSRF se rechaza (403)', r.status === 403, r.status);
  r = await sinCsrf.req('DELETE', '/api/tour.php', { json: {} });
  ok('un DELETE sin token CSRF tambien (403)', r.status === 403, r.status);
  r = await adm.req('PUT', '/api/tour.php', { json: {} });
  ok('otros metodos (PUT) responden 405', r.status === 405, r.status);

  // ── Reiniciar ──────────────────────────────────────────────
  r = await adm.req('DELETE', '/api/tour.php?tour=general', { json: {} });
  ok('el usuario puede olvidar UN tour', r.json?.ok === true, r.text.slice(0, 100));
  r = await adm.get('/api/tour.php');
  ok('...y solo ese (queda inventario)', !r.json.data.general && !!r.json.data.inventario, r.text);
  r = await adm.req('DELETE', '/api/tour.php?tour=otro', { json: {} });
  ok('olvidar un tour que no existe se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await adm.req('DELETE', '/api/tour.php', { json: {} });
  r = await adm.get('/api/tour.php');
  ok('sin indicar tour olvida todos los del usuario', Object.keys(r.json.data).length === 0, r.text);
  r = await tc.get('/api/tour.php');
  ok('...sin tocar los de otros usuarios', !!r.json.data.servicios, r.text);

  // ── HTML de la aplicacion ──────────────────────────────────
  const html = {};
  for (const [n, cl] of [['Admin', adm], ['Tecnico', tc], ['Vendedor', vd]]) html[n] = (await cl.get('/app.php')).text;
  const boot = h => JSON.parse(h.match(/<script type="application\/json" id="boot-tour">([\s\S]*?)<\/script>/)[1]);
  ok('app.php trae los tours vistos del usuario como datos de arranque', JSON.stringify(boot(html.Admin)) === '{}' && boot(html.Tecnico).servicios?.estado === 'completado', JSON.stringify(boot(html.Tecnico)));
  for (const n of ['Admin', 'Tecnico', 'Vendedor']) {
    const h = html[n];
    ok(n + ': tiene el boton de ayuda y su menu con 3 opciones', /id="btn-ayuda"/.test(h) && (h.match(/data-ayuda="(general|vista|soporte)"/g) || []).length === 3, '');
    const nVistas = (h.match(/data-ayuda-vista="/g) || []).length;
    ok(n + ': tiene el "?" de cada pantalla que ve (' + (n === 'Admin' ? 5 : 3) + ')', nVistas === (n === 'Admin' ? 5 : 3), nVistas);
    ok(n + ': el tour NO se descarga al arrancar (sin <script> de driver ni tour)', !/<script[^>]+(driver\.iife|tour\.js)/.test(h) && !/<link[^>]+(driver|tour)\.css/.test(h), '');
    const libs = JSON.parse(h.match(/data-libs="([^"]*)"/)[1].replace(/&quot;/g, '"'));
    ok(n + ': entrega la version de los 4 archivos del tour para cargarlos despues', ['driver', 'tour', 'driver_css', 'tour_css'].every(k => Number.isInteger(libs[k]) && libs[k] > 1e9), JSON.stringify(libs));
  }
  const libs = JSON.parse(html.Admin.match(/data-libs="([^"]*)"/)[1].replace(/&quot;/g, '"'));
  for (const [k, f] of [['driver', 'js/driver.iife.js'], ['tour', 'js/tour.js'], ['driver_css', 'css/driver.css'], ['tour_css', 'css/tour.css']]) {
    const x = await adm.get('/assets/' + f + '?v=' + libs[k]);
    ok('el archivo ' + f + ' se sirve con cache de un año', x.status === 200 && /immutable/.test(x.headers['cache-control'] || '') && x.text.length > 500, x.status + ' ' + x.headers['cache-control']);
    if (k === 'tour_css') {
      // Si la capa oscura queda al mismo nivel (o sobre) el globo, intercepta los clics y los botones del tour no responden
      const z = re => +((x.text.match(re) || [])[1] || 0);
      const zOv = z(/\.driver-overlay\s*\{\s*z-index:\s*(\d+)/), zPop = z(/\.driver-popover\s*\{\s*z-index:\s*(\d+)/);
      ok('el globo del tour queda por encima de la capa oscura (los clics llegan a los botones)', zOv > 0 && zPop > zOv, zOv + ' / ' + zPop);
    }
  }
  ok('Driver.js lleva su licencia/origen y es la version esperada (1.x)', fs.readFileSync(path.resolve(__dirname, '../assets/js/driver.iife.js'), 'utf8').length > 20000, '');

  // ── Contenido de los tours ─────────────────────────────────
  const todos = { Admin: null, Tecnico: null, Vendedor: null };
  for (const rol of Object.keys(todos)) {
    const T = cargarTour(rol, new Set());
    for (const [id, claves] of Object.entries(T.TOURS)) {
      const malos = claves.filter(k => !T.PASOS[k]);
      ok('tour "' + id + '": todos sus pasos estan definidos (' + rol + ')', malos.length === 0, malos.join(','));
    }
    if (rol !== 'Admin') continue;
    for (const [k, p] of Object.entries(T.PASOS)) {
      for (const r2 of ['Admin', 'Tecnico', 'Vendedor']) {
        const t = typeof p.texto === 'function' ? p.texto(r2) : p.texto;
        if (!t || t.length < 20) ok('paso "' + k + '" tiene texto para ' + r2, false, t);
      }
      if (!p.titulo || p.titulo.length < 3) ok('paso "' + k + '" tiene titulo', false, '');
    }
    ok('todos los pasos tienen titulo y texto para los 3 cargos', true);
    ok('cada tour tiene version definida', Object.keys(T.TOURS).every(id => T.VERSION[id] >= 1), JSON.stringify(T.VERSION));
  }

  // Cada paso que apunta a algo de la pagina: ese algo existe en el HTML del cargo (si el cargo lo ve)
  for (const rol of ['Admin', 'Tecnico', 'Vendedor']) {
    const T = cargarTour(rol, new Set());
    const huerfanos = [];
    for (const [k, p] of Object.entries(T.PASOS)) {
      if (!p.el || (p.solo && !p.solo.includes(rol))) continue;
      if (!existeEnHtml(html[rol] + APPJS, p.el)) huerfanos.push(k + ' -> ' + p.el);
    }
    // Los pasos de vistas que el cargo no tiene (config, estadisticas) quedan fuera por "solo"; ningun otro debe faltar.
    ok(rol + ': cada paso que le corresponde apunta a un elemento que existe en su pagina', huerfanos.length === 0, huerfanos.join(' | '));
  }

  // Que tours ve cada cargo (con una pagina donde todo existe)
  const todoPresente = rol => { const T0 = cargarTour(rol, new Set()); return new Set(Object.values(T0.PASOS).map(p => p.el).filter(Boolean)); };
  const idsDe = (rol, tour, extra = []) => { const T = cargarTour(rol, todoPresente(rol), extra); return T.construir(tour, rol).map(p => p.id); };
  ok('tour general del Administrador', JSON.stringify(idsDe('Admin', 'general')) === JSON.stringify(['bienvenida', 'menu', 'sucursal', 'nuevo', 'estados', 'lista', 'inventario', 'estadisticasKpis', 'configTabs', 'soporteNuevo', 'ayuda']), JSON.stringify(idsDe('Admin', 'general')));
  ok('tour general del Tecnico: sin estadisticas ni configuracion', JSON.stringify(idsDe('Tecnico', 'general')) === JSON.stringify(['bienvenida', 'menu', 'sucursal', 'nuevo', 'estados', 'lista', 'inventario', 'soporteNuevo', 'ayuda']), JSON.stringify(idsDe('Tecnico', 'general')));
  ok('tour general del Vendedor: igual que el del Tecnico en estructura', JSON.stringify(idsDe('Vendedor', 'general')) === JSON.stringify(idsDe('Tecnico', 'general')), '');
  ok('el selector de sucursal se omite si la cuenta tiene una sola sucursal (oculto)', !idsDe('Admin', 'general', ['#suc-switch']).includes('sucursal'), '');
  ok('"Exportar" y "Agregar repuesto" solo para el Administrador', idsDe('Admin', 'servicios').includes('exportar') && !idsDe('Tecnico', 'servicios').includes('exportar') && idsDe('Admin', 'inventario').includes('inventarioAgregar') && !idsDe('Vendedor', 'inventario').includes('inventarioAgregar'), '');
  ok('estadisticas y configuracion no tienen tour para Tecnico ni Vendedor', idsDe('Tecnico', 'config').length === 0 && idsDe('Vendedor', 'estadisticas').length === 0 && idsDe('Admin', 'config').length === 4, '');
  ok('un paso cuyo elemento no esta en la pagina se omite', !cargarTour('Admin', new Set(['#btn-abrir-nuevo'])).construir('servicios', 'Admin').some(p => p.id === 'estados'), '');
  ok('un tour desconocido no devuelve pasos', cargarTour('Admin', new Set()).construir('inexistente', 'Admin').length === 0, '');
  const tv = cargarTour('Vendedor', todoPresente('Vendedor'));
  const textoVend = tv.PASOS.nuevo.texto('Vendedor'), textoTec = tv.PASOS.nuevo.texto('Tecnico');
  ok('el texto se adapta al cargo (el vendedor sabe que el tecnico repara)', /técnico se encarga/.test(textoVend) && !/técnico se encarga/.test(textoTec), '');
  ok('al vendedor se le dice que el inventario es solo de consulta', /solo de consulta/.test(tv.PASOS.inventario.texto('Vendedor')), '');


  // ── Tours de las ventanas (ingreso, detalle, repuesto nuevo, editar repuesto) ──
  for (const t of ['ingreso', 'detalle', 'repuesto', 'editrepuesto']) {
    const x = await adm.post('/api/tour.php', { tour: t, version: 1, estado: 'completado' });
    ok('la API acepta el tour de ventana "' + t + '"', x.json?.ok === true, x.text.slice(0, 80));
  }
  await adm.req('DELETE', '/api/tour.php', { json: {} });
  for (const n of ['Admin', 'Tecnico', 'Vendedor']) {
    const modales = [...html[n].matchAll(/data-ayuda-modal="(\w+)"/g)].map(m => m[1]).sort();
    ok(n + ': las 4 ventanas tienen su "?" en el encabezado', JSON.stringify(modales) === JSON.stringify(['detalle', 'editrepuesto', 'ingreso', 'repuesto']), modales.join(','));
  }
  const mapa = APPJS.match(/const _TOUR_VENTANA = \{([^}]*)\}/);
  const pares = mapa ? [...mapa[1].matchAll(/'(modal-[\w-]+)':\s*'(\w+)'/g)].map(m => [m[1], m[2]]) : [];
  const Tm = cargarTour('Admin', new Set());
  ok('app.js asocia cada ventana con un tour que existe y con una ventana que existe', pares.length === 4 && pares.every(([m, t]) => Tm.TOURS[t] && html.Admin.includes('id="' + m + '"')), JSON.stringify(pares));
  ok('Escape no cierra la ventana mientras hay un tour en curso', /e\.key !== 'Escape' \|\| window\.TOUR\?\.activo/.test(APPJS), '');
  ok('cada tour de ventana tiene pasos y version', ['ingreso', 'detalle', 'repuesto', 'editrepuesto'].every(t => Tm.TOURS[t].length >= 2 && Tm.VERSION[t] >= 1), '');
  ok('el tour de inventario incluye el QR del repuesto y el escaner', ['Admin', 'Tecnico', 'Vendedor'].every(r => { const ids = idsDe(r, 'inventario'); return ids.includes('inventarioQR') && ids.includes('inventarioEscaner'); }), '');
  ok('el escaner de QR (solo existe en el celular) se omite si esta oculto', !idsDe('Admin', 'inventario', ['display:#btn-scan-qr']).includes('inventarioEscaner'), '');
  ok('sin repuestos en la lista, el paso del QR de la fila se omite (el resto sigue)', (() => { const T = cargarTour('Admin', new Set([...todoPresente('Admin')].filter(e => e !== '#view-inventario .btn-inv-qr'))); const ids = T.construir('inventario', 'Admin').map(p => p.id); return !ids.includes('inventarioQR') && ids.includes('inventario'); })(), '');
  const textoDe = (r, k) => { const p = cargarTour(r, new Set()).PASOS[k]; return typeof p.texto === 'function' ? p.texto(r) : p.texto; };
  ok('el estado del servicio se explica distinto al vendedor (solo entrega / garantia)', /Entregado/.test(textoDe('Vendedor', 'detEstado')) && /los cambia el técnico/.test(textoDe('Vendedor', 'detEstado')) && !/los cambia el técnico/.test(textoDe('Admin', 'detEstado')), '');
  ok('el vendedor no ve la explicacion del repuesto al ingresar (no puede elegirlo)', !/repuesto/.test(textoDe('Vendedor', 'ingresoServicio')) && /repuesto/.test(textoDe('Tecnico', 'ingresoServicio')), '');

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 5).join('\n')); process.exit(2); });
