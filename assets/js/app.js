// ═══════════════════════════════════════════════════════════
// Centrotec — SPA Frontend
// ═══════════════════════════════════════════════════════════

// ── TagInput: entrada de etiquetas separadas por coma ────────
class TagInput {
  constructor(containerId) {
    this._wrap = document.getElementById(containerId);
    this._tags = [];
    if (this._wrap) {
      this._render();
      this._wrap.addEventListener('click', e => {
        const btn = e.target.closest('[data-tag-del]');
        if (btn) { this._tags.splice(+btn.dataset.tagDel, 1); this._render(); }
        else this._inp?.focus();
      });
    }
  }
  setValue(str) { this._tags = str ? str.split(',').map(s => s.trim()).filter(Boolean) : []; this._render(); }
  getValue()    { return this._tags.join(', '); }
  _add(raw) {
    raw.split(',').map(s => s.trim()).filter(Boolean).forEach(t => {
      if (!this._tags.includes(t)) this._tags.push(t);
    });
    this._render();
  }
  _render() {
    if (!this._wrap) return;
    const wasFocused = document.activeElement === this._inp;
    this._wrap.innerHTML =
      this._tags.map((t, i) =>
        `<span class="tag-chip">${esc(t)}<button type="button" data-tag-del="${i}" tabindex="-1">×</button></span>`
      ).join('') +
      `<input class="tag-inp" type="text" placeholder="${this._tags.length ? 'Añadir...' : 'Galaxy A54, A53...'}">`;
    this._inp = this._wrap.querySelector('.tag-inp');
    if (wasFocused) this._inp.focus();
    this._inp.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        if (this._inp.value.trim()) { this._add(this._inp.value); this._inp.value = ''; }
      } else if (e.key === 'Backspace' && !this._inp.value && this._tags.length) {
        this._tags.pop(); this._render(); this._inp.focus();
      }
    });
    this._inp.addEventListener('blur', () => {
      if (this._inp.value.trim()) { this._add(this._inp.value); this._inp.value = ''; }
    });
  }
}

// Contexto del usuario desde atributos data del <body>
const BASE_PATH = document.body.dataset.base || '';
const CURRENT_USER = {
  role:   document.body.dataset.role   || '',
  user:   document.body.dataset.user   || '',
  nombre: document.body.dataset.nombre || '',
  csrf:   document.body.dataset.csrf   || '',
  uid:    parseInt(document.body.dataset.uid || '0', 10),
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
  if (name === 'servicios')    loadServicios();
  if (name === 'inventario')   loadInventario();
  if (name === 'config')       loadConfigData();
  if (name === 'estadisticas') initEstadisticas();
  document.dispatchEvent(new CustomEvent('viewchange', { detail: name }));
}

function openModal(id) {
  document.getElementById(id).classList.add('active');
  document.body.style.overflow = 'hidden';
  setTimeout(() => {
    const first = document.getElementById(id).querySelector(
      'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
    );
    first?.focus();
  }, 60);
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
  document.body.style.overflow = '';
  if (id === 'modal-nuevo')   _resetModalNuevo?.();
  if (id === 'modal-detalle') document.querySelector('.tbl-row.row-active')?.classList.remove('row-active');
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
  url = BASE_PATH + url.replace(/^\/reparo/, '');
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
    setTimeout(() => { window.location.href = BASE_PATH + '/index.php?expired=1'; }, 1500);
    throw new Error('session_expired');
  }
  if (response.status === 403) {
    var msg403 = '';
    try { msg403 = (await response.clone().json()).msg || ''; } catch(_) {}
    var m = msg403.toLowerCase();
    if (m.includes('suscripci') || m.includes('vencid') || m.includes('suspendid') || m.includes('pendiente')) {
      var isPending = m.includes('pendiente');
      mostrarPantallaSuspendida(isPending);
      throw new Error('account_suspended');
    }
  }
  return response;
}

function mostrarPantallaSuspendida(isPending) {
  if (document.getElementById('overlay-suspendida')) return;
  var el = document.createElement('div');
  el.id = 'overlay-suspendida';
  el.className = 'overlay-suspendida';
  var titulo = isPending ? 'Completa tu pago para continuar' : 'Tu suscripción ha vencido';
  var msg    = isPending
    ? 'Tu cuenta fue creada. Completa el pago para activar Centrotec.'
    : 'Para seguir usando Centrotec, renueva tu suscripción o contacta a soporte.';
  var btnLabel = isPending ? 'Ir a pagar' : 'Renovar suscripción';
  el.innerHTML =
    '<div class="susp-card">' +
      '<div class="susp-icon"><span class="material-icons-round">schedule</span></div>' +
      '<h2 class="susp-title">' + titulo + '</h2>' +
      '<p class="susp-msg">' + msg + '</p>' +
      '<a href="' + BASE_PATH + '/landing.php#precios" class="susp-btn">' +
        '<span class="material-icons-round">rocket_launch</span>' + btnLabel +
      '</a>' +
      '<a href="mailto:soporte@centrotec.cl" class="susp-btn-ghost">' +
        '<span class="material-icons-round">mail</span>Contactar soporte — soporte@centrotec.cl' +
      '</a>' +
    '</div>';
  document.body.appendChild(el);
}

// ═══════════════════════════════════════════════════════════
// SERVICIOS
// ═══════════════════════════════════════════════════════════
function _buildServicioRow(rep) {
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
      <td class="cell-sub">${esc(rep.dano_ingreso)}</td>
      <td class="cell-val">${v}</td>
      <td class="cell-sub">${esc(rep.ingresado_por)}</td>
      <td class="cell-sub">${fmtDate(rep.fecha_ingreso)}</td>
      <td><span class="pill ${pill}">${lbl}</span></td>
      <td class="action-col">
        <div class="row-actions">
          <button type="button" class="btn-row-action btn-row-edit" title="Editar servicio">
            <span class="material-icons-round">edit</span>
          </button>
          <button type="button" class="btn-row-action btn-row-print" data-orden="${rep.id_ingreso}" title="Imprimir orden de servicio técnico">
            <span class="material-icons-round">print</span>
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
}

function _applySortServicios() {
  const tbody = document.getElementById('tbl-servicios');
  if (!_repMap.size) return;
  let reps = Array.from(_repMap.values());
  if (_sortCol) {
    reps.sort((a, b) => {
      const va = _sortCol === 'id' ? a.id_ingreso : (a.nombre_cliente || '').toLowerCase();
      const vb = _sortCol === 'id' ? b.id_ingreso : (b.nombre_cliente || '').toLowerCase();
      if (va < vb) return _sortDir === 'asc' ? -1 : 1;
      if (va > vb) return _sortDir === 'asc' ?  1 : -1;
      return 0;
    });
  }
  const total      = reps.length;
  const pageSize   = _repPageSize === 0 ? total : _repPageSize;
  const totalPages = pageSize > 0 ? Math.ceil(total / pageSize) : 1;
  _repPage = Math.min(_repPage, Math.max(1, totalPages));
  const start = (_repPage - 1) * pageSize;
  const slice = _repPageSize === 0 ? reps : reps.slice(start, start + pageSize);
  tbody.innerHTML = slice.map(_buildServicioRow).join('');
  document.querySelectorAll('.th-sortable').forEach(th => {
    th.classList.remove('sort-asc', 'sort-desc');
    if (th.dataset.sort === _sortCol)
      th.classList.add(_sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
  });
  _renderRepPagination(total, pageSize, totalPages);
}

function _renderRepPagination(total, pageSize, totalPages) {
  const footer = document.getElementById('rep-pagination');
  if (!footer) return;
  if (_repPageSize === 0 || totalPages <= 1) {
    footer.innerHTML = `<span class="pag-info">${total} servicio${total !== 1 ? 's' : ''}</span>`;
    return;
  }
  const from = (_repPage - 1) * pageSize + 1;
  const to   = Math.min(_repPage * pageSize, total);
  footer.innerHTML = `
    <span class="pag-info">${from}–${to} de ${total}</span>
    <div class="pag-btns">
      <button class="pag-btn" id="rep-pag-prev"${_repPage <= 1 ? ' disabled' : ''}>
        <span class="material-icons-round">chevron_left</span>
      </button>
      <span class="pag-pages">${_repPage} / ${totalPages}</span>
      <button class="pag-btn" id="rep-pag-next"${_repPage >= totalPages ? ' disabled' : ''}>
        <span class="material-icons-round">chevron_right</span>
      </button>
    </div>`;
  footer.querySelector('#rep-pag-prev').addEventListener('click', () => { _repPage--; _applySortServicios(); });
  footer.querySelector('#rep-pag-next').addEventListener('click', () => { _repPage++; _applySortServicios(); });
}

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
    rows.forEach(rep => _repMap.set(rep.id_ingreso, rep));
    _applySortServicios();
  } catch(e) {
    if (e.message !== 'session_expired')
      tbody.innerHTML = `<tr><td colspan="9" class="tbl-empty">Error de red. ¿Está XAMPP activo?</td></tr>`;
  }
}

