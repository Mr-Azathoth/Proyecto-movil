// ═══════════════════════════════════════════════════════════
// REPARO — SPA Frontend
// ═══════════════════════════════════════════════════════════

// Contexto del usuario desde atributos data del <body>
const CURRENT_USER = {
  role:   document.body.dataset.role   || '',
  user:   document.body.dataset.user   || '',
  nombre: document.body.dataset.nombre || '',
  csrf:   document.body.dataset.csrf   || '',
};

const STATUS_COLORS = {
  'Ingresado':     'pill-blue',
  'En Reparacion': 'pill-orange',
  'Reparado':      'pill-green',
  'Entregado':     'pill-gray',
  'Garantia':      'pill-purple',
};
const STATUS_LABELS = {
  'Ingresado':     'Ingresado',
  'En Reparacion': 'En Reparación',
  'Reparado':      'Reparado',
  'Entregado':     'Entregado',
  'Garantia':      'Garantía',
};

const fmt         = n => parseInt(n||0).toLocaleString('es-CL');
const esc         = s => { if(!s) return ''; const d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; };
const fmtDate     = s => s ? new Date(s).toLocaleDateString('es-CL',{day:'2-digit',month:'2-digit',year:'numeric'}) : '';
const fmtDateTime = s => s ? new Date(s).toLocaleString('es-CL',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '';

// ═══════════════════════════════════════════════════════════
// SELECT CON BÚSQUEDA
// ═══════════════════════════════════════════════════════════
class SearchableSelect {
  constructor(containerId, {
    placeholder = '— Seleccionar —',
    allowAdd    = false,
    addLabel    = '+ Agregar nuevo...',
    onChange    = null,
    disabled    = false,
  } = {}) {
    this.placeholder = placeholder;
    this.allowAdd    = allowAdd;
    this.addLabel    = addLabel;
    this.onChange    = onChange;
    this.items       = [];
    this.selected    = null;
    this._dis        = disabled;

    this.el = document.getElementById(containerId);
    if (!this.el) return;
    this.el.classList.add('srch-sel');
    this._build();
    this._bind();
    if (disabled) this.btn.disabled = true;
  }

  _build() {
    this.el.innerHTML = `
      <button type="button" class="srch-sel-btn">
        <span class="ss-lbl ss-ph">${esc(this.placeholder)}</span>
        <span class="material-icons-round ss-arrow">expand_more</span>
      </button>
      <div class="srch-sel-panel">
        <div class="ss-search">
          <span class="material-icons-round">search</span>
          <input type="text" class="ss-input" placeholder="Buscar..." autocomplete="off" spellcheck="false">
        </div>
        <div class="ss-list"></div>
      </div>`;
    this.btn   = this.el.querySelector('.srch-sel-btn');
    this.panel = this.el.querySelector('.srch-sel-panel');
    this.input = this.el.querySelector('.ss-input');
    this.list  = this.el.querySelector('.ss-list');
  }

  _bind() {
    this.btn.addEventListener('click', e => {
      e.stopPropagation();
      if (this._dis) return;
      this.el.classList.contains('open') ? this._close() : this._open();
    });
    this.input.addEventListener('input',   () => this._renderOpts(this.input.value));
    this.input.addEventListener('keydown', e => {
      if (e.key === 'Escape') { this._close(); this.btn.focus(); }
      if (e.key === 'ArrowDown') { e.preventDefault(); const f = this.list.querySelector('.ss-opt'); if (f) f.focus(); }
    });
    document.addEventListener('click', e => { if (!this.el.contains(e.target)) this._close(); });
  }

  _open() {
    document.querySelectorAll('.srch-sel.open').forEach(el => { if (el !== this.el) el.classList.remove('open'); });
    this.el.classList.add('open');
    this.input.value = '';
    this._renderOpts('');
    setTimeout(() => this.input.focus(), 30);
  }

  _close() { this.el.classList.remove('open'); }

  _renderOpts(q) {
    const lower    = q.toLowerCase();
    const filtered = q ? this.items.filter(it => it.label.toLowerCase().includes(lower)) : this.items;

    let html = filtered.map((it, i) =>
      `<div class="ss-opt" tabindex="-1" data-v="${esc(it.value)}" data-id="${it.id||''}" data-lbl="${esc(it.label)}">${esc(it.label)}</div>`
    ).join('');

    if (!filtered.length && !this.allowAdd) {
      html = `<div class="ss-empty">Sin resultados${q ? ` para "${esc(q)}"` : ''}</div>`;
    }

    if (this.allowAdd) {
      const addTxt = q ? `+ Agregar "<strong>${esc(q)}</strong>"` : this.addLabel;
      html += `<div class="ss-opt ss-opt-add" tabindex="-1" data-v="__nuevo" data-lbl="${esc(q)}">${addTxt}</div>`;
    }

    this.list.innerHTML = html;
    this.list.querySelectorAll('.ss-opt').forEach((opt, i) => {
      opt.addEventListener('click', () => this._pick(opt.dataset.v, opt.dataset.lbl, opt.dataset.id));
      opt.addEventListener('keydown', e => {
        const opts = this.list.querySelectorAll('.ss-opt');
        if (e.key === 'Enter')     { e.preventDefault(); this._pick(opt.dataset.v, opt.dataset.lbl, opt.dataset.id); }
        if (e.key === 'ArrowDown') { e.preventDefault(); if (opts[i+1]) opts[i+1].focus(); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); if (i > 0) opts[i-1].focus(); else this.input.focus(); }
        if (e.key === 'Escape')    { this._close(); this.btn.focus(); }
      });
    });
  }

  _pick(value, label, id) {
    const lbl = this.el.querySelector('.ss-lbl');
    if (value === '__nuevo') {
      this.selected = { value: '__nuevo', label: label || '', id: null };
      lbl.textContent = label ? `+ "${label}"` : '+ Nuevo...';
      lbl.className   = 'ss-lbl ss-add';
    } else {
      this.selected = { value, label, id: id || null };
      lbl.textContent = label;
      lbl.className   = 'ss-lbl';
    }
    this._close();
    if (this.onChange) this.onChange(this.selected);
  }

  populate(items) {
    this.items = items;
    this.enable();
  }

  enable(newPlaceholder) {
    if (newPlaceholder !== undefined) this.placeholder = newPlaceholder;
    this._dis = false;
    this.btn.disabled = false;
    if (!this.selected) {
      const lbl = this.el.querySelector('.ss-lbl');
      if (lbl) { lbl.textContent = this.placeholder; lbl.className = 'ss-lbl ss-ph'; }
    }
  }

  disable(newPlaceholder) {
    if (newPlaceholder !== undefined) this.placeholder = newPlaceholder;
    this._dis     = true;
    this.selected = null;
    this._close();
    this.btn.disabled = true;
    const lbl = this.el.querySelector('.ss-lbl');
    if (lbl) { lbl.textContent = this.placeholder; lbl.className = 'ss-lbl ss-ph'; }
  }

  reset() {
    this.selected = null;
    this._close();
    const lbl = this.el.querySelector('.ss-lbl');
    if (lbl) { lbl.textContent = this.placeholder; lbl.className = 'ss-lbl ss-ph'; }
  }
}

