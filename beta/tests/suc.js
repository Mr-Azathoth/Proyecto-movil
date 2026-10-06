// Sucursales: permisos, aislamiento entre empresas y entre sucursales, usuarios y estadisticas.
const { Client, creds, ids, fixtures, BETA_DIR, PHP_BIN } = require('./lib');
let pass = 0, fail = 0;
const ok = (name, cond, extra = '') => { cond ? pass++ : fail++; console.log((cond ? 'PASS ' : 'FAIL ') + name + (cond ? '' : '  -> ' + extra)); };

(async () => {
  const adm = await new Client().login('adminA');
  const tc  = await new Client().login('tecCentro');
  const tn  = await new Client().login('tecNorte');
  const tm  = await new Client().login('tecMulti');
  const adb = await new Client().login('adminB');

  // ── Lectura ────────────────────────────────────────────────
  let r = await tc.get('/api/reparaciones.php');
  ok('tecnico ve reparaciones de todas las sucursales (4)', r.json?.ok && r.json.data.length === 4, JSON.stringify(r.json).slice(0, 120));
  r = await tc.get('/api/reparaciones.php?sucursal=' + ids.nor);
  ok('filtro por Norte devuelve 2', r.json?.data?.length === 2 && r.json.data.every(x => x.id_sucursal === ids.nor), r.text.slice(0, 120));
  r = await tc.get('/api/reparaciones.php?sucursal=' + ids.b1);
  ok('filtro con sucursal de OTRA empresa se rechaza', r.status === 400 && r.json?.ok === false, r.status + r.text.slice(0, 100));
  r = await adb.get('/api/reparaciones.php');
  ok('empresa B solo ve su reparacion', r.json?.data?.length === 1 && r.json.data[0].id_ingreso === ids.repB1, r.text.slice(0, 120));

  // ── Escritura: tecnico en otra sucursal ────────────────────
  r = await tc.put('/api/reparaciones.php', { id: ids.repNor1, status: 'En Reparacion' });
  ok('tecCentro NO puede editar reparacion de Norte (403)', r.status === 403, r.status + r.text.slice(0, 100));
  r = await tc.put('/api/reparaciones.php', { id: ids.repCen1, status: 'En Reparacion' });
  ok('tecCentro SI edita reparacion de Centro', r.json?.ok === true, r.text.slice(0, 120));
  r = await tm.put('/api/reparaciones.php', { id: ids.repNor1, status: 'En Reparacion' });
  ok('tecMulti (habilitado en Norte) SI edita reparacion de Norte', r.json?.ok === true, r.text.slice(0, 120));
  r = await tn.put('/api/reparaciones.php', { id: ids.repCen2, status: 'En Reparacion' });
  ok('tecNorte NO puede editar Centro (403)', r.status === 403, r.status);

  // ── Traslado ───────────────────────────────────────────────
  r = await tc.put('/api/reparaciones.php', { id: ids.repCen1, id_sucursal: ids.nor });
  ok('tecnico NO puede trasladar de sucursal (403)', r.status === 403, r.status + r.text.slice(0, 100));
  r = await adm.put('/api/reparaciones.php', { id: ids.repCen2, id_sucursal: ids.bod });
  ok('admin NO puede trasladar a una bodega', r.json?.ok === false, r.text.slice(0, 120));
  r = await adm.put('/api/reparaciones.php', { id: ids.repCen2, id_sucursal: ids.b1 });
  ok('admin NO puede trasladar a sucursal de otra empresa', r.json?.ok === false, r.text.slice(0, 120));
  r = await adm.put('/api/reparaciones.php', { id: ids.repCen2, id_sucursal: ids.nor });
  ok('admin SI traslada Centro -> Norte', r.json?.ok === true, r.text.slice(0, 120));
  r = await adm.get('/api/reparaciones.php?sucursal=' + ids.nor);
  ok('Norte ahora tiene 3', r.json?.data?.length === 3, r.json?.data?.length);
  await adm.put('/api/reparaciones.php', { id: ids.repCen2, id_sucursal: ids.cen });

  // ── Creacion ───────────────────────────────────────────────
  const base = { nombre_cliente: 'T Cliente', telefono_cliente: '+56922222222', dano_ingreso: 'Prueba', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono' };
  r = await tc.postForm('/api/reparaciones.php', base);
  ok('crear sin id_sucursal usa la base del tecnico', r.json?.ok === true, r.text.slice(0, 120));
  const idDef = r.json?.data?.id;
  r = await tc.get('/api/reparaciones.php?sucursal=' + ids.cen);
  ok('...y quedo en Centro', !!r.json?.data?.find(x => x.id_ingreso === idDef), '');
  r = await tc.postForm('/api/reparaciones.php', { ...base, id_sucursal: ids.nor });
  ok('tecCentro NO crea en Norte (403)', r.status === 403, r.status + r.text.slice(0, 100));
  r = await tc.postForm('/api/reparaciones.php', { ...base, id_sucursal: ids.b1 });
  ok('crear en sucursal de otra empresa se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await adm.postForm('/api/reparaciones.php', { ...base, id_sucursal: ids.bod });
  ok('crear servicio en bodega se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await tm.postForm('/api/reparaciones.php', { ...base, id_sucursal: ids.nor });
  ok('tecMulti SI crea en Norte', r.json?.ok === true, r.text.slice(0, 100));

  // ── API sucursales ─────────────────────────────────────────
  r = await tc.post('/api/sucursales.php', { nombre: 'Hack' });
  ok('tecnico NO crea sucursales (403)', r.status === 403, r.status);
  r = await adm.post('/api/sucursales.php', { nombre: 'Sur', direccion: 'Calle 1' });
  ok('admin crea sucursal', r.json?.ok === true, r.text.slice(0, 100));
  const idSur = r.json?.data?.id;
  r = await adm.post('/api/sucursales.php', { nombre: 'Sur' });
  ok('nombre duplicado se rechaza', r.json?.ok === false, '');
  r = await adb.get('/api/sucursales.php');
  ok('empresa B no ve las sucursales de A', r.json?.data?.sucursales?.every(s => s.nombre === 'B1'), r.text.slice(0, 150));
  r = await adb.put('/api/sucursales.php', { id_sucursal: idSur, nombre: 'Robada' });
  ok('admin B NO edita sucursal de A (404)', r.status === 404, r.status);
  r = await adm.put('/api/sucursales.php', { id_sucursal: ids.cen, activa: 0 });
  ok('no desactiva sucursal con usuarios asignados', r.json?.ok === false, r.text.slice(0, 120));
  r = await adm.put('/api/sucursales.php', { id_sucursal: idSur, activa: 0 });
  ok('desactiva sucursal vacia', r.json?.ok === true, r.text.slice(0, 120));
  r = await tc.get('/api/sucursales.php');
  ok('tecnico no ve sucursales inactivas', !r.json?.data?.sucursales?.some(s => s.id_sucursal === idSur), '');
  r = await tm.get('/api/sucursales.php');
  ok('escritura de tecMulti = Centro + Norte', JSON.stringify([...r.json.data.escritura].sort()) === JSON.stringify([ids.cen, ids.nor].sort()), JSON.stringify(r.json?.data?.escritura));

  // ── Usuarios ───────────────────────────────────────────────
  r = await adm.get('/api/usuarios.php');
  const uMulti = r.json?.data?.find(u => u.user === 'tecMulti');
  ok('listado de usuarios incluye sucursal y extras', uMulti?.id_sucursal === ids.cen && uMulti.sucursales_extra.includes(ids.nor), JSON.stringify(uMulti));
  r = await adm.put('/api/usuarios.php', { id_usuario: uMulti.id_usuario, id_sucursal: ids.b1 });
  ok('asignar sucursal de otra empresa se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await tc.put('/api/usuarios.php', { id_usuario: uMulti.id_usuario, id_sucursal: ids.nor });
  ok('tecnico NO cambia sucursales de usuarios (403)', r.status === 403, r.status);
  // Traslado de local: tecCentro pasa a Norte -> el cambio rige de inmediato
  const uCen = (await adm.get('/api/usuarios.php')).json.data.find(u => u.user === 'tecCentro');
  r = await adm.put('/api/usuarios.php', { id_usuario: uCen.id_usuario, id_sucursal: ids.nor, sucursales_extra: [] });
  ok('admin traslada a tecCentro a Norte', r.json?.ok === true, r.text.slice(0, 100));
  r = await tc.put('/api/reparaciones.php', { id: ids.repCen1, status: 'Ingresado' });
  ok('...tras el traslado ya NO edita Centro (inmediato, sin re-login)', r.status === 403, r.status);
  r = await tc.put('/api/reparaciones.php', { id: ids.repNor2, status: 'En Reparacion' });
  ok('...y SI edita Norte', r.json?.ok === true, r.text.slice(0, 100));
  await adm.put('/api/usuarios.php', { id_usuario: uCen.id_usuario, id_sucursal: ids.cen, sucursales_extra: [] });

  // ── Estadisticas ───────────────────────────────────────────
  r = await adm.get('/api/estadisticas.php?desde=2000-01-01&hasta=2100-01-01');
  const total = r.json?.data?.kpis?.total_ordenes;
  const sum = (r.json?.data?.por_sucursal || []).reduce((a, s) => a + Number(s.ordenes), 0);
  ok('estadisticas: comparativa suma el total', Number(total) === sum && sum > 0, `total=${total} suma=${sum}`);
  ok('estadisticas: la bodega no aparece en la comparativa', !(r.json?.data?.por_sucursal || []).some(s => s.nombre === 'Bodega'), '');
  r = await adm.get('/api/estadisticas.php?desde=2000-01-01&hasta=2100-01-01&sucursal=' + ids.nor);
  ok('estadisticas filtradas por sucursal', r.json?.ok && Number(r.json.data.kpis.total_ordenes) >= 2 && r.json.data.por_sucursal.length === 0, r.text.slice(0, 150));
  r = await adm.get('/api/estadisticas.php?desde=2000-01-01&hasta=2100-01-01&sucursal=' + ids.b1);
  ok('estadisticas con sucursal ajena se rechaza', r.json?.ok === false, r.status);

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.message); process.exit(2); });
