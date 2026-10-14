// Soporte: todos los cargos pueden enviar requerimientos; cada uno ve los suyos y el admin los de toda la empresa.
const { Client, ids } = require('./lib');
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : '  -> ' + x)); };

(async () => {
  const adm = await new Client().login('adminA'), tc = await new Client().login('tecCentro'), vd = await new Client().login('vendCentro'), adb = await new Client().login('adminB');
  const ticket = (cl, asunto) => cl.postForm('/api/tickets.php', { action: 'nuevo', asunto, mensaje: '<p>Necesito ayuda con ' + asunto + ' por favor</p>' });
  const lista = async cl => (await cl.get('/api/tickets.php')).json.data;

  // ── La pantalla existe para todos los cargos ───────────────
  for (const [nombre, cl] of [['admin', adm], ['tecnico', tc], ['vendedor', vd]]) {
    const h = (await cl.get('/app.php')).text;
    ok(nombre + ': la vista de Soporte y el formulario de ticket existen en la pagina (una vez cada uno)',
       (h.match(/id="view-soporte"/g) || []).length === 1 && (h.match(/id="modal-soporte"/g) || []).length === 1 && /id="btn-nuevo-ticket"/.test(h), '');
    const ab = (h.match(/<div[\s>]/g) || []).length, ce = (h.match(/<\/div>/g) || []).length;
    // La pagina original ya trae UN </div> de mas (tambien en produccion); lo que se comprueba es que ningun cargo agregue otro.
    ok(nombre + ': el HTML no queda desbalanceado por el cargo (solo el </div> sobrante que ya existia)', ce - ab === 1, ab + ' vs ' + ce);
    ok(nombre + ': la vista de Soporte queda dentro del <main>', h.indexOf('id="view-soporte"') < h.indexOf('</main>') && h.indexOf('id="view-soporte"') > h.indexOf('<main'), '');
  }
  const hT = (await tc.get('/app.php')).text, hV = (await vd.get('/app.php')).text, hA = (await adm.get('/app.php')).text;
  ok('tecnico y vendedor siguen SIN ver Configuracion ni Estadisticas', !/id="view-config"/.test(hT + hV) && !/id="view-estadisticas"/.test(hT + hV), '');
  ok('el admin sigue viendo Configuracion, Estadisticas y los modales de usuarios', /id="view-config"/.test(hA) && /id="view-estadisticas"/.test(hA) && /id="modal-nuevo-tecnico"/.test(hA), '');

  // ── Enviar y ver tickets ───────────────────────────────────
  let r = await ticket(vd, 'Vendedor: no imprime la boleta');
  const idV = r.json?.data?.id_ticket;
  ok('el vendedor envia un ticket', r.json?.ok === true && !!idV, r.text.slice(0, 100));
  r = await ticket(tc, 'Tecnico: la foto no sube');
  const idT = r.json?.data?.id_ticket;
  ok('el tecnico envia un ticket', r.json?.ok === true && !!idT, r.text.slice(0, 100));
  r = await ticket(adm, 'Admin: consulta del plan');
  const idA = r.json?.data?.id_ticket;
  ok('el admin envia un ticket', r.json?.ok === true && !!idA, r.text.slice(0, 100));

  const lv = await lista(vd), lt = await lista(tc), la = await lista(adm);
  ok('el vendedor ve SOLO el suyo', lv.length === 1 && lv[0].id_ticket === idV, JSON.stringify(lv.map(x => x.id_ticket)));
  ok('el tecnico ve SOLO el suyo', lt.length === 1 && lt[0].id_ticket === idT, JSON.stringify(lt.map(x => x.id_ticket)));
  ok('el admin ve los tickets de toda la empresa (3)', la.length === 3 && [idV, idT, idA].every(i => la.some(x => x.id_ticket === i)), JSON.stringify(la.map(x => x.id_ticket)));
  ok('...con el nombre de quien lo envio', la.find(x => x.id_ticket === idV).usuario_nombre === 'Vend Centro', la.find(x => x.id_ticket === idV).usuario_nombre);
  ok('la empresa B no ve ninguno', (await lista(adb)).length === 0, '');

  // ── Responder y marcar visto: solo lo propio ───────────────
  r = await vd.postForm('/api/tickets.php', { action: 'reply', id_ticket: String(idV), mensaje: '<p>Gracias, adjunto mas detalles</p>' });
  ok('el vendedor responde en su ticket', r.json?.ok === true, r.text.slice(0, 100));
  r = await vd.postForm('/api/tickets.php', { action: 'reply', id_ticket: String(idT), mensaje: '<p>Intento responder en el ajeno</p>' });
  ok('NO puede responder en el ticket de otro usuario', r.json?.ok === false && /no encontrado/i.test(r.json.msg || ''), r.text.slice(0, 100));
  r = await tc.postForm('/api/tickets.php', { action: 'reply', id_ticket: String(idA), mensaje: '<p>Intento responder en el del admin</p>' });
  ok('el tecnico tampoco en el del admin', r.json?.ok === false, r.text.slice(0, 100));
  r = await adm.postForm('/api/tickets.php', { action: 'reply', id_ticket: String(idT), mensaje: '<p>Soy el admin y completo la informacion</p>' });
  ok('el admin SI responde en el ticket de un tecnico de su empresa', r.json?.ok === true, r.text.slice(0, 100));
  const antes = (await lista(adm)).find(x => x.id_ticket === idA).visto;
  await vd.postForm('/api/tickets.php', { action: 'marcar_visto', id_ticket: String(idA) });
  ok('un vendedor no puede marcar como visto el ticket de otro', Number((await lista(adm)).find(x => x.id_ticket === idA).visto) === Number(antes), '');
  r = await adb.postForm('/api/tickets.php', { action: 'reply', id_ticket: String(idV), mensaje: '<p>Otra empresa intentando</p>' });
  ok('otra empresa no puede responder en tickets ajenos', r.json?.ok === false, r.text.slice(0, 100));

  // ── Validaciones que ya existian siguen ────────────────────
  r = await ticket(vd, 'x');
  ok('un asunto demasiado corto se rechaza', r.json?.ok === false, r.text.slice(0, 80));
  r = await vd.postForm('/api/tickets.php', { action: 'nuevo', asunto: 'Asunto valido', mensaje: '<p>corto</p>' });
  ok('un mensaje demasiado corto se rechaza', r.json?.ok === false, r.text.slice(0, 80));

  console.log(`\n${pass} OK, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('ERROR', e.stack.split('\n').slice(0, 4).join('\n')); process.exit(2); });