// ─── VISTA ACTIVA ──────────────────────────────────────────
function switchView(name, el) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
  document.getElementById('view-'+name).classList.add('active');
  if (el) el.classList.add('active');
  if (name === 'servicios') loadServicios();
  if (name === 'inventario') loadInventario();
}

function openModal(id) {
  document.getElementById(id).classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
  document.body.style.overflow = '';
}

function toast(msg, type='ok') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'toast toast-'+type+' show';
  setTimeout(() => t.className = 'toast', 3000);
}

function debounce(fn, ms) {
  let timer; return (...args) => { clearTimeout(timer); timer = setTimeout(()=>fn(...args), ms); };
}

async function apiFetch(url, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  if (method !== 'GET' && options.headers?.['Content-Type'] === 'application/json') {
    options.headers['X-CSRF-Token'] = CURRENT_USER.csrf;
  }
  if (method !== 'GET' && options.body instanceof FormData) {
    options.body.set('csrf_token', CURRENT_USER.csrf);
  }
  const response = await fetch(url, options);
  if (response.status === 401) {
    toast('Sesión expirada. Redirigiendo...', 'err');
    setTimeout(() => { window.location.href = '/reparo/index.php?expired=1'; }, 1500);
    throw new Error('session_expired');
  }
  return response;
}