function updateStats(rows) {
  const counts = { total:rows.length, Ingresado:0, 'En Reparacion':0, Reparado:0, Entregado:0, Garantia:0 };
  rows.forEach(r => { if (counts[r.status]!==undefined) counts[r.status]++; });
  document.getElementById('st-total').textContent = counts.total;
  document.getElementById('st-ing').textContent   = counts['Ingresado'];
  document.getElementById('st-rep').textContent   = counts['En Reparacion'];
  document.getElementById('st-done').textContent  = counts['Reparado'];
  document.getElementById('st-entr').textContent  = counts['Entregado'];
  document.getElementById('st-gar').textContent   = counts['Garantia'];
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
  document.getElementById('det-daño').textContent    = rep.dano_ingreso;
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
  // Resaltar fila activa en la tabla
  document.querySelector('.tbl-row.row-active')?.classList.remove('row-active');
  document.querySelector(`.tbl-row[data-id="${rep.id_ingreso}"]`)?.classList.add('row-active');
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
        label: `${i.nombre}${i.marca_compatible ? ' · '+i.marca_compatible : ''}${i.modelo_compatible ? ' · '+i.modelo_compatible : ''} (stock: ${i.cantidad})`,
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

  const telInput  = document.querySelector('[name="telefono_cliente"]');
  const telDigits = (telInput?.value || '').replace(/\D/g, '').replace(/^56/, '');
  if (telDigits.length !== 9) {
    telInput?.classList.add('inp-err');
    telInput?.focus();
    toast('El teléfono debe tener 9 dígitos después del +56.', 'err');
    return;
  }
  telInput?.classList.remove('inp-err');

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
      _lastNuevoId = j.data.id ?? null;
      loadServicios();
      document.getElementById('nuevo-post-save').classList.remove('hidden');
      document.getElementById('form-nuevo').classList.add('hidden');
      document.getElementById('ps-num').textContent = _lastNuevoId ? `#${_lastNuevoId}` : '';
      // Mostrar código de seguimiento
      const codigo = j.data.codigo_seguimiento ?? '';
      const psCode = document.getElementById('ps-codigo');
      if (psCode) psCode.textContent = codigo || '–';
      // Construir enlace WhatsApp
      const waBtn = document.getElementById('ps-wa-btn');
      if (waBtn && codigo) {
        const tel   = (document.querySelector('[name="telefono_cliente"]')?.value || '').replace(/\D/g, '');
        const nombre = document.querySelector('[name="nombre_cliente"]')?.value?.trim() || 'cliente';
        const local = document.getElementById('sidebar-nombre')?.textContent?.trim() || 'el servicio técnico';
        const url   = `${location.origin}${BASE_PATH}/seguimiento.php?codigo=${encodeURIComponent(codigo)}`;
        const msg   = `Hola ${nombre}! Tu equipo ingresó a *${local}*.\n` +
                      `Código de seguimiento: *${codigo}*\n` +
                      `Consulta el estado en: ${url}`;
        const wa    = tel.length >= 11
          ? `https://wa.me/${tel}?text=${encodeURIComponent(msg)}`
          : `https://wa.me/?text=${encodeURIComponent(msg)}`;
        waBtn.href = wa;
        waBtn.classList.remove('hidden');
      } else if (waBtn) {
        waBtn.classList.add('hidden');
      }
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
      const msg = j.data.stock_descontado
        ? '✔ Guardado · Repuestos descontados del inventario'
        : '✔ Guardado.';
      toast(msg, 'ok');
      if (j.data.stock_descontado) _repuestosCache = null;
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
function _buildInventarioRow(rep) {
  const isAdmin    = CURRENT_USER.role === 'Admin';
  const stockColor = rep.cantidad > 0 ? 'color:#e6edf3' : 'color:#f87171';
  const actions = `<td class="action-col">
    <div class="row-actions">
      <button type="button" class="btn-qr-row btn-inv-qr" data-id="${rep.id_repuesto}" title="Ver QR">
        <span class="material-icons-round">qr_code</span> QR
      </button>
      ${isAdmin ? `<button type="button" class="btn-row-action btn-inv-edit" title="Editar repuesto">
        <span class="material-icons-round">edit</span>
      </button>
      <button type="button" class="btn-row-action btn-inv-del" data-id="${rep.id_repuesto}" data-nombre="${esc(rep.nombre)}" title="Eliminar repuesto" style="color:#f87171">
        <span class="material-icons-round">delete</span>
      </button>` : ''}
      <button type="button" class="btn-stock" data-id="${rep.id_repuesto}" data-qty="${parseInt(rep.cantidad)+1}" title="Aumentar">+</button>
      <button type="button" class="btn-stock" data-id="${rep.id_repuesto}" data-qty="${Math.max(0,parseInt(rep.cantidad)-1)}" title="Disminuir">−</button>
    </div>
  </td>`;
  return `<tr data-inv-id="${rep.id_repuesto}">
    <td><strong>${esc(rep.nombre)}</strong></td>
    <td>${esc(rep.marca_compatible||'—')}</td>
    <td>${esc(rep.modelo_compatible||'—')}</td>
    <td>$${fmt(rep.precio_venta)}</td>
    <td><strong style="${stockColor};font-size:16px">${rep.cantidad}</strong></td>
    ${actions}
  </tr>`;
}

function _applySortInventario() {
  const tbody = document.getElementById('tbl-inventario');
  if (!_invMap.size) return;
  let reps = Array.from(_invMap.values());
  if (_invSortCol) {
    reps.sort((a, b) => {
      let va, vb;
      switch (_invSortCol) {
        case 'nombre': va = (a.nombre||'').toLowerCase(); vb = (b.nombre||'').toLowerCase(); break;
        case 'marca':  va = (a.marca_compatible||'').toLowerCase(); vb = (b.marca_compatible||'').toLowerCase(); break;
        case 'modelo': va = (a.modelo_compatible||'').toLowerCase(); vb = (b.modelo_compatible||'').toLowerCase(); break;
        case 'precio': va = parseFloat(a.precio_venta)||0; vb = parseFloat(b.precio_venta)||0; break;
        case 'stock':  va = parseInt(a.cantidad)||0; vb = parseInt(b.cantidad)||0; break;
        default: va = vb = 0;
      }
      if (va < vb) return _invSortDir === 'asc' ? -1 : 1;
      if (va > vb) return _invSortDir === 'asc' ?  1 : -1;
      return 0;
    });
  }
  const total      = reps.length;
  const pageSize   = _invPageSize === 0 ? total : _invPageSize;
  const totalPages = pageSize > 0 ? Math.ceil(total / pageSize) : 1;
  _invPage = Math.min(_invPage, Math.max(1, totalPages));
  const start = (_invPage - 1) * pageSize;
  const slice = _invPageSize === 0 ? reps : reps.slice(start, start + pageSize);
  tbody.innerHTML = slice.map(_buildInventarioRow).join('');
  document.querySelectorAll('.th-sortable[data-sort-inv]').forEach(th => {
    th.classList.remove('sort-asc', 'sort-desc');
    if (th.dataset.sortInv === _invSortCol)
      th.classList.add(_invSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
  });
  _renderInvPagination(total, pageSize, totalPages);
}

function _renderInvPagination(total, pageSize, totalPages) {
  const footer = document.getElementById('inv-pagination');
  if (!footer) return;
  if (_invPageSize === 0 || totalPages <= 1) {
    footer.innerHTML = `<span class="pag-info">${total} repuesto${total !== 1 ? 's' : ''}</span>`;
    return;
  }
  const from = (_invPage - 1) * pageSize + 1;
  const to   = Math.min(_invPage * pageSize, total);
  footer.innerHTML = `
    <span class="pag-info">${from}–${to} de ${total}</span>
    <div class="pag-btns">
      <button class="pag-btn" id="pag-prev"${_invPage <= 1 ? ' disabled' : ''}>
        <span class="material-icons-round">chevron_left</span>
      </button>
      <span class="pag-pages">${_invPage} / ${totalPages}</span>
      <button class="pag-btn" id="pag-next"${_invPage >= totalPages ? ' disabled' : ''}>
        <span class="material-icons-round">chevron_right</span>
      </button>
    </div>`;
  footer.querySelector('#pag-prev').addEventListener('click', () => { _invPage--; _applySortInventario(); });
  footer.querySelector('#pag-next').addEventListener('click', () => { _invPage++; _applySortInventario(); });
}

async function loadInventario() {
  const q     = document.getElementById('search-inv').value;
  const tbody = document.getElementById('tbl-inventario');
  tbody.innerHTML = `<tr><td colspan="6" class="tbl-loading"><span class="material-icons-round spin">sync</span> Cargando...</td></tr>`;
  try {
    const r = await apiFetch(`/reparo/api/inventario.php?q=${encodeURIComponent(q)}`);
    const j = await r.json();
    if (!j.ok) { tbody.innerHTML=`<tr><td colspan="6" class="tbl-empty">${esc(j.msg)}</td></tr>`; return; }
    if (!j.data.length) {
      const addBtn = CURRENT_USER.role === 'Admin'
        ? ' <button class="link-btn" data-action="open-modal-repuesto">Agregar el primero</button>' : '';
      tbody.innerHTML = `<tr><td colspan="6" class="tbl-empty">Sin repuestos registrados.${addBtn}</td></tr>`;
      return;
    }
    _invMap.clear();
    j.data.forEach(rep => _invMap.set(rep.id_repuesto, rep));
    _applySortInventario();
  } catch(e) {
    if (e.message !== 'session_expired')
      tbody.innerHTML=`<tr><td colspan="6" class="tbl-empty">Error de red.</td></tr>`;
  }
}

async function alterStock(id, qty) {
  // Actualización optimista: _invMap refleja el nuevo valor antes del fetch
  const cached = _invMap.get(id);
  const prevQty = cached ? cached.cantidad : null;
  if (cached) cached.cantidad = qty;

  try {
    const r = await apiFetch('/reparo/api/inventario.php', {
      method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id, cantidad: qty})
    });
    const j = await r.json();
    if (!j.ok) {
      // Revertir en caso de error
      if (cached && prevQty !== null) cached.cantidad = prevQty;
      toast(j.msg, 'err'); return;
    }

    // Actualiza solo la fila afectada — sin recargar la tabla completa
    const row = document.querySelector(`#tbl-inventario tr[data-inv-id="${id}"]`);
    if (!row) { loadInventario(); return; }

    const cell  = row.querySelector('td:nth-child(5) strong');
    cell.textContent = `${qty}`;
    cell.style.color = qty > 0 ? '#e6edf3' : '#f87171';

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
    document.getElementById('edit-rep-nombre').value = item.nombre;
    document.getElementById('edit-rep-marca').value  = item.marca_compatible || '';
    document.getElementById('edit-rep-precio').value = item.precio_venta;
    _tagModeloEdit.setValue(item.modelo_compatible || '');
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
    payload.nombre            = document.getElementById('edit-rep-nombre').value.trim();
    payload.marca_compatible  = document.getElementById('edit-rep-marca').value.trim();
    payload.modelo_compatible = _tagModeloEdit.getValue();
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
      // Actualizar _invMap de inmediato para que modal muestre valores correctos si se abre antes del reload
      const cached = _invMap.get(id);
      if (cached) {
        cached.cantidad = payload.cantidad;
        if (isAdmin) {
          cached.nombre             = payload.nombre;
          cached.marca_compatible   = payload.marca_compatible;
          cached.modelo_compatible  = payload.modelo_compatible;
          cached.precio_venta       = payload.precio_venta;
        }
      }
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
  document.getElementById('hid-nuevo-modelo').value = _tagModeloNuevo.getValue();
  const fd = new FormData(e.target);
  try {
    const r = await apiFetch('/reparo/api/inventario.php', {method: 'POST', body: fd});
    const j = await r.json();
    if (j.ok) {
      toast('✔ Repuesto agregado.', 'ok');
      closeModal('modal-repuesto');
      e.target.reset();
      _tagModeloNuevo.setValue('');
      loadInventario();
    } else toast(j.msg, 'err');
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
let   _repPageSize      = 25;        // 0 = todos
let   _repPage          = 1;
const _invMap           = new Map(); // id_repuesto → objeto repuesto completo
let   _sortCol          = null;      // columna activa servicios: 'id' | 'cliente' | null
let   _sortDir          = 'asc';    // 'asc' | 'desc'
let   _invSortCol       = null;      // columna activa inventario
let   _invSortDir       = 'asc';
let   _invPageSize      = 25;        // 0 = todos
let   _invPage          = 1;
let _selMarcaNuevo      = null;
let _selModeloNuevo     = null;
let _selRepNuevo        = null; // select repuesto en modal-nuevo
let _selRepAdicional    = null; // select repuesto adicional en modal-detalle
let _lastNuevoId        = null; // id del último servicio ingresado (para post-save)
let _tagModeloNuevo     = null; // TagInput modelos en modal-repuesto (nuevo)
let _tagModeloEdit      = null; // TagInput modelos en modal-edit-repuesto

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
// ── Reset modal nuevo ─────────────────────────────────────────
function _resetModalNuevo() {
  // Mostrar form, ocultar panel post-save
  document.getElementById('form-nuevo').classList.remove('hidden');
  document.getElementById('nuevo-post-save').classList.add('hidden');

  // Reset form fields
  document.getElementById('form-nuevo').reset();
  _selMarcaNuevo?.reset();
  _selModeloNuevo?.disable('— Primero selecciona una marca —');
  _selRepNuevo?.reset();
  document.getElementById('hid-rep-nuevo').value = '';

  const inpNM = document.getElementById('inp-marca-nueva-nuevo');
  const inpMM = document.getElementById('inp-modelo-nuevo-nuevo');
  if (inpNM) { inpNM.classList.remove('visible'); inpNM.value = ''; }
  if (inpMM) { inpMM.classList.remove('visible'); inpMM.value = ''; }
  document.getElementById('hid-marca-nuevo').value  = '';
  document.getElementById('hid-modelo-nuevo').value = '';

  // Restaurar botón submit
  const btn = document.getElementById('btn-submit-nuevo');
  if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-icons-round">save</span> Registrar ingreso'; }
}

// ── Split button ─────────────────────────────────────────────
function _initSplitBtn(wrapId, mainId, arrowId, menuId) {
  const wrap  = document.getElementById(wrapId);
  const main  = document.getElementById(mainId);
  const arrow = document.getElementById(arrowId);
  const menu  = document.getElementById(menuId);
  if (!wrap || !main || !arrow || !menu) return;

  const toggleMenu = open => {
    menu.classList.toggle('open', open);
    const ic = arrow.querySelector('.material-icons-round');
    if (ic) ic.textContent = open ? 'expand_less' : 'expand_more';
  };

  main.addEventListener('click', () => toggleMenu(!menu.classList.contains('open')));
  arrow.addEventListener('click', e => { e.stopPropagation(); toggleMenu(!menu.classList.contains('open')); });

  document.addEventListener('click', e => {
    if (!wrap.contains(e.target)) toggleMenu(false);
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') toggleMenu(false);
  });
}

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
  // Banda de vista actual
  const q  = document.getElementById('search-bar').value;
  const st = document.getElementById('filter-status').value;
  const wrap = document.getElementById('exp-vista-wrap');
  const info = document.getElementById('exp-vista-info');
  if (wrap && info) {
    const parts = [];
    if (q)        parts.push(`"${q}"`);
    if (st)       parts.push(st);
    if (_sortCol) parts.push(`orden ${_sortCol === 'id' ? '#' : 'cliente'} ${_sortDir === 'asc' ? '↑' : '↓'}`);
    if (parts.length) { info.textContent = parts.join(' · '); wrap.style.display = ''; }
    else wrap.style.display = 'none';
  }
  openModal('modal-exportar');
}

function openExportInvModal() {
  const q    = document.getElementById('search-inv').value;
  const info = document.getElementById('exp-inv-vista-info');
  if (info) {
    const parts = [];
    if (q)           parts.push(`"${q}"`);
    if (_invSortCol) parts.push(`orden ${_invSortCol} ${_invSortDir === 'asc' ? '↑' : '↓'}`);
    info.textContent = parts.length ? parts.join(' · ') : 'Sin filtros';
  }
  openModal('modal-exportar-inv');
}

function doExportInv(formato) {
  const params = new URLSearchParams();
  params.set('formato', formato);
  const q = document.getElementById('search-inv').value;
  if (q) params.set('q', q);
  if (_invSortCol) { params.set('sort_col', _invSortCol); params.set('sort_dir', _invSortDir); }
  const url = `${BASE_PATH}/api/exportar_inventario.php?${params.toString()}`;
  closeModal('modal-exportar-inv');
  if (formato === 'csv') {
    window.open(url, '_blank');
  } else {
    const w = window.open(url, 'inv_export', 'width=900,height=700,scrollbars=yes,resizable=yes');
    if (w) { w.focus(); setTimeout(() => w.print(), 800); }
  }
}

// Construye params desde la VISTA ACTUAL (search-bar + filter-status + sort)
function _buildViewParams(formato) {
  const params = new URLSearchParams();
  params.set('formato', formato);
  const q  = document.getElementById('search-bar').value;
  const st = document.getElementById('filter-status').value;
  if (q)  params.set('q', q);
  if (st) params.append('status[]', st);
  if (_sortCol) { params.set('sort_col', _sortCol); params.set('sort_dir', _sortDir); }
  return params;
}

// Construye params desde el MODAL de personalización
function _buildExportParams(formato) {
  const params = new URLSearchParams();
  params.set('formato', formato);
  const q = document.getElementById('search-bar').value;
  if (q) params.set('q', q);
  if (_sortCol) { params.set('sort_col', _sortCol); params.set('sort_dir', _sortDir); }
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

function doExportView(formato) {
  const params = _buildViewParams(formato);
  const url    = `${BASE_PATH}/api/exportar.php?${params.toString()}`;
  if (formato === 'csv') { window.open(url, '_blank'); return; }
  const w = window.open(url, 'serv_export', 'width=1000,height=700,scrollbars=yes,resizable=yes');
  if (w) { w.focus(); setTimeout(() => w.print(), 800); }
}

function doExport(formato) {
  const params = _buildExportParams(formato);
  const url    = `${BASE_PATH}/api/exportar.php?${params.toString()}`;
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

// ─── Orden de servicio técnico (popup ventana) ────────────────
function doOrden(id) {
  if (!id) return;
  const url = `${BASE_PATH}/orden.php?id=${id}`;
  const w = window.open(url, `orden_${id}`,
    'width=600,height=720,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no');
  if (w) w.focus();
}

// ═══════════════════════════════════════════════════════════
// CONFIGURACIÓN
// ═══════════════════════════════════════════════════════════
async function loadConfigData() {
  try {
    const r = await apiFetch('/reparo/api/empresa.php');
    const j = await r.json();
    if (!j.ok) return;
    const d = j.data;
    const el = id => document.getElementById(id);
    if (el('cfg-dir'))    el('cfg-dir').value    = d.direccion || '';
    if (el('cfg-tel'))    el('cfg-tel').value    = d.telefono  || '';
    if (el('cfg-mail'))   el('cfg-mail').value   = d.correo    || '';
    if (el('cfg-comuna')) el('cfg-comuna').value = d.comuna    || '';
    if (el('cfg-region')) el('cfg-region').value = d.region    || '';
  } catch(e) {}
  loadUsuarios();
}

async function loadSuscripcion() {
  const wrap = document.getElementById('subs-historial-wrap');
  if (!wrap) return;
  try {
    const r = await apiFetch('/reparo/api/suscripcion.php');
    const j = await r.json();
    if (!j.ok) return;
    const d = j.data;

    const el = id => document.getElementById(id);

    if (el('subs-plan-nombre')) el('subs-plan-nombre').textContent = d.plan_tipo || 'Básico';

    if (el('subs-estado-badge')) {
      const colores = { 'Activo':'pill-green', 'Por vencer':'pill-orange', 'Vencido':'pill-red', 'Pendiente':'pill-orange' };
      el('subs-estado-badge').className = 'pill ' + (colores[d.plan_estado] || 'pill-gray');
      el('subs-estado-badge').textContent = d.plan_estado || 'Activo';
    }

    if (el('subs-vence-txt')) {
      el('subs-vence-txt').textContent = d.plan_vencimiento
        ? 'Vence el ' + fmtDate(d.plan_vencimiento)
        : 'Sin fecha de vencimiento definida';
    }

    const diasWrap = el('subs-dias-wrap');
    const diasNum  = el('subs-dias-num');
    const diasLbl  = el('subs-dias-lbl');
    if (diasWrap && diasNum && diasLbl) {
      if (d.dias_restantes === null) {
        diasNum.textContent  = '∞';
        diasLbl.textContent  = 'sin vencimiento';
        diasWrap.className   = 'subs-dias-wrap ok';
      } else {
        diasNum.textContent = d.dias_restantes;
        diasLbl.textContent = d.dias_restantes === 1 ? 'día restante' : 'días restantes';
        const cls = d.dias_restantes > 30 ? 'ok' : d.dias_restantes > 7 ? 'warn' : 'danger';
        diasWrap.className = 'subs-dias-wrap ' + cls;
      }
    }

    const notifChk = el('subs-notif-chk');
    if (notifChk) notifChk.checked = !!d.notif_vencimiento;

    if (!d.historial.length) {
      wrap.innerHTML = `<div class="subs-empty-state">
        <span class="material-icons-round">receipt_long</span>
        <span>Sin registros de pago aún</span>
      </div>`;
    } else {
      wrap.innerHTML = `<table class="subs-historial-table">
        <thead><tr><th>Fecha</th><th>Descripción</th><th>Monto</th><th>Estado</th></tr></thead>
        <tbody>${d.historial.map(p => {
          const col = p.estado === 'Pagado' ? 'pill-green' : p.estado === 'Pendiente' ? 'pill-orange' : 'pill-red';
          return `<tr>
            <td>${fmtDate(p.fecha)}</td>
            <td>${esc(p.descripcion)}</td>
            <td>$${fmt(p.monto)}</td>
            <td><span class="pill ${col}">${esc(p.estado)}</span></td>
          </tr>`;
        }).join('')}</tbody>
      </table>`;
    }
  } catch(e) { if (e.message !== 'session_expired') toast('Error al cargar suscripción', 'err'); }
}

async function loadUsuarios() {
  const tbody = document.getElementById('tbl-usuarios');
  if (!tbody) return;
  try {
    const r = await apiFetch('/reparo/api/usuarios.php');
    const j = await r.json();
    if (!j.ok) { tbody.innerHTML = `<tr><td colspan="4" class="tbl-empty">${esc(j.msg)}</td></tr>`; return; }
    // Actualizar contador de técnicos y estado del botón
    const tecnicos = j.data.filter(u => u.cargo === 'Tecnico').length;
    const countEl  = document.getElementById('cfg-tecnicos-count');
    const btnNuevo = document.getElementById('btn-nuevo-tecnico');
    if (countEl) {
      countEl.textContent = `${tecnicos} / 5 técnicos`;
      countEl.classList.remove('hidden');
    }
    if (btnNuevo) {
      btnNuevo.disabled = tecnicos >= 5;
      btnNuevo.title    = tecnicos >= 5 ? 'Límite alcanzado: máximo 5 técnicos' : '';
    }
    const me = CURRENT_USER.user;
    tbody.innerHTML = j.data.map(u => {
      const esSelf  = u.user === me;
      const esAdmin = u.cargo === 'Admin';
      const badge   = esAdmin
        ? '<span class="pill pill-blue">Admin</span>'
        : '<span class="pill pill-orange">Técnico</span>';
      const nuevoC  = esAdmin ? 'Tecnico' : 'Admin';
      const lblC    = esAdmin ? 'Pasar a Técnico' : 'Pasar a Admin';
      const btnCargo = esSelf ? '' :
        `<button class="btn-sm btn-sec" data-action="toggle-cargo" data-uid="${u.id_usuario}" data-cargo="${nuevoC}">${lblC}</button>`;
      const btnPass =
        `<button class="btn-sm btn-sec" data-action="reset-pass" data-uid="${u.id_usuario}" data-nombre="${esc(u.nombre)}">
           <span class="material-icons-round" style="font-size:15px">lock_reset</span> Contraseña
         </button>`;
      const inicial = u.nombre.charAt(0).toUpperCase();
      return `<tr>
        <td><div class="usr-cell"><div class="user-av">${inicial}</div><strong>${esc(u.nombre)}</strong></div></td>
        <td><code class="code-lbl">${esc(u.user)}</code></td>
        <td>${badge}</td>
        <td><div class="row-actions">${btnCargo}${btnPass}</div></td>
      </tr>`;
    }).join('');
  } catch(e) { if (e.message !== 'session_expired') toast('Error de red.', 'err'); }
}

document.addEventListener('DOMContentLoaded', async () => {

  // Revelar buscador después del escaneo de gestores de contraseñas
  setTimeout(() => {
    const sb = document.getElementById('search-bar');
    if (sb) sb.style.display = '';
  }, 50);

  document.querySelectorAll('.nav-link[data-view]').forEach(link => {
    link.addEventListener('click', () => switchView(link.dataset.view, link));
  });

  document.getElementById('btn-abrir-nuevo')?.addEventListener('click', () => {
    _resetModalNuevo();
    openModal('modal-nuevo');
  });

  // Botones post-guardado
  document.getElementById('ps-nuevo')?.addEventListener('click', () => {
    _resetModalNuevo();    // limpia form y vuelve a mostrar el formulario
  });

  document.getElementById('ps-editar')?.addEventListener('click', () => {
    closeModal('modal-nuevo');
    _resetModalNuevo();
    if (_lastNuevoId) {
      const rep = _repMap.get(_lastNuevoId);
      if (rep) openDetalle(rep);
    }
  });

  document.getElementById('ps-cerrar')?.addEventListener('click', () => {
    closeModal('modal-nuevo');
    _resetModalNuevo();
  });
  document.getElementById('ps-boleta')?.addEventListener('click', () => doOrden(_lastNuevoId));
  document.getElementById('det-boleta-btn')?.addEventListener('click', () => {
    const id = parseInt(document.getElementById('det-hidden-id').value);
    doOrden(id);
  });
  // Split buttons — servicios
  _initSplitBtn('split-exportar', 'btn-exportar-main', 'btn-exportar-arrow', 'split-exportar-menu');
  // Split buttons — inventario exportar
  _initSplitBtn('split-exportar-inv', 'btn-exportar-inv-main', 'btn-exportar-inv-arrow', 'split-exportar-inv-menu');
  // Split button — agregar repuesto (solo flecha abre dropdown; botón principal abre modal)
  (function() {
    var arrow = document.getElementById('btn-agregar-inv-arrow');
    var menu  = document.getElementById('split-agregar-inv-menu');
    if (!arrow || !menu) return;
    arrow.addEventListener('click', function(e) {
      e.stopPropagation();
      var open = menu.classList.toggle('open');
      var ic = arrow.querySelector('.material-icons-round');
      if (ic) ic.textContent = open ? 'expand_less' : 'expand_more';
    });
    document.addEventListener('click', function(e) {
      if (!menu.classList.contains('open')) return;
      if (!menu.contains(e.target) && e.target !== arrow) {
        menu.classList.remove('open');
        var ic = arrow.querySelector('.material-icons-round');
        if (ic) ic.textContent = 'expand_more';
      }
    });
  }());

  // Acciones del dropdown de exportar (servicios e inventario)
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-split-action]');
    if (!btn) return;
    const action = btn.dataset.splitAction;
    // cerrar todos los dropdowns abiertos
    document.querySelectorAll('.split-dropdown.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.split-arrow .material-icons-round').forEach(i => i.textContent = 'expand_more');
    if      (action === 'exp-serv-csv')          doExportView('csv');
    else if (action === 'exp-serv-pdf')          doExportView('pdf');
    else if (action === 'exp-serv-personalizar') openExportModal();
    else if (action === 'exp-inv-csv')           doExportInv('csv');
    else if (action === 'exp-inv-pdf')           doExportInv('pdf');
    else if (action === 'exp-inv-personalizar')  openExportInvModal();
    else if (action === 'imp-inv-csv')           document.dispatchEvent(new Event('openImportModal'));
  });

  document.getElementById('btn-exp-csv')?.addEventListener('click', () => doExport('csv'));
  document.getElementById('btn-exp-pdf')?.addEventListener('click', () => doExport('pdf'));
  document.getElementById('btn-abrir-repuesto')?.addEventListener('click', () => openModal('modal-repuesto'));

  // Click en headers ordenables del inventario
  document.querySelector('#tbl-inventario').closest('table').querySelector('thead')
    .addEventListener('click', e => {
      const th = e.target.closest('.th-sortable[data-sort-inv]');
      if (!th || !_invMap.size) return;
      const col = th.dataset.sortInv;
      if (_invSortCol === col) _invSortDir = _invSortDir === 'asc' ? 'desc' : 'asc';
      else { _invSortCol = col; _invSortDir = 'asc'; }
      _invPage = 1;
      _applySortInventario();
    });

  document.querySelectorAll('.modal-close[data-modal], .btn-sec[data-modal]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.modal));
  });

  // Backdrop click removed — accidental clicks outside a modal no longer close it
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-bg.active').forEach(m => closeModal(m.id));
  });

  document.getElementById('form-nuevo')?.addEventListener('submit', submitNuevo);
  document.getElementById('form-actualizar')?.addEventListener('submit', submitActualizar);
  document.getElementById('form-repuesto')?.addEventListener('submit', submitRepuesto);

  // ── Solo dígitos en IMEI y Teléfono ────────────────────────────────────────
  const PREFIX = '+56 ';
  document.querySelectorAll('[name="imei"],[name="telefono_cliente"]').forEach(inp => {
    if (inp.name === 'telefono_cliente' && !inp.value.startsWith(PREFIX)) inp.value = PREFIX;
    inp.addEventListener('input', () => {
      if (inp.name === 'telefono_cliente') {
        // Asegurar que el prefijo siempre esté presente
        if (!inp.value.startsWith(PREFIX)) inp.value = PREFIX + inp.value.replace(/^\+56\s?/,'').replace(/[^0-9 ]/g,'');
        // Quitar letras del resto
        const rest = inp.value.slice(PREFIX.length).replace(/[^0-9 ]/g, '');
        inp.value = PREFIX + rest;
        // Limpiar error visual cuando tiene 9 dígitos
        const digits = rest.replace(/\D/g, '');
        if (digits.length === 9) inp.classList.remove('inp-err');
      } else {
        inp.value = inp.value.replace(/\D/g, '');
      }
    });
    inp.addEventListener('keydown', e => {
      if (inp.name !== 'telefono_cliente') return;
      // Bloquear borrado dentro del prefijo
      const pos = inp.selectionStart;
      if ((e.key === 'Backspace' && pos <= PREFIX.length && inp.selectionStart === inp.selectionEnd) ||
          (e.key === 'Delete'    && pos <  PREFIX.length)) {
        e.preventDefault();
      }
    });
    inp.addEventListener('click', () => {
      if (inp.name === 'telefono_cliente' && inp.selectionStart < PREFIX.length) {
        inp.setSelectionRange(PREFIX.length, PREFIX.length);
      }
    });
  });

  // ── Formateo automático de RUT chileno ─────────────────────────────────────
  const rutInput = document.querySelector('[name="rut_cliente"]');
  if (rutInput) {
    rutInput.addEventListener('input', () => {
      let v = rutInput.value.toUpperCase().replace(/[^0-9K]/g, '');
      if (v.length < 2) { rutInput.value = v; return; }
      const dv   = v.slice(-1);          // dígito verificador
      const body = v.slice(0, -1);       // cuerpo sin DV
      // Insertar puntos cada 3 dígitos desde la derecha
      const fmt  = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      rutInput.value = fmt + '-' + dv;
    });
    rutInput.addEventListener('keydown', e => {
      // Permitir borrar el guión-DV sin quedarse pegado
      if (e.key === 'Backspace' && rutInput.value.endsWith('-')) {
        e.preventDefault();
        rutInput.value = rutInput.value.slice(0, -2);
      }
    });
  }

  // TagInput de modelos compatibles en inventario
  _tagModeloNuevo = new TagInput('tag-nuevo-modelo');
  _tagModeloEdit  = new TagInput('tag-edit-modelo');

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
      _repPage = 1;
      loadServicios();
    });
  });

  document.getElementById('search-bar').addEventListener('input', debounce(() => { _repPage = 1; loadServicios(); }, 300));
  document.getElementById('rep-per-page')?.addEventListener('change', function() {
    _repPageSize = parseInt(this.value);
    _repPage = 1;
    _applySortServicios();
  });
  document.getElementById('search-inv').addEventListener('input', debounce(() => { _invPage = 1; loadInventario(); }, 300));
  document.getElementById('inv-per-page')?.addEventListener('change', function() {
    _invPageSize = parseInt(this.value);
    _invPage = 1;
    _applySortInventario();
  });

  // Click en headers ordenables (# y Cliente)
  document.querySelector('#tbl-servicios').closest('table').querySelector('thead')
    .addEventListener('click', e => {
      const th = e.target.closest('.th-sortable[data-sort]');
      if (!th || !_repMap.size) return;
      const col = th.dataset.sort;
      if (_sortCol === col) _sortDir = _sortDir === 'asc' ? 'desc' : 'asc';
      else { _sortCol = col; _sortDir = 'asc'; }
      _repPage = 1;
      _applySortServicios();
    });

  // Click simple: botón editar, eliminar y limpiar filtros (servicios)
  document.getElementById('tbl-servicios').addEventListener('click', e => {
    if (e.target.dataset.action === 'clear-filters') {
      document.getElementById('search-bar').value    = '';
      document.getElementById('filter-status').value = '';
      _repPage = 1;
      loadServicios();
      return;
    }
    const editBtn = e.target.closest('.btn-row-edit');
    if (editBtn) {
      const row = editBtn.closest('.tbl-row[data-id]');
      if (row) { const rep = _repMap.get(parseInt(row.dataset.id)); if (rep) openDetalle(rep); }
      return;
    }
    const printBtn = e.target.closest('.btn-row-print[data-orden]');
    if (printBtn) { doOrden(parseInt(printBtn.dataset.orden)); return; }
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

  // Escape cierra el modal activo más reciente
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const active = document.querySelector('.modal-bg.active');
    if (active) closeModal(active.id);
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

  // Click inventario: editar, stock +/-, QR
  document.getElementById('tbl-inventario').addEventListener('click', e => {
    if (e.target.dataset.action === 'open-modal-repuesto') { openModal('modal-repuesto'); return; }
    const qrBtn = e.target.closest('.btn-inv-qr');
    if (qrBtn) {
      const item = _invMap.get(parseInt(qrBtn.dataset.id));
      if (item) openQRModal(item);
      return;
    }
    const editBtn = e.target.closest('.btn-inv-edit');
    if (editBtn) {
      const row = editBtn.closest('tr[data-inv-id]');
      if (row) { const item = _invMap.get(parseInt(row.dataset.invId)); if (item) openInvEdit(item); }
      return;
    }
    const delBtn = e.target.closest('.btn-inv-del');
    if (delBtn) {
      const id     = parseInt(delBtn.dataset.id);
      const nombre = delBtn.dataset.nombre;
      showConfirm('Eliminar repuesto', `¿Eliminar "${nombre}" del inventario? Esta acción no se puede deshacer.`, async () => {
        try {
          const r = await apiFetch(`/reparo/api/inventario.php?id=${id}`, { method: 'DELETE' });
          const j = await r.json();
          if (j.ok) { toast('Repuesto eliminado.', 'ok'); _invMap.delete(id); _applySortInventario(); }
          else toast(j.msg || 'Error al eliminar.', 'err');
        } catch(err) { if (err.message !== 'session_expired') toast('Error de red.', 'err'); }
      });
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
      label: `${i.nombre}${i.marca_compatible ? ' · '+i.marca_compatible : ''}${i.modelo_compatible ? ' · '+i.modelo_compatible : ''} (stock: ${i.cantidad})`,
    }));
    _selRepNuevo?.populate(_repuestosCache);
  }).catch(() => {});

  loadServicios();

  // ── Configuración: tabs ──────────────────────────────────────
  document.querySelectorAll('.cfg-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.cfg-tab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.cfg-panel').forEach(p => p.classList.add('hidden'));
      btn.classList.add('active');
      const panel = document.getElementById('cfg-' + btn.dataset.tab);
      if (panel) panel.classList.remove('hidden');
      if (btn.dataset.tab === 'suscripcion') loadSuscripcion();
    });
  });

  // ── Sidebar: editar logo y nombre ───────────────────────────
  document.getElementById('btn-logo-edit')?.addEventListener('click', () => {
    document.getElementById('logo-display').classList.add('hidden');
    document.getElementById('logo-edit-panel').classList.remove('hidden');
    document.getElementById('inp-emp-nombre')?.focus();
  });
  document.getElementById('btn-logo-cancel')?.addEventListener('click', () => {
    document.getElementById('logo-display').classList.remove('hidden');
    document.getElementById('logo-edit-panel').classList.add('hidden');
    document.getElementById('inp-emp-logo').value        = '';
    document.getElementById('logo-file-lbl').textContent = 'Subir logo';
  });
  document.getElementById('inp-emp-logo')?.addEventListener('change', e => {
    const f = e.target.files[0];
    document.getElementById('logo-file-lbl').textContent = f ? f.name : 'Subir logo';
  });
  document.getElementById('btn-logo-save')?.addEventListener('click', async () => {
    const nombre = document.getElementById('inp-emp-nombre').value.trim();
    const file   = document.getElementById('inp-emp-logo').files[0];
    if (!nombre) { toast('El nombre no puede estar vacío.', 'err'); return; }
    const fd = new FormData();
    fd.append('nombre', nombre);
    if (file) fd.append('logo', file);
    try {
      const r = await apiFetch('/reparo/api/empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (!j.ok) { toast(j.msg, 'err'); return; }
      // Actualizar sidebar en vivo
      document.getElementById('sidebar-nombre').textContent = j.data.nombre;
      const iconWrap = document.getElementById('logo-icon-wrap');
      if (j.data.logo_path) {
        const img = document.createElement('img');
        img.className = 'logo-img'; img.id = 'logo-img'; img.alt = 'Logo';
        img.src = BASE_PATH + '/' + j.data.logo_path + '?t=' + Date.now();
        iconWrap.innerHTML = ''; iconWrap.appendChild(img);
      } else {
        iconWrap.innerHTML = `<span id="logo-letra">${esc(j.data.nombre.charAt(0).toUpperCase())}</span>`;
      }
      document.getElementById('inp-emp-nombre').value = j.data.nombre;
      document.getElementById('inp-emp-logo').value   = '';
      document.getElementById('logo-file-lbl').textContent = 'Subir logo';
      document.getElementById('logo-display').classList.remove('hidden');
      document.getElementById('logo-edit-panel').classList.add('hidden');
      toast('✔ Datos actualizados.', 'ok');
    } catch(e) { if (e.message !== 'session_expired') toast('Error de red.', 'err'); }
  });

  // ── Guardar datos de contacto empresa ───────────────────────
  document.getElementById('btn-cfg-empresa')?.addEventListener('click', async () => {
    const payload = {
      direccion: document.getElementById('cfg-dir').value.trim(),
      telefono:  document.getElementById('cfg-tel').value.trim(),
      correo:    document.getElementById('cfg-mail').value.trim(),
      comuna:    document.getElementById('cfg-comuna').value.trim(),
      region:    document.getElementById('cfg-region').value.trim(),
    };
    try {
      const r = await apiFetch('/reparo/api/empresa.php', {
        method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
      });
      const j = await r.json();
      j.ok ? toast('✔ Datos guardados.', 'ok') : toast(j.msg, 'err');
    } catch(e) { if (e.message !== 'session_expired') toast('Error de red.', 'err'); }
  });

  // ── Cambiar propia contraseña ────────────────────────────────
  document.getElementById('btn-cfg-pass')?.addEventListener('click', async () => {
    const actual   = document.getElementById('cfg-pass-actual').value;
    const nueva    = document.getElementById('cfg-pass-nueva').value;
    const confirm  = document.getElementById('cfg-pass-confirm').value;
    if (!actual)          { toast('Ingresa tu contraseña actual.', 'err'); return; }
    if (nueva.length < 6) { toast('La nueva contraseña debe tener al menos 6 caracteres.', 'err'); return; }
    if (nueva !== confirm) { toast('Las contraseñas no coinciden.', 'err'); return; }
    try {
      const r = await apiFetch('/reparo/api/usuarios.php', {
        method: 'PUT', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id_usuario: CURRENT_USER.uid, password_actual: actual, password: nueva })
      });
      const j = await r.json();
      if (j.ok) {
        toast('✔ Contraseña actualizada.', 'ok');
        document.getElementById('cfg-pass-actual').value  = '';
        document.getElementById('cfg-pass-nueva').value   = '';
        document.getElementById('cfg-pass-confirm').value = '';
      } else { toast(j.msg, 'err'); }
    } catch(e) { if (e.message !== 'session_expired') toast('Error de red.', 'err'); }
  });

  // ── Event delegation tabla Usuarios ─────────────────────────
  document.getElementById('tbl-usuarios')?.addEventListener('click', async e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const buid = parseInt(btn.dataset.uid);

    if (btn.dataset.action === 'toggle-cargo') {
      const nuevoCargo = btn.dataset.cargo;
      try {
        const r = await apiFetch('/reparo/api/usuarios.php', {
          method: 'PUT', headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ id_usuario: buid, cargo: nuevoCargo })
        });
        const j = await r.json();
        j.ok ? (toast(`✔ ${j.data.msg}`, 'ok'), loadUsuarios()) : toast(j.msg, 'err');
      } catch(e) { if (e.message !== 'session_expired') toast('Error de red.', 'err'); }
    }

    if (btn.dataset.action === 'reset-pass') {
      document.getElementById('reset-pass-uid').value        = buid;
      document.getElementById('reset-pass-nombre').textContent = btn.dataset.nombre;
      document.getElementById('reset-pass-nueva').value      = '';
      document.getElementById('reset-pass-confirm').value    = '';
      openModal('modal-reset-pass');
    }
  });

  // ── Retorno desde pasarela de pago ──────────────────────────
  const urlParams = new URLSearchParams(window.location.search);
  const pagoParam = urlParams.get('pago');
  if (pagoParam === 'ok' || pagoParam === 'suscripcion') {
    history.replaceState({}, '', BASE_PATH + '/app.php');
    const msg = pagoParam === 'suscripcion'
      ? '✔ Suscripción activada. El pago se confirmará en breve.'
      : '✔ Pago exitoso. Suscripción actualizada.';
    toast(msg, 'ok');
    const cfgLink = document.querySelector('[data-view="config"]');
    if (cfgLink) switchView('config', cfgLink);
    setTimeout(() => document.querySelector('[data-tab="suscripcion"]')?.click(), 150);
  }

  // ── Seleccionar plan y pagar con Mercado Pago ────────────────
  document.querySelector('.plan-grid')?.addEventListener('click', async e => {
    const btn = e.target.closest('.btn-plan');
    if (!btn) return;
    const plan    = btn.dataset.plan;
    const txtSpan = btn.querySelector('span:last-child') ?? btn;
    const allBtns = document.querySelectorAll('.btn-plan');

    allBtns.forEach(b => { b.disabled = true; });
    const oldText = btn.textContent.trim();
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin 1s linear infinite;font-size:16px">sync</span><span>Procesando...</span>';

    try {
      const r = await apiFetch('/reparo/api/pago.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ metodo: 'mercadopago', plan }),
      });
      const j = await r.json();
      if (j.ok) { window.location.href = j.data.url; return; }
      toast(j.msg || 'Error al iniciar el pago', 'err');
    } catch(ex) { if (ex.message !== 'session_expired') toast('Error de conexión', 'err'); }

    allBtns.forEach(b => { b.disabled = false; });
    btn.innerHTML = '<span class="material-icons-round">shopping_cart</span><span>Suscribirse</span>';
  });

  // ── Toggle notificación de vencimiento ──────────────────────
  document.getElementById('subs-notif-chk')?.addEventListener('change', async e => {
    const checked = e.target.checked;
    try {
      const r = await apiFetch('/reparo/api/suscripcion.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notif_vencimiento: checked }),
      });
      const j = await r.json();
      if (j.ok) toast(checked ? 'Notificaciones activadas' : 'Notificaciones desactivadas', 'ok');
      else { toast(j.msg || 'Error', 'err'); e.target.checked = !checked; }
    } catch(ex) { if (ex.message !== 'session_expired') { toast('Error de red', 'err'); e.target.checked = !checked; } }
  });

  // ── Resetear contraseña de otro usuario ─────────────────────
  document.getElementById('btn-reset-pass-save')?.addEventListener('click', async () => {
    const ruid    = parseInt(document.getElementById('reset-pass-uid').value);
    const nueva   = document.getElementById('reset-pass-nueva').value;
    const confirm = document.getElementById('reset-pass-confirm').value;
    if (nueva.length < 6) { toast('Mínimo 6 caracteres.', 'err'); return; }
    if (nueva !== confirm) { toast('Las contraseñas no coinciden.', 'err'); return; }
    try {
      const r = await apiFetch('/reparo/api/usuarios.php', {
        method: 'PUT', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id_usuario: ruid, password: nueva })
      });
      const j = await r.json();
      if (j.ok) {
        toast('✔ Contraseña actualizada.', 'ok');
        closeModal('modal-reset-pass');
        document.getElementById('reset-pass-nueva').value   = '';
        document.getElementById('reset-pass-confirm').value = '';
      } else { toast(j.msg, 'err'); }
    } catch(e) { if (e.message !== 'session_expired') toast('Error de red.', 'err'); }
  });
});

