// Regresion de los hallazgos del code review (deltas, bodega, deadlock, esquema autoaplicable...).
const { Client, creds, ids, fixtures, BETA_DIR, PHP_BIN } = require('./lib');
// Regresion de los 10 hallazgos del code review. Uso: node t_fix.js
const fs = require('fs');
const fx = fixtures();
const INV = fx.inv;
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };
const stockDe = async (cl, id, suc) => ((await cl.get('/api/inventario.php?sucursal=' + suc)).json.data.find(x => x.id_repuesto === id)) || null;
const nuevaRep = (cl, extra) => cl.postForm('/api/reparaciones.php', { nombre_cliente: 'T', telefono_cliente: '+56944444444', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', ...extra });
const mysql = (sql) => require("child_process").execFileSync(PHP_BIN,
  ["-r", "chdir(\"" + BETA_DIR + "\"); require \"includes/config.php\"; $db=getDB(); foreach (explode(\";\", $argv[1]) as $q) { if (trim($q)==\"\") continue; $st=$db->query($q); if ($st && $st->columnCount()) { echo $st->fetchColumn(); } }", sql]).toString().trim();

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro');
  const tm = await new Client().login('tecMulti'), adb = await new Client().login('adminB');
  const P = INV['Pantalla A54'], BAT = INV['Bateria X'], RARO = INV['Repuesto Raro'];

  // ── 1. Deltas atomicos ──────────────────────────────────────
  const base = (await stockDe(adm, BAT, ids.nor)).cantidad;
  let r = await tm.put('/api/inventario.php', { id: BAT, cantidad_delta: 1, id_sucursal: ids.nor });
  ok('delta +1 devuelve la cantidad real', r.json?.ok && r.json.data.cantidad === base + 1, r.text.slice(0, 100));
  r = await tm.put('/api/inventario.php', { id: BAT, cantidad_delta: -1, id_sucursal: ids.nor });
  ok('delta -1 vuelve al valor inicial', r.json?.data?.cantidad === base, r.text.slice(0, 100));
  const cls = await Promise.all(Array.from({ length: 10 }, () => new Client().login('tecMulti')));
  await Promise.all(cls.map(c => c.put('/api/inventario.php', { id: BAT, cantidad_delta: 1, id_sucursal: ids.nor })));
  ok('10 "+" simultaneos suman exactamente 10 (con valor absoluto se perderian)', (await stockDe(adm, BAT, ids.nor)).cantidad === base + 10, (await stockDe(adm, BAT, ids.nor)).cantidad);
  r = await tm.put('/api/inventario.php', { id: BAT, cantidad_delta: -1000, id_sucursal: ids.nor });
  ok('un delta nunca deja la cantidad bajo 0', r.json?.data?.cantidad === 0, r.text.slice(0, 100));
  await tm.put('/api/inventario.php', { id: BAT, cantidad_delta: base, id_sucursal: ids.nor });
  r = await tc.put('/api/inventario.php', { id: BAT, cantidad_delta: 1, id_sucursal: ids.nor });
  ok('delta en sucursal ajena sigue dando 403', r.status === 403, r.status);
  // un traspaso intermedio no se pierde con "+"
  const antes = (await stockDe(adm, P, ids.cen)).cantidad;
  await adm.post('/api/traspasos.php', { id_repuesto: P, id_origen: ids.cen, id_destino: ids.nor, cantidad: 2 });
  r = await tc.put('/api/inventario.php', { id: P, cantidad_delta: 1, id_sucursal: ids.cen });
  ok('"+" tras un traspaso parte del valor REAL (antes-2+1)', r.json?.data?.cantidad === antes - 1, `${r.json?.data?.cantidad} vs ${antes - 1}`);
  await adm.post('/api/traspasos.php', { id_repuesto: P, id_origen: ids.nor, id_destino: ids.cen, cantidad: 2 });
  await tc.put('/api/inventario.php', { id: P, cantidad_delta: -1, id_sucursal: ids.cen });

  // ── 2. es_bodega ────────────────────────────────────────────
  r = await adm.put('/api/sucursales.php', { id_sucursal: ids.cen, es_bodega: 1 });
  ok('no se convierte en bodega una sucursal con usuarios', r.json?.ok === false && /usuarios/i.test(r.json.msg), r.text.slice(0, 140));
  const nueva = await adm.post('/api/sucursales.php', { nombre: 'Temp ' + Date.now() % 100000 });
  const idN = nueva.json.data.id;
  r = await adm.put('/api/sucursales.php', { id_sucursal: idN, es_bodega: 1 });
  ok('una sucursal vacia SI puede pasar a bodega', r.json?.ok === true, r.text.slice(0, 100));
  await adm.put('/api/sucursales.php', { id_sucursal: idN, es_bodega: 0 });
  const rN = await nuevaRep(adm, { id_sucursal: idN });
  r = await adm.put('/api/sucursales.php', { id_sucursal: idN, es_bodega: 1 });
  ok('con servicios registrados NO puede ser bodega', r.json?.ok === false && /servicios registrados/i.test(r.json.msg), r.text.slice(0, 140));
  await adm.req('DELETE', '/api/reparaciones.php', { json: { id: rN.json.data.id } });
  await adm.put('/api/sucursales.php', { id_sucursal: idN, activa: 0 });
  r = await adb.put('/api/sucursales.php', { id_sucursal: ids.b1, es_bodega: 1 });
  ok('la unica sucursal de una empresa NO puede ser bodega', r.json?.ok === false && /única/i.test(r.json.msg), r.text.slice(0, 140));

  // ── 3. Comparativa sin inactivas ────────────────────────────
  r = await adm.get('/api/estadisticas.php?desde=2000-01-01&hasta=2100-01-01');
  const nombres = (r.json?.data?.por_sucursal || []).map(x => x.nombre);
  ok('la comparativa no lista sucursales inactivas sin ordenes', !nombres.includes('Sur') && nombres.includes('Centro'), nombres.join(','));

  // ── 4. Traslado vs reserva de adicional (carrera) ───────────
  // Stock suficiente en ambas sucursales: asi se ejercitan las dos ramas de la carrera (adicional antes o despues del traslado).
  await adm.put('/api/inventario.php', { id: BAT, cantidad: 20, id_sucursal: ids.cen });
  await adm.put('/api/inventario.php', { id: BAT, cantidad: 20, id_sucursal: ids.nor });
  let incons = 0, rondas = 12, traslados = 0, bloqueados = 0;
  for (let i = 0; i < rondas; i++) {
    const rep = (await nuevaRep(tc, {})).json.data.id;
    const reservaAntes = { c: (await stockDe(adm, BAT, ids.cen)).cantidad_reservada, n: (await stockDe(adm, BAT, ids.nor)).cantidad_reservada };
    const [a, b] = await Promise.all([
      adm.put('/api/reparaciones.php', { id: rep, id_sucursal: ids.nor }),
      adm.post('/api/rep_servicio.php', { id_reparacion: rep, id_repuesto: BAT, cantidad: 1 }),
    ]);
    const rowN = (await adm.get('/api/reparaciones.php?sucursal=' + ids.nor)).json.data.find(x => x.id_ingreso === rep);
    const final = rowN ? ids.nor : ids.cen;
    const d = { c: (await stockDe(adm, BAT, ids.cen)).cantidad_reservada - reservaAntes.c, n: (await stockDe(adm, BAT, ids.nor)).cantidad_reservada - reservaAntes.n };
    const addOk = b.json?.ok === true;
    const esperado = addOk ? { c: final === ids.cen ? 1 : 0, n: final === ids.nor ? 1 : 0 } : { c: 0, n: 0 };
    if (a.json?.ok) traslados++; else bloqueados++;
    if (d.c !== esperado.c || d.n !== esperado.n) { incons++; console.log('   inconsistente ronda', i, JSON.stringify({ final, d, esperado, a: a.json?.msg, b: b.json?.msg })); }
    await adm.req('DELETE', '/api/reparaciones.php', { json: { id: rep } });
  }
  ok(`carrera traslado/adicional: ${rondas} rondas sin reservas en la sucursal equivocada (traslados ${traslados}, bloqueados ${bloqueados})`, incons === 0, incons);
  const resFinal = { c: (await stockDe(adm, BAT, ids.cen)).cantidad_reservada, n: (await stockDe(adm, BAT, ids.nor)).cantidad_reservada };
  ok('...y no queda ninguna reserva huerfana tras eliminar las reparaciones', resFinal.c === 0 || resFinal.c === 0, JSON.stringify(resFinal));

  // ── 5. Traspasos opuestos concurrentes ──────────────────────
  await adm.put('/api/inventario.php', { id: P, cantidad: 30, id_sucursal: ids.cen });
  await adm.put('/api/inventario.php', { id: P, cantidad: 30, id_sucursal: ids.nor });
  const c2 = await Promise.all(Array.from({ length: 16 }, () => new Client().login('adminA')));
  const res = await Promise.all(c2.map((c, i) => c.post('/api/traspasos.php', i % 2 ? { id_repuesto: P, id_origen: ids.nor, id_destino: ids.cen, cantidad: 1 } : { id_repuesto: P, id_origen: ids.cen, id_destino: ids.nor, cantidad: 1 })));
  const todosJson = res.every(x => x.json !== null);
  const exitos = res.filter(x => x.json?.ok).length;
  ok('traspasos opuestos concurrentes: todas las respuestas son JSON (sin HTML 500)', todosJson, res.filter(x => !x.json).map(x => x.status + x.text.slice(0, 60)).join('|'));
  ok(`...y se completan (${exitos}/16)`, exitos === 16, exitos);
  const tot = (await stockDe(adm, P, ids.cen)).cantidad + (await stockDe(adm, P, ids.nor)).cantidad;
  ok('...el total entre ambas sucursales se conserva (60)', tot === 60, tot);
  await adm.put('/api/inventario.php', { id: P, cantidad: 6, id_sucursal: ids.cen });
  await adm.put('/api/inventario.php', { id: P, cantidad: 20, id_sucursal: ids.nor });

  // ── 6. Esquema autoaplicable ────────────────────────────────
  mysql('RENAME TABLE traspasos TO traspasos_bak');
  const cNew = await new Client().login('adminA');
  r = await cNew.get('/api/traspasos.php');
  ok('si falta una tabla nueva, guard() la recrea y el endpoint responde', r.json?.ok === true && Array.isArray(r.json.data), r.status + r.text.slice(0, 100));
  ok('...la tabla existe de nuevo', mysql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='centrotec_beta' AND table_name='traspasos'") === '1', '');
  mysql('DROP TABLE traspasos; RENAME TABLE traspasos_bak TO traspasos');
  r = await adm.get('/api/traspasos.php?id_repuesto=' + P);
  ok('...y se restauro el historial original', r.json?.ok && r.json.data.length > 0, r.text.slice(0, 80));

  // ── 8/9. Inventario acotado ─────────────────────────────────
  r = await adm.get('/api/inventario.php?q=Pantalla');
  ok('GET con filtro devuelve solo el desglose de lo filtrado', r.json?.data?.length === 1 && r.json.data[0].stock.length >= 2, r.text.slice(0, 120));
  r = await adm.get('/api/inventario.php?q=zzzzno');
  ok('GET sin resultados no falla', r.json?.ok === true && r.json.data.length === 0, r.text.slice(0, 80));
  r = await adm.get('/api/inventario.php');
  ok('GET completo trae todos con su desglose', r.json?.data?.length >= 3 && r.json.data.every(x => Array.isArray(x.stock)), r.json?.data?.length);

  // ── 10. Validacion de sucursal unificada ────────────────────
  r = await adm.put('/api/inventario.php', { id: P, cantidad: 3, id_sucursal: idN });
  ok('escribir stock en una sucursal INACTIVA se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await adm.put('/api/inventario.php', { id: P, cantidad: 3, id_sucursal: ids.b1 });
  ok('...y en una de otra empresa (403)', r.status === 403, r.status);
  r = await nuevaRep(adm, { id_sucursal: idN });
  ok('crear servicio en sucursal inactiva se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await nuevaRep(adm, { id_sucursal: ids.bod });
  ok('crear servicio en bodega se rechaza', r.json?.ok === false && /bodega/i.test(r.json.msg), r.text.slice(0, 100));
  r = await adm.put('/api/usuarios.php', { id_usuario: (await adm.get('/api/usuarios.php')).json.data.find(u => u.user === 'tecNorte').id_usuario, id_sucursal: idN });
  ok('asignar un usuario a una sucursal inactiva se rechaza', r.json?.ok === false, r.text.slice(0, 100));

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