// ═══════════════════════════════════════════════════════════
// SERVICIOS
// ═══════════════════════════════════════════════════════════
async function loadServicios() {
  const q     = document.getElementById('search-bar').value;
  const st    = document.getElementById('filter-status').value;
  const tbody = document.getElementById('tbl-servicios');
  tbody.innerHTML = `<tr><td colspan="9" class="tbl-loading"><span class="material-icons-round spin">sync</span> Cargando...</td></tr>`;

  try {
    const r = await apiFetch(`/reparo/api/reparaciones.php?q=${encodeURIComponent(q)}&status=${encodeURIComponent(st)}`);
    const json = await r.json();
    if (!json.ok) { tbody.innerHTML = `<tr><td colspan="9" class="tbl-empty">Error: ${esc(json.msg)}</td></tr>`; return; }
    const rows = json.data;

    if (!q && !st) updateStats(rows);
    document.getElementById('sub-servicios').textContent =
      `${rows.length} resultado${rows.length!==1?'s':''}${q||st?' (filtrado)':''}`;

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="9" class="tbl-empty">Sin resultados. <button class="link-btn" data-action="clear-filters">Limpiar filtros</button></td></tr>`;
      return;
    }

    _repMap.clear();
    tbody.innerHTML = rows.map(rep => {
      _repMap.set(rep.id_ingreso, rep);
      const pill = STATUS_COLORS[rep.status] || 'pill-gray';
      const lbl  = STATUS_LABELS[rep.status] || rep.status;
      const v    = rep.valor_ingreso > 0 ? `$${fmt(rep.valor_ingreso)}` : '—';
      return `
        <tr class="tbl-row" data-id="${rep.id_ingreso}">
          <td class="id-col">#${rep.id_ingreso}</td>
          <td>
            <div class="cell-main">${esc(rep.nombre_cliente)}</div>
            <div class="cell-sub">${esc(rep.telefono_cliente)}</div>
          </td>
          <td>
            <div class="cell-main">${esc(rep.marca_ingreso)} ${esc(rep.modelo_ingreso)}</div>
            <div class="cell-sub">${esc(rep.tipo_ingreso)}</div>
          </td>
          <td class="cell-sub">${esc(rep.daño_ingreso)}</td>
          <td class="cell-val">${v}</td>
          <td class="cell-sub">${esc(rep.ingresado_por)}</td>
          <td class="cell-sub">${fmtDate(rep.fecha_ingreso)}</td>
          <td><span class="pill ${pill}">${lbl}</span></td>
          <td class="action-col">
            <div class="row-actions">
              <button type="button" class="btn-row-action btn-row-edit" title="Editar servicio">
                <span class="material-icons-round">edit</span>
              </button>
              <a href="${waLink(rep)}" target="_blank" class="btn-row-action btn-row-wa" title="Abrir chat WhatsApp">
                ${WA_SVG}
              </a>
              ${CURRENT_USER.role === 'Admin' ? `<button type="button" class="btn-row-action btn-row-delete" title="Eliminar servicio">
                <span class="material-icons-round">delete</span>
              </button>` : ''}
            </div>
          </td>
        </tr>`;
    }).join('');
  } catch(e) {
    if (e.message !== 'session_expired')
      tbody.innerHTML = `<tr><td colspan="9" class="tbl-empty">Error de red. ¿Está XAMPP activo?</td></tr>`;
  }
}

function updateStats(rows) {
  const counts = { total:rows.length, Ingresado:0, 'En Reparacion':0, Reparado:0, Entregado:0 };
  rows.forEach(r => { if (counts[r.status]!==undefined) counts[r.status]++; });
  document.getElementById('st-total').textContent = counts.total;
  document.getElementById('st-ing').textContent   = counts['Ingresado'];
  document.getElementById('st-rep').textContent   = counts['En Reparacion'];
  document.getElementById('st-done').textContent  = counts['Reparado'];
  document.getElementById('st-entr').textContent  = counts['Entregado'];
}

// Retorna link directo al chat de WhatsApp sin mensaje predefinido
function waLink(rep) {
  const phone = (rep.telefono_cliente||'').replace(/[^0-9]/g,'');
  // Normalizar número chileno: agregar 56 si no lo tiene
  const normalized = /^56/.test(phone) ? phone : /^9\d{8}$/.test(phone) ? '56'+phone : phone;
  return `https://wa.me/${normalized}`;
}

// SVG oficial de WhatsApp
const WA_SVG = `<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>`;

function openDetalle(rep) {
  document.getElementById('det-id').textContent      = rep.id_ingreso;
  document.getElementById('det-cliente').textContent = rep.nombre_cliente;
  document.getElementById('det-tel').textContent     = rep.telefono_cliente || '—';
  document.getElementById('det-equipo').textContent  = `${rep.marca_ingreso} ${rep.modelo_ingreso} (${rep.tipo_ingreso})`;
  document.getElementById('det-imei').textContent    = `IMEI: ${rep.imei||'—'} · Clave: ${rep.pass_ingreso||'—'}`;
  document.getElementById('det-daño').textContent    = rep.daño_ingreso;
  document.getElementById('det-tecnico').textContent = rep.ingresado_por;
  document.getElementById('det-hidden-id').value     = rep.id_ingreso;
  document.getElementById('det-status').value        = rep.status;
  document.getElementById('det-obs').value           = '';

  const valInput = document.getElementById('det-valor');
  valInput.value    = rep.valor_ingreso || 0;
  valInput.disabled = CURRENT_USER.role !== 'Admin';
  document.getElementById('grp-valor').style.opacity = CURRENT_USER.role === 'Admin' ? '1' : '0.5';

  document.getElementById('det-wa-link').href = waLink(rep);
  _updateHintEntregado(rep.status);

  // Cargar repuestos y preparar select adicional
  document.getElementById('det-rep-list').innerHTML =
    '<p class="tl-loading" style="font-size:12px">Cargando repuestos...</p>';
  if (_selRepAdicional) { _selRepAdicional.reset(); }
  document.getElementById('hid-rep-adicional').value = '';
  document.getElementById('inp-rep-cant').value = '1';
  _loadRepuestosEditor(rep.id_ingreso);

  loadTimeline(rep.id_ingreso);
  openModal('modal-detalle');
}

async function _loadRepuestosEditor(idServicio) {
  const status = document.getElementById('det-status').value;
  try {
    if (!_repuestosCache) {
      const ri = await apiFetch('/reparo/api/inventario.php');
      const ji = await ri.json();
      _repuestosCache = (ji.data || []).map(i => ({
        id:    i.id_repuesto,
        value: String(i.id_repuesto),
        label: `${i.nombre}${i.marca_compatible ? ' · '+i.marca_compatible : ''} (stock: ${i.cantidad})`,
      }));
    }
    if (_selRepAdicional) _selRepAdicional.populate(_repuestosCache);

    const r = await apiFetch(`/reparo/api/rep_servicio.php?id=${idServicio}`);
    const j = await r.json();
    if (!j.ok) return;
    _renderRepuestosList(j.data.inicial, j.data.adicionales, status);
  } catch(e) {
    document.getElementById('det-rep-list').innerHTML = '';
  }
}

function _renderRepuestosList(inicial, adicionales, status) {
  const container = document.getElementById('det-rep-list');
  let html = '';

  if (inicial) {
    const entregado = status === 'Entregado';
    const desc = entregado
      ? '<span class="rep-chip-desc">(stock descontado)</span>'
      : `<span class="rep-chip-desc">$${fmt(inicial.precio)}</span>`;
    html += `<div class="rep-chip">
      <span class="material-icons-round">inventory_2</span>
      <span class="rep-chip-lbl">${esc(inicial.nombre)}</span>
      ${desc}
    </div>`;
  }

  if (adicionales.length) {
    html += '<div class="rep-list">';
    for (const a of adicionales) {
      const total = fmt(a.precio_snap * a.cantidad);
      html += `<div class="rep-list-item" data-rep-row="${a.id}">
        <span class="rep-name">${esc(a.nombre_snap)}${a.cantidad > 1 ? ` ×${a.cantidad}` : ''}</span>
        <span class="rep-price">$${total}</span>
        <button type="button" class="btn-rep-del" data-del-rep="${a.id}" title="Quitar repuesto">
          <span class="material-icons-round">close</span>
        </button>
      </div>`;
    }
    html += '</div>';
  }

  if (!inicial && !adicionales.length) {
    html = '<p class="hint-txt">Sin repuestos asignados.</p>';
  }

  container.innerHTML = html;
}

function _updateHintEntregado(status) {
  const hint = document.getElementById('hint-entregado');
  if (hint) hint.style.display = status === 'Entregado' ? '' : 'none';
}

async function loadTimeline(id) {
  const box = document.getElementById('timeline');
  box.innerHTML = '<p class="tl-loading">Cargando...</p>';
  try {
    const r = await apiFetch(`/reparo/api/timeline.php?id=${id}`);
    const json = await r.json();
    if (!json.ok || !json.data.length) { box.innerHTML='<p class="tl-empty">Sin registros aún.</p>'; return; }
    box.innerHTML = json.data.map(item => {
      const icon = item.tipo==='hist' ? 'swap_horiz' : 'notes';
      const cls  = item.tipo==='hist' ? 'tl-hist' : 'tl-obs';
      return `<div class="tl-item ${cls}">
        <span class="material-icons-round tl-icon">${icon}</span>
        <div class="tl-body">
          <div class="tl-meta"><strong>${esc(item.user)}</strong> · ${fmtDateTime(item.fecha)}</div>
          <div class="tl-txt">${esc(item.texto)}</div>
        </div>
      </div>`;
    }).join('');
  } catch(e) { box.innerHTML='<p class="tl-empty">Error al cargar.</p>'; }
}

async function submitNuevo(e) {
  e.preventDefault();

  const hidMarca  = document.getElementById('hid-marca-nuevo');
  const hidModelo = document.getElementById('hid-modelo-nuevo');
  const inpNM     = document.getElementById('inp-marca-nueva-nuevo');
  const inpMM     = document.getElementById('inp-modelo-nuevo-nuevo');

  const marcaNombre  = inpNM.classList.contains('visible') ? inpNM.value.trim() : hidMarca.value.trim();
  const modeloNombre = inpMM.classList.contains('visible') ? inpMM.value.trim() : hidModelo.value.trim();

  if (!marcaNombre)  { toast('Selecciona o escribe la marca del equipo.', 'err'); return; }
  if (!modeloNombre) { toast('Selecciona o escribe el modelo del equipo.', 'err'); return; }

  const btn = document.getElementById('btn-submit-nuevo');
  btn.disabled = true; btn.innerHTML = '<span class="material-icons-round spin">sync</span> Guardando...';

  try {
    let idMarca = _selMarcaNuevo?.selected?.id || null;

    if (_selMarcaNuevo?.selected?.value === '__nuevo') {
      const fd = new FormData(); fd.append('nombre', marcaNombre);
      const r = await apiFetch('/reparo/api/marcas.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (!j.ok) { toast(j.msg, 'err'); return; }
      idMarca = j.data.id_marca;
      _marcasCache = null;
    }

    if (_selModeloNuevo?.selected?.value === '__nuevo' && idMarca) {
      const fdm = new FormData();
      fdm.append('nombre', modeloNombre); fdm.append('id_marca', idMarca);
      const rm = await apiFetch('/reparo/api/modelos.php', { method: 'POST', body: fdm });
      const jm = await rm.json();
      if (!jm.ok) { toast(jm.msg, 'err'); return; }
    }

    const fd = new FormData(e.target);
    fd.set('marca_ingreso',  marcaNombre);
    fd.set('modelo_ingreso', modeloNombre);
    const idRepNuevo = document.getElementById('hid-rep-nuevo').value;
    if (idRepNuevo) fd.set('id_repuesto_usado', idRepNuevo);
    const r = await apiFetch('/reparo/api/reparaciones.php', { method: 'POST', body: fd });
    const j = await r.json();

    if (j.ok) {
      toast(`✔ ${j.data.msg}`, 'ok');
      closeModal('modal-nuevo');
      e.target.reset();
      _selMarcaNuevo?.reset();
      _selModeloNuevo?.disable('— Primero selecciona una marca —');
      _selRepNuevo?.reset();
      document.getElementById('hid-rep-nuevo').value = '';
      inpNM.classList.remove('visible'); inpNM.value = '';
      inpMM.classList.remove('visible'); inpMM.value = '';
      hidMarca.value = ''; hidModelo.value = '';
      loadServicios();
    } else { toast(j.msg, 'err'); }
  } catch(err) {
    if (err.message !== 'session_expired') toast('Error de red.', 'err');
  } finally {
    btn.disabled = false; btn.innerHTML = '<span class="material-icons-round">save</span> Registrar ingreso';
  }
}

async function submitActualizar(e) {
  e.preventDefault();
  const payload = {
    id:     parseInt(document.getElementById('det-hidden-id').value),
    status: document.getElementById('det-status').value,
    valor:  parseInt(document.getElementById('det-valor').value || 0),
    obs:    document.getElementById('det-obs').value.trim(),
  };
  try {
    const r = await apiFetch('/reparo/api/reparaciones.php', {
      method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (j.ok) {
      const msg = j.stock_descontado
        ? '✔ Guardado · Repuestos descontados del inventario'
        : '✔ Guardado.';
      toast(msg, 'ok');
      if (j.stock_descontado) _repuestosCache = null;
      closeModal('modal-detalle');
      loadServicios();
    } else toast(j.msg, 'err');
  } catch(err) {
    if (err.message !== 'session_expired') toast('Error de red.', 'err');
  }
}

// ═══════════════════════════════════════════════════════════
// INVENTARIO
// ═══════════════════════════════════════════════════════════
async function loadInventario() {
  const q     = document.getElementById('search-inv').value;
  const tbody = document.getElementById('tbl-inventario');
  tbody.innerHTML = `<tr><td colspan="7" class="tbl-loading"><span class="material-icons-round spin">sync</span> Cargando...</td></tr>`;
  try {
    const r = await apiFetch(`/reparo/api/inventario.php?q=${encodeURIComponent(q)}`);
    const j = await r.json();
    if (!j.ok) { tbody.innerHTML=`<tr><td colspan="7" class="tbl-empty">${esc(j.msg)}</td></tr>`; return; }
    if (!j.data.length) {
      const addBtn = CURRENT_USER.role === 'Admin'
        ? ' <button class="link-btn" data-action="open-modal-repuesto">Agregar el primero</button>' : '';
      tbody.innerHTML = `<tr><td colspan="7" class="tbl-empty">Sin repuestos registrados.${addBtn}</td></tr>`;
      return;
    }
    const isAdmin = CURRENT_USER.role === 'Admin';
    _invMap.clear();
    tbody.innerHTML = j.data.map(rep => {
      _invMap.set(rep.id_repuesto, rep);
      const stockColor = rep.cantidad > 5 ? 'color:#4ade80' : rep.cantidad > 0 ? 'color:#fb923c' : 'color:#f87171';
      const actions = `<td class="action-col">
        <div class="row-actions">
          <button type="button" class="btn-row-action btn-inv-edit" title="Editar repuesto">
            <span class="material-icons-round">edit</span>
          </button>
          ${isAdmin ? `
          <button class="btn-stock" data-id="${rep.id_repuesto}" data-qty="${parseInt(rep.cantidad)+1}" title="Aumentar">+</button>
          <button class="btn-stock" data-id="${rep.id_repuesto}" data-qty="${Math.max(0,parseInt(rep.cantidad)-1)}" title="Disminuir">−</button>` : ''}
        </div>
      </td>`;
      return `<tr data-inv-id="${rep.id_repuesto}">
        <td><code class="code-lbl">${esc(rep.codigo)}</code></td>
        <td><strong>${esc(rep.nombre)}</strong></td>
        <td>${esc(rep.marca_compatible||'—')}</td>
        <td>${esc(rep.modelo_compatible||'—')}</td>
        <td>$${fmt(rep.precio_venta)}</td>
        <td><strong style="${stockColor}">${rep.cantidad} un.</strong></td>
        ${actions}
      </tr>`;
    }).join('');
  } catch(e) {
    if (e.message !== 'session_expired')
      tbody.innerHTML=`<tr><td colspan="7" class="tbl-empty">Error de red.</td></tr>`;
  }
}

async function alterStock(id, qty) {
  try {
    const r = await apiFetch('/reparo/api/inventario.php', {
      method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id, cantidad: qty})
    });
    const j = await r.json();
    if (!j.ok) { toast(j.msg, 'err'); return; }

    // Actualiza solo la fila afectada — sin recargar la tabla completa
    const row = document.querySelector(`#tbl-inventario tr[data-inv-id="${id}"]`);
    if (!row) { loadInventario(); return; }

    const cell  = row.querySelector('td:nth-child(6) strong');
    cell.textContent = `${qty} un.`;
    cell.style.color = qty > 5 ? '#4ade80' : qty > 0 ? '#fb923c' : '#f87171';

    const [btnPlus, btnMinus] = row.querySelectorAll('.btn-stock');
    btnPlus.dataset.qty  = qty + 1;
    btnMinus.dataset.qty = Math.max(0, qty - 1);
  } catch(e) {
    if (e.message !== 'session_expired') toast('Error de red.', 'err');
  }
}

// ── Confirmación genérica ────────────────────────────────
let _confirmCallback = null;
function showConfirm(title, msg, onConfirm) {
  document.getElementById('confirm-title').textContent = title;
  document.getElementById('confirm-msg').textContent   = msg;
  _confirmCallback = onConfirm;
  openModal('modal-confirm');
}

// ── Eliminar servicio (admin) ────────────────────────────
async function deleteServicio(id) {
  try {
    const r = await apiFetch('/reparo/api/reparaciones.php', {
      method: 'DELETE',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id}),
    });
    const j = await r.json();
    if (j.ok) {
      _repMap.delete(id);
      document.querySelector(`.tbl-row[data-id="${id}"]`)?.remove();
      toast(`✔ ${j.msg}`, 'ok');
    } else {
      toast(j.msg, 'err');
    }
  } catch(e) {
    if (e.message !== 'session_expired') toast('Error de red.', 'err');
  }
}