// ── ESTADÍSTICAS ────────────────────────────────────────────────────────────
(function () {
  var chartIngresos = null;
  var chartFlujo    = null;
  var chartMarcas   = null;
  var chartModelos  = null;
  var estIniciado   = false;

  var COLORS = {
    blue:   '#2f81f7',
    green:  '#3fb950',
    purple: '#bc8cff',
    orange: '#f78166',
    yellow: '#fbbf24',
    gray:   '#8b949e',
  };

  function pesos(n) {
    return '$' + Math.round(n).toLocaleString('es-CL');
  }

  function getRango() {
    var desde = document.getElementById('est-desde').value;
    var hasta = document.getElementById('est-hasta').value;
    return { desde: desde, hasta: hasta };
  }

  function setRangoAtajo(rango) {
    var hoy   = new Date();
    var hasta = hoy.toISOString().slice(0, 10);
    var desde;
    if (rango === 'mes') {
      desde = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-01';
    } else if (rango === 'trim') {
      var d = new Date(hoy); d.setMonth(d.getMonth() - 2); d.setDate(1);
      desde = d.toISOString().slice(0, 10);
    } else if (rango === 'anio') {
      desde = hoy.getFullYear() + '-01-01';
    } else {
      desde = '2020-01-01';
    }
    document.getElementById('est-desde').value = desde;
    document.getElementById('est-hasta').value = hasta;
  }

  function destroyChart(c) { if (c) { try { c.destroy(); } catch(e) {} } return null; }

  function renderIngresos(data) {
    chartIngresos = destroyChart(chartIngresos);
    var labels = data.map(function(r) {
      var p = r.mes.split('-'); return ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][parseInt(p[1])-1] + ' ' + p[0].slice(2);
    });
    var vals = data.map(function(r) { return parseInt(r.ingresos); });
    var ctx = document.getElementById('chart-ingresos').getContext('2d');
    chartIngresos = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{ label: 'Ingresos', data: vals, backgroundColor: COLORS.blue + 'cc', borderColor: COLORS.blue, borderWidth: 1, borderRadius: 4 }]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return ' ' + pesos(c.raw); } } } },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8b949e', font: { size: 11 } } },
          y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8b949e', font: { size: 11 }, callback: function(v) { return pesos(v); } } }
        }
      }
    });
  }

  function renderFlujo(data) {
    chartFlujo = destroyChart(chartFlujo);
    var labels = data.map(function(r) {
      var p = r.mes.split('-'); return ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][parseInt(p[1])-1] + ' ' + p[0].slice(2);
    });
    var ctx = document.getElementById('chart-flujo').getContext('2d');
    chartFlujo = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          { label: 'Ingresadas', data: data.map(function(r){ return parseInt(r.ingresadas); }), backgroundColor: COLORS.blue + '99', borderColor: COLORS.blue, borderWidth: 1, borderRadius: 3 },
          { label: 'Cerradas',   data: data.map(function(r){ return parseInt(r.cerradas); }),   backgroundColor: COLORS.green + '99', borderColor: COLORS.green, borderWidth: 1, borderRadius: 3 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { labels: { color: '#8b949e', font: { size: 11 } } } },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8b949e', font: { size: 11 } } },
          y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8b949e', font: { size: 11 } }, beginAtZero: true }
        }
      }
    });
  }

  function renderMarcas(data) {
    chartMarcas = destroyChart(chartMarcas);
    var ctx = document.getElementById('chart-marcas').getContext('2d');
    var palette = [COLORS.blue, COLORS.green, COLORS.purple, COLORS.orange, COLORS.yellow, COLORS.gray, '#60a5fa', '#34d399'];
    chartMarcas = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(function(r){ return r.marca; }),
        datasets: [{ data: data.map(function(r){ return parseInt(r.total); }), backgroundColor: data.map(function(_, i){ return palette[i % palette.length] + 'cc'; }), borderRadius: 4 }]
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8b949e', font: { size: 11 } }, beginAtZero: true },
          y: { grid: { display: false }, ticks: { color: '#e6edf3', font: { size: 12 } } }
        }
      }
    });
  }

  function renderFallas(data) {
    var el = document.getElementById('est-fallas-list');
    if (!el) return;
    if (!data.length) { el.innerHTML = '<p style="color:var(--txt3);font-size:13px;padding:12px 0;">Sin datos en el período.</p>'; return; }
    var max = parseInt(data[0].total) || 1;
    el.innerHTML = data.map(function(r) {
      var pct = Math.round(parseInt(r.total) / max * 100);
      return '<div class="est-falla-item">' +
        '<div class="est-falla-txt"><span class="est-falla-lbl">' + esc(r.falla) + '</span><span class="est-falla-cnt">' + r.total + '</span></div>' +
        '<div class="est-falla-bar"><div class="est-falla-fill" style="width:' + pct + '%"></div></div>' +
      '</div>';
    }).join('');
  }

  function renderModelos(data) {
    chartModelos = destroyChart(chartModelos);
    var ctx = document.getElementById('chart-modelos').getContext('2d');
    var palette = [COLORS.blue, COLORS.green, COLORS.purple, COLORS.orange, COLORS.yellow, COLORS.gray, '#60a5fa', '#34d399'];
    chartModelos = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(function(r){ return r.modelo; }),
        datasets: [{ data: data.map(function(r){ return parseInt(r.total); }), backgroundColor: data.map(function(_, i){ return palette[i % palette.length] + 'cc'; }), borderRadius: 4 }]
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8b949e', font: { size: 11 }, stepSize: 1, callback: function(v) { return Number.isInteger(v) ? v : ''; } }, beginAtZero: true },
          y: { grid: { display: false }, ticks: { color: '#e6edf3', font: { size: 11 } } }
        }
      }
    });
  }

  async function cargarEstadisticas() {
    var r = getRango();
    try {
      var j = await (await apiFetch('/reparo/api/estadisticas.php?desde=' + r.desde + '&hasta=' + r.hasta)).json();
      if (!j.ok) return;
      var d = j.data;

      // KPIs
      document.getElementById('est-k-ordenes').textContent  = d.kpis.total_ordenes;
      document.getElementById('est-k-ingresos').textContent = pesos(d.kpis.ingresos_totales);
      document.getElementById('est-k-ticket').textContent   = pesos(d.kpis.ticket_promedio);
      document.getElementById('est-k-cerradas').textContent = d.kpis.ordenes_cerradas;
      document.getElementById('est-k-dias').textContent     = parseFloat(d.kpis.dias_promedio).toFixed(1) + ' días';

      renderIngresos(d.por_mes);
      renderFlujo(d.flujo_mes);
      renderMarcas(d.marcas);
      renderFallas(d.fallas);
      renderModelos(d.modelos);
    } catch(e) {
      if (e.message !== 'session_expired') toast('Error al cargar estadísticas.', 'err');
    }
  }

  window.initEstadisticas = function() {
    if (!estIniciado) {
      estIniciado = true;

      // Atajos de fecha
      document.querySelectorAll('.est-atajo').forEach(function(btn) {
        btn.addEventListener('click', function() {
          document.querySelectorAll('.est-atajo').forEach(function(b) { b.classList.remove('active'); });
          btn.classList.add('active');
          setRangoAtajo(btn.dataset.rango);
          cargarEstadisticas();
        });
      });

      // Aplicar rango custom
      document.getElementById('est-btn-aplicar').addEventListener('click', function() {
        document.querySelectorAll('.est-atajo').forEach(function(b) { b.classList.remove('active'); });
        cargarEstadisticas();
      });
    }
    // Siempre cargar al entrar a la vista
    setRangoAtajo('mes');
    document.querySelector('.est-atajo[data-rango="mes"]').classList.add('active');
    cargarEstadisticas();
  };

  // ── Modal nuevo técnico ────────────────────────────────────────────────────
  function openModalTecnico() {
    document.getElementById('tecnico-nombre').value = '';
    document.getElementById('tecnico-user').value   = '';
    document.getElementById('tecnico-pass').value   = '';
    document.getElementById('tecnico-pass2').value  = '';
    document.getElementById('modal-nuevo-tecnico').classList.add('active');
    document.getElementById('tecnico-nombre').focus();
  }
  function closeModalTecnico() {
    document.getElementById('modal-nuevo-tecnico').classList.remove('active');
  }

  document.getElementById('btn-nuevo-tecnico')?.addEventListener('click', openModalTecnico);
  document.getElementById('modal-tecnico-close')?.addEventListener('click', closeModalTecnico);
  document.getElementById('modal-tecnico-cancel')?.addEventListener('click', closeModalTecnico);

  document.getElementById('btn-tecnico-guardar')?.addEventListener('click', async () => {
    const nombre = document.getElementById('tecnico-nombre').value.trim();
    const user   = document.getElementById('tecnico-user').value.trim();
    const pass   = document.getElementById('tecnico-pass').value;
    const pass2  = document.getElementById('tecnico-pass2').value;

    if (!nombre || !user || !pass) { toast('Completa todos los campos.', 'err'); return; }
    if (pass !== pass2)            { toast('Las contraseñas no coinciden.', 'err'); return; }

    const btn = document.getElementById('btn-tecnico-guardar');
    btn.disabled = true;
    try {
      const r = await apiFetch('/reparo/api/usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre, user, password: pass })
      });
      const j = await r.json();
      if (j.ok) {
        toast(j.data.msg, 'ok');
        closeModalTecnico();
        loadUsuarios();
      } else {
        toast(j.msg, 'err');
      }
    } catch(e) {
      if (e.message !== 'session_expired') toast('Error de red.', 'err');
    } finally {
      btn.disabled = false;
    }
  });

  // ── Resaltar fila ─────────────────────────────────────────────────────────
}());

