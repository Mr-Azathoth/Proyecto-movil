// Stock minimo por repuesto: alta/edicion y filtro/exportacion "bajo el minimo".
const { Client, ids, fixtures } = require('./lib');
const INV = fixtures().inv;
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };
const csv = (txt) => txt.replace(/^﻿/, '').split(/\r?\n/).filter(Boolean).map(l => l.split(';').map(c => c.replace(/^"|"$/g, '')));

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro'), adb = await new Client().login('adminB');
  const nuevaRep = (cl, e) => cl.postForm('/api/reparaciones.php', { nombre_cliente: 'T', telefono_cliente: '+56977777777', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', ...e });

  // Estado inicial: Centro tiene Pantalla 10, Bateria 5, Raro 1; ninguno tiene minimo.
  let r;
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('el inventario trae stock_minimo (0 por defecto)', r.json.data.every(x => Number(x.stock_minimo) === 0), JSON.stringify(r.json.data[0]));

  // Editar el minimo (admin)
  r = await adm.put('/api/inventario.php', { id: INV['Pantalla A54'], nombre: 'Pantalla A54', marca_compatible: 'Samsung', precio_venta: 45000, stock_minimo: 10 });
  ok('el admin define el minimo de un repuesto', r.json?.ok === true, r.text);
  await adm.put('/api/inventario.php', { id: INV['Repuesto Raro'], nombre: 'Repuesto Raro', precio_venta: 9000, stock_minimo: 2 });
  await adm.put('/api/inventario.php', { id: INV['Bateria X'], nombre: 'Bateria X', precio_venta: 12000, stock_minimo: 3 });
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  const min = n => Number(r.json.data.find(x => x.id_repuesto === INV[n]).stock_minimo);
  ok('...y se devuelve en la lista (10 / 3 / 2)', min('Pantalla A54') === 10 && min('Bateria X') === 3 && min('Repuesto Raro') === 2, '');

  // Editar sin enviar el minimo lo conserva
  await adm.put('/api/inventario.php', { id: INV['Pantalla A54'], nombre: 'Pantalla A54', precio_venta: 45000 });
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('editar el repuesto SIN enviar stock_minimo conserva el valor', min('Pantalla A54') === 10, '');

  // Entradas raras
  await adm.put('/api/inventario.php', { id: INV['Bateria X'], nombre: 'Bateria X', precio_venta: 12000, stock_minimo: -5 });
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('un minimo negativo se guarda como 0', min('Bateria X') === 0, '');
  await adm.put('/api/inventario.php', { id: INV['Bateria X'], nombre: 'Bateria X', precio_venta: 12000, stock_minimo: 3 });

  // El tecnico no puede cambiar el catalogo (ni el minimo)
  r = await tc.put('/api/inventario.php', { id: INV['Pantalla A54'], nombre: 'X', stock_minimo: 0 });
  ok('un tecnico NO puede cambiar el minimo (403)', r.status === 403, r.status);

  // El stock DISPONIBLE es el que cuenta: reservar la unica unidad del Raro lo deja con disponible 0
  const rep = (await nuevaRep(tc, { id_repuesto_usado: INV['Repuesto Raro'] })).json.data.id;
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  const raro = r.json.data.find(x => x.id_repuesto === INV['Repuesto Raro']);
  ok('el Raro reservado queda con 1 fisico, 1 reservado y minimo 2', raro.cantidad === 1 && raro.cantidad_reservada === 1 && Number(raro.stock_minimo) === 2, JSON.stringify(raro));

  // Exportacion
  r = await adm.get('/api/exportar_inventario.php?formato=csv&sucursal=' + ids.cen + '&stock=minimo');
  const rows = csv(r.text);
  ok('exportar "bajo el minimo": Raro (disp 0) y Pantalla (disp 10)', JSON.stringify(rows.slice(1).map(x => x[1])) === JSON.stringify(['Repuesto Raro', 'Pantalla A54']), rows.map(x => x[1]).join('|'));
  ok('...con la columna minimo al final', rows[0][8] === 'minimo' && rows[1][8] === '2' && rows[2][8] === '10', rows[0].join(';') + ' / ' + rows[1].join(';'));
  r = await adm.get('/api/exportar_inventario.php?formato=csv&sucursal=' + ids.cen);
  ok('sin filtro la columna minimo tambien viaja (3 filas)', csv(r.text).length === 4 && csv(r.text)[0][8] === 'minimo', '');
  r = await adm.get('/api/exportar_inventario.php?formato=pdf&sucursal=' + ids.cen + '&stock=minimo');
  ok('PDF: indica el criterio y trae la columna Minimo', r.text.includes('stock mínimo definido') && r.text.includes('<th>Mínimo</th>'), '');

  // Crear un repuesto con minimo
  r = await adm.postForm('/api/inventario.php', { nombre: 'Con minimo', precio_venta: '1000', cantidad: '4', stock_minimo: '6', id_sucursal: String(ids.cen) });
  ok('crear un repuesto con minimo', r.json?.ok === true, r.text);
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  const nuevo = r.json.data.find(x => x.nombre === 'Con minimo');
  ok('...queda guardado y con stock 4', nuevo && Number(nuevo.stock_minimo) === 6 && nuevo.cantidad === 4, JSON.stringify(nuevo));
  r = await adm.get('/api/exportar_inventario.php?formato=csv&sucursal=' + ids.cen + '&stock=minimo');
  ok('...y ya aparece en "bajo el minimo" (4 <= 6)', csv(r.text).slice(1).some(x => x[1] === 'Con minimo'), '');
  if (nuevo) await adm.req('DELETE', '/api/inventario.php?id=' + nuevo.id_repuesto, { json: {} });
  r = await adm.get('/api/exportar_inventario.php?formato=csv&sucursal=' + ids.cen + '&stock=minimo');
  ok('un repuesto eliminado deja de aparecer', !csv(r.text).slice(1).some(x => x[1] === 'Con minimo'), '');

  await adm.req('DELETE', '/api/reparaciones.php', { json: { id: rep } });
  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