// ── Abrir modal edición repuesto ─────────────────────────
function openInvEdit(item) {
  const isAdmin = CURRENT_USER.role === 'Admin';
  document.getElementById('edit-rep-id').value      = item.id_repuesto;
  document.getElementById('edit-rep-cantidad').value = item.cantidad;
  const adminFields = document.getElementById('edit-rep-admin-fields');
  if (isAdmin) {
    adminFields.style.display = '';
    document.getElementById('edit-rep-codigo').value  = item.codigo;
    document.getElementById('edit-rep-nombre').value  = item.nombre;
    document.getElementById('edit-rep-marca').value   = item.marca_compatible  || '';
    document.getElementById('edit-rep-modelo').value  = item.modelo_compatible || '';
    document.getElementById('edit-rep-precio').value  = item.precio_venta;
  } else {
    adminFields.style.display = 'none';
  }
  openModal('modal-edit-repuesto');
}

// ── Guardar edición repuesto ──────────────────────────────
async function submitEditRepuesto(e) {
  e.preventDefault();
  const isAdmin = CURRENT_USER.role === 'Admin';
  const id = parseInt(document.getElementById('edit-rep-id').value);
  const payload = { id, cantidad: parseInt(document.getElementById('edit-rep-cantidad').value) || 0 };
  if (isAdmin) {
    payload.codigo            = document.getElementById('edit-rep-codigo').value.trim();
    payload.nombre            = document.getElementById('edit-rep-nombre').value.trim();
    payload.marca_compatible  = document.getElementById('edit-rep-marca').value.trim();
    payload.modelo_compatible = document.getElementById('edit-rep-modelo').value.trim();
    payload.precio_venta      = parseInt(document.getElementById('edit-rep-precio').value) || 0;
  }
  try {
    const r = await apiFetch('/reparo/api/inventario.php', {
      method: 'PUT',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload),
    });
    const j = await r.json();
    if (j.ok) {
      toast('✔ Repuesto actualizado.', 'ok');
      closeModal('modal-edit-repuesto');
      loadInventario();
    } else {
      toast(j.msg, 'err');
    }
  } catch(err) {
    if (err.message !== 'session_expired') toast('Error de red.', 'err');
  }
}

