// Frontend real (app.js + sucursales.js) sobre un DOM simulado contra beta. Debe correr despues de stock.js.
const { Client, creds, ids, fixtures, BETA_DIR, PHP_BIN } = require('./lib');
const fs=require('fs'), vm=require('vm');
(async()=>{
  const cli=await new Client().login('adminA');
  const els={};
  const mk=(id)=>els[id]||(els[id]={id,value:'',innerHTML:'',textContent:'',dataset:{},style:{},classList:{add(){},remove(){},toggle(){},contains(){return false}},
    addEventListener(){},removeEventListener(){},querySelector(){return null},querySelectorAll(){return[]},closest(){return null},appendChild(){},focus(){},click(){}});
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
  vm.runInContext(app+'\n;globalThis.__t={loadServicios,loadInventario,alterStock,submitEditRepuesto,openInvEdit,refrescarRepuestosNuevo,get inv(){return Array.from(_invMap.values())},get cache(){return _repuestosCache}};',ctx);
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
  ok('formulario nuevo con sucursal Norte: la lista muestra stock de Norte (20)', cN && /stock: 20\)/.test(cN.label), cN&&cN.label);
  els['nuevo-sucursal'].value=String(ids.cen);
  await T.refrescarRepuestosNuevo();
  const cC=T.cache.get(String(ids.cen)).find(x=>x.id===INV['Pantalla A54']);
  ok('...y con Centro muestra el de Centro (6)', cC && /stock: 6\)/.test(cC.label), cC&&cC.label);


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

  console.log(`\n${pass} OK, ${fail} fallos`); process.exit(fail?1:0);
})().catch(e=>{console.error('ERR',e.stack.split('\n').slice(0,5).join('\n'));process.exit(1)});
