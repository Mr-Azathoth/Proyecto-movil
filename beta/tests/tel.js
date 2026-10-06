// Direccion/telefono por sucursal y boleta (herencia de la casa matriz).
const { Client, creds, ids, fixtures, BETA_DIR, PHP_BIN } = require('./lib');
// Direccion/telefono por sucursal + boleta. Uso: node t_tel.js
const fs = require('fs');
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };
const php = (code) => require('child_process').execFileSync(PHP_BIN,
  ['-r', 'chdir("' + BETA_DIR + '"); require "includes/config.php"; $db=getDB(); ' + code]).toString().trim();
const nueva = (cl, e) => cl.postForm('/api/reparaciones.php', { nombre_cliente: 'Cliente Boleta', telefono_cliente: '+56966666666', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', ...e });

(async () => {
  // Casa matriz de la empresa A
  php('$db->exec("UPDATE empresas SET direccion=\'Av. Matriz 100\', comuna=\'Santiago\', telefono=\'+56 2 2000 0000\' WHERE id_empresa=' + ids.A + '");');
  // la columna se agrega sola: se simula una BD anterior quitando telefono
  php('try { $db->exec("ALTER TABLE sucursales DROP COLUMN telefono"); } catch (Throwable $e) {}');
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro'), adb = await new Client().login('adminB');

  let r = await adm.get('/api/sucursales.php');
  const cen = r.json?.data?.sucursales?.find(s => s.id_sucursal === ids.cen);
  ok('esquema anterior: guard() agrega la columna telefono solo', r.json?.ok && cen && 'telefono' in cen, r.text.slice(0, 160));
  ok('GET incluye los datos de la casa matriz', r.json.data.matriz?.direccion === 'Av. Matriz 100, Santiago' && r.json.data.matriz.telefono === '+56 2 2000 0000', JSON.stringify(r.json.data.matriz));

  // Editar Centro con datos propios; Norte queda en blanco
  r = await adm.put('/api/sucursales.php', { id_sucursal: ids.cen, direccion: 'Calle Centro 123', telefono: '+56 9 1111 2222' });
  ok('editar direccion y telefono de Centro', r.json?.ok === true, r.text.slice(0, 120));
  r = await adm.put('/api/sucursales.php', { id_sucursal: ids.nor, direccion: '', telefono: '' });
  ok('Norte con campos vacios (hereda de la casa matriz)', r.json?.ok === true, r.text.slice(0, 120));
  r = await adm.get('/api/sucursales.php');
  const c2 = r.json.data.sucursales.find(s => s.id_sucursal === ids.cen), n2 = r.json.data.sucursales.find(s => s.id_sucursal === ids.nor);
  ok('la lista devuelve lo guardado (y vacio en Norte)', c2.telefono === '+56 9 1111 2222' && c2.direccion === 'Calle Centro 123' && n2.telefono === '' && n2.direccion === '', JSON.stringify([c2.telefono, n2.telefono]));

  // Validacion
  for (const [t, esperado] of [['abc 123456', false], ['12345', false], ['+56 9 11', false], ['x'.repeat(31), false], ['(2) 2345-6789', true], ['+56912345678', true]]) {
    r = await adm.put('/api/sucursales.php', { id_sucursal: ids.nor, telefono: t });
    ok(`telefono "${t.slice(0, 14)}" -> ${esperado ? 'valido' : 'rechazado'}`, (r.json?.ok === true) === esperado, r.text.slice(0, 120));
  }
  // El mismo telefono en varias sucursales y igual al de la casa matriz
  r = await adm.put('/api/sucursales.php', { id_sucursal: ids.nor, telefono: '+56 9 1111 2222' });
  ok('el telefono puede ser el mismo que el de otra sucursal', r.json?.ok === true, r.text.slice(0, 100));
  r = await adm.put('/api/sucursales.php', { id_sucursal: ids.bod, telefono: '+56 2 2000 0000' });
  ok('...o el mismo que el de la casa matriz', r.json?.ok === true, r.text.slice(0, 100));
  await adm.put('/api/sucursales.php', { id_sucursal: ids.nor, telefono: '' });
  await adm.put('/api/sucursales.php', { id_sucursal: ids.bod, telefono: '' });

  r = await tc.put('/api/sucursales.php', { id_sucursal: ids.cen, telefono: '+56 9 0000 0000' });
  ok('un tecnico no puede editar los datos de contacto (403)', r.status === 403, r.status);
  r = await adb.get('/api/sucursales.php');
  ok('empresa B ve su propia casa matriz, no la de A', !/Matriz 100/.test(r.text), r.text.slice(0, 200));
  r = await adb.put('/api/sucursales.php', { id_sucursal: ids.cen, telefono: '+56 9 0000 0000' });
  ok('empresa B no puede editar una sucursal de A (404)', r.status === 404, r.status);

  // Boleta
  const repC = (await nueva(adm, { id_sucursal: ids.cen })).json.data.id;
  const repN = (await nueva(adm, { id_sucursal: ids.nor })).json.data.id;
  const bC = (await adm.get('/orden.php?id=' + repC)).text;
  const bN = (await adm.get('/orden.php?id=' + repN)).text;
  ok('boleta de Centro: direccion y telefono de la sucursal', bC.includes('Calle Centro 123') && bC.includes('+56 9 1111 2222'), '');
  ok('...y NO los de la casa matriz', !bC.includes('Av. Matriz 100') && !bC.includes('+56 2 2000 0000'), '');
  ok('boleta de Norte (campos vacios): hereda direccion y telefono de la casa matriz', bN.includes('Av. Matriz 100, Santiago') && bN.includes('+56 2 2000 0000'), '');
  ok('la boleta indica la sucursal (hay mas de una con atencion)', bC.includes('Sucursal Centro') && bN.includes('Sucursal Norte'), '');
  // Direccion propia pero telefono heredado
  await adm.put('/api/sucursales.php', { id_sucursal: ids.nor, direccion: 'Calle Norte 456', telefono: '' });
  const bN2 = (await adm.get('/orden.php?id=' + repN)).text;
  ok('direccion propia + telefono heredado conviven en la misma boleta', bN2.includes('Calle Norte 456') && bN2.includes('+56 2 2000 0000'), '');
  // Escapado
  await adm.put('/api/sucursales.php', { id_sucursal: ids.nor, direccion: '<script>alert(1)</script>', telefono: '' });
  const bX = (await adm.get('/orden.php?id=' + repN)).text;
  ok('la boleta escapa el HTML de la direccion', !bX.includes('<script>alert(1)</script>') && bX.includes('&lt;script&gt;'), '');
  r = await adb.get('/orden.php?id=' + repC);
  ok('otra empresa no puede ver la boleta (no encontrada)', /no encontrada/i.test(r.text) && !r.text.includes('Calle Centro 123'), r.text.slice(0, 80));
  await adm.put('/api/sucursales.php', { id_sucursal: ids.nor, direccion: '', telefono: '' });

  // Limpieza de las reparaciones de prueba
  for (const id of [repC, repN]) await adm.req('DELETE', '/api/reparaciones.php', { json: { id } });
  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