async function addRepuestoAdicional() {
  const idRep   = parseInt(document.getElementById('hid-rep-adicional').value);
  const idServ  = parseInt(document.getElementById('det-hidden-id').value);
  const cantidad = Math.max(1, parseInt(document.getElementById('inp-rep-cant').value) || 1);
  if (!idRep) { toast('Selecciona un repuesto primero.', 'err'); return; }

  try {
    const r = await apiFetch('/reparo/api/rep_servicio.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id_reparacion: idServ, id_repuesto: idRep, cantidad }),
    });
    const j = await r.json();
    if (!j.ok) { toast(j.msg, 'err'); return; }

    // Actualizar valor en el campo
    document.getElementById('det-valor').value = j.data.nuevo_valor;
    // Recargar lista de repuestos
    _repuestosCache = null;
    await _loadRepuestosEditor(idServ);
    // Resetear select
    _selRepAdicional?.reset();
    document.getElementById('hid-rep-adicional').value = '';
    document.getElementById('inp-rep-cant').value = '1';
    toast('✔ Repuesto agregado.', 'ok');
  } catch(e) {
    if (e.message !== 'session_expired') toast('Error de red.', 'err');
  }
}

async function removeRepuestoAdicional(id) {
  const idServ = parseInt(document.getElementById('det-hidden-id').value);
  try {
    const r = await apiFetch('/reparo/api/rep_servicio.php', {
      method: 'DELETE',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id }),
    });
    const j = await r.json();
    if (!j.ok) { toast(j.msg, 'err'); return; }
    document.getElementById('det-valor').value = j.data.nuevo_valor;
    _repuestosCache = null;
    await _loadRepuestosEditor(idServ);
  } catch(e) {
    if (e.message !== 'session_expired') toast('Error de red.', 'err');
  }
}