// ── QR inventario: funciones a nivel de módulo ────────────────────────────
function _qrUrl(id) {
  return location.origin + BASE_PATH + '/app.php?inv=' + id;
}

function openQRModal(item) {
  document.getElementById('qr-item-nombre').textContent = item.nombre;
  const meta = [item.marca_compatible, item.modelo_compatible].filter(Boolean).join(' · ');
  document.getElementById('qr-item-meta').textContent = meta || 'Sin marca/modelo registrado';

  const wrap = document.getElementById('qr-canvas-wrap');
  wrap.innerHTML = '';
  new QRCode(wrap, {
    text:         _qrUrl(item.id_repuesto),
    width:        200,
    height:       200,
    colorDark:    '#000000',
    colorLight:   '#ffffff',
    correctLevel: QRCode.CorrectLevel.M,
  });

  document.getElementById('modal-qr').classList.add('active');
  document.getElementById('btn-qr-print').onclick = () => _printQR(item);
}

function _printQR(item) {
  let label = document.getElementById('print-qr-label');
  if (!label) {
    label = document.createElement('div');
    label.id = 'print-qr-label';
    document.body.appendChild(label);
  }
  label.innerHTML = '';

  const qrDiv = document.createElement('div');
  new QRCode(qrDiv, {
    text:         _qrUrl(item.id_repuesto),
    width:        160,
    height:       160,
    colorDark:    '#000000',
    colorLight:   '#ffffff',
    correctLevel: QRCode.CorrectLevel.M,
  });
  label.appendChild(qrDiv);

  const pNombre = document.createElement('p');
  pNombre.className = 'pql-nombre';
  pNombre.textContent = item.nombre;
  label.appendChild(pNombre);

  const meta = [item.marca_compatible, item.modelo_compatible].filter(Boolean).join(' · ');
  if (meta) {
    const pMeta = document.createElement('p');
    pMeta.className = 'pql-meta';
    pMeta.textContent = meta;
    label.appendChild(pMeta);
  }

  const pCod = document.createElement('p');
  pCod.className = 'pql-codigo';
  pCod.textContent = 'ID: ' + item.id_repuesto;
  label.appendChild(pCod);

  setTimeout(() => window.print(), 150);
}

