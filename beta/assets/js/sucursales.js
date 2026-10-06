// Sucursales: selector global, filtros y administracion. Se carga ANTES de app.js.
(function () {
  'use strict';

  const BASE  = document.body.dataset.base || '';
  const CSRF  = document.body.dataset.csrf || '';
  const UID   = document.body.dataset.uid  || '0';
  const ADMIN = document.body.dataset.role === 'Admin';
  const KEY   = 'suc_activa_' + UID;

  const esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  const toast = (m, t) => (typeof window.toast === 'function' ? window.toast(m, t) : alert(m));

  async function api(method, body) {
    const opts = { method, headers: {} };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.headers['X-CSRF-Token'] = CSRF; opts.body = JSON.stringify(body); }
    const r = await fetch(BASE + '/api/sucursales.php', opts);
    return r.json();
  }

  const SUC = {
    list: [], base: 0, escritura: [], active: '', users: [], matriz: {},
    ready: null,

    // Sucursales con atencion al publico, activas.
    atencion() { return this.list.filter(s => s.activa && !s.es_bodega); },
    multi()    { return this.atencion().length > 1; },
    nombre(id) { const s = this.list.find(x => x.id_sucursal === Number(id)); return s ? s.nombre : ''; },
    puedeEscribir(id) { return id == null || this.escritura.includes(Number(id)); },

    // Valor para el parametro ?sucursal= de las consultas ('' = todas)
    param() { return this.multi() ? this.active : ''; },
    qs()    { const p = this.param(); return p ? '&sucursal=' + encodeURIComponent(p) : ''; },

    async load() {
      const j = await (await fetch(BASE + '/api/sucursales.php')).json();
      if (!j.ok) return;
      this.list      = j.data.sucursales;
      this.base      = j.data.base;
      this.escritura = j.data.escritura;
      this.matriz    = j.data.matriz || {};
      let stored = '';
      try { stored = localStorage.getItem(KEY) || ''; } catch (e) {}
      const validos = this.atencion().map(s => String(s.id_sucursal));
      if (stored === 'todas' || validos.includes(stored)) this.active = stored === 'todas' ? '' : stored;
      else this.active = ADMIN ? '' : String(this.base);
      this.renderSwitch();
      this.initInv();
      this.renderUsrFiltro();
    },

    renderSwitch() {
      const box = document.getElementById('suc-switch');
      const sel = document.getElementById('suc-select');
      if (!box || !sel) return;
      if (!this.multi()) { box.classList.add('hidden'); return; }
      sel.innerHTML = '<option value="">Todas las sucursales</option>' +
        this.atencion().map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)}</option>`).join('');
      sel.value = this.active;
      box.classList.remove('hidden');
    },

    onChange(value) {
      this.active = value;
      try { localStorage.setItem(KEY, value || 'todas'); } catch (e) {}
      // Solo se recarga la vista abierta; las demas se cargan al entrar (switchView).
      const vista = document.querySelector('.view.active')?.id;
      if (vista === 'view-servicios' && typeof window.loadServicios === 'function') window.loadServicios();
      else if (vista === 'view-estadisticas') document.getElementById('est-btn-aplicar')?.click();
    },

    // <select> para escoger sucursal de escritura (formularios). Devuelve true si se muestra.
    fillSelect(sel, { soloEscritura = true, valor = null } = {}) {
      if (!sel) return false;
      const opts = this.atencion().filter(s => !soloEscritura || this.escritura.includes(s.id_sucursal));
      // Si la sucursal actual de la reparacion esta inactiva, se conserva como opcion para que
      // guardar sin tocar el selector no provoque un traslado involuntario.
      if (valor != null && !opts.some(s => s.id_sucursal === Number(valor))) {
        const actual = this.list.find(s => s.id_sucursal === Number(valor));
        if (actual) opts.unshift(actual);
      }
      sel.innerHTML = opts.map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)}</option>`).join('');
      const pref = valor != null ? Number(valor)
                 : (this.active ? Number(this.active) : this.base);
      if (opts.some(s => s.id_sucursal === pref)) sel.value = String(pref);
      return opts.length > 1;
    },

    // ── Formulario "nuevo servicio" ────────────────────────────
    prepararNuevo() {
      const fg  = document.getElementById('fg-nuevo-sucursal');
      const sel = document.getElementById('nuevo-sucursal');
      if (!fg || !sel) return;
      const mostrar = this.fillSelect(sel);
      fg.classList.toggle('hidden', !mostrar);
      sel.disabled = !mostrar;
      if (typeof window.refrescarRepuestosNuevo === 'function') window.refrescarRepuestosNuevo();
    },

    // ── Detalle de una reparacion ──────────────────────────────
    onOpenDetalle(rep) {
      const lbl = document.getElementById('det-sucursal');
      if (lbl) lbl.textContent = rep.nombre_sucursal || this.nombre(rep.id_sucursal) || '—';
      const row = document.getElementById('det-sucursal-row');
      if (row) row.classList.toggle('hidden', !this.multi());

      const grp = document.getElementById('grp-det-sucursal');
      const sel = document.getElementById('det-sucursal-sel');
      if (grp && sel) {
        const mostrar = ADMIN && this.multi();
        grp.classList.toggle('hidden', !mostrar);
        if (mostrar) this.fillSelect(sel, { soloEscritura: false, valor: rep.id_sucursal });
      }

      // Solo lectura para tecnicos en reparaciones de otra sucursal
      const ro = !this.puedeEscribir(rep.id_sucursal);
      const aviso = document.getElementById('det-solo-lectura');
      if (aviso) aviso.classList.toggle('hidden', !ro);
      const form = document.getElementById('form-actualizar');
      if (form) {
        form.querySelectorAll('#det-status, #det-obs, #det-tel-edit, #btn-agregar-rep, button[type="submit"]')
            .forEach(el => { el.disabled = ro; });
        // #det-valor lo gestiona app.js (solo admin); aqui solo se bloquea en modo lectura.
        if (ro) document.getElementById('det-valor').disabled = true;
      }
    },

    detSelected() {
      const sel = document.getElementById('det-sucursal-sel');
      const grp = document.getElementById('grp-det-sucursal');
      return sel && grp && !grp.classList.contains('hidden') ? sel.value : null;
    },

    badge(rep) {
      if (!this.multi() || !rep.nombre_sucursal) return '';
      return `<div class="cell-sub"><span class="material-icons-round suc-ic">storefront</span> ${esc(rep.nombre_sucursal)}</div>`;
    },

    // ── Estadisticas: tabla comparativa ────────────────────────
    renderComparativa(rows) {
      const box = document.getElementById('est-sucursales');
      if (!box) return;
      if (!ADMIN || !this.multi() || !rows || !rows.length) { box.classList.add('hidden'); return; }
      const pesos = n => '$' + parseInt(n || 0).toLocaleString('es-CL');
      box.innerHTML = '<p class="section-label">Comparativa por sucursal</p>' +
        '<table class="tbl"><thead><tr><th>Sucursal</th><th>Órdenes</th><th>Cerradas</th><th>Ingresos</th></tr></thead><tbody>' +
        rows.map(r => `<tr><td><strong>${esc(r.nombre)}</strong></td><td>${r.ordenes}</td><td>${r.cerradas}</td><td>${pesos(r.ingresos)}</td></tr>`).join('') +
        '</tbody></table>';
      box.classList.remove('hidden');
    },

    // ── Configuracion (solo admin) ─────────────────────────────
    async cargarUsuarios() {
      try {
        const r = await fetch(BASE + '/api/usuarios.php');
        const j = await r.json();
        if (j.ok) this.users = j.data;
      } catch (e) {}
    },

    async renderAdmin() {
      await this.load();
      await this.cargarUsuarios();
      const tb = document.getElementById('tbl-sucursales');
      if (!tb) return;
      const rows = this.list;
      tb.innerHTML = rows.length ? rows.map(s => `<tr>
        <td><strong>${esc(s.nombre)}</strong>${s.es_bodega ? ' <span class="pill pill-gray">Bodega</span>' : ''}${s.activa ? '' : ' <span class="pill pill-orange">Inactiva</span>'}
            <div class="cell-sub">${this.contactoHtml(s)}</div></td>
        <td><button type="button" class="suc-num" data-suc-action="usuarios" data-id="${s.id_sucursal}" title="Ver los usuarios de esta sucursal">${s.n_usuarios}</button></td>
        <td>${s.n_reparaciones}</td>
        <td><div class="row-actions">
          <button class="btn-sm btn-sec" data-suc-action="editar" data-id="${s.id_sucursal}">Editar</button>
          <button class="btn-sm btn-sec" data-suc-action="${s.activa ? 'desactivar' : 'activar'}" data-id="${s.id_sucursal}">${s.activa ? 'Desactivar' : 'Activar'}</button>
        </div></td></tr>`).join('')
        : '<tr><td colspan="4" class="tbl-empty">Sin sucursales.</td></tr>';
      this._admin = rows;
    },

    // Direccion y telefono de una sucursal; lo que deja vacio lo hereda de la casa matriz (se muestra atenuado).
    contactoHtml(s) {
      const m = this.matriz || {};
      const dir = s.direccion
        ? esc(s.direccion)
        : '<span class="suc-heredado">' + (m.direccion ? 'casa matriz: ' + esc(m.direccion) : 'sin dirección') + '</span>';
      const tel = s.telefono
        ? esc(s.telefono)
        : '<span class="suc-heredado">' + (m.telefono ? 'casa matriz: ' + esc(m.telefono) : 'sin teléfono') + '</span>';
      return '<span class="material-icons-round suc-ic">place</span> ' + dir +
             '<br><span class="material-icons-round suc-ic">call</span> ' + tel;
    },

    // ── Filtro de sucursal en la pestana Usuarios ──────────────
    usrFiltro: '',

    renderUsrFiltro() {
      const wrap = document.getElementById('usr-suc-filtro-wrap');
      const sel  = document.getElementById('usr-suc-filtro');
      if (!wrap || !sel) return;
      const activas = this.list.filter(s => s.activa);
      if (activas.length < 2) { wrap.classList.add('hidden'); this.usrFiltro = ''; return; }
      if (this.usrFiltro && !activas.some(s => String(s.id_sucursal) === this.usrFiltro)) this.usrFiltro = '';
      sel.innerHTML = '<option value="">Todas las sucursales</option>' +
        activas.map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)}${s.es_bodega ? ' · Bodega' : ''}</option>`).join('');
      sel.value = this.usrFiltro;
      wrap.classList.remove('hidden');
    },

    // Usuarios de la sucursal filtrada: los que la tienen como base y los habilitados para trabajar en ella.
    filtrarLista(usuarios) {
      if (!this.usrFiltro) return usuarios;
      const id = Number(this.usrFiltro);
      return usuarios.filter(u => Number(u.id_sucursal) === id || (u.sucursales_extra || []).includes(id));
    },

    pintarFiltroUsuarios(mostrados, total) {
      const info = document.getElementById('usr-filtro-info');
      if (!info) return;
      if (!this.usrFiltro) { info.classList.add('hidden'); return; }
      info.innerHTML = 'Mostrando <strong>' + mostrados + '</strong> de ' + total + ' usuarios de <strong>' + esc(this.nombre(this.usrFiltro)) +
        '</strong> (asignados y habilitados). <button type="button" class="link-btn" data-usr-filtro-limpiar>Ver todos</button>';
      info.classList.remove('hidden');
    },

    aplicarFiltroUsuarios(value) {
      this.usrFiltro = value || '';
      const sel = document.getElementById('usr-suc-filtro');
      if (sel) sel.value = this.usrFiltro;
      if (typeof window.loadUsuarios === 'function') window.loadUsuarios();
    },

    // Desde Sucursales: lleva a la pestana Usuarios ya filtrada por esa sucursal.
    irAUsuarios(id) {
      this.renderUsrFiltro();
      document.querySelector('.cfg-tab[data-tab="usuarios"]')?.click();
      this.aplicarFiltroUsuarios(String(id));
    },

    abrirModalSucursal(s) {
      document.getElementById('suc-id').value        = s ? s.id_sucursal : '';
      document.getElementById('suc-nombre').value    = s ? s.nombre : '';
      document.getElementById('suc-direccion').value = s ? s.direccion : '';
      document.getElementById('suc-telefono').value  = s ? (s.telefono || '') : '';
      const m = this.matriz || {};
      document.getElementById('suc-direccion').placeholder = m.direccion ? 'Vacío = casa matriz (' + m.direccion + ')' : 'Opcional';
      document.getElementById('suc-telefono').placeholder  = m.telefono  ? 'Vacío = casa matriz (' + m.telefono  + ')' : 'Opcional';
      document.getElementById('suc-nota-matriz').textContent =
        'La boleta usa estos datos. Lo que dejes vacío se toma de la casa matriz (los datos de tu empresa). El teléfono puede ser el mismo que el de otra sucursal.';
      document.getElementById('suc-bodega').checked  = !!(s && s.es_bodega);
      document.getElementById('suc-modal-titulo').textContent = s ? 'Editar sucursal' : 'Nueva sucursal';
      if (typeof window.openModal === 'function') window.openModal('modal-sucursal');
    },

    async guardarSucursal() {
      const id = document.getElementById('suc-id').value;
      const body = {
        nombre:    document.getElementById('suc-nombre').value.trim(),
        direccion: document.getElementById('suc-direccion').value.trim(),
        telefono:  document.getElementById('suc-telefono').value.trim(),
        es_bodega: document.getElementById('suc-bodega').checked ? 1 : 0,
      };
      if (id) body.id_sucursal = Number(id);
      const j = await api(id ? 'PUT' : 'POST', body);
      if (!j.ok) { toast(j.msg, 'err'); return; }
      toast('✔ ' + j.data.msg, 'ok');
      if (typeof window.closeModal === 'function') window.closeModal('modal-sucursal');
      this.renderAdmin();
    },

    async cambiarActiva(id, activa) {
      const j = await api('PUT', { id_sucursal: Number(id), activa });
      if (!j.ok) { toast(j.msg, 'err'); return; }
      toast('✔ ' + j.data.msg, 'ok');
      this.renderAdmin();
    },

    // ── Asignacion de sucursal a un usuario ────────────────────
    async abrirAsignacion(uid) {
      await this.cargarUsuarios();
      const u = this.users.find(x => Number(x.id_usuario) === Number(uid));
      if (!u) return;
      document.getElementById('usc-uid').value = u.id_usuario;
      document.getElementById('usc-nombre').textContent = u.nombre;
      const activas = this.list.filter(s => s.activa);
      const selBase = document.getElementById('usc-base');
      selBase.innerHTML = activas.map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)}</option>`).join('');
      selBase.value = String(u.id_sucursal || this.base);
      this._usrExtras = new Set(u.sucursales_extra || []);
      this.pintarExtras();
      selBase.onchange = () => { this.capturarExtras(); this.pintarExtras(); };
      document.getElementById('usc-extras-wrap').classList.toggle('hidden', u.cargo === 'Admin');
      if (typeof window.openModal === 'function') window.openModal('modal-usr-sucursal');
    },

    // La sucursal base nunca aparece entre las "extra": ya es una sucursal donde el usuario escribe.
    capturarExtras() {
      this._usrExtras = new Set([...document.querySelectorAll('#usc-extras input:checked')].map(i => Number(i.value)));
    },
    pintarExtras() {
      const base = Number(document.getElementById('usc-base').value);
      this._usrExtras.delete(base);
      document.getElementById('usc-extras').innerHTML = this.list.filter(s => s.activa && s.id_sucursal !== base).map(s =>
        `<label class="usc-chk"><input type="checkbox" value="${s.id_sucursal}"${this._usrExtras.has(s.id_sucursal) ? ' checked' : ''}> ${esc(s.nombre)}</label>`
      ).join('');
    },

    async guardarAsignacion() {
      const uid   = Number(document.getElementById('usc-uid').value);
      const base  = Number(document.getElementById('usc-base').value);
      const extras = [...document.querySelectorAll('#usc-extras input:checked')].map(i => Number(i.value));
      const r = await fetch(BASE + '/api/usuarios.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ id_usuario: uid, id_sucursal: base, sucursales_extra: extras }),
      });
      const j = await r.json();
      if (!j.ok) { toast(j.msg, 'err'); return; }
      toast('✔ ' + j.data.msg, 'ok');
      if (typeof window.closeModal === 'function') window.closeModal('modal-usr-sucursal');
      if (typeof window.loadUsuarios === 'function') window.loadUsuarios();
      this.cargarUsuarios();
    },

    // Rellena el <select> de sucursal en "Agregar tecnico"
    prepararTecnico() {
      const sel = document.getElementById('tecnico-sucursal');
      if (!sel) return;
      sel.innerHTML = this.list.filter(s => s.activa).map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)}</option>`).join('');
      if (this.active) sel.value = this.active;
      document.getElementById('fg-tecnico-sucursal')?.classList.toggle('hidden', this.list.filter(s => s.activa).length < 2);
    },

    // Etiqueta de sucursal para la tabla de usuarios
    etiquetaUsuario(u) {
      const base = this.nombre(u.id_sucursal) || '—';
      const ext  = (u.sucursales_extra || []).map(id => this.nombre(id)).filter(Boolean);
      const aqui = this.usrFiltro && Number(u.id_sucursal) !== Number(this.usrFiltro) ? '<div class="cell-sub">habilitado en ' + esc(this.nombre(this.usrFiltro)) + '</div>' : '';
      return esc(base) + (ext.length ? `<div class="cell-sub">+ ${esc(ext.join(', '))}</div>` : '') + aqui;
    },
  };

  // ── Inventario: stock por sucursal y traspasos ────────────────
  const KEY_INV = 'suc_inv_' + UID;
  Object.assign(SUC, {
    invActive: '',

    activas()  { return this.list.filter(s => s.activa); },
    multiInv() { return this.activas().length > 1; },

    initInv() {
      let stored = null;
      try { stored = localStorage.getItem(KEY_INV); } catch (e) {}
      const ids = this.activas().map(s => String(s.id_sucursal));
      if (stored !== null && (stored === 'todas' || ids.includes(stored))) this.invActive = stored === 'todas' ? '' : stored;
      else this.invActive = ADMIN ? '' : String(this.base);
      this.renderInvSwitch();
    },

    renderInvSwitch() {
      const wrap = document.getElementById('suc-inv-wrap');
      const sel  = document.getElementById('inv-suc-select');
      if (!wrap || !sel) return;
      if (!this.multiInv()) { wrap.classList.add('hidden'); return; }
      sel.innerHTML = '<option value="">Todas (stock total)</option>' +
        this.activas().map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)}${s.es_bodega ? ' · Bodega' : ''}</option>`).join('');
      sel.value = this.invActive;
      wrap.classList.remove('hidden');
    },

    onInvChange(value) {
      this.invActive = value;
      try { localStorage.setItem(KEY_INV, value || 'todas'); } catch (e) {}
      if (typeof window.loadInventario === 'function') window.loadInventario();
    },

    invParam() { return this.multiInv() ? this.invActive : ''; },
    invQs()    { const p = this.invParam(); return p ? '&sucursal=' + encodeURIComponent(p) : ''; },

    // Sucursal sobre la que se escribe el stock (null = vista "Todas" con varias sucursales).
    invTarget() {
      if (!this.multiInv()) return this.base || (this.activas()[0] ? this.activas()[0].id_sucursal : null);
      return this.invActive ? Number(this.invActive) : null;
    },
    invTargetOrBase() { const t = this.invTarget(); return t !== null ? t : this.base; },

    // Devuelve la sucursal si se puede modificar su stock; si no, avisa y devuelve null.
    requireInvTarget() {
      const t = this.invTarget();
      if (t === null) { toast('Elige una sucursal en «Stock de» para modificar el stock.', 'err'); return null; }
      if (!this.puedeEscribir(t)) { toast('No tienes permiso para modificar el stock de ' + this.nombre(t) + '.', 'err'); return null; }
      return t;
    },

    stockDesglose(rep) {
      if (!this.multiInv()) return '';
      const ctx = this.invActive ? Number(this.invActive) : null;
      const partes = (rep.stock || [])
        .filter(x => x.cantidad > 0 && x.id_sucursal !== ctx)
        .map(x => esc(this.nombre(x.id_sucursal)) + ' ' + x.cantidad);
      if (ctx !== null && rep.cantidad_reservada > 0) partes.unshift(rep.cantidad_reservada + ' reservado' + (rep.cantidad_reservada !== 1 ? 's' : ''));
      return partes.length ? `<div class="stock-desg">${partes.join(' · ')}</div>` : '';
    },

    // Etiquetas de los modales de alta/edicion segun la sucursal destino
    prepararStockModales() {
      const multi = this.multiInv();
      const a = document.getElementById('lbl-rep-stock-nuevo');
      if (a) a.textContent = 'Stock inicial' + (multi ? ' en ' + this.nombre(this.invTargetOrBase()) : '');
      const t = this.invTarget();
      const b = document.getElementById('lbl-edit-stock');
      if (b) b.textContent = multi ? (t === null ? 'Stock (elige una sucursal para editarlo)' : 'Stock en ' + this.nombre(t)) : 'Stock';
      const inp = document.getElementById('edit-rep-cantidad');
      if (inp) inp.disabled = multi && (t === null || !this.puedeEscribir(t));
    },

    prepararImport() {
      const n = document.getElementById('imp-suc-note');
      if (!n) return;
      n.textContent = this.multiInv()
        ? 'El stock del archivo se aplicará a: ' + this.nombre(this.invTargetOrBase()) + '. Para usar otra sucursal, cámbiala en «Stock de» antes de importar.'
        : '';
    },

    // ── Traspasos ──────────────────────────────────────────────
    async abrirTraspaso(idRep) {
      const rep = typeof _invMap !== 'undefined' ? _invMap.get(Number(idRep)) : null;
      if (!rep) return;
      const stock = new Map((rep.stock || []).map(x => [x.id_sucursal, x]));
      const disp  = id => { const x = stock.get(id); return x ? x.cantidad - x.cantidad_reservada : 0; };
      const origenes = this.activas().filter(s => disp(s.id_sucursal) > 0);
      if (!origenes.length) { toast('No hay stock disponible para traspasar de este repuesto.', 'err'); return; }
      document.getElementById('tr-rep').value = rep.id_repuesto;
      document.getElementById('tr-nombre').textContent = rep.nombre + (rep.marca_compatible ? ' · ' + rep.marca_compatible : '');
      const so = document.getElementById('tr-origen'), sd = document.getElementById('tr-destino');
      so.innerHTML = origenes.map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)} (disp. ${disp(s.id_sucursal)})</option>`).join('');
      const pintarDestino = () => {
        sd.innerHTML = this.activas().filter(s => String(s.id_sucursal) !== so.value)
          .map(s => `<option value="${s.id_sucursal}">${esc(s.nombre)}${s.es_bodega ? ' · Bodega' : ''} (hay ${stock.get(s.id_sucursal) ? stock.get(s.id_sucursal).cantidad : 0})</option>`).join('');
        const c = document.getElementById('tr-cant');
        c.max = disp(Number(so.value));
        if (Number(c.value) > Number(c.max)) c.value = c.max;
      };
      so.onchange = pintarDestino;
      document.getElementById('tr-cant').value = 1;
      pintarDestino();
      document.getElementById('tr-nota').value = '';
      document.getElementById('tr-hist').innerHTML = '';
      if (typeof window.openModal === 'function') window.openModal('modal-traspaso');
      this.cargarHistorialTraspasos(rep.id_repuesto);
    },

    async cargarHistorialTraspasos(idRep) {
      const box = document.getElementById('tr-hist');
      try {
        const j = await (await fetch(BASE + '/api/traspasos.php?id_repuesto=' + idRep)).json();
        if (!j.ok || !j.data.length) { box.innerHTML = ''; return; }
        box.innerHTML = '<strong>Últimos traspasos</strong><ul>' + j.data.slice(0, 5).map(t =>
          `<li>${esc(new Date(t.fecha.replace(' ', 'T')).toLocaleDateString('es-CL'))} · ${t.cantidad} un. ${esc(t.origen || '?')} → ${esc(t.destino || '?')} <span style="opacity:.7">(${esc(t.usuario)})</span>${t.nota ? ' — ' + esc(t.nota) : ''}</li>`
        ).join('') + '</ul>';
      } catch (e) {}
    },

    async guardarTraspaso() {
      const body = {
        id_repuesto: Number(document.getElementById('tr-rep').value),
        id_origen:   Number(document.getElementById('tr-origen').value),
        id_destino:  Number(document.getElementById('tr-destino').value),
        cantidad:    parseInt(document.getElementById('tr-cant').value) || 0,
        nota:        document.getElementById('tr-nota').value.trim(),
      };
      const btn = document.getElementById('btn-tr-guardar');
      btn.disabled = true;
      try {
        const r = await fetch(BASE + '/api/traspasos.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(body) });
        const j = await r.json();
        if (!j.ok) { toast(j.msg, 'err'); return; }
        toast('✔ ' + j.data.msg, 'ok');
        if (typeof window.closeModal === 'function') window.closeModal('modal-traspaso');
        try { _repuestosCache = null; } catch (e) {}
        if (typeof window.loadInventario === 'function') window.loadInventario();
      } finally { btn.disabled = false; }
    },
  });

  window.SUC = SUC;
  SUC.ready = SUC.load().catch(() => {});

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('suc-select')?.addEventListener('change', e => SUC.onChange(e.target.value));

    document.getElementById('btn-nueva-sucursal')?.addEventListener('click', () => SUC.abrirModalSucursal(null));
    document.getElementById('btn-suc-guardar')?.addEventListener('click', () => SUC.guardarSucursal());
    document.getElementById('usr-suc-filtro')?.addEventListener('change', e => SUC.aplicarFiltroUsuarios(e.target.value));
    document.getElementById('usr-filtro-info')?.addEventListener('click', e => {
      if (e.target.closest('[data-usr-filtro-limpiar]')) SUC.aplicarFiltroUsuarios('');
    });
    document.getElementById('btn-usc-guardar')?.addEventListener('click', () => SUC.guardarAsignacion());

    document.getElementById('tbl-sucursales')?.addEventListener('click', e => {
      const b = e.target.closest('[data-suc-action]');
      if (!b) return;
      const id = Number(b.dataset.id);
      if (b.dataset.sucAction === 'usuarios') { SUC.irAUsuarios(id); return; }
      if (b.dataset.sucAction === 'editar') SUC.abrirModalSucursal((SUC._admin || []).find(s => s.id_sucursal === id));
      else SUC.cambiarActiva(id, b.dataset.sucAction === 'activar' ? 1 : 0);
    });

    document.getElementById('tbl-usuarios')?.addEventListener('click', e => {
      const b = e.target.closest('[data-action="asignar-sucursal"]');
      if (b) SUC.abrirAsignacion(b.dataset.uid);
    });

    document.querySelector('.cfg-tab[data-tab="sucursales"]')?.addEventListener('click', () => SUC.renderAdmin());
    document.querySelector('.cfg-tab[data-tab="usuarios"]')?.addEventListener('click', async () => { await SUC.cargarUsuarios(); });
    document.getElementById('btn-nuevo-tecnico')?.addEventListener('click', () => SUC.prepararTecnico());
    document.getElementById('btn-abrir-nuevo')?.addEventListener('click', () => SUC.prepararNuevo());
    document.getElementById('nuevo-sucursal')?.addEventListener('change', () => {
      document.getElementById('hid-rep-nuevo').value = '';
      if (typeof window.refrescarRepuestosNuevo === 'function') window.refrescarRepuestosNuevo(true);
    });
    document.getElementById('inv-suc-select')?.addEventListener('change', e => SUC.onInvChange(e.target.value));
    document.getElementById('btn-tr-guardar')?.addEventListener('click', () => SUC.guardarTraspaso());
    document.getElementById('tbl-inventario')?.addEventListener('click', e => {
      const b = e.target.closest('[data-traspaso]');
      if (b) SUC.abrirTraspaso(b.dataset.traspaso);
    });
  });
}());
