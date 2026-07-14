(function () {
  'use strict';

  const ESTADO_BADGE = {
    'Abierto':     'pill-blue',
    'En revision': 'pill-orange',
    'Resuelto':    'pill-green',
  };
  const ESTADO_LABEL = {
    'Abierto':     'Abierto',
    'En revision': 'En revisión',
    'Resuelto':    'Resuelto',
  };

  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmt(dt) {
    if (!dt) return '';
    const d = new Date(dt.replace(' ', 'T'));
    return d.toLocaleDateString('es-CL') + ' ' + d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
  }

  // ── Cargar lista de tickets ────────────────────────────────
  async function loadTickets() {
    const list = document.getElementById('soporte-ticket-list');
    if (!list) return;
    list.innerHTML = '<div class="soporte-empty"><span class="material-icons-round">hourglass_top</span><p>Cargando...</p></div>';
    try {
      const r = await apiFetch('/reparo/api/tickets.php');
      const j = await r.json();
      if (!j.ok) throw new Error(j.msg);
      renderTickets(j.data);
    } catch (e) {
      list.innerHTML = '<div class="soporte-empty"><span class="material-icons-round">error_outline</span><p>' + esc(e.message) + '</p></div>';
    }
  }

  function renderTickets(tickets) {
    const list = document.getElementById('soporte-ticket-list');
    if (!tickets.length) {
      list.innerHTML = '<div class="soporte-empty"><span class="material-icons-round">support_agent</span><p>No tienes tickets de soporte aún.<br>Usa el botón para enviar una consulta.</p></div>';
      return;
    }
    list.innerHTML = tickets.map(t => `
      <div class="soporte-ticket-card" data-id="${t.id_ticket}">
        <div class="stk-header">
          <span class="stk-id">#${t.id_ticket}</span>
          <span class="pill ${ESTADO_BADGE[t.estado] || ''}">${esc(ESTADO_LABEL[t.estado] || t.estado)}</span>
          <span class="stk-fecha">${fmt(t.created_at)}</span>
        </div>
        <div class="stk-asunto">${esc(t.asunto)}</div>
        <div class="stk-preview">${esc(t.mensaje_preview)}${t.mensaje_preview && t.mensaje_preview.length >= 120 ? '…' : ''}</div>
        ${t.respuesta ? `<div class="stk-respuesta"><span class="material-icons-round">reply</span>${esc(t.respuesta)}</div>` : ''}
      </div>
    `).join('');
  }

  // ── Modal nuevo ticket ─────────────────────────────────────
  const btnNuevo   = document.getElementById('btn-nuevo-ticket');
  const modalSop   = document.getElementById('modal-soporte');
  const btnCerrar  = document.getElementById('btn-sop-close');
  const btnEnviar  = document.getElementById('btn-sop-enviar');
  const inpAsunto  = document.getElementById('sop-asunto');
  const inpMensaje = document.getElementById('sop-mensaje');
  const sopError   = document.getElementById('sop-error');

  if (btnNuevo) btnNuevo.addEventListener('click', () => {
    inpAsunto.value = '';
    inpMensaje.value = '';
    sopError.textContent = '';
    modalSop.classList.add('active');
    inpAsunto.focus();
  });

  function cerrarModal() { modalSop.classList.remove('active'); }
  if (btnCerrar) btnCerrar.addEventListener('click', cerrarModal);
  const btnCerrarFt = document.getElementById('btn-sop-close-ft');
  if (btnCerrarFt) btnCerrarFt.addEventListener('click', cerrarModal);
  if (modalSop)  modalSop.addEventListener('click', e => { if (e.target === modalSop) cerrarModal(); });

  if (btnEnviar) btnEnviar.addEventListener('click', async () => {
    const asunto  = inpAsunto.value.trim();
    const mensaje = inpMensaje.value.trim();
    sopError.textContent = '';
    if (asunto.length < 3)   { sopError.textContent = 'El asunto es demasiado corto.'; return; }
    if (mensaje.length < 10) { sopError.textContent = 'El mensaje es demasiado corto.'; return; }

    btnEnviar.disabled = true;
    const fd = new FormData();
    fd.append('asunto', asunto);
    fd.append('mensaje', mensaje);
    try {
      const r = await apiFetch('/reparo/api/tickets.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        cerrarModal();
        loadTickets();
        showToast('Ticket enviado. Te responderemos pronto.');
      } else {
        sopError.textContent = j.msg || 'Error al enviar.';
      }
    } catch {
      sopError.textContent = 'Error de red. Intenta nuevamente.';
    }
    btnEnviar.disabled = false;
  });

  // ── Inicializar cuando se active la vista ─────────────────
  document.addEventListener('viewchange', e => {
    if (e.detail === 'soporte') loadTickets();
  });

  // Cargar inmediatamente si la vista ya está activa al cargar
  if (document.getElementById('view-soporte')?.classList.contains('active')) loadTickets();
}());
