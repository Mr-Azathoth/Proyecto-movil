// Navegacion rapida (servidor): datos de arranque de sucursales dentro del HTML y cache de assets versionados.
const { Client, ids } = require('./lib');
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro');
  const boot = html => { const m = html.match(/<script type="application\/json" id="boot-sucursales">([\s\S]*?)<\/script>/); return m ? m[1] : null; };

  // ── Datos de arranque ──────────────────────────────────────
  let r = await adm.get('/app.php');
  let raw = boot(r.text);
  ok('app.php trae el bloque de datos de sucursales', raw !== null, r.text.slice(0, 0));
  const dato = raw ? JSON.parse(raw) : null;
  const api = (await adm.get('/api/sucursales.php')).json.data;
  ok('...y es identico a lo que devuelve la API', dato && JSON.stringify(dato) === JSON.stringify(api), '');
  ok('...con base, permisos y casa matriz', dato && dato.base > 0 && Array.isArray(dato.escritura) && 'matriz' in dato && dato.sucursales.length === 3, JSON.stringify(dato && Object.keys(dato)));
  ok('el bloque es un dato, no codigo (no se ejecuta ni lo toca el CSP)', /type="application\/json"/.test(r.text), '');

  // El tecnico recibe lo suyo: solo sucursales activas y su propia sucursal base
  r = await tc.get('/app.php');
  const dt = JSON.parse(boot(r.text));
  const at = (await tc.get('/api/sucursales.php')).json.data;
  ok('el tecnico recibe lo mismo que su API (solo activas y su base)', JSON.stringify(dt) === JSON.stringify(at) && dt.sucursales.every(s => s.activa === 1), '');

  // Un nombre malicioso no puede cerrar la etiqueta ni inyectar HTML
  const feo = '</script><img src=x onerror=alert(1)>&"\'';
  let c = await adm.post('/api/sucursales.php', { nombre: feo.slice(0, 80), direccion: '', telefono: '' });
  ok('se crea una sucursal con un nombre con HTML', c.json?.ok === true, c.text.slice(0, 100));
  r = await adm.get('/app.php');
  raw = boot(r.text);
  ok('el nombre no cierra la etiqueta ni queda sin escapar en el HTML', raw !== null && !/<img src=x/.test(raw) && !r.text.includes('</script><img'), raw && raw.slice(0, 120));
  const parsed = JSON.parse(raw);
  ok('...y al leerlo como JSON el nombre llega intacto', parsed.sucursales.some(s => s.nombre === feo.slice(0, 80)), '');
  if (c.json?.data?.id) await adm.put('/api/sucursales.php', { id_sucursal: c.json.data.id, activa: 0 });

  // ── Cache de assets versionados ────────────────────────────
  const js = (await adm.get('/app.php')).text.match(/assets\/js\/app\.js\?v=\d+/)[0];
  r = await adm.get('/' + js);
  ok('app.js con ?v=<numero> se guarda un año (immutable)', /max-age=31536000/.test(r.headers['cache-control'] || '') && /immutable/.test(r.headers['cache-control'] || ''), r.headers['cache-control']);
  r = await adm.get('/assets/css/style.css?v=123');
  ok('style.css con ?v tambien', /immutable/.test(r.headers['cache-control'] || ''), r.headers['cache-control']);
  r = await adm.get('/assets/js/app.js');
  ok('app.js SIN ?v no se cachea por un año', !/immutable/.test(r.headers['cache-control'] || ''), r.headers['cache-control']);
  r = await adm.get('/assets/js/app.js?v=abc');
  ok('un ?v que no es numerico tampoco', !/immutable/.test(r.headers['cache-control'] || ''), r.headers['cache-control']);
  r = await adm.get('/assets/js/chart.umd.min.js');
  ok('las librerias sin version no se tocan', !/immutable/.test(r.headers['cache-control'] || ''), r.headers['cache-control']);
  r = await adm.get('/api/sucursales.php');
  ok('las respuestas dinamicas (API) no llevan cache de assets', !/immutable/.test(r.headers['cache-control'] || ''), r.headers['cache-control']);

  // ── Librerias pesadas bajo demanda ─────────────────────────
  r = await adm.get('/app.php');
  const pesadas = ['chart.umd.min.js', 'qrcode.min.js', 'jsqr.min.js', 'xlsx.mini.min.js'];
  ok('app.php YA NO descarga las 4 librerias pesadas al arrancar', pesadas.every(f => !r.text.includes('<script src') || !new RegExp('<script[^>]+' + f.replace(/\./g, '\\.')).test(r.text)), '');
  const libs = JSON.parse(r.text.match(/data-libs="([^"]*)"/)[1].replace(/&quot;/g, '"'));
  ok('...pero entrega la version de cada una para cargarlas despues', ['chart', 'qrcode', 'jsqr', 'xlsx'].every(k => Number.isInteger(libs[k]) && libs[k] > 1e9), JSON.stringify(libs));
  const arch = { chart: 'chart.umd.min.js', qrcode: 'qrcode.min.js', jsqr: 'jsqr.min.js', xlsx: 'xlsx.mini.min.js' };
  for (const k of Object.keys(arch)) {
    const f = await adm.get('/assets/js/' + arch[k] + '?v=' + libs[k]);
    ok('la libreria ' + k + ' se sirve con su version y cache de un año', f.status === 200 && /immutable/.test(f.headers['cache-control'] || '') && f.text.length > 5000, f.status + ' ' + f.headers['cache-control']);
  }
  r = await adm.get('/assets/js/app.js?v=1');
  ok('app.js define cargarLib y la usa en graficos, QR, lector y Excel', ['function cargarLib', "cargarLib('chart')", "cargarLib('qrcode')", "cargarLib('jsqr')", "cargarLib('xlsx')"].every(t => r.text.includes(t)), '');

  // ── Fuentes alojadas en el servidor ────────────────────────
  const fs = require('fs'), path = require('path');
  const raiz = path.resolve(__dirname, '..');
  const archivos = [];
  const recorrer = d => { for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    if (['vendor', 'tests', 'node_modules', 'sesiones', 'uploads', '.git', 'fonts'].includes(e.name)) continue;
    const f = path.join(d, e.name); if (e.isDirectory()) recorrer(f); else if (/\.(php|css|html|js)$/.test(e.name)) archivos.push(f);
  } };
  recorrer(raiz);
  const conGoogle = archivos.filter(f => /fonts\.(googleapis|gstatic)\.com/.test(fs.readFileSync(f, 'utf8')) && !/config\.example/.test(f)).map(f => path.relative(raiz, f));
  ok('ningun archivo del proyecto pide fuentes a Google', conGoogle.length === 0, conGoogle.join(', '));

  const fcss = (await adm.get('/assets/css/fonts.css')).text;
  const urls = [...fcss.matchAll(/url\('\.\.\/fonts\/([^']+)'\)/g)].map(m => m[1]);
  ok('fonts.css declara las 5 fuentes (Inter, Outfit y los iconos)', urls.length === 5 && /Inter/.test(fcss) && /Outfit/.test(fcss) && /Material Icons Round/.test(fcss), urls.join(','));
  for (const u of urls) {
    const f = await adm.get('/assets/fonts/' + u);
    ok('fuente ' + u + ': existe, es woff2 y se guarda un año', f.status === 200 && /font\/woff2/.test(f.headers['content-type'] || '') && /immutable/.test(f.headers['cache-control'] || '') && f.text.length > 1000, f.status + ' ' + f.headers['content-type']);
  }
  ok('los iconos se muestran sin parpadeo del nombre (font-display: block)', /Material Icons Round[^}]*font-display:block/.test(fcss.replace(/\s+/g, ' ')), '');
  ok('y el texto usa swap (se ve de inmediato con la fuente del sistema)', /Inter[^}]*font-display:swap/.test(fcss.replace(/\s+/g, ' ')), '');
  ok('la clase .material-icons-round viene en fonts.css', /\.material-icons-round\s*\{[^}]*font-family:'Material Icons Round'/.test(fcss), '');

  for (const pg of ['/ingresar.php', '/registro.php', '/recuperar.php', '/seguimiento.php']) {
    const h = (await new Client().get(pg));
    ok('pagina publica ' + pg + ' usa fonts.css y ninguna fuente externa', h.status === 200 && /assets\/css\/fonts\.css\?v=\d+/.test(h.text) && !/fonts\.(googleapis|gstatic)/.test(h.text), h.status);
  }
  r = await adm.get('/app.php');
  ok('app.php precarga las tres fuentes del primer dibujo', ['inter-v20-latin', 'outfit-v15-latin', 'material-icons-round-v109'].every(n => new RegExp('rel="preload" href="[^"]*' + n + '\\.woff2" as="font" type="font/woff2" crossorigin').test(r.text)), '');
  ok('la politica de seguridad ya no permite ningun origen externo de fuentes ni estilos', /font-src 'self';/.test(r.headers['content-security-policy'] || '') && !/google/.test(r.headers['content-security-policy'] || ''), r.headers['content-security-policy']);
  const st = (await adm.get('/assets/css/style.css')).text;
  ok('style.css ya no importa estilos externos', !/@import\s+url\(['"]?https?:/.test(st), '');

  // ── Cambio de cargo con confirmacion hacia/desde Admin ─────
  const appJs = (await adm.get('/assets/js/app.js?v=1')).text;
  ok('app.js pide confirmacion al dar o quitar el cargo de administrador desde el selector', /Dar acceso de administrador/.test(appJs) && /Quitar acceso de administrador/.test(appJs) && /nuevo === 'Admin' \|\| actual === 'Admin'/.test(appJs), '');

  // Inventario en escritorio: botones de acciones en una sola linea
  const cssInv = (await adm.get('/assets/css/style.css')).text;
  ok('style.css mantiene los botones de acciones del inventario en una sola linea (solo escritorio)', /@media \(min-width: 901px\)\s*\{\s*#tbl-inventario \.row-actions\s*\{\s*flex-wrap:\s*nowrap/.test(cssInv), '');

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
