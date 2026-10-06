// Stock por sucursal, traspasos, reservas, importar/exportar y carreras.
const { Client, creds, ids, fixtures, BETA_DIR, PHP_BIN } = require('./lib');
// Pruebas de stock por sucursal y traspasos contra beta. Uso: node t_stock.js
const fs = require('fs');
const fx = fixtures();
const INV = fx.inv;

let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };

const stockDe = async (cl, idRep, suc) => {
  const r = await cl.get('/api/inventario.php' + (suc ? '?sucursal=' + suc : ''));
  const it = (r.json?.data || []).find(x => x.id_repuesto === idRep);
  return it ? { c: it.cantidad, r: it.cantidad_reservada, stock: it.stock } : null;
};
const nuevaRep = (cl, extra) => cl.postForm('/api/reparaciones.php', { nombre_cliente: 'T', telefono_cliente: '+56933333333', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', ...extra });

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro');
  const tn = await new Client().login('tecNorte'), tm = await new Client().login('tecMulti'), adb = await new Client().login('adminB');
  const P = INV['Pantalla A54'], BAT = INV['Bateria X'], RARO = INV['Repuesto Raro'], RB = INV['Repuesto B'];

  // ── Migracion / lectura ───────────────────────────────────
  let s = await stockDe(tc, P, ids.cen);
  ok('migracion: el stock antiguo quedo en Centro (10)', s?.c === 10 && s.r === 0, JSON.stringify(s));
  s = await stockDe(tc, P, ids.nor);
  ok('Norte parte en 0', s?.c === 0, JSON.stringify(s));
  s = await stockDe(tc, P);
  ok('sin filtro = total de todas las sucursales (10)', s?.c === 10, JSON.stringify(s));
  let r = await adb.get('/api/inventario.php');
  ok('empresa B solo ve su inventario', r.json?.data?.length === 1 && r.json.data[0].id_repuesto === RB, r.text.slice(0, 120));
  r = await adb.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('B con sucursal de A se rechaza', r.json?.ok === false, r.status);

  // ── Edicion de stock y permisos ───────────────────────────
  r = await tc.put('/api/inventario.php', { id: P, cantidad: 12, id_sucursal: ids.cen });
  ok('tecCentro fija stock en Centro', r.json?.ok === true, r.text.slice(0, 100));
  r = await tc.put('/api/inventario.php', { id: P, cantidad: 99, id_sucursal: ids.nor });
  ok('tecCentro NO fija stock en Norte (403)', r.status === 403, r.status);
  s = await stockDe(tc, P, ids.nor);
  ok('...y Norte sigue en 0', s?.c === 0, JSON.stringify(s));
  r = await tm.put('/api/inventario.php', { id: P, cantidad: 2, id_sucursal: ids.nor });
  ok('tecMulti SI fija stock en Norte', r.json?.ok === true, r.text.slice(0, 100));
  await tm.put('/api/inventario.php', { id: P, cantidad: 0, id_sucursal: ids.nor });
  r = await tc.put('/api/inventario.php', { id: P, nombre: 'Hack', cantidad: 1 });
  ok('tecnico NO edita el catalogo (403)', r.status === 403, r.status);
  r = await tc.put('/api/inventario.php', { id: RB, cantidad: 1 });
  ok('no se edita un repuesto de otra empresa (404)', r.status === 404, r.status + r.text.slice(0, 80));
  r = await adm.put('/api/inventario.php', { id: P, cantidad: 5, id_sucursal: ids.b1 });
  ok('admin NO fija stock en sucursal ajena (403)', r.status === 403, r.status);
  await tc.put('/api/inventario.php', { id: P, cantidad: 10, id_sucursal: ids.cen });

  // ── Crear repuesto ────────────────────────────────────────
  r = await adm.postForm('/api/inventario.php', { nombre: 'Nuevo en Norte', precio_venta: 1000, cantidad: 4, id_sucursal: ids.nor });
  ok('admin crea repuesto con stock en Norte', r.json?.ok === true, r.text.slice(0, 100));
  r = await tc.postForm('/api/inventario.php', { nombre: 'X', cantidad: 1 });
  ok('tecnico NO crea repuestos (403)', r.status === 403, r.status);
  r = await adm.postForm('/api/inventario.php', { nombre: 'Ajeno', cantidad: 1, id_sucursal: ids.b1 });
  ok('crear con sucursal ajena se rechaza (403)', r.status === 403, r.status);
  const lista = (await adm.get('/api/inventario.php?sucursal=' + ids.nor)).json.data;
  const nn = lista.find(x => x.nombre === 'Nuevo en Norte');
  ok('...y aparece con 4 en Norte y 0 en Centro', nn?.cantidad === 4 && (await stockDe(adm, nn.id_repuesto, ids.cen)).c === 0, JSON.stringify(nn));

  // ── Reservas por sucursal ─────────────────────────────────
  r = await nuevaRep(tc, { id_repuesto_usado: P });
  const repC = r.json?.data?.id;
  ok('servicio en Centro con repuesto reserva en Centro', r.json?.ok === true, r.text.slice(0, 120));
  s = await stockDe(tc, P, ids.cen);
  ok('...Centro: stock 10, reservado 1', s?.c === 10 && s.r === 1, JSON.stringify(s));
  r = await nuevaRep(tn, { id_repuesto_usado: BAT });
  ok('Norte NO puede reservar un repuesto que solo hay en Centro', r.json?.ok === false && /sucursal/i.test(r.json.msg), r.text.slice(0, 160));
  r = await nuevaRep(tn, { id_repuesto_usado: nn.id_repuesto });
  ok('Norte SI reserva su propio stock', r.json?.ok === true, r.text.slice(0, 120));
  s = await stockDe(tn, nn.id_repuesto, ids.nor);
  ok('...Norte: reservado 1, Centro intacto', s?.r === 1 && (await stockDe(tn, nn.id_repuesto, ids.cen)).r === 0, JSON.stringify(s));

  // ── Traspasos ─────────────────────────────────────────────
  const tr = (cl, o) => cl.post('/api/traspasos.php', o);
  r = await tr(tc, { id_repuesto: P, id_origen: ids.cen, id_destino: ids.nor, cantidad: 1 });
  ok('tecnico NO hace traspasos (403)', r.status === 403, r.status);
  r = await tr(adm, { id_repuesto: P, id_origen: ids.cen, id_destino: ids.cen, cantidad: 1 });
  ok('origen = destino se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await tr(adm, { id_repuesto: P, id_origen: ids.cen, id_destino: ids.nor, cantidad: 0 });
  ok('cantidad 0 se rechaza', r.json?.ok === false, '');
  r = await tr(adm, { id_repuesto: P, id_origen: ids.cen, id_destino: ids.b1, cantidad: 1 });
  ok('destino de OTRA empresa se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await tr(adm, { id_repuesto: RB, id_origen: ids.cen, id_destino: ids.nor, cantidad: 1 });
  ok('repuesto de OTRA empresa se rechaza (404)', r.status === 404, r.status);
  r = await tr(adb, { id_repuesto: P, id_origen: ids.b1, id_destino: ids.b1, cantidad: 1 });
  ok('admin B no puede mover repuestos de A', r.json?.ok === false, r.text.slice(0, 80));
  r = await tr(adm, { id_repuesto: P, id_origen: ids.cen, id_destino: ids.nor, cantidad: 10 });
  ok('no mueve mas de lo DISPONIBLE (10 con 1 reservado)', r.json?.ok === false && /disponible: 9/.test(r.json.msg), r.text.slice(0, 160));
  r = await tr(adm, { id_repuesto: P, id_origen: ids.cen, id_destino: ids.nor, cantidad: 3, nota: 'reabastecer' });
  ok('traspaso valido 3: Centro -> Norte', r.json?.ok === true, r.text.slice(0, 120));
  s = await stockDe(adm, P, ids.cen);
  const s2 = await stockDe(adm, P, ids.nor);
  ok('...Centro 7 (reservado 1) y Norte 3', s.c === 7 && s.r === 1 && s2.c === 3, JSON.stringify([s, s2]));
  s = await stockDe(adm, P);
  ok('...el total sigue siendo 10', s.c === 10, JSON.stringify(s));
  r = await adm.get('/api/traspasos.php?id_repuesto=' + P);
  ok('historial muestra el traspaso', r.json?.data?.length === 1 && r.json.data[0].origen === 'Centro' && r.json.data[0].destino === 'Norte' && r.json.data[0].cantidad === 3, r.text.slice(0, 200));
  r = await adb.get('/api/traspasos.php');
  ok('empresa B no ve traspasos de A', (r.json?.data || []).length === 0, r.text.slice(0, 100));
  r = await tr(adm, { id_repuesto: P, id_origen: ids.cen, id_destino: ids.bod, cantidad: 2 });
  ok('traspaso a la bodega funciona', r.json?.ok === true, r.text.slice(0, 100));
  r = await tr(adm, { id_repuesto: P, id_origen: ids.bod, id_destino: ids.cen, cantidad: 2 });
  ok('...y de la bodega de vuelta', r.json?.ok === true, r.text.slice(0, 100));

  // ── Consumo, liberacion y traslado de reparaciones ────────
  r = await tc.put('/api/reparaciones.php', { id: repC, status: 'Reparado' });
  ok('marcar Reparado descuenta del stock de Centro', r.json?.ok === true && r.json.data.stock_descontado === 1, r.text.slice(0, 100));
  s = await stockDe(adm, P, ids.cen);
  ok('...Centro: 7 -> 6 y reserva liberada', s.c === 6 && s.r === 0, JSON.stringify(s));
  s = await stockDe(adm, P, ids.nor);
  ok('...Norte no se toco', s.c === 3, JSON.stringify(s));

  r = await nuevaRep(tc, { id_repuesto_usado: P });
  const repC2 = r.json?.data?.id;
  r = await adm.put('/api/reparaciones.php', { id: repC2, id_sucursal: ids.nor });
  ok('traslado con repuesto reservado se bloquea', r.json?.ok === false && /reservad/i.test(r.json.msg), r.text.slice(0, 160));
  r = await adm.post('/api/rep_servicio.php', { id_reparacion: repC2, id_repuesto: BAT, cantidad: 2 });
  ok('adicional se reserva en la sucursal de la reparacion (Centro)', r.json?.ok === true, r.text.slice(0, 120));
  const idRR = r.json?.data?.id;
  s = await stockDe(adm, BAT, ids.cen);
  ok('...Bateria en Centro: reservadas 2', s.r === 2, JSON.stringify(s));
  r = await adm.post('/api/rep_servicio.php', { id_reparacion: repC2, id_repuesto: BAT, cantidad: 4 });
  ok('adicional mayor al disponible se rechaza', r.json?.ok === false, r.text.slice(0, 120));
  r = await tr(adm, { id_repuesto: BAT, id_origen: ids.cen, id_destino: ids.nor, cantidad: 4 });
  ok('traspaso no toca lo reservado (5 con 2 reservadas, pide 4)', r.json?.ok === false && /disponible: 3/.test(r.json.msg), r.text.slice(0, 160));
  r = await tr(adm, { id_repuesto: BAT, id_origen: ids.cen, id_destino: ids.nor, cantidad: 3 });
  ok('...pero mueve los 3 disponibles', r.json?.ok === true, r.text.slice(0, 100));
  r = await adm.request?.('DELETE', '/api/rep_servicio.php', { json: { id: idRR } }) ?? await adm.req('DELETE', '/api/rep_servicio.php', { json: { id: idRR } });
  ok('quitar el adicional libera la reserva en Centro', r.json?.ok === true && (await stockDe(adm, BAT, ids.cen)).r === 0, r.text.slice(0, 100));
  r = await adm.req('DELETE', '/api/reparaciones.php', { json: { id: repC2 } });
  ok('eliminar la reparacion libera la reserva del inicial', r.json?.ok === true && (await stockDe(adm, P, ids.cen)).r === 0, r.text.slice(0, 100));
  r = await nuevaRep(tc, {});
  const repSin = r.json?.data?.id;
  r = await adm.put('/api/reparaciones.php', { id: repSin, id_sucursal: ids.nor });
  ok('sin reservas, el admin SI traslada la reparacion', r.json?.ok === true, r.text.slice(0, 100));
  r = await adm.put('/api/reparaciones.php', { id: repSin, id_repuesto_usado: BAT });
  ok('asignar repuesto tras el traslado reserva en Norte (hay 3)', r.json?.ok === true, r.text.slice(0, 120));
  s = await stockDe(adm, BAT, ids.nor);
  ok('...Norte reservado 1, Centro 0', s.r === 1 && (await stockDe(adm, BAT, ids.cen)).r === 0, JSON.stringify(s));

  // ── Carreras ──────────────────────────────────────────────
  const cls = await Promise.all(Array.from({ length: 8 }, () => new Client().login('tecCentro')));
  const res = await Promise.all(cls.map(c => nuevaRep(c, { id_repuesto_usado: RARO })));
  const exitos = res.filter(x => x.json?.ok === true).length;
  s = await stockDe(adm, RARO, ids.cen);
  ok(`carrera: 8 peticiones por la ultima unidad -> 1 exito (fue ${exitos})`, exitos === 1 && s.r === 1, JSON.stringify(s));
  await adm.put('/api/inventario.php', { id: RARO, cantidad: 1, id_sucursal: ids.nor });
  const cls2 = await Promise.all(Array.from({ length: 6 }, () => new Client().login('adminA')));
  const res2 = await Promise.all(cls2.map(c => tr(c, { id_repuesto: RARO, id_origen: ids.nor, id_destino: ids.bod, cantidad: 1 })));
  const ex2 = res2.filter(x => x.json?.ok === true).length;
  const t1 = await stockDe(adm, RARO);
  ok(`carrera: 6 traspasos de 1 unidad con stock 1 -> 1 exito (fue ${ex2})`, ex2 === 1, JSON.stringify(t1));
  ok('...y el total no cambia (2 = 1 Centro reservada + 1 bodega)', t1.c === 2, JSON.stringify(t1));

  // ── Importar / exportar ───────────────────────────────────
  const csv = 'id;nombre;marca_compatible;modelo_compatible;precio_venta;cantidad\n' + `${P};Pantalla A54;Samsung;A54;45000;20\n;Importado Nuevo;Apple;X;3000;7\n`;
  const b = '----t' + Date.now();
  const body = (campos) => Buffer.from(Object.entries(campos).map(([k, v]) => `--${b}\r\nContent-Disposition: form-data; name="${k}"\r\n\r\n${v}\r\n`).join('')
    + `--${b}\r\nContent-Disposition: form-data; name="archivo"; filename="i.csv"\r\nContent-Type: text/csv\r\n\r\n${csv}\r\n--${b}--\r\n`);
  const imp = (cl, campos) => new Promise((resolve, reject) => {
    const data = body({ csrf_token: cl.csrf, ...campos });
    const headers = { Host: 'beta.centrotec.cl', 'Content-Type': 'multipart/form-data; boundary=' + b, 'Content-Length': data.length,
      Cookie: Object.entries(cl.cookies).map(([k, v]) => `${k}=${v}`).join('; ') };
    const q = require('http').request({ host: '127.0.0.1', port: 8081, path: '/api/importar_inventario.php', method: 'POST', headers }, res => {
      let d = ''; res.on('data', x => d += x); res.on('end', () => { let j = null; try { j = JSON.parse(d); } catch (e) {} resolve({ status: res.statusCode, json: j, text: d }); });
    }); q.on('error', reject); q.write(data); q.end();
  });
  r = await imp(adm, { id_sucursal: ids.nor });
  ok('importar a Norte funciona', r.json?.ok === true && r.json.data.insertados === 1 && r.json.data.actualizados === 1, r.text.slice(0, 200));
  ok('...Pantalla en Norte = 20 y Centro intacto', (await stockDe(adm, P, ids.nor)).c === 20 && (await stockDe(adm, P, ids.cen)).c === 6, '');
  r = await imp(adm, { id_sucursal: ids.b1 });
  ok('importar a sucursal ajena se rechaza (403)', r.status === 403, r.status + r.text.slice(0, 100));
  r = await imp(tc, { id_sucursal: ids.cen });
  ok('tecnico NO importa (403)', r.status === 403, r.status);
  r = await adm.get('/api/exportar_inventario.php?formato=csv&sucursal=' + ids.nor);
  ok('exportar CSV de Norte trae el stock de Norte (Pantalla 20)', /Pantalla A54;?[^\n]*;20/.test(r.text.replace(/"/g, '')) || r.text.includes(';20'), r.text.slice(0, 200));
  r = await adm.get('/api/exportar_inventario.php?formato=csv');
  ok('exportar CSV sin sucursal trae el total (Pantalla 26 = 6+20)', r.text.includes(';26'), r.text.slice(0, 250));

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 3).join('\n')); process.exit(2); });