// ── Scanner QR: funciones a nivel de módulo ───────────────────────────────
var _scannerStream   = null;
var _scannerInterval = null;

function _stopScanner() {
  clearInterval(_scannerInterval);
  _scannerInterval = null;
  if (_scannerStream) { _scannerStream.getTracks().forEach(function(t) { t.stop(); }); _scannerStream = null; }
  document.getElementById('modal-scanner').classList.remove('active');
  document.getElementById('scanner-result').classList.add('hidden');
  document.getElementById('scanner-wrap').classList.remove('hidden');
  document.getElementById('scanner-fallback').classList.add('hidden');
}

function _highlightInvItem(id) {
  _stopScanner();
  var item = _invMap.get(id);
  if (!item) { toast('Repuesto no encontrado en inventario.', 'err'); return; }
  switchView('inventario');
  setTimeout(function() { openInvEdit(item); }, 300);
}

function _handleScannedUrl(text) {
  try {
    var url = new URL(text);
    var inv = url.searchParams.get('inv');
    if (inv) { _highlightInvItem(parseInt(inv)); return; }
  } catch (_) {}
  var num = parseInt(text.replace(/\D/g, ''));
  if (num && _invMap.has(num)) { _highlightInvItem(num); return; }
  toast('QR no reconocido como repuesto.', 'err');
}

