'use strict';
// ─── Compresión de imagen vía Canvas ─────────────────────────────────────────
async function compressImage(file, maxW = 1280, quality = 0.82) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const blobUrl = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(blobUrl);
      let { width, height } = img;
      if (width > maxW) { height = Math.round(height * maxW / width); width = maxW; }
      const canvas = document.createElement('canvas');
      canvas.width = width; canvas.height = height;
      canvas.getContext('2d').drawImage(img, 0, 0, width, height);
      canvas.toBlob(blob => {
        if (!blob) { reject(new Error('Compresión fallida')); return; }
        resolve(new File([blob], 'foto.jpg', { type: 'image/jpeg' }));
      }, 'image/jpeg', quality);
    };
    img.onerror = () => { URL.revokeObjectURL(blobUrl); reject(new Error('No se pudo cargar la imagen')); };
    img.src = blobUrl;
  });
}

// ─── Modal NUEVO: foto de ingreso (1 foto, pendiente hasta guardar) ───────────
let _nuevoFotoFile = null;
let _nuevoFotoPreviewUrl = null;

function _renderNuevoFotoThumb(file) {
  const thumbsEl = document.getElementById('nuevo-foto-thumbs');
  const drop     = document.getElementById('nuevo-foto-drop');
  const counter  = document.getElementById('nuevo-foto-counter');
  if (!thumbsEl) return;

  if (_nuevoFotoPreviewUrl) URL.revokeObjectURL(_nuevoFotoPreviewUrl);
  _nuevoFotoPreviewUrl = URL.createObjectURL(file);

  thumbsEl.innerHTML = `
    <div class="foto-thumb">
      <img src="${_nuevoFotoPreviewUrl}" alt="Foto de ingreso">
      <div class="foto-thumb-lbl">Ingreso</div>
      <button type="button" class="foto-thumb-del" id="nuevo-foto-del" title="Quitar foto">
        <span class="material-icons-round">close</span>
      </button>
    </div>`;
  thumbsEl.classList.remove('hidden');
  drop.classList.add('hidden');
  if (counter) { counter.textContent = '1 / 1'; counter.classList.add('counter-full'); }

  document.getElementById('nuevo-foto-del')?.addEventListener('click', () => {
    _nuevoFotoFile = null;
    if (_nuevoFotoPreviewUrl) { URL.revokeObjectURL(_nuevoFotoPreviewUrl); _nuevoFotoPreviewUrl = null; }
    thumbsEl.innerHTML = '';
    thumbsEl.classList.add('hidden');
    drop.classList.remove('hidden');
    if (counter) { counter.textContent = '0 / 1'; counter.classList.remove('counter-full'); }
  });
}

function initNuevoFotos() {
  const drop  = document.getElementById('nuevo-foto-drop');
  const input = document.getElementById('nuevo-foto-input');
  if (!drop || !input) return;

  const handleFile = async (file) => {
    if (!file || !file.type.startsWith('image/')) { toast('Solo se permiten imágenes.', 'err'); return; }
    try {
      const compressed = await compressImage(file);
      _nuevoFotoFile = compressed;
      _renderNuevoFotoThumb(compressed);
    } catch(e) {
      toast('Error al procesar la imagen.', 'err');
    }
  };

  drop.addEventListener('click', () => input.click());
  drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', () => { if (input.files[0]) handleFile(input.files[0]); input.value = ''; });

  drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('drag-over'); });
  drop.addEventListener('dragleave', ()  => drop.classList.remove('drag-over'));
  drop.addEventListener('drop', e => {
    e.preventDefault(); drop.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
  });
}

function resetNuevoFotos() {
  _nuevoFotoFile = null;
  if (_nuevoFotoPreviewUrl) { URL.revokeObjectURL(_nuevoFotoPreviewUrl); _nuevoFotoPreviewUrl = null; }
  const thumbsEl = document.getElementById('nuevo-foto-thumbs');
  const drop     = document.getElementById('nuevo-foto-drop');
  const counter  = document.getElementById('nuevo-foto-counter');
  if (thumbsEl) { thumbsEl.innerHTML = ''; thumbsEl.classList.add('hidden'); }
  if (drop)     drop.classList.remove('hidden');
  if (counter)  { counter.textContent = '0 / 1'; counter.classList.remove('counter-full'); }
}