async function submitRepuesto(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  try {
    const r = await apiFetch('/reparo/api/inventario.php', {method: 'POST', body: fd});
    const j = await r.json();
    if (j.ok) { toast('✔ Repuesto agregado.', 'ok'); closeModal('modal-repuesto'); e.target.reset(); loadInventario(); }
    else toast(j.msg, 'err');
  } catch(err) {
    if (err.message !== 'session_expired') toast('Error de red.', 'err');
  }
}

// ═══════════════════════════════════════════════════════════
// CATÁLOGO GLOBAL: MARCAS Y MODELOS
// ═══════════════════════════════════════════════════════════
let _marcasCache        = null;
let _repuestosCache     = null; // cache de items inventario para selects de repuesto
const _repMap           = new Map(); // id_ingreso → objeto rep completo
const _invMap           = new Map(); // id_repuesto → objeto repuesto completo
let _selMarcaNuevo      = null;
let _selModeloNuevo     = null;
let _selRepNuevo        = null; // select repuesto en modal-nuevo
let _selRepAdicional    = null; // select repuesto adicional en modal-detalle

async function fetchMarcas() {
  if (_marcasCache) return _marcasCache;
  try {
    const r = await apiFetch('/reparo/api/marcas.php');
    const j = await r.json();
    _marcasCache = j.ok ? j.data : [];
  } catch(e) { _marcasCache = []; }
  return _marcasCache;
}

async function loadMarcasSearchable() {
  const marcas = await fetchMarcas();
  _selMarcaNuevo?.populate(marcas.map(m => ({ value: m.nombre, label: m.nombre, id: m.id_marca })));
}

async function loadModelosSearchable(idMarca) {
  if (!_selModeloNuevo) return;
  _selModeloNuevo.disable('Cargando modelos...');
  try {
    const r = await apiFetch(`/reparo/api/modelos.php?id_marca=${idMarca}`);
    const j = await r.json();
    const items = (j.ok ? j.data : []).map(m => ({ value: m.nombre, label: m.nombre, id: m.id_modelo }));
    _selModeloNuevo.items = items;
    _selModeloNuevo.enable('— Seleccionar modelo —');
  } catch(e) {
    _selModeloNuevo.disable('Error al cargar');
  }
}

async function loadMarcasDatalist(datalistId) {
  const dl = document.getElementById(datalistId);
  if (!dl) return;
  const marcas = await fetchMarcas();
  dl.innerHTML = marcas.map(m => `<option value="${esc(m.nombre)}">`).join('');
}

