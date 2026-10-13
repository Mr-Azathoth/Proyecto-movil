// Filtro de stock bajo / agotado (sobre el stock DISPONIBLE) y exportacion de la lista de reposicion.
const { Client, creds, ids, fixtures } = require('./lib');
const INV = fixtures().inv;
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };

// CSV -> filas (sin BOM, separador ;)
const csv = (txt) => txt.replace(/^\uFEFF/, '').split(/\r?\n/).filter(Boolean).map(l => l.split(';').map(c => c.replace(/^"|"$/g, '')));
const nombres = (rows) => rows.slice(1).map(r => r[1]);
const exp = (cl, q) => cl.get('/api/exportar_inventario.php?formato=csv' + q);

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro'), adb = await new Client().login('adminB');
  const nuevaRep = (cl, e) => cl.postForm('/api/reparaciones.php', { nombre_cliente: 'T', telefono_cliente: '+56977777777', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', ...e });

  // Estado inicial: Centro tiene Pantalla 10, Bateria 5, Raro 1; Norte y Bodega no tienen nada.
  let r = await exp(adm, '&sucursal=' + ids.cen);
  let rows = csv(r.text);
  ok('el CSV trae las columnas reservado y disponible', rows[0].join(';') === 'id;nombre;marca_compatible;modelo_compatible;precio_venta;cantidad;reservado;disponible;minimo', rows[0].join(';'));
  ok('...y sin filtro devuelve todo', rows.length - 1 === 3, rows.length);

  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=bajo&umbral=5');
  rows = csv(r.text);
  ok('Bajo (<= 5) en Centro: Raro (1) y Bateria (5), sin Pantalla (10)', JSON.stringify(nombres(rows)) === JSON.stringify(['Repuesto Raro', 'Bateria X']), nombres(rows).join('|'));
  ok('...ordenados de menos a mas stock disponible', Number(rows[1][7]) <= Number(rows[2][7]), rows.map(x => x[7]).join(','));

  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=bajo&umbral=0');
  ok('Bajo (<= 0) sin agotados: solo el encabezado', csv(r.text).length === 1, r.text.slice(0, 80));

  // Reservar el Raro (cantidad 1): disponible 0 aunque fisicamente hay 1
  const rep = (await nuevaRep(tc, { id_repuesto_usado: INV['Repuesto Raro'] })).json.data.id;
  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=agotado');
  rows = csv(r.text);
  ok('Agotados usa el stock DISPONIBLE: el Raro (1 fisico, 1 reservado) aparece', JSON.stringify(nombres(rows)) === JSON.stringify(['Repuesto Raro']), nombres(rows).join('|'));
  ok('...con cantidad 1, reservado 1 y disponible 0', rows[1].slice(5, 8).join(',') === '1,1,0', rows[1].join(';'));

  r = await exp(adm, '&sucursal=' + ids.nor + '&stock=agotado');
  ok('Agotados en Norte (sin stock): aparecen los 3 repuestos del catalogo', csv(r.text).length - 1 === 3, nombres(csv(r.text)).join('|'));

  r = await exp(adm, '&stock=bajo&umbral=1');
  rows = csv(r.text);
  ok('Todas las sucursales (total): Bajo <= 1 trae solo el Raro (total disponible 0)', JSON.stringify(nombres(rows)) === JSON.stringify(['Repuesto Raro']), nombres(rows).join('|'));

  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=bajo&umbral=5&q=Bateria');
  ok('el filtro se combina con la busqueda', JSON.stringify(nombres(csv(r.text))) === JSON.stringify(['Bateria X']), nombres(csv(r.text)).join('|'));

  // Entradas raras
  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=xyz');
  ok('un valor de filtro desconocido se ignora (devuelve todo)', csv(r.text).length - 1 === 3, csv(r.text).length);
  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=bajo&umbral=-5');
  const r0 = await exp(adm, '&sucursal=' + ids.cen + '&stock=bajo&umbral=0');
  ok('un umbral negativo se trata como 0 (igual que umbral=0)', JSON.stringify(csv(r.text)) === JSON.stringify(csv(r0.text)), csv(r.text).length + ' vs ' + csv(r0.text).length);
  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=bajo&umbral=1;DROP%20TABLE%20inventario');
  ok('un umbral con SQL se convierte a numero y no rompe nada', r.status === 200 && csv(r.text).length >= 1, r.status + r.text.slice(0, 60));
  r = await adm.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('...y el inventario sigue intacto', r.json?.ok && r.json.data.length === 3, r.text.slice(0, 80));

  // Orden manual del usuario se respeta
  r = await exp(adm, '&sucursal=' + ids.cen + '&stock=bajo&umbral=100&sort_col=nombre&sort_dir=desc');
  ok('con un orden elegido (nombre desc) se respeta ese orden', JSON.stringify(nombres(csv(r.text))) === JSON.stringify(['Repuesto Raro', 'Pantalla A54', 'Bateria X']), nombres(csv(r.text)).join('|'));

  // Otros formatos
  r = await adm.get('/api/exportar_inventario.php?formato=pdf&sucursal=' + ids.cen + '&stock=bajo&umbral=5');
  ok('PDF: indica el criterio y las unidades disponibles', r.text.includes('Stock disponible ≤ 5 (incluye agotados)') && r.text.includes('disponibles') && r.text.includes('<th>Disponible</th>'), '');
  r = await adm.get('/api/exportar_inventario.php?formato=pdf&sucursal=' + ids.cen + '&stock=agotado');
  ok('PDF agotados: indica "Solo agotados"', r.text.includes('Solo agotados'), '');
  r = await adm.get('/api/exportar_inventario.php?formato=xlsx&sucursal=' + ids.cen + '&stock=bajo&umbral=5');
  ok('XLSX responde como planilla', r.status === 200 && /spreadsheetml/.test(r.headers['content-type'] || ''), r.status + ' ' + r.headers['content-type']);

  // Permisos y aislamiento
  r = await tc.get('/api/exportar_inventario.php?formato=csv&stock=bajo&umbral=5');
  ok('un tecnico no puede exportar (403)', r.status === 403, r.status);
  r = await exp(adb, '&sucursal=' + ids.cen + '&stock=bajo&umbral=5');
  ok('la empresa B no puede filtrar por una sucursal de A (400)', r.status === 400, r.status);
  r = await exp(adb, '&stock=bajo&umbral=100');
  ok('...y solo ve su propio inventario', JSON.stringify(nombres(csv(r.text))) === JSON.stringify(['Repuesto B']), nombres(csv(r.text)).join('|'));

  await adm.req('DELETE', '/api/reparaciones.php', { json: { id: rep } });
  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