async function openScanner() {
  document.getElementById('modal-scanner').classList.add('active');
  document.getElementById('scanner-result').classList.add('hidden');
  document.getElementById('scanner-fallback').classList.add('hidden');
  document.getElementById('scanner-wrap').classList.remove('hidden');

  try {
    _scannerStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    var video = document.getElementById('scanner-video');
    video.srcObject = _scannerStream;
    var _scanCanvas = document.createElement('canvas');
    var _scanCtx    = _scanCanvas.getContext('2d');
    _scannerInterval = setInterval(function() {
      if (video.readyState < 2 || !video.videoWidth) return;
      _scanCanvas.width  = video.videoWidth;
      _scanCanvas.height = video.videoHeight;
      _scanCtx.drawImage(video, 0, 0);
      var imgData = _scanCtx.getImageData(0, 0, _scanCanvas.width, _scanCanvas.height);
      var code = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'dontInvert' });
      if (code) {
        clearInterval(_scannerInterval);
        _scannerInterval = null;
        document.getElementById('scanner-wrap').classList.add('hidden');
        document.getElementById('scanner-result').classList.remove('hidden');
        document.getElementById('scanner-ok-text').textContent = 'Código detectado. Buscando repuesto...';
        _handleScannedUrl(code.data);
      }
    }, 300);
  } catch(err) {
    document.getElementById('scanner-wrap').classList.add('hidden');
    document.getElementById('scanner-fallback').classList.remove('hidden');
  }
}