function setupMarcaModeloPair() {
  const inpNuevaMarca  = document.getElementById('inp-marca-nueva-nuevo');
  const hidMarca       = document.getElementById('hid-marca-nuevo');
  const inpNuevoModelo = document.getElementById('inp-modelo-nuevo-nuevo');
  const hidModelo      = document.getElementById('hid-modelo-nuevo');

  _selMarcaNuevo = new SearchableSelect('sel-marca-nuevo', {
    placeholder: '— Seleccionar marca —',
    allowAdd:    true,
    addLabel:    '+ Agregar nueva marca...',
    onChange:    async ({ value, label, id }) => {
      hidModelo.value = '';
      inpNuevoModelo.classList.remove('visible'); inpNuevoModelo.value = '';
      _selModeloNuevo?.disable('— Primero selecciona una marca —');

      if (value === '__nuevo') {
        inpNuevaMarca.classList.add('visible');
        inpNuevaMarca.value = label;
        hidMarca.value = label;
        if (_selModeloNuevo) { _selModeloNuevo.items = []; _selModeloNuevo.enable('— Seleccionar modelo —'); }
      } else if (value) {
        inpNuevaMarca.classList.remove('visible'); inpNuevaMarca.value = '';
        hidMarca.value = label;
        if (id) await loadModelosSearchable(id);
      } else {
        inpNuevaMarca.classList.remove('visible');
        hidMarca.value = '';
      }
    },
  });

  _selModeloNuevo = new SearchableSelect('sel-modelo-nuevo', {
    placeholder: '— Primero selecciona una marca —',
    allowAdd:    true,
    addLabel:    '+ Agregar nuevo modelo...',
    disabled:    true,
    onChange:    ({ value, label }) => {
      if (value === '__nuevo') {
        inpNuevoModelo.classList.add('visible');
        inpNuevoModelo.value = label;
        hidModelo.value = label;
      } else if (value) {
        inpNuevoModelo.classList.remove('visible'); inpNuevoModelo.value = '';
        hidModelo.value = label;
      } else {
        inpNuevoModelo.classList.remove('visible');
        hidModelo.value = '';
      }
    },
  });

  inpNuevaMarca.addEventListener('input',  () => { hidMarca.value  = inpNuevaMarca.value.trim(); });
  inpNuevoModelo.addEventListener('input', () => { hidModelo.value = inpNuevoModelo.value.trim(); });
}

// ═══════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════
// ── Exportar ─────────────────────────────────────────────────
async function openExportModal() {
  // Poblar select de repuestos si aún no tiene opciones
  const sel = document.getElementById('exp-repuesto');
  if (sel && sel.options.length <= 1) {
    try {
      const r = await apiFetch('/reparo/api/inventario.php');
      const j = await r.json();
      (j.data || []).forEach(i => {
        const opt    = document.createElement('option');
        opt.value    = i.id_repuesto;
        opt.textContent = i.nombre + (i.marca_compatible ? ` · ${i.marca_compatible}` : '');
        sel.appendChild(opt);
      });
    } catch(e) {}
  }
  // Default fecha hasta = hoy
  if (sel) {
    const today = new Date().toISOString().slice(0, 10);
    const hasta = document.getElementById('exp-f-hasta');
    if (hasta && !hasta.value) hasta.value = today;
  }
  openModal('modal-exportar');
}

function _buildExportParams(formato) {
  const params = new URLSearchParams();
  params.set('formato', formato);
  const desde = document.getElementById('exp-f-desde').value;
  const hasta  = document.getElementById('exp-f-hasta').value;
  if (desde) params.set('f_desde', desde);
  if (hasta) params.set('f_hasta', hasta);
  [...document.querySelectorAll('.exp-status:checked')].forEach(c => params.append('status[]', c.value));
  const pMin = document.getElementById('exp-p-min').value;
  const pMax = document.getElementById('exp-p-max').value;
  if (pMin) params.set('p_min', pMin);
  if (pMax) params.set('p_max', pMax);
  const idRep = document.getElementById('exp-repuesto').value;
  if (idRep) params.set('id_rep', idRep);
  return params;
}

function doExport(formato) {
  const params = _buildExportParams(formato);
  const url    = `/reparo/api/exportar.php?${params.toString()}`;
  closeModal('modal-exportar');

  if (formato === 'csv') {
    window.open(url, '_blank');
    return;
  }

  // PDF: iframe oculto → solo se ve el diálogo de impresión
  const prev = document.getElementById('_exp_iframe');
  if (prev) prev.remove();

  const iframe = document.createElement('iframe');
  iframe.id = '_exp_iframe';
  iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;border:0';
  document.body.appendChild(iframe);
  iframe.src = url;
  iframe.onload = () => {
    try {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
    } catch(e) { window.open(url, '_blank'); }
    // Limpiar después de que el usuario cierre el diálogo
    iframe.contentWindow.addEventListener('afterprint', () => iframe.remove(), { once: true });
  };
}

