// Correcciones del code review de sucursales: stock huerfano al desactivar, bodega como sucursal base,
// estadisticas sin papelera y aviso de stock bajo lo reservado.
const { Client, ids, fixtures } = require('./lib');
const INV = fixtures().inv;
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro');
  const nuevaRep = (cl, e) => cl.postForm('/api/reparaciones.php', { nombre_cliente: 'T', telefono_cliente: '+56977777777', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', ...e });
  const kpi = async (q = '') => Number((await adm.get('/api/estadisticas.php?desde=2000-01-01&hasta=2100-01-01' + q)).json.data.kpis.total_ordenes);

  // ── 1. Desactivar una sucursal con stock ───────────────────
  let r = await adm.post('/api/sucursales.php', { nombre: 'Temporal', direccion: '', telefono: '' });
  const temp = r.json?.data?.id;
  ok('se crea una sucursal temporal', !!temp, r.text.slice(0, 100));
  r = await adm.post('/api/traspasos.php', { id_repuesto: INV['Pantalla A54'], id_origen: ids.cen, id_destino: temp, cantidad: 3 });
  ok('se traspasan 3 unidades a la temporal', r.json?.ok === true, r.text.slice(0, 120));
  r = await adm.put('/api/sucursales.php', { id_sucursal: temp, activa: 0 });
  ok('NO se puede desactivar una sucursal con stock', r.json?.ok === false && /3 unidades en stock/.test(r.json.msg || ''), r.text.slice(0, 160));
  r = await adm.post('/api/traspasos.php', { id_repuesto: INV['Pantalla A54'], id_origen: temp, id_destino: ids.cen, cantidad: 3 });
  ok('se devuelve el stock a Centro', r.json?.ok === true, r.text.slice(0, 120));
  r = await adm.put('/api/sucursales.php', { id_sucursal: temp, activa: 0 });
  ok('...y entonces SI se puede desactivar', r.json?.ok === true, r.text.slice(0, 120));

  // ── 2. Bodega como sucursal base ───────────────────────────
  const us = (await adm.get('/api/usuarios.php')).json.data;
  const uid = u => us.find(x => x.user === u).id_usuario;
  r = await adm.put('/api/usuarios.php', { id_usuario: uid('tecCentro'), id_sucursal: ids.bod, sucursales_extra: [] });
  ok('una bodega NO puede ser la sucursal base de un usuario', r.json?.ok === false && /bodega/i.test(r.json.msg || ''), r.text.slice(0, 140));
  r = await adm.put('/api/usuarios.php', { id_usuario: uid('tecCentro'), id_sucursal: ids.cen, sucursales_extra: [ids.bod] });
  ok('...pero SI como sucursal adicional', r.json?.ok === true, r.text.slice(0, 120));
  r = await tc.get('/api/sucursales.php');
  ok('...y el tecnico puede escribir en la bodega', r.json?.data?.escritura?.includes(ids.bod), JSON.stringify(r.json?.data?.escritura));
  r = await adm.put('/api/usuarios.php', { id_usuario: uid('tecCentro'), id_sucursal: ids.cen, sucursales_extra: [] });
  r = await adm.post('/api/usuarios.php', { nombre: 'Bodeguero', user: 'bodeguero_t', password: 'Clave12345', id_sucursal: ids.bod });
  ok('un tecnico nuevo tampoco puede nacer con una bodega como base', r.json?.ok === false, r.text.slice(0, 140));

  // ── 3. Estadisticas no cuentan la papelera ─────────────────
  const antes = await kpi(), antesNor = await kpi('&sucursal=' + ids.nor);
  const id = (await nuevaRep(adm, { id_sucursal: ids.nor })).json.data.id;
  ok('estadisticas: un servicio nuevo suma 1', (await kpi()) === antes + 1 && (await kpi('&sucursal=' + ids.nor)) === antesNor + 1, '');
  const cmp = async () => (await adm.get('/api/estadisticas.php?desde=2000-01-01&hasta=2100-01-01')).json.data.por_sucursal.find(s => s.id_sucursal === ids.nor).ordenes;
  const cmpAntes = Number(await cmp());
  await adm.req('DELETE', '/api/reparaciones.php', { json: { id } });
  ok('estadisticas: al mandarlo a la papelera ya no cuenta', (await kpi()) === antes && (await kpi('&sucursal=' + ids.nor)) === antesNor, '');
  ok('...ni en la comparativa por sucursal', Number(await cmp()) === cmpAntes - 1, `${cmpAntes} -> ${await cmp()}`);

  // ── 4. Aviso cuando el stock queda bajo lo reservado ───────
  const rep = (await nuevaRep(tc, { id_repuesto_usado: INV['Repuesto Raro'] })).json.data.id; // reserva 1 de 1
  r = await adm.put('/api/inventario.php', { id: INV['Repuesto Raro'], cantidad_delta: -1, id_sucursal: ids.cen });
  ok('bajar el stock por debajo de lo reservado avisa', r.json?.ok === true && /1 unidad reservada/.test(r.json.data.aviso || ''), r.text.slice(0, 200));
  r = await adm.put('/api/inventario.php', { id: INV['Repuesto Raro'], cantidad: 5, id_sucursal: ids.cen });
  ok('con stock suficiente no hay aviso', r.json?.ok === true && !r.json.data.aviso, r.text.slice(0, 160));
  await adm.req('DELETE', '/api/reparaciones.php', { json: { id: rep } });

  // ── 5. El inventario sigue respondiendo sin crear tablas en cada GET ──
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('GET inventario responde con stock y desglose', r.json?.ok && r.json.data.length === 3 && Array.isArray(r.json.data[0].stock), r.text.slice(0, 100));

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