// ── Listeners QR + scanner (DOM ya listo — script al final del body) ──────
document.getElementById('modal-qr-close')?.addEventListener('click', function() {
  document.getElementById('modal-qr').classList.remove('active');
});
document.getElementById('modal-qr-cancel')?.addEventListener('click', function() {
  document.getElementById('modal-qr').classList.remove('active');
});
var _esMobil = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
if (!_esMobil) {
  var _btnScanQr = document.getElementById('btn-scan-qr');
  if (_btnScanQr) _btnScanQr.style.display = 'none';
} else {
  document.getElementById('btn-scan-qr')?.addEventListener('click', openScanner);
}
document.getElementById('modal-scanner-close')?.addEventListener('click', _stopScanner);

// ── Importar inventario CSV / XLSX ────────────────────────────────────────
(function() {
  var _parsedRows  = [];
  var _sourceIsXlsx = false;

  function _csvToRows(text) {
    var lines = text.trim().split(/\r\n|\r|\n/);
    if (!lines.length) return [];
    var delim = (lines[0].split(';').length >= lines[0].split(',').length) ? ';' : ',';
    return lines.map(function(line) {
      var result = [], cur = '', inQ = false;
      for (var i = 0; i < line.length; i++) {
        var c = line[i];
        if (c === '"') { inQ = !inQ; }
        else if (c === delim && !inQ) { result.push(cur.trim()); cur = ''; }
        else { cur += c; }
      }
      result.push(cur.trim());
      return result;
    });
  }

  function _renderPreview(rows) {
    var header = rows[0] || [];
    var body   = rows.slice(1, 6);
    var tbl    = document.getElementById('imp-preview-tbl');
    var html   = '<thead><tr>' + header.map(function(h){ return '<th>' + esc(h) + '</th>'; }).join('') + '</tr></thead>';
    html += '<tbody>' + body.map(function(r){
      return '<tr>' + r.map(function(c){ return '<td>' + esc(c) + '</td>'; }).join('') + '</tr>';
    }).join('') + '</tbody>';
    tbl.innerHTML = html;
    var total = rows.length - 1;
    document.getElementById('imp-preview-title').textContent =
      total + ' fila' + (total !== 1 ? 's' : '') + ' encontrada' + (total !== 1 ? 's' : '') +
      (total > 5 ? ' (mostrando primeras 5)' : '');
    document.getElementById('imp-preview-wrap').classList.remove('hidden');
    document.getElementById('imp-result').classList.add('hidden');
    document.getElementById('btn-importar-confirm').disabled = total === 0;
  }

  function _handleFile(file) {
    if (!file) return;
    var ext = file.name.split('.').pop().toLowerCase();
    if (ext === 'xlsx' || ext === 'xls') {
      _sourceIsXlsx = true;
      var reader = new FileReader();
      reader.onload = function(e) {
        var wb   = XLSX.read(e.target.result, { type: 'array' });
        var ws   = wb.Sheets[wb.SheetNames[0]];
        var rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
        _parsedRows = rows;
        _renderPreview(rows);
      };
      reader.readAsArrayBuffer(file);
    } else {
      _sourceIsXlsx = false;
      var reader = new FileReader();
      reader.onload = function(e) {
        var rows = _csvToRows(e.target.result);
        _parsedRows = rows;
        _renderPreview(rows);
      };
      reader.readAsText(file, 'UTF-8');
    }
  }

  // Dropzone drag & drop
  var dz = document.getElementById('imp-dropzone');
  if (dz) {
    dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', function() { dz.classList.remove('drag-over'); });
    dz.addEventListener('drop', function(e) {
      e.preventDefault(); dz.classList.remove('drag-over');
      _handleFile(e.dataTransfer.files[0]);
    });
    dz.addEventListener('click', function() { document.getElementById('imp-file-input').click(); });
  }

  var fileInput = document.getElementById('imp-file-input');
  if (fileInput) {
    fileInput.addEventListener('change', function() { _handleFile(this.files[0]); });
  }

  // Abrir modal
  document.addEventListener('openImportModal', function() {
    _parsedRows = [];
    _sourceIsXlsx = false;
    document.getElementById('imp-preview-wrap').classList.add('hidden');
    document.getElementById('imp-result').classList.add('hidden');
    document.getElementById('btn-importar-confirm').disabled = true;
    if (fileInput) fileInput.value = '';
    document.getElementById('modal-importar-inv').classList.add('active');
  });

  // Cerrar modal
  function _closeImport() { document.getElementById('modal-importar-inv').classList.remove('active'); }
  document.getElementById('modal-importar-close')?.addEventListener('click', _closeImport);
  document.getElementById('btn-importar-cancel')?.addEventListener('click', _closeImport);

  // Descargar plantilla
  document.getElementById('btn-descargar-plantilla')?.addEventListener('click', function(e) {
    e.preventDefault();
    var csv = '﻿' +
              'nombre;marca_compatible;modelo_compatible;precio_venta;cantidad\n' +
              'Batería iPhone 14;Apple;iPhone 14;28000;5\n' +
              'Pantalla Samsung A54;Samsung;Galaxy A54;35000;3\n';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'plantilla_inventario.csv';
    a.click();
  });

  // Confirmar importación
  document.getElementById('btn-importar-confirm')?.addEventListener('click', async function() {
    if (!fileInput || !fileInput.files[0]) return;
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Importando...';

    var fileToSend;
    if (_sourceIsXlsx) {
      var csvContent = _parsedRows.map(function(row) {
        return row.map(function(cell) {
          var s = String(cell == null ? '' : cell);
          if (s.includes(';') || s.includes('"') || s.includes('\n')) s = '"' + s.replace(/"/g, '""') + '"';
          return s;
        }).join(';');
      }).join('\n');
      fileToSend = new File([csvContent], 'inventario.csv', { type: 'text/csv;charset=utf-8;' });
    } else {
      fileToSend = fileInput.files[0];
    }
    var formData = new FormData();
    formData.append('archivo', fileToSend);

    try {
      var res = await apiFetch('/reparo/api/importar_inventario.php', { method: 'POST', body: formData });
      var j = await res.json();
      var el = document.getElementById('imp-result');
      el.classList.remove('hidden', 'ok', 'err');
      if (j.ok) {
        el.classList.add('ok');
        el.innerHTML = '<strong>' + j.data.insertados + '</strong> repuesto' + (j.data.insertados !== 1 ? 's' : '') + ' importado' + (j.data.insertados !== 1 ? 's' : '') + ' correctamente.' +
          (j.data.omitidos ? ' <span style="color:var(--txt2)">(' + j.data.omitidos + ' filas omitidas)</span>' : '') +
          (j.data.errores?.length ? '<br><small>' + j.data.errores.join('<br>') + '</small>' : '');
        loadInventario();
        // Refrescar cache de repuestos para el modal de nuevo servicio
        apiFetch('/reparo/api/inventario.php').then(r => r.json()).then(ji => {
          _repuestosCache = (ji.data || []).map(i => ({
            id:    i.id_repuesto,
            value: String(i.id_repuesto),
            label: `${i.nombre}${i.marca_compatible ? ' · '+i.marca_compatible : ''}${i.modelo_compatible ? ' · '+i.modelo_compatible : ''} (stock: ${i.cantidad})`,
          }));
          _selRepNuevo?.populate(_repuestosCache);
        }).catch(() => {});
      } else {
        el.classList.add('err');
        el.textContent = j.msg || 'Error al importar.';
      }
    } catch(err) {
      var el = document.getElementById('imp-result');
      el.classList.remove('hidden'); el.classList.add('err');
      el.textContent = 'Error de conexión.';
    }

    btn.disabled = false;
    btn.innerHTML = '<span class="material-icons-round">cloud_upload</span> Importar';
  });
}());

// ── Detectar ?inv=ID al cargar (QR escaneado externamente) ────────────────
(function() {
  var invParam = new URLSearchParams(location.search).get('inv');
  if (!invParam) return;
  var invId = parseInt(invParam);
  if (!invId) return;
  function _waitAndHighlight() {
    if (_invMap.has(invId)) { _highlightInvItem(invId); }
    else { setTimeout(_waitAndHighlight, 400); }
  }
  switchView('inventario');
  loadInventario().then(_waitAndHighlight);
}());

// Hamburguesa móvil
(function() {
  var btn     = document.getElementById('btn-hamburger');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if (!btn || !sidebar || !overlay) return;
  function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('active'); }
  function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); }
  btn.addEventListener('click', function() {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay.addEventListener('click', closeSidebar);
  document.querySelectorAll('.nav-link').forEach(function(l) {
    l.addEventListener('click', closeSidebar);
  });
}());
