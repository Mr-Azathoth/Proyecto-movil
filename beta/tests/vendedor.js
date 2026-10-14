// Rol Vendedor (mostrador): permisos, limites por cargo, cambio de cargo inmediato y sesion de usuarios eliminados.
const { Client, creds, ids, fixtures } = require('./lib');
const INV = fixtures().inv;
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro'), vd = await new Client().login('vendCentro');
  const nuevaRep = (cl, e = {}) => cl.postForm('/api/reparaciones.php', { nombre_cliente: 'Cli', telefono_cliente: '+56977777777', dano_ingreso: 'x', marca_ingreso: 'X', modelo_ingreso: 'Y', tipo_ingreso: 'Telefono', ...e });
  const rep = async id => (await adm.get('/api/reparaciones.php')).json.data.find(x => x.id_ingreso === id);
  const prohibido = (r) => r.status === 403;

  // ── Acceso y pantalla ──────────────────────────────────────
  let r = await vd.get('/app.php');
  ok('el vendedor inicia sesion y la app lo identifica como Vendedor', r.status === 200 && /data-role="Vendedor"/.test(r.text), r.status);
  ok('...no ve Estadisticas ni Configuracion en el menu', !/data-view="estadisticas"/.test(r.text) && !/data-view="config"/.test(r.text), '');
  ok('...ni el selector de repuesto al ingresar un servicio (oculto)', /id="fg-rep-nuevo"/.test(r.text) && /class="fg fg-full hidden" id="fg-rep-nuevo"/.test(r.text), '');
  ok('...ni la opcion "En Reparacion" al ingresar', !/<option value="En Reparacion">En Reparación<\/option>\s*<\/select>\s*<\/div>\s*<div class="fg fg-full"><label>Observación inicial/.test(r.text), '');
  ok('...ni "+ Agregar" repuestos en el detalle (oculto)', /class="rep-add-row hidden" id="rep-add-row"/.test(r.text), '');
  let ra = (await adm.get('/app.php')).text;
  ok('el admin SI ve el selector de repuesto y "+ Agregar" (sin cambios)', /class="fg fg-full" id="fg-rep-nuevo"/.test(ra) && /class="rep-add-row" id="rep-add-row"/.test(ra) && /<option value="En Reparacion">/.test(ra), '');
  ra = (await tc.get('/app.php')).text;
  ok('el tecnico ve el selector de repuesto y la opcion En Reparacion (sin cambios), pero no "+ Agregar" (solo admin)', /class="fg fg-full" id="fg-rep-nuevo"/.test(ra) && /<option value="En Reparacion">/.test(ra) && /class="rep-add-row hidden"/.test(ra), '');

  // ── Lo que SI puede ────────────────────────────────────────
  r = await vd.get('/api/reparaciones.php');
  ok('ve todas las reparaciones (lectura de todas las sucursales)', r.json?.ok && r.json.data.length === 4, r.text.slice(0, 80));
  r = await vd.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('consulta el inventario', r.json?.ok && r.json.data.length === 3, r.text.slice(0, 80));
  r = await vd.get('/api/sucursales.php');
  ok('...y las sucursales', r.json?.ok && r.json.data.escritura.includes(ids.cen) && !r.json.data.escritura.includes(ids.nor), JSON.stringify(r.json?.data?.escritura));

  r = await nuevaRep(vd, { status: 'En Reparacion' });
  const nueva = r.json?.data?.id;
  ok('ingresa un servicio en su sucursal', r.json?.ok === true && !!nueva, r.text.slice(0, 100));
  ok('...y aunque pida "En Reparacion" queda como Ingresado', (await rep(nueva))?.status === 'Ingresado', (await rep(nueva))?.status);
  r = await nuevaRep(vd, { id_sucursal: String(ids.nor) });
  ok('NO puede ingresarlo en una sucursal donde no esta habilitado (403)', prohibido(r), r.status);
  r = await nuevaRep(vd, { id_repuesto_usado: INV['Repuesto Raro'] });
  ok('NO puede asignar un repuesto al ingresarlo (403)', prohibido(r), r.status + ' ' + r.text.slice(0, 100));
  r = await vd.get('/api/inventario.php?sucursal=' + ids.cen);
  ok('...y no se reservo ningun repuesto', r.json.data.every(x => x.cantidad_reservada === 0), JSON.stringify(r.json.data.map(x => x.cantidad_reservada)));

  r = await vd.put('/api/reparaciones.php', { id: nueva, status: 'Ingresado', obs: 'cliente llamo' });
  ok('agrega una observacion sin cambiar el estado', r.json?.ok === true, r.text.slice(0, 100));
  r = await vd.put('/api/reparaciones.php', { id: nueva, status: 'En Reparacion' });
  ok('NO puede pasarlo a En Reparacion (403)', prohibido(r), r.status);
  r = await vd.put('/api/reparaciones.php', { id: nueva, status: 'Reparado' });
  ok('NO puede marcarlo Reparado (403)', prohibido(r), r.status);
  r = await vd.put('/api/reparaciones.php', { id: nueva, valor: 99999 });
  ok('puede guardar, pero el valor no cambia (solo el admin lo edita)', r.json?.ok === true && Number((await rep(nueva)).valor_ingreso) === 0, JSON.stringify((await rep(nueva)).valor_ingreso));
  r = await vd.put('/api/reparaciones.php', { id: nueva, id_repuesto_usado: INV['Repuesto Raro'] });
  ok('NO puede asignarle un repuesto (403)', prohibido(r), r.status);
  r = await vd.put('/api/reparaciones.php', { id: ids.repNor1, status: 'Entregado' });
  ok('NO puede tocar reparaciones de otra sucursal (403)', prohibido(r), r.status);
  r = await vd.put('/api/reparaciones.php', { id: nueva, status: 'Entregado' });
  ok('SI puede entregar el equipo', r.json?.ok === true && (await rep(nueva)).status === 'Entregado', r.text.slice(0, 100));
  r = await vd.put('/api/reparaciones.php', { id: nueva, status: 'Garantia' });
  ok('...y recibirlo en garantia', r.json?.ok === true && (await rep(nueva)).status === 'Garantia', r.text.slice(0, 100));

  // ── Lo que NO puede ────────────────────────────────────────
  r = await vd.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad_delta: 1, id_sucursal: ids.cen });
  ok('NO puede ajustar el stock (+/-) (403)', prohibido(r), r.status);
  r = await vd.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad: 99, id_sucursal: ids.cen });
  ok('NO puede fijar el stock (403)', prohibido(r), r.status);
  r = await vd.put('/api/inventario.php', { id: INV['Pantalla A54'], nombre: 'X' });
  ok('NO puede editar el catalogo (403)', prohibido(r), r.status);
  r = await vd.postForm('/api/inventario.php', { nombre: 'X', cantidad: '1' });
  ok('NO puede crear repuestos (403)', prohibido(r), r.status);
  r = await vd.req('DELETE', '/api/inventario.php?id=' + INV['Bateria X'], { json: {} });
  ok('NO puede eliminar repuestos (403)', prohibido(r), r.status);
  for (const [nombre, f] of [
    ['exportar el inventario', () => vd.get('/api/exportar_inventario.php?formato=csv')],
    ['exportar servicios', () => vd.get('/api/exportar.php?formato=csv')],
    ['ver estadisticas', () => vd.get('/api/estadisticas.php?desde=2000-01-01&hasta=2100-01-01')],
    ['ver la lista de usuarios', () => vd.get('/api/usuarios.php')],
    ['crear sucursales', () => vd.post('/api/sucursales.php', { nombre: 'Z' })],
    ['hacer traspasos', () => vd.post('/api/traspasos.php', { id_repuesto: INV['Pantalla A54'], id_origen: ids.cen, id_destino: ids.nor, cantidad: 1 })],
    ['agregar repuestos adicionales a un servicio', () => vd.post('/api/rep_servicio.php', { id_reparacion: nueva, id_repuesto: INV['Pantalla A54'], cantidad: 1 })],
  ]) { const x = await f(); ok('NO puede ' + nombre + ' (403)', prohibido(x), x.status); }

  // ── El tecnico sigue igual ─────────────────────────────────
  const r2 = (await nuevaRep(tc)).json.data.id;
  r = await tc.put('/api/reparaciones.php', { id: r2, status: 'En Reparacion' });
  ok('el tecnico SI pasa a En Reparacion (sin cambios)', r.json?.ok === true, r.text.slice(0, 100));
  r = await tc.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad_delta: 1, id_sucursal: ids.cen });
  ok('el tecnico SI ajusta el stock de su sucursal (sin cambios)', r.json?.ok === true, r.text.slice(0, 100));
  await tc.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad_delta: -1, id_sucursal: ids.cen });

  // ── El cargo rige al instante ──────────────────────────────
  const us = (await adm.get('/api/usuarios.php')).json.data;
  const uid = u => us.find(x => x.user === u).id_usuario;
  const r3 = (await nuevaRep(vd)).json.data.id;
  r = await vd.put('/api/reparaciones.php', { id: r3, status: 'En Reparacion' });
  ok('(antes) vendedor: no puede pasar a En Reparacion', prohibido(r), r.status);
  r = await adm.put('/api/usuarios.php', { id_usuario: uid('vendCentro'), cargo: 'Tecnico' });
  ok('el admin lo cambia a Tecnico', r.json?.ok === true, r.text.slice(0, 100));
  r = await vd.put('/api/reparaciones.php', { id: r3, status: 'En Reparacion' });
  ok('...y su sesion YA abierta lo refleja de inmediato (puede pasar a En Reparacion)', r.json?.ok === true, r.status + ' ' + r.text.slice(0, 100));
  ok('...y la app lo muestra como Tecnico sin volver a iniciar sesion', /data-role="Tecnico"/.test((await vd.get('/app.php')).text), '');
  r = await adm.put('/api/usuarios.php', { id_usuario: uid('vendCentro'), cargo: 'Vendedor' });
  r = await vd.put('/api/reparaciones.php', { id: r2, status: 'Reparado' });
  ok('al volver a Vendedor, la misma sesion pierde ese permiso de inmediato', prohibido(r), r.status);

  // ── Administrar vendedores ─────────────────────────────────
  r = await adm.post('/api/usuarios.php', { nombre: 'Otro Vend', user: 'vend_otro', password: 'Clave12345', cargo: 'Vendedor' });
  ok('el admin crea un vendedor', r.json?.ok === true && /Vendedor Otro Vend/.test(r.json.data.msg) && r.json.data.cargo === 'Vendedor', r.text.slice(0, 120));
  const creados = [r.json?.data?.id];
  r = await adm.post('/api/usuarios.php', { nombre: 'Raro', user: 'cargo_raro', password: 'Clave12345', cargo: 'Admin' });
  ok('no se puede crear un Admin desde ahi (cargo invalido)', r.json?.ok === false, r.text.slice(0, 100));
  r = await adm.post('/api/usuarios.php', { nombre: 'Cargo lista', user: 'cargo_lista', password: 'Clave12345', cargo: ['Vendedor'] });
  ok('un cargo que no es texto (lista) se rechaza sin errores de PHP', r.json?.ok === false && /Cargo inválido/.test(r.json.msg || '') && !/Warning|Array to string/.test(r.text), r.text.slice(0, 160));
  r = await adm.post('/api/usuarios.php', { nombre: 'Sin cargo', user: 'sin_cargo', password: 'Clave12345' });
  ok('sin indicar cargo se crea un Tecnico (compatibilidad)', r.json?.ok === true && r.json.data.cargo === 'Tecnico', r.text.slice(0, 100));
  creados.push(r.json?.data?.id);
  let nVend = 2;                               // vendCentro + vend_otro
  for (let i = 0; nVend < 10; i++, nVend++) { const x = await adm.post('/api/usuarios.php', { nombre: 'V' + i, user: 'vend_x' + i, password: 'Clave12345', cargo: 'Vendedor' }); creados.push(x.json?.data?.id); }
  r = await adm.post('/api/usuarios.php', { nombre: 'Sexto', user: 'vend_sexto', password: 'Clave12345', cargo: 'Vendedor' });
  ok('el limite es de 10 vendedores', r.json?.ok === false && /máximo 10 vendedores/.test(r.json.msg || ''), r.text.slice(0, 120));
  r = await adm.post('/api/usuarios.php', { nombre: 'Tec', user: 'tec_extra', password: 'Clave12345', cargo: 'Tecnico' });
  ok('...y es independiente del de tecnicos (hay 4, aun cabe uno)', r.json?.ok === true, r.text.slice(0, 120));
  creados.push(r.json?.data?.id);
  r = await adm.post('/api/usuarios.php', { nombre: 'Tec6', user: 'tec_sexto', password: 'Clave12345', cargo: 'Tecnico' });
  ok('el limite de tecnicos sigue siendo 5', r.json?.ok === false && /máximo 5 técnicos/.test(r.json.msg || ''), r.text.slice(0, 120));
  r = await adm.put('/api/usuarios.php', { id_usuario: uid('tecNorte'), cargo: 'Vendedor' });
  ok('pasar a alguien a Vendedor con el cupo lleno tambien se rechaza', r.json?.ok === false && /máximo 10 vendedores/.test(r.json.msg || ''), r.text.slice(0, 120));
  r = await adm.put('/api/usuarios.php', { id_usuario: uid('vendCentro'), cargo: 'Superman' });
  ok('un cargo inexistente se rechaza', r.json?.ok === false, r.text.slice(0, 100));
  r = await adm.put('/api/usuarios.php', { id_usuario: ids.A, cargo: 'Tecnico' });
  ok('el admin no se cambia a si mismo el cargo', r.json?.ok === false, r.text.slice(0, 100));
  r = await vd.put('/api/usuarios.php', { id_usuario: uid('tecNorte'), cargo: 'Admin' });
  ok('un vendedor NO puede cambiar cargos (403)', prohibido(r), r.status);

  // Se liberan los cupos usados por las pruebas anteriores
  for (const id of creados.slice(1)) if (id) await adm.req('DELETE', '/api/usuarios.php', { json: { id_usuario: id } });

  // ── Un usuario eliminado pierde la sesion ──────────────────
  creds.vend_temp = 'Clave12345';
  r = await adm.post('/api/usuarios.php', { nombre: 'Temporal', user: 'vend_temp', password: 'Clave12345', cargo: 'Tecnico' });
  const tmpId = r.json?.data?.id;
  const sesionTmp = await new Client().login('vend_temp');
  ok('un usuario nuevo inicia sesion', (await sesionTmp.get('/api/reparaciones.php')).json?.ok === true, '');
  r = await adm.req('DELETE', '/api/usuarios.php', { json: { id_usuario: tmpId } });
  ok('el admin lo elimina', r.json?.ok === true, r.text.slice(0, 100));
  r = await sesionTmp.get('/api/reparaciones.php');
  ok('...y su sesion ya abierta deja de funcionar (401), no sigue operando', r.status === 401, r.status + ' ' + r.text.slice(0, 80));
  r = await adm.req('DELETE', '/api/usuarios.php', { json: { id_usuario: creados[0] } });
  ok('se puede eliminar a un vendedor', r.json?.ok === true && /Vendedor Otro Vend eliminado/.test(r.json.data.msg), r.text.slice(0, 100));
  r = await adm.req('DELETE', '/api/usuarios.php', { json: { id_usuario: ids.A ? us.find(x => x.cargo === 'Admin').id_usuario : 0 } });
  ok('a un admin no (solo tecnicos y vendedores)', r.json?.ok === false, r.text.slice(0, 100));

  // ── Tecnico y vendedor en VARIAS sucursales ────────────────
  const sur = (await adm.post('/api/sucursales.php', { nombre: 'Sur tercera', direccion: '', telefono: '' })).json.data.id;
  const crear = async (user, cargo, extra) => {
    creds[user] = 'Clave12345';
    const x = await adm.post('/api/usuarios.php', { nombre: user, user, password: 'Clave12345', cargo, id_sucursal: ids.cen, ...extra });
    return x;
  };
  r = await crear('vend_multi', 'Vendedor', { sucursales_extra: [ids.nor, ids.cen, ids.bod] });
  const vmId = r.json?.data?.id;
  ok('se crea un vendedor con sucursal base Centro y adicionales Norte y Bodega (la base repetida se descarta)', r.json?.ok === true && !!vmId, r.text.slice(0, 120));
  let u = (await adm.get('/api/usuarios.php')).json.data.find(x => x.id_usuario === vmId);
  ok('...y queda guardado: base Centro + extras Norte y Bodega', u.id_sucursal === ids.cen && JSON.stringify(u.sucursales_extra.slice().sort()) === JSON.stringify([ids.nor, ids.bod].sort()), JSON.stringify(u));
  const vm = await new Client().login('vend_multi');
  const esc = (await vm.get('/api/sucursales.php')).json.data.escritura.slice().sort();
  ok('el vendedor puede escribir en Centro, Norte y Bodega, no en Sur', JSON.stringify(esc) === JSON.stringify([ids.cen, ids.nor, ids.bod].sort()) && !esc.includes(sur), JSON.stringify(esc));
  r = await nuevaRep(vm, { id_sucursal: String(ids.nor) });
  ok('...ingresa un servicio en Norte (adicional)', r.json?.ok === true && (await rep(r.json.data.id)).id_sucursal === ids.nor, r.text.slice(0, 100));
  r = await nuevaRep(vm);
  ok('...y sin indicar sucursal queda en su base (Centro)', r.json?.ok === true && (await rep(r.json.data.id)).id_sucursal === ids.cen, r.text.slice(0, 100));
  r = await nuevaRep(vm, { id_sucursal: String(sur) });
  ok('...pero NO en Sur, donde no esta habilitado (403)', prohibido(r), r.status);
  r = await nuevaRep(vm, { id_sucursal: String(ids.bod) });
  ok('...ni en la bodega: no atiende servicios', r.json?.ok === false, r.text.slice(0, 100));
  r = await vm.put('/api/reparaciones.php', { id: ids.repNor1, status: 'Entregado' });
  ok('...entrega equipos de Norte (adicional)', r.json?.ok === true, r.text.slice(0, 100));
  r = await vm.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad_delta: 1, id_sucursal: ids.bod });
  ok('...y aunque este habilitado en la bodega sigue sin poder tocar el stock (cargo Vendedor)', prohibido(r), r.status);

  r = await crear('tec_multi2', 'Tecnico', { sucursales_extra: [ids.nor, ids.bod] });
  const tmId = r.json?.data?.id;
  const tm2 = await new Client().login('tec_multi2');
  r = await tm2.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad_delta: 1, id_sucursal: ids.bod });
  ok('un tecnico con la bodega como adicional SI ajusta el stock de la bodega', r.json?.ok === true, r.text.slice(0, 100));
  r = await tm2.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad_delta: 1, id_sucursal: sur });
  ok('...pero no el de Sur (403)', prohibido(r), r.status);
  await tm2.put('/api/inventario.php', { id: INV['Pantalla A54'], cantidad_delta: -1, id_sucursal: ids.bod });

  const antes = (await adm.get('/api/usuarios.php')).json.data.length;
  r = await crear('vend_ajena', 'Vendedor', { sucursales_extra: [ids.nor, ids.b1] });
  ok('una sucursal de OTRA empresa como adicional se rechaza...', r.json?.ok === false, r.text.slice(0, 100));
  ok('...y no se crea el usuario a medias', (await adm.get('/api/usuarios.php')).json.data.length === antes, '');
  await adm.put('/api/sucursales.php', { id_sucursal: sur, activa: 0 });
  r = await crear('vend_inactiva', 'Vendedor', { sucursales_extra: [sur] });
  ok('una sucursal inactiva como adicional tambien se rechaza', r.json?.ok === false, r.text.slice(0, 100));

  // Cambiar despues las sucursales de un vendedor (modal "Sucursal")
  r = await adm.put('/api/usuarios.php', { id_usuario: vmId, id_sucursal: ids.nor, sucursales_extra: [ids.cen] });
  ok('el admin le cambia la base a Norte y deja Centro como adicional', r.json?.ok === true, r.text.slice(0, 100));
  r = await nuevaRep(vm);
  ok('...y ahora sin indicar sucursal queda en Norte (rige de inmediato)', r.json?.ok === true && (await rep(r.json.data.id)).id_sucursal === ids.nor, r.text.slice(0, 100));
  for (const id of [vmId, tmId]) if (id) await adm.req('DELETE', '/api/usuarios.php', { json: { id_usuario: id } });

  // ── Servicios que YA tienen repuesto asignado (lo hizo el tecnico) ──
  const conRep = (await nuevaRep(tc, { id_repuesto_usado: INV['Pantalla A54'] })).json.data.id;
  r = await vd.put('/api/reparaciones.php', { id: conRep, obs: 'llamo el cliente' });
  ok('el vendedor puede guardar una nota en un servicio con repuesto (sin enviar el repuesto)', r.json?.ok === true, r.status + ' ' + r.text.slice(0, 100));
  r = await vd.put('/api/reparaciones.php', { id: conRep, id_repuesto_usado: INV['Bateria X'] });
  ok('...pero NO puede cambiarlo por otro repuesto (403)', prohibido(r), r.status);
  r = await vd.put('/api/reparaciones.php', { id: conRep, id_repuesto_usado: 0 });
  ok('...ni quitarlo (403)', prohibido(r), r.status);
  r = await vd.put('/api/reparaciones.php', { id: conRep, status: 'Entregado', id_repuesto_usado: INV['Pantalla A54'] });
  ok('...y SI puede entregarlo (la pantalla reenvia el mismo repuesto) y se descuenta el stock', r.json?.ok === true && r.json.data.stock_descontado === 1, r.text.slice(0, 120));

  // ── Exportaciones: la columna dice "Ingresado por" (puede ser un vendedor) ──
  r = await adm.get('/api/exportar.php?formato=csv');
  ok('la exportacion de servicios llama a la columna "Ingresado por", no "Tecnico"', /Ingresado por/.test(r.text) && !/Repuesto Inicial[^\n]*Técnico/.test(r.text), r.text.split('\n')[0]);

  // ── Login con varios cargos ────────────────────────────────
  const nuevo = await new Client().login('vendCentro');
  ok('el vendedor sigue pudiendo iniciar sesion de nuevo', (await nuevo.get('/api/reparaciones.php')).json?.ok === true, '');

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
