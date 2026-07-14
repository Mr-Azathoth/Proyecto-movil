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
    return d.toLocaleDateString('es-CL');
  }

  function updateBadge(count) {
    const badge = document.getElementById('nav-soporte-badge');
    if (!badge) return;
    badge.textContent = count;
    if (count > 0) badge.classList.remove('oculto');
    else           badge.classList.add('oculto');
  }

  // ── Lista de tickets ───────────────────────────────────────
  let ticketsData = [];

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
    ticketsData = tickets;
    const list  = document.getElementById('soporte-ticket-list');

    if (!tickets.length) {
      list.innerHTML = '<div class="soporte-empty"><span class="material-icons-round">support_agent</span><p>No tienes tickets de soporte aún.<br>Usa el botón para enviar una consulta.</p></div>';
      updateBadge(0);
      return;
    }

    const unread = tickets.filter(t => t.respuesta && !parseInt(t.visto)).length;
    updateBadge(unread);

    const rows = tickets.map(t => {
      const esNoLeido = t.respuesta && !parseInt(t.visto);
      const dot = esNoLeido
        ? '<span class="sop-dot" title="Nueva respuesta"></span>'
        : '<span class="sop-dot-placeholder"></span>';
      return `<tr class="sop-row" data-id="${t.id_ticket}">
        <td class="sop-td-id">#${t.id_ticket}</td>
        <td class="sop-td-asunto"><div class="sop-asunto-wrap">${dot}${esc(t.asunto)}</div></td>
        <td><span class="pill ${ESTADO_BADGE[t.estado] || ''}">${esc(ESTADO_LABEL[t.estado] || t.estado)}</span></td>
        <td class="sop-td-fecha">${fmt(t.created_at)}</td>
      </tr>`;
    }).join('');

    list.innerHTML = `
      <table class="sop-table">
        <thead><tr><th>#</th><th>Asunto</th><th>Estado</th><th>Fecha</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
      <p class="sop-hint">Doble clic en un ticket para ver el detalle</p>
    `;

    list.querySelectorAll('.sop-row').forEach(row => {
      row.addEventListener('dblclick', () => openDetalle(parseInt(row.dataset.id)));
    });
  }

  // ── Modal detalle ──────────────────────────────────────────
  const modalDetalle = document.getElementById('modal-sop-detalle');
  const btnMsdClose  = document.getElementById('btn-msd-close');

  function openDetalle(id) {
    const t = ticketsData.find(x => parseInt(x.id_ticket) === id);
    if (!t || !modalDetalle) return;

    document.getElementById('msd-titulo').textContent = 'Ticket #' + t.id_ticket;

    const subEl = document.getElementById('msd-sub');
    subEl.innerHTML = '<span>' + esc(t.asunto) + '</span>'
      + '<span class="pill ' + (ESTADO_BADGE[t.estado] || '') + '">'
      + esc(ESTADO_LABEL[t.estado] || t.estado) + '</span>';

    document.getElementById('msd-msg').textContent = t.mensaje || '';

    const respWrap = document.getElementById('msd-resp-wrap');
    const respTxt  = document.getElementById('msd-resp-txt');
    const noResp   = document.getElementById('msd-no-resp');

    if (t.respuesta) {
      respWrap.classList.remove('sop-hidden');
      noResp.classList.add('sop-hidden');
      respTxt.textContent = t.respuesta;
    } else {
      respWrap.classList.add('sop-hidden');
      noResp.classList.remove('sop-hidden');
    }

    modalDetalle.classList.add('active');

    if (t.respuesta && !parseInt(t.visto)) {
      marcarVisto(id);
    }
  }

  async function marcarVisto(id) {
    try {
      const fd = new FormData();
      fd.append('action',    'marcar_visto');
      fd.append('id_ticket', id);
      const r = await apiFetch('/reparo/api/tickets.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (!j.ok) return;

      const t = ticketsData.find(x => parseInt(x.id_ticket) === id);
      if (t) t.visto = 1;

      const unread = ticketsData.filter(x => x.respuesta && !parseInt(x.visto)).length;
      updateBadge(unread);

      const dot = document.querySelector('.sop-row[data-id="' + id + '"] .sop-dot');
      if (dot) dot.className = 'sop-dot-placeholder';
    } catch (_) {}
  }

  if (btnMsdClose) btnMsdClose.addEventListener('click', () => modalDetalle.classList.remove('active'));
  if (modalDetalle) modalDetalle.addEventListener('click', e => {
    if (e.target === modalDetalle) modalDetalle.classList.remove('active');
  });

  // ── Modal nuevo ticket ─────────────────────────────────────
  const btnNuevo   = document.getElementById('btn-nuevo-ticket');
  const modalSop   = document.getElementById('modal-soporte');
  const btnCerrar  = document.getElementById('btn-sop-close');
  const btnEnviar  = document.getElementById('btn-sop-enviar');
  const inpAsunto  = document.getElementById('sop-asunto');
  const inpMensaje = document.getElementById('sop-mensaje');
  const sopError   = document.getElementById('sop-error');

  if (btnNuevo) btnNuevo.addEventListener('click', () => {
    inpAsunto.value  = '';
    inpMensaje.value = '';
    sopError.textContent = '';
    modalSop.classList.add('active');
    inpAsunto.focus();
  });

  function cerrarModal() { modalSop.classList.remove('active'); }
  if (btnCerrar) btnCerrar.addEventListener('click', cerrarModal);
  const btnCerrarFt = document.getElementById('btn-sop-close-ft');
  if (btnCerrarFt) btnCerrarFt.addEventListener('click', cerrarModal);
  if (modalSop) modalSop.addEventListener('click', e => { if (e.target === modalSop) cerrarModal(); });

  if (btnEnviar) btnEnviar.addEventListener('click', async () => {
    const asunto  = inpAsunto.value.trim();
    const mensaje = inpMensaje.value.trim();
    sopError.textContent = '';
    if (asunto.length < 3)   { sopError.textContent = 'El asunto es demasiado corto.'; return; }
    if (mensaje.length < 10) { sopError.textContent = 'El mensaje es demasiado corto.'; return; }

    btnEnviar.disabled = true;
    const fd = new FormData();
    fd.append('asunto',  asunto);
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

  // ── Activar cuando se muestre la vista ────────────────────
  document.addEventListener('viewchange', e => {
    if (e.detail === 'soporte') loadTickets();
  });

  if (document.getElementById('view-soporte')?.classList.contains('active')) loadTickets();
}());