async function uploadNuevoFoto(id_reparacion) {
  if (!_nuevoFotoFile) return;
  try {
    const fd = new FormData();
    fd.append('imagen', _nuevoFotoFile);
    fd.append('id_reparacion', id_reparacion);
    fd.append('etiqueta', 'Ingreso');
    const r = await apiFetch('/reparo/api/upload_rep_img.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (!j.ok) console.warn('Error subiendo foto de ingreso:', j.msg);
  } catch(e) {
    console.warn('Error subiendo foto:', e);
  }
}

// ─── Modal DETALLE: hasta 3 fotos, sube al guardar ───────────────────────────
let _detFotos        = [];   // fotos ya subidas al servidor
let _pendingDetFotos = [];   // { file, previewUrl } esperando Guardar
let _detRepId        = 0;

async function loadDetalleFotos(idReparacion) {
  _detRepId  = idReparacion;
  _detFotos  = [];
  _pendingDetFotos.forEach(p => URL.revokeObjectURL(p.previewUrl));
  _pendingDetFotos = [];
  _renderDetalleFotos();
  try {
    const r = await apiFetch(`/reparo/api/rep_fotos.php?id=${idReparacion}`);
    const j = await r.json();
    if (j.ok) { _detFotos = j.data || []; _renderDetalleFotos(); }
  } catch(e) { /* silencioso */ }
}

function _renderDetalleFotos() {
  const grid    = document.getElementById('det-foto-thumbs');
  const counter = document.getElementById('det-foto-counter');
  if (!grid) return;

  const TOTAL = 3;
  const count = _detFotos.length + _pendingDetFotos.length;
  if (counter) {
    counter.textContent = `${count} / ${TOTAL}`;
    counter.className   = 'foto-counter' + (count >= TOTAL ? ' counter-full' : '');
  }

  let html = '';
  for (const f of _detFotos) {
    html += `<div class="foto-thumb">
      <img src="${esc(f.url)}" alt="${esc(f.etiqueta)}" loading="lazy">
      <div class="foto-thumb-lbl">${esc(f.etiqueta)}</div>
      <button type="button" class="foto-thumb-del" data-foto-del="${f.id}" title="Eliminar foto">
        <span class="material-icons-round">close</span>
      </button>
    </div>`;
  }
  for (let i = 0; i < _pendingDetFotos.length; i++) {
    html += `<div class="foto-thumb foto-thumb-pending">
      <img src="${_pendingDetFotos[i].previewUrl}" alt="Foto pendiente">
      <div class="foto-thumb-lbl">Reparación</div>
      <button type="button" class="foto-thumb-del" data-pending-del="${i}" title="Quitar foto">
        <span class="material-icons-round">close</span>
      </button>
    </div>`;
  }
  for (let i = count; i < TOTAL; i++) {
    html += `<button type="button" class="foto-thumb-add" data-foto-add="1" title="Agregar foto">
      <span class="material-icons-round">add_a_photo</span>
    </button>`;
  }
  grid.innerHTML = html;

  grid.querySelectorAll('[data-foto-del]').forEach(btn => {
    btn.addEventListener('click', () => _deleteFoto(parseInt(btn.dataset.fotoDel)));
  });
  grid.querySelectorAll('[data-pending-del]').forEach(btn => {
    btn.addEventListener('click', () => {
      const idx = parseInt(btn.dataset.pendingDel);
      URL.revokeObjectURL(_pendingDetFotos[idx].previewUrl);
      _pendingDetFotos.splice(idx, 1);
      _renderDetalleFotos();
    });
  });
  grid.querySelectorAll('[data-foto-add]').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById('det-foto-input')?.click());
  });
}

async function _deleteFoto(fotoId) {
  try {
    const r = await apiFetch('/reparo/api/rep_fotos.php', {
      method:  'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id: fotoId }),
    });
    const j = await r.json();
    if (j.ok) {
      _detFotos = _detFotos.filter(f => f.id !== fotoId);
      _renderDetalleFotos();
      if (_detRepId) loadTimeline(_detRepId);
    } else {
      toast(j.msg || 'Error al eliminar foto.', 'err');
    }
  } catch(e) {
    toast('Error al eliminar foto.', 'err');
  }
}

function initDetalleFotoInput() {
  const input = document.getElementById('det-foto-input');
  if (!input) return;
  input.addEventListener('change', async () => {
    const files = Array.from(input.files || []);
    input.value = '';
    if (!files.length) return;
    const slots = 3 - (_detFotos.length + _pendingDetFotos.length);
    if (slots <= 0) { toast('Ya tienes el máximo de 3 fotos.', 'err'); return; }
    for (const file of files.slice(0, slots)) {
      if (!file.type.startsWith('image/')) continue;
      try {
        const compressed = await compressImage(file);
        _pendingDetFotos.push({ file: compressed, previewUrl: URL.createObjectURL(compressed) });
      } catch(e) { toast('Error al procesar imagen.', 'err'); }
    }
    _renderDetalleFotos();
  });
}

async function uploadPendingDetFotos(idReparacion) {
  if (!_pendingDetFotos.length) return;
  const pending = _pendingDetFotos.slice();
  _pendingDetFotos = [];
  for (const p of pending) {
    URL.revokeObjectURL(p.previewUrl);
    const fd = new FormData();
    fd.append('imagen',        p.file);
    fd.append('id_reparacion', idReparacion);
    fd.append('etiqueta',      'Reparación');
    try {
      const r = await apiFetch('/reparo/api/upload_rep_img.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (!j.ok) toast(j.msg || 'Error al subir foto.', 'err');
    } catch(e) {
      toast('Error al subir foto.', 'err');
    }
  }
}

async function _uploadDetalleFoto(file) {
  if (_detFotos.length >= 3) { toast('Máximo 3 fotos por servicio.', 'err'); return; }
  const fd = new FormData();
  fd.append('imagen',         file);
  fd.append('id_reparacion',  _detRepId);
  fd.append('etiqueta',       'Reparación');
  try {
    const r = await apiFetch('/reparo/api/upload_rep_img.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) {
      _detFotos.push({ id: j.data.id, url: j.data.url, etiqueta: j.data.etiqueta });
      _renderDetalleFotos();
    } else {
      toast(j.msg || 'Error al subir foto.', 'err');
    }
  } catch(e) {
    toast('Error al subir foto.', 'err');
  }
}
