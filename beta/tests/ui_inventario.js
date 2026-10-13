// Frontend real (app.js + sucursales.js) sobre un DOM simulado contra beta. Debe correr despues de stock.js.
const { Client, creds, ids, fixtures, BETA_DIR, PHP_BIN } = require('./lib');
const fs=require('fs'), vm=require('vm');
(async()=>{
  const cli=await new Client().login('adminA');
  const els={};
  const mk=(id)=>els[id]||(els[id]={id,value:'',innerHTML:'',textContent:'',dataset:{},style:{},classList:{add(){},remove(){},toggle(){},contains(){return false}},
    setAttribute(){},addEventListener(){},removeEventListener(){},querySelector(){return null},querySelectorAll(){return[]},closest(){return null},appendChild(){},focus(){},click(){}});
  const doc={body:{style:{},classList:{add(){},remove(){},toggle(){}},dataset:{base:'',csrf:cli.csrf,role:'Admin',user:'adminA',nombre:'Admin A',uid:'1'}},
    getElementById:mk,querySelectorAll:()=>[],querySelector:()=>null,addEventListener(){},createElement:()=>({set textContent(v){this._t=v},get innerHTML(){return String(this._t).replace(/&/g,'&amp;').replace(/</g,'&lt;')}})};
  const doFetch=async(url,opts={})=>{const m=(opts.method||'GET');
    const r=await cli.req(m,url.replace(/^https?:\/\/[^/]+/,''),opts.body?{json:JSON.parse(opts.body)}:{});
    return {status:r.status,ok:r.status<400,headers:{get:h=>/content-type/i.test(h)?'application/json':null},clone(){return this},json:async()=>r.json,text:async()=>r.text};};
  const ctx={document:doc,window:null,fetch:doFetch,console,setTimeout,clearTimeout,localStorage:{getItem(){return null},setItem(){}},
    location:{origin:'https://x',href:''},navigator:{},alert:console.log,URLSearchParams,FormData:class{},Map,Promise,Intl,Date,JSON,Math,parseInt,String,Array,Object,Error,encodeURIComponent,Set,
    Response:class{},IntersectionObserver:class{observe(){}},MutationObserver:class{observe(){}},requestAnimationFrame:f=>f(),matchMedia:()=>({matches:false,addEventListener(){}}),
    performance:{now:()=>0},HTMLElement:class{},Element:class{},Node:class{}};
  ctx.window=ctx; vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(BETA_DIR + '/assets/js/sucursales.js','utf8'),ctx);
  const app=fs.readFileSync(BETA_DIR + '/assets/js/app.js','utf8').split("document.addEventListener('DOMContentLoaded', async () => {")[0];
  vm.runInContext(app+'\n;globalThis.__t={loadServicios,loadInventario,alterStock,submitEditRepuesto,openInvEdit,refrescarRepuestosNuevo,waLink,get inv(){return Array.from(_invMap.values())},get cache(){return _repuestosCache}};',ctx);
  await ctx.SUC.ready;
  const msgs=[]; ctx.toast=(m,t)=>msgs.push(t+': '+m);
  const fx=fixtures(); const INV=fx.inv;
  let pass=0,fail=0; const ok=(n,c,x='')=>{c?pass++:fail++;console.log((c?'PASS ':'FAIL ')+n+(c?'':'  -> '+x))};
  const T=ctx.__t, S=ctx.SUC;
  const rows=()=> (els['tbl-inventario'].innerHTML.match(/data-inv-id=/g)||[]).length;
  const stock=async(id,suc)=>{const r=await cli.get('/api/inventario.php?sucursal='+suc);return r.json.data.find(x=>x.id_repuesto===id)};

  ok('inventario: hay varias sucursales activas', S.multiInv(), S.activas().length);
  ok('admin parte en "Todas" (invActive vacio)', S.invActive==='' && S.invTarget()===null, JSON.stringify(S.invActive));
  await T.loadInventario();
  const html=els['tbl-inventario'].innerHTML;
  ok('lista "Todas": muestra todos los repuestos de A', rows()===5, rows());
  ok('...con boton de traspaso por fila', (html.match(/btn-inv-traspaso/g)||[]).length===5, (html.match(/btn-inv-traspaso/g)||[]).length);
  ok('...y desglose por sucursal (ej. "Norte 20")', html.includes('stock-desg') && html.includes('Norte 20'), html.slice(0,0));

  // +/- en vista Todas se bloquea
  msgs.length=0; await T.alterStock(INV['Pantalla A54'],1);
  ok('+/- en "Todas" se bloquea con aviso', msgs.some(m=>/err: Elige una sucursal/.test(m)), JSON.stringify(msgs));
  ok('...y no cambio el stock', (await stock(INV['Pantalla A54'],ids.cen)).cantidad===6, '');

  // Filtrar por Centro
  S.onInvChange(String(ids.cen)); await new Promise(r=>setTimeout(r,900));
  const p=T.inv.find(x=>x.id_repuesto===INV['Pantalla A54']);
  ok('vista Centro: Pantalla muestra 6 (stock de Centro)', p && p.cantidad===6, JSON.stringify(p&&p.cantidad));
  ok('...el desglose muestra las otras sucursales (Norte 20)', els['tbl-inventario'].innerHTML.includes('Norte 20'), '');
  ok('invTarget es Centro y se puede escribir', S.invTarget()===ids.cen && S.puedeEscribir(S.invTarget()), '');

  // alterStock en Centro
  msgs.length=0; await T.alterStock(INV['Pantalla A54'],1); await new Promise(r=>setTimeout(r,500));
  ok('+ en Centro suma 1 al stock de Centro (6 -> 7)', (await stock(INV['Pantalla A54'],ids.cen)).cantidad===7 && !msgs.length, JSON.stringify(msgs));
  ok('...Norte no cambio (20)', (await stock(INV['Pantalla A54'],ids.nor)).cantidad===20, '');
  await T.alterStock(INV['Pantalla A54'],-1);

  // Edicion de repuesto: catalogo desde "Todas" sin tocar stock
  S.onInvChange(''); await new Promise(r=>setTimeout(r,900));
  vm.runInContext("_tagModeloEdit={setValue(){},getValue(){return 'A54'}}",ctx);
  T.openInvEdit(T.inv.find(x=>x.id_repuesto===INV['Pantalla A54']));
  ok('editar desde "Todas": el campo stock queda bloqueado', els['edit-rep-cantidad'].disabled===true, '');
  ok('...y la etiqueta lo explica', /elige una sucursal/i.test(els['lbl-edit-stock'].textContent), els['lbl-edit-stock'].textContent);
  els['edit-rep-nombre'].value='Pantalla A54'; els['edit-rep-marca'].value='Samsung'; els['edit-rep-precio'].value='46000';
  await T.submitEditRepuesto({preventDefault(){}}); await new Promise(r=>setTimeout(r,600));
  const pr=(await cli.get('/api/inventario.php?sucursal='+ids.cen)).json.data.find(x=>x.id_repuesto===INV['Pantalla A54']);
  ok('...el precio se guardo (46000) y el stock de Centro sigue en 6', pr.precio_venta===46000 && pr.cantidad===6, JSON.stringify([pr.precio_venta,pr.cantidad]));
  await cli.put('/api/inventario.php',{id:INV['Pantalla A54'],nombre:'Pantalla A54',marca_compatible:'Samsung',modelo_compatible:'A54',precio_venta:45000});

  // Traspaso por la UI
  await T.loadInventario();
  await S.abrirTraspaso(INV['Pantalla A54']);
  const opts=els['tr-origen'].innerHTML;
  ok('modal traspaso: origen lista Norte y Centro con disponible', /Norte \(disp\. 20\)/.test(opts) && /Centro \(disp\. 6\)/.test(opts), opts);
  ok('...destino excluye el origen', !new RegExp('value="'+els['tr-origen'].value+'"').test(els['tr-destino'].innerHTML), els['tr-destino'].innerHTML);
  els['tr-origen'].value=String(ids.nor); els['tr-origen'].onchange();
  els['tr-destino'].value=String(ids.cen); els['tr-cant'].value='5'; els['tr-nota'].value='prueba UI'; els['tr-rep'].value=String(INV['Pantalla A54']);
  await S.guardarTraspaso(); await new Promise(r=>setTimeout(r,700));
  ok('traspaso por UI: Norte 20 -> 15 y Centro 6 -> 11', (await stock(INV['Pantalla A54'],ids.nor)).cantidad===15 && (await stock(INV['Pantalla A54'],ids.cen)).cantidad===11, '');
  await cli.post('/api/traspasos.php',{id_repuesto:INV['Pantalla A54'],id_origen:ids.cen,id_destino:ids.nor,cantidad:5});

  // Repuestos del formulario: stock por sucursal
  els['nuevo-sucursal']={...els['nuevo-sucursal'],value:String(ids.nor)};
  ctx.document.getElementById=(i)=>els[i]||mk(i);
  els['nuevo-sucursal'].value=String(ids.nor);
  ctx._repuestosCache=null;
  await T.refrescarRepuestosNuevo();
  const cN=T.cache.get(String(ids.nor)).find(x=>x.id===INV['Pantalla A54']);
  ok('formulario nuevo con sucursal Norte: la lista muestra stock de Norte (20)', cN && /disp\. 20\)/.test(cN.label), cN&&cN.label);
  els['nuevo-sucursal'].value=String(ids.cen);
  await T.refrescarRepuestosNuevo();
  const cC=T.cache.get(String(ids.cen)).find(x=>x.id===INV['Pantalla A54']);
  ok('...y con Centro muestra el de Centro (6)', cC && /disp\. 6\)/.test(cC.label), cC&&cC.label);
  const raroN=T.cache.get(String(ids.nor)).find(x=>x.id===INV['Repuesto Raro']);
  ok('repuesto agotado en Norte: queda deshabilitado y dice donde hay (Bodega 1)', raroN && raroN.disabled===true && /Bodega 1/.test(raroN.hint), JSON.stringify(raroN));
  ok('...y uno con stock en Norte NO esta deshabilitado', cN && !cN.disabled, JSON.stringify(cN));


  // ── Respuestas fuera de orden: solo la ultima carga pinta la tabla ──
  const fetchOrig = ctx.fetch; const urls = [];
  ctx.fetch = async (url, opts) => { urls.push(String(url)); if (/inventario\.php\?.*sucursal=1(&|$)/.test(String(url))) await new Promise(r => setTimeout(r, 1500)); return fetchOrig(url, opts); };
  vm.runInContext('_invMap.clear()', ctx);
  S.onInvChange(String(ids.cen));   // lenta (1.5 s)
  S.onInvChange(String(ids.nor));   // rapida
  await new Promise(r => setTimeout(r, 2600));
  const pn = T.inv.find(x => x.id_repuesto === INV['Pantalla A54']);
  ok('carga lenta de Centro NO pisa a la rapida de Norte (la tabla sigue en Norte: 20)', pn && pn.cantidad === 20, JSON.stringify(pn && pn.cantidad));
  ok('...y el selector de inventario sigue en Norte', S.invActive === String(ids.nor), S.invActive);
  ctx.fetch = fetchOrig;

  // ── Cambiar la sucursal del sidebar solo recarga la vista abierta ──
  urls.length = 0; ctx.fetch = async (url, opts) => { urls.push(String(url)); return fetchOrig(url, opts); };
  const qOrig = ctx.document.querySelector;
  ctx.document.querySelector = (q) => q === '.view.active' ? { id: 'view-inventario' } : null;
  S.onChange(String(ids.cen)); await new Promise(r => setTimeout(r, 700));
  ok('cambiar el sidebar en Inventario NO recarga reparaciones', !urls.some(u => /reparaciones\.php/.test(u)), urls.join(' '));
  ctx.document.querySelector = (q) => q === '.view.active' ? { id: 'view-servicios' } : null;
  urls.length = 0; S.onChange(String(ids.nor)); await new Promise(r => setTimeout(r, 900));
  ok('...y en Servicios SI recarga reparaciones', urls.some(u => /reparaciones\.php/.test(u)), urls.join(' '));
  ctx.document.querySelector = qOrig; ctx.fetch = fetchOrig;

  // ── Sucursales extra de un usuario: nunca incluye la base ──
  await S.cargarUsuarios();
  const uMulti = S.users.find(u => u.user === 'tecMulti');
  await S.abrirAsignacion(uMulti.id_usuario);
  let ex = els['usc-extras'].innerHTML;
  ok('extras de tecMulti (base Centro): no ofrece Centro y trae Norte marcada', !new RegExp('value="' + ids.cen + '"').test(ex) && new RegExp('value="' + ids.nor + '"[^>]* checked').test(ex), ex);
  els['usc-base'].value = String(ids.nor); els['usc-base'].onchange();
  ex = els['usc-extras'].innerHTML;
  ok('al cambiar la base a Norte: Norte sale de la lista y Centro queda disponible', !new RegExp('value="' + ids.nor + '"').test(ex) && new RegExp('value="' + ids.cen + '"').test(ex), ex);

  // ── Mensaje de WhatsApp con la sucursal (direccion y telefono, con herencia de la casa matriz) ──
  await cli.put('/api/sucursales.php', { id_sucursal: ids.cen, direccion: 'Calle Centro 123', telefono: '+56 9 1111 2222' });
  await S.load();
  const texto = rep => decodeURIComponent(T.waLink(rep).split('?text=')[1] || '');
  const base = { nombre_cliente: 'Ana', telefono_cliente: '+56 9 8888 7777', codigo_seguimiento: 'ABC234' };
  const m1 = texto({ ...base, id_sucursal: ids.cen });
  ok('WhatsApp: indica la sucursal, direccion y telefono del local', /Sucursal Centro/.test(m1) && /Dirección: Calle Centro 123/.test(m1) && /Teléfono: \+56 9 1111 2222/.test(m1) && /ABC234/.test(m1), m1);
  S.matriz = { direccion: 'Av. Matriz 100, Santiago', telefono: '+56 2 2000 0000' };
  const m2 = texto({ ...base, id_sucursal: ids.nor });
  ok('WhatsApp: sucursal sin datos propios hereda los de la casa matriz', /Sucursal Norte/.test(m2) && /Dirección: Av\. Matriz 100, Santiago/.test(m2) && /Teléfono: \+56 2 2000 0000/.test(m2), m2);
  ok('WhatsApp: el enlace sigue apuntando al numero del cliente y al seguimiento', /^https:\/\/wa\.me\/56988887777\?text=/.test(T.waLink({ ...base, id_sucursal: ids.cen })) && /seguimiento\.php\?codigo=ABC234/.test(m1), m1);
  ok('WhatsApp: sin codigo de seguimiento devuelve solo el enlace base', T.waLink({ ...base, codigo_seguimiento: '', id_sucursal: ids.cen }) === 'https://wa.me/56988887777', '');


  // ── Filtro de stock bajo / agotado (sobre el disponible) ──
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const filas = () => (els['tbl-inventario'].innerHTML.match(/data-inv-id="(\d+)"/g) || []).map(x => Number(x.match(/\d+/)[0]));
  const dsp = x => x.cantidad - x.cantidad_reservada;
  S.onInvChange(''); await sleep(900);
  const todos = T.inv.length;
  ok('sin filtro: la tabla muestra todos los repuestos', filas().length === todos, filas().length + '/' + todos);
  vm.runInContext("_invStockFiltro='bajo'; _invUmbral=3;", ctx);
  vm.runInContext('_applySortInventario()', ctx);
  const esperadoBajo = T.inv.filter(x => dsp(x) <= 3);
  ok('filtro Bajo (<= 3): solo los de stock DISPONIBLE bajo', filas().length === esperadoBajo.length && esperadoBajo.length > 0 && esperadoBajo.length < todos, filas().length + ' vs ' + esperadoBajo.length + ' de ' + todos);
  const disps = filas().map(id => dsp(T.inv.find(x => x.id_repuesto === id)));
  ok('...ordenados de menos a mas stock disponible', disps.every((d, i) => i === 0 || disps[i - 1] <= d), disps.join(','));
  ok('...y el aviso indica cuantos se muestran de cuantos', new RegExp('Mostrando <strong>' + esperadoBajo.length + '</strong> de ' + todos).test(els['inv-filtro-info'].innerHTML), els['inv-filtro-info'].innerHTML);
  vm.runInContext('_invUmbral=0', ctx); vm.runInContext('_applySortInventario()', ctx);
  const esperado0 = T.inv.filter(x => dsp(x) <= 0).length;
  ok('umbral 0 incluye solo los agotados (disponible 0)', filas().length === esperado0, filas().length + ' vs ' + esperado0);
  vm.runInContext("_invStockFiltro='agotado'", ctx); vm.runInContext('_applySortInventario()', ctx);
  ok('filtro Agotados coincide con disponible <= 0', filas().length === esperado0 && /agotados/.test(els['inv-filtro-info'].innerHTML), filas().length);
  vm.runInContext("_invStockFiltro='bajo'; _invUmbral=100000", ctx); vm.runInContext('_applySortInventario()', ctx);
  ok('un umbral muy alto muestra todos', filas().length === todos, filas().length);
  vm.runInContext("_invStockFiltro='bajo'; _invUmbral=-1", ctx); vm.runInContext('_applySortInventario()', ctx);
  ok('sin ningun resultado muestra un mensaje y limpia la paginacion', filas().length === 0 && /Ningún repuesto con ese criterio/.test(els['tbl-inventario'].innerHTML) && els['inv-pagination'].innerHTML === '', els['tbl-inventario'].innerHTML.slice(0, 80));
  vm.runInContext("_invStockFiltro=''", ctx); vm.runInContext('_applySortInventario()', ctx);
  ok('quitar el filtro devuelve todos', filas().length === todos, filas().length);

  // ── Correcciones del code review ──
  // colores por clase (el CSP bloquea style="" en linea)
  S.onInvChange(String(ids.cen)); await sleep(900);
  vm.runInContext("_invStockFiltro='bajo'; _invUmbral=100000;", ctx); vm.runInContext('_applySortInventario()', ctx);
  const htmlCls = els['tbl-inventario'].innerHTML;
  ok('cantidades: sin style="" en linea (lo bloquea el CSP)', !/stock-qty[^>]*style=/.test(htmlCls), htmlCls.slice(0, 0));
  vm.runInContext("_invUmbral=5", ctx); vm.runInContext('_applySortInventario()', ctx);
  ok('cantidades: stock bajo con filtro activo lleva la clase stock-bajo', /class="stock-qty stock-bajo"/.test(els['tbl-inventario'].innerHTML), '');
  vm.runInContext("_invStockFiltro=''", ctx); vm.runInContext('_applySortInventario()', ctx);
  ok('cantidades: sin filtro los repuestos con stock no llevan clase de alerta', !/stock-bajo/.test(els['tbl-inventario'].innerHTML), '');

  // la cache de repuestos de los selectores se invalida al cambiar el stock
  await T.refrescarRepuestosNuevo(); await sleep(300);
  ok('selector de repuestos: la cache esta cargada', T.cache instanceof Map && T.cache.size > 0, String(T.cache));
  await T.alterStock(INV['Pantalla A54'], 1); await sleep(300);
  ok('+/- invalida la cache del selector (para que un repuesto repuesto se pueda elegir)', T.cache === null, String(T.cache));
  await T.alterStock(INV['Pantalla A54'], -1); await sleep(300);

  // aviso cuando el stock queda bajo lo reservado (viene del servidor)
  msgs.length = 0;
  await cli.put('/api/inventario.php', { id: INV['Repuesto Raro'], cantidad: 10, id_sucursal: ids.cen });
  const rr = await cli.postForm('/api/reparaciones.php', { nombre_cliente: 'T', telefono_cliente: '+56977777777', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', id_repuesto_usado: INV['Repuesto Raro'], id_sucursal: ids.cen });
  const reservado = (await stock(INV['Repuesto Raro'], ids.cen)).cantidad_reservada;
  await cli.put('/api/inventario.php', { id: INV['Repuesto Raro'], cantidad: reservado, id_sucursal: ids.cen });
  await T.loadInventario(); await sleep(300);
  await T.alterStock(INV['Repuesto Raro'], -1); await sleep(300);
  ok('al bajar el stock por debajo de lo reservado se muestra el aviso', reservado > 0 && msgs.some(m => /err: Atención: hay \d+ unidades? reservadas? /.test(m)), reservado + ' ' + JSON.stringify(msgs));
  await cli.req('DELETE', '/api/reparaciones.php', { json: { id: rr.json.data.id } });
  await cli.put('/api/inventario.php', { id: INV['Repuesto Raro'], cantidad: 1, id_sucursal: ids.cen });

  // WhatsApp: el texto de "donde esta el equipo" es el mismo en el mensaje del detalle y en el del ingreso
  const dd = vm.runInContext('_waDonde(' + ids.cen + ')', ctx);
  ok('_waDonde: incluye sucursal y direccion/telefono', /Sucursal Centro/.test(dd.local) && typeof dd.donde === 'string', JSON.stringify(dd));

  // Inventario vacio: no deja datos viejos
  const sb = els['search-inv']; sb.value = 'zzzz-no-existe';
  await T.loadInventario(); await sleep(300);
  ok('busqueda sin resultados: se limpia el mapa y la paginacion', T.inv.length === 0 && els['inv-pagination'].innerHTML === '', T.inv.length);
  sb.value = '';
  await T.loadInventario(); await sleep(300);

  // ── Stock minimo por repuesto ──
  await cli.put('/api/inventario.php', { id: INV['Repuesto Raro'], nombre: 'Repuesto Raro', precio_venta: 9000, stock_minimo: 2 });
  const pc = await stock(INV['Pantalla A54'], ids.cen);
  await cli.put('/api/inventario.php', { id: INV['Pantalla A54'], nombre: 'Pantalla A54', precio_venta: 45000, stock_minimo: pc.cantidad - pc.cantidad_reservada });
  S.onInvChange(String(ids.cen)); await sleep(1200);
  const raro = T.inv.find(x => x.id_repuesto === INV['Repuesto Raro']);
  ok('la lista trae el minimo de cada repuesto', raro && Number(raro.stock_minimo) === 2, JSON.stringify(raro && raro.stock_minimo));
  ok('bajo la cantidad se muestra "minimo N"', /mínimo 2/.test(els['tbl-inventario'].innerHTML), '');
  ok('un repuesto en o bajo su minimo se marca en ambar aunque no haya filtro', /class="stock-qty stock-bajo"/.test(els['tbl-inventario'].innerHTML), '');
  vm.runInContext("_invStockFiltro='minimo'", ctx); vm.runInContext('_applySortInventario()', ctx);
  const idsMin = (els['tbl-inventario'].innerHTML.match(/data-inv-id="(\d+)"/g) || []).map(x => Number(x.match(/\d+/)[0]));
  const esperadoMin = T.inv.filter(x => (parseInt(x.stock_minimo) || 0) > 0 && x.cantidad - x.cantidad_reservada <= parseInt(x.stock_minimo)).map(x => x.id_repuesto);
  ok('filtro "bajo el minimo": solo los que tienen minimo y estan en o bajo el', esperadoMin.length > 0 && idsMin.length === esperadoMin.length && idsMin.every(id => esperadoMin.includes(id)), idsMin + ' vs ' + esperadoMin);
  ok('...y el aviso lo describe', /stock mínimo/.test(els['inv-filtro-info'].innerHTML), els['inv-filtro-info'].innerHTML);
  vm.runInContext("_invStockFiltro=''", ctx); vm.runInContext('_applySortInventario()', ctx);


  // ── Navegacion rapida: precarga, tabla sin vaciar, datos de arranque ──
  let nInv = 0, nSuc = 0; const reqOrig = cli.req.bind(cli);
  let retardo = 0; // simula la demora de la red hacia el servidor
  cli.req = async (m, u, o) => { if (m === 'GET' && /inventario\.php/.test(u)) { nInv++; if (retardo) await sleep(retardo); } if (m === 'GET' && /sucursales\.php/.test(u)) nSuc++; return reqOrig(m, u, o); };
  S.onInvChange(String(ids.cen)); await sleep(1200);
  const rutas = () => (els['tbl-inventario'].innerHTML.match(/data-inv-id=/g) || []).length;

  // 1. la precarga se usa y evita pedir otra vez
  vm.runInContext('_prefetch.clear()', ctx); nInv = 0;
  vm.runInContext("prefetchView('inventario')", ctx); await sleep(700);
  ok('precargar una vista hace UNA peticion', nInv === 1, nInv);
  vm.runInContext("prefetchView('inventario')", ctx); await sleep(100);
  ok('precargar de nuevo la misma vista no repite la peticion (dedupe)', nInv === 1, nInv);
  await T.loadInventario();
  ok('al abrir la vista se usa la precarga: no hay otra peticion (tiene menos de 3 s)', nInv === 1 && rutas() >= 3, nInv + ' filas ' + rutas());
  ok('...y la precarga se consume una sola vez', vm.runInContext('_prefetch.size', ctx) === 0, '');

  // 2. una precarga vieja se pinta de inmediato y se revalida en segundo plano
  vm.runInContext("prefetchView('inventario')", ctx); await sleep(700);
  vm.runInContext("[..._prefetch.values()].forEach(v => v.t -= 5000)", ctx); nInv = 0;
  els['tbl-inventario'].innerHTML = '';
  retardo = 600; const pCarga = T.loadInventario(); await sleep(150);
  ok('con una precarga de mas de 3 s se pinta al instante, sin esperar al servidor (la red tarda 600 ms)', rutas() >= 3, rutas());
  await pCarga; retardo = 0; await sleep(300);
  ok('...y se vuelve a pedir en segundo plano para confirmar', nInv === 1, nInv);

  // 3. una precarga con mas de 30 s se descarta
  vm.runInContext("prefetchView('inventario')", ctx); await sleep(700);
  vm.runInContext("[..._prefetch.values()].forEach(v => v.t -= 31000)", ctx); nInv = 0;
  await T.loadInventario();
  ok('una precarga de mas de 30 s no se usa: se pide de nuevo', nInv === 1, nInv);

  // 4. cualquier escritura descarta las precargas
  vm.runInContext("prefetchView('inventario')", ctx); await sleep(700);
  ok('hay una precarga guardada', vm.runInContext('_prefetch.size', ctx) === 1, vm.runInContext('_prefetch.size', ctx));
  await T.alterStock(INV['Pantalla A54'], 1); await sleep(400);
  ok('un cambio de stock (PUT) descarta las precargas', vm.runInContext('_prefetch.size', ctx) === 0, vm.runInContext('_prefetch.size', ctx));
  await T.alterStock(INV['Pantalla A54'], -1); await sleep(400);
  vm.runInContext("prefetchView('inventario')", ctx); await sleep(700);
  S.cambio();
  ok('...y SUC.cambio() (traspasos, usuarios, sucursales) tambien', vm.runInContext('_prefetch.size', ctx) === 0, '');

  // 5. la tabla no se vacia al recargar
  await T.loadInventario(); await sleep(300);
  const pendiente = T.loadInventario();
  ok('al recargar con datos ya pintados la tabla NO se reemplaza por "Cargando..."', !/Cargando/.test(els['tbl-inventario'].innerHTML) && rutas() >= 3, els['tbl-inventario'].innerHTML.slice(0, 60));
  await pendiente;
  vm.runInContext('_invPintada = false', ctx);
  const primera = T.loadInventario();
  ok('la primera carga si muestra "Cargando..."', /Cargando/.test(els['tbl-inventario'].innerHTML), '');
  await primera; await sleep(200);

  // 6. no precarga la vista que ya esta abierta
  const abierta = els['view-inventario'] || (els['view-inventario'] = mk('view-inventario'));
  abierta.classList.contains = () => true; vm.runInContext('_prefetch.clear()', ctx); nInv = 0;
  vm.runInContext("prefetchView('inventario')", ctx); await sleep(500);
  ok('no se precarga una vista que ya esta abierta', nInv === 0, nInv);
  abierta.classList.contains = () => false;

  // 7. datos de arranque de sucursales: la primera carga no pide nada
  const boot = (await cli.get('/api/sucursales.php')).json.data;
  els['boot-sucursales'] = els['boot-sucursales'] || mk('boot-sucursales');
  els['boot-sucursales'].textContent = JSON.stringify(boot);
  S._bootUsado = false; nSuc = 0;
  await S.load();
  ok('SUC.load usa los datos de arranque del HTML (0 peticiones)', nSuc === 0 && S.list.length === boot.sucursales.length && S.base === boot.base, nSuc);
  await S.load();
  ok('...y las cargas siguientes si piden datos nuevos', nSuc === 1, nSuc);
  S._bootUsado = false; els['boot-sucursales'].textContent = 'esto no es json';
  nSuc = 0; await S.load();
  ok('si los datos de arranque estan danados, cae a pedirlos al servidor', nSuc === 1 && S.list.length === boot.sucursales.length, nSuc);
  cli.req = reqOrig;

  console.log(`\n${pass} OK, ${fail} fallos`); process.exit(fail?1:0);
})().catch(e=>{console.error('ERR',e.stack.split('\n').slice(0,5).join('\n'));process.exit(1)});