document.addEventListener('DOMContentLoaded', async () => {

  document.querySelectorAll('.nav-link[data-view]').forEach(link => {
    link.addEventListener('click', () => switchView(link.dataset.view, link));
  });

  document.getElementById('btn-abrir-nuevo')?.addEventListener('click', () => openModal('modal-nuevo'));
  document.getElementById('btn-exportar')?.addEventListener('click', openExportModal);
  document.getElementById('btn-exp-csv')?.addEventListener('click', () => doExport('csv'));
  document.getElementById('btn-exp-pdf')?.addEventListener('click', () => doExport('pdf'));
  document.getElementById('btn-abrir-repuesto')?.addEventListener('click', () => openModal('modal-repuesto'));

  document.querySelectorAll('.modal-close[data-modal], .btn-sec[data-modal]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.modal));
  });

  document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-bg')) closeModal(e.target.id);
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-bg.active').forEach(m => closeModal(m.id));
  });

  document.getElementById('form-nuevo')?.addEventListener('submit', submitNuevo);
  document.getElementById('form-actualizar')?.addEventListener('submit', submitActualizar);
  document.getElementById('form-repuesto')?.addEventListener('submit', submitRepuesto);

  // SearchableSelect de repuesto en modal-nuevo (ingreso)
  _selRepNuevo = new SearchableSelect('sel-rep-nuevo', {
    placeholder: '— Sin repuesto —',
    allowAdd:    false,
    onChange:    ({ id }) => { document.getElementById('hid-rep-nuevo').value = id || ''; },
  });

  // SearchableSelect de repuesto adicional en modal-detalle
  _selRepAdicional = new SearchableSelect('sel-rep-adicional', {
    placeholder: 'Buscar repuesto...',
    allowAdd:    false,
    onChange:    ({ id }) => { document.getElementById('hid-rep-adicional').value = id || ''; },
  });

  // Hint entregado y re-render chip al cambiar estado
  document.getElementById('det-status')?.addEventListener('change', e => {
    _updateHintEntregado(e.target.value);
    // Re-render para actualizar etiqueta stock descontado sin llamar API
    const idServ = parseInt(document.getElementById('det-hidden-id').value);
    if (idServ) _loadRepuestosEditor(idServ);
  });

  // Botón agregar repuesto adicional
  document.getElementById('btn-agregar-rep')?.addEventListener('click', addRepuestoAdicional);

  // Delegación para quitar repuesto adicional (botón ×)
  document.getElementById('det-rep-list')?.addEventListener('click', e => {
    const btn = e.target.closest('[data-del-rep]');
    if (btn) removeRepuestoAdicional(parseInt(btn.dataset.delRep));
  });

  document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', () => {
      document.getElementById('filter-status').value = card.dataset.filter;
      document.getElementById('search-bar').value    = '';
      document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-stat'));
      if (card.dataset.filter) card.classList.add('active-stat');
      loadServicios();
    });
  });

  document.getElementById('search-bar').addEventListener('input', debounce(loadServicios, 300));
  document.getElementById('filter-status').addEventListener('change', loadServicios);
  document.getElementById('search-inv').addEventListener('input', debounce(loadInventario, 300));

  // Click simple: botón editar, eliminar y limpiar filtros (servicios)
  document.getElementById('tbl-servicios').addEventListener('click', e => {
    if (e.target.dataset.action === 'clear-filters') {
      document.getElementById('search-bar').value    = '';
      document.getElementById('filter-status').value = '';
      loadServicios();
      return;
    }
    const editBtn = e.target.closest('.btn-row-edit');
    if (editBtn) {
      const row = editBtn.closest('.tbl-row[data-id]');
      if (row) { const rep = _repMap.get(parseInt(row.dataset.id)); if (rep) openDetalle(rep); }
      return;
    }
    const delBtn = e.target.closest('.btn-row-delete');
    if (delBtn) {
      const row = delBtn.closest('.tbl-row[data-id]');
      if (!row) return;
      const id  = parseInt(row.dataset.id);
      const rep = _repMap.get(id);
      const cliente = rep?.nombre_cliente ?? `#${id}`;
      showConfirm(
        'Eliminar servicio técnico',
        `¿Está seguro que desea eliminar el servicio #${id} de ${cliente}?\n\nEsta acción borrará el registro completo del sistema de forma permanente, sin posibilidad de recuperarlo.`,
        () => deleteServicio(id)
      );
      return;
    }
  });

  // Doble clic en fila → abre modal de edición (servicios)
  document.getElementById('tbl-servicios').addEventListener('dblclick', e => {
    if (e.target.closest('.action-col')) return;
    const row = e.target.closest('.tbl-row[data-id]');
    if (row) { const rep = _repMap.get(parseInt(row.dataset.id)); if (rep) openDetalle(rep); }
  });

  // Botón confirmar del modal de confirmación
  document.getElementById('confirm-ok').addEventListener('click', () => {
    closeModal('modal-confirm');
    if (typeof _confirmCallback === 'function') { _confirmCallback(); _confirmCallback = null; }
  });

  // Click inventario: editar, stock +/-, abrir modal agregar
  document.getElementById('tbl-inventario').addEventListener('click', e => {
    if (e.target.dataset.action === 'open-modal-repuesto') { openModal('modal-repuesto'); return; }
    const editBtn = e.target.closest('.btn-inv-edit');
    if (editBtn) {
      const row = editBtn.closest('tr[data-inv-id]');
      if (row) { const item = _invMap.get(parseInt(row.dataset.invId)); if (item) openInvEdit(item); }
      return;
    }
    const btn = e.target.closest('.btn-stock[data-id]');
    if (btn) alterStock(parseInt(btn.dataset.id), parseInt(btn.dataset.qty));
  });

  // Doble clic en fila inventario → abre modal edición
  document.getElementById('tbl-inventario').addEventListener('dblclick', e => {
    if (e.target.closest('.action-col')) return;
    const row = e.target.closest('tr[data-inv-id]');
    if (row) { const item = _invMap.get(parseInt(row.dataset.invId)); if (item) openInvEdit(item); }
  });

  document.getElementById('form-edit-repuesto').addEventListener('submit', submitEditRepuesto);

  setupMarcaModeloPair();
  await loadMarcasSearchable();
  loadMarcasDatalist('dl-marcas-inv');

  // Pre-cargar inventario para los selects de repuesto
  apiFetch('/reparo/api/inventario.php').then(r => r.json()).then(j => {
    _repuestosCache = (j.data || []).map(i => ({
      id:    i.id_repuesto,
      value: String(i.id_repuesto),
      label: `${i.nombre}${i.marca_compatible ? ' · '+i.marca_compatible : ''} (stock: ${i.cantidad})`,
    }));
    _selRepNuevo?.populate(_repuestosCache);
  }).catch(() => {});

  loadServicios();
});
