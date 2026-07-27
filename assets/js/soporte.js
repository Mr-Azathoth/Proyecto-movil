(function () {
  'use strict';

  const GRACE_HOURS = 72;

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
    return d.toLocaleDateString('es-CL', { day:'2-digit', month:'2-digit', year:'numeric' })
      + ' ' + d.toLocaleTimeString('es-CL', { hour:'2-digit', minute:'2-digit' });
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

    // Badge: tickets con respuesta nueva no vistos
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
  const btnMsdCloseFt = document.getElementById('btn-msd-close-ft');
  const btnReply     = document.getElementById('btn-msd-reply');

  let currentTicketId = null;

  function buildBubble(tipo, autor, mensaje, dt) {
    const isCliente = tipo === 'cliente';
    const icon = isCliente ? 'person' : 'support_agent';
    const nombre = isCliente ? esc(autor) : 'Equipo Centrotec';
    return `<div class="sop-bubble sop-bubble-${isCliente ? 'cliente' : 'admin'}">
      <div class="sop-bubble-meta">
        <span class="material-icons-round">${icon}</span>${nombre} · ${fmt(dt)}
      </div>
      <div class="sop-bubble-body">${mensaje}</div>
    </div>`;
  }

  function openDetalle(id) {
    const t = ticketsData.find(x => parseInt(x.id_ticket) === id);
    if (!t || !modalDetalle) return;

    currentTicketId = id;

    document.getElementById('msd-titulo').textContent = 'Ticket #' + t.id_ticket + ' — ' + t.asunto;

    const subEl = document.getElementById('msd-sub');
    subEl.innerHTML = '<span class="pill ' + (ESTADO_BADGE[t.estado] || '') + '">'
      + esc(ESTADO_LABEL[t.estado] || t.estado) + '</span>'
      + '<span style="color:var(--txt3);font-size:12px;">'
      + esc(t.asunto) + '</span>';

    // Construir hilo de conversación
    const thread = document.getElementById('msd-thread');
    let html = buildBubble('cliente', t.usuario_nombre, t.mensaje || '', t.created_at);

    const mensajes = t.mensajes || [];
    const hasAdminMensajes = mensajes.some(m => m.tipo === 'admin');

    // Si no hay mensajes en el hilo, mostrar respuesta legacy (tickets.respuesta)
    if (!hasAdminMensajes && t.respuesta) {
      html += buildBubble('admin', 'admin', t.respuesta, t.updated_at || t.created_at);
    }

    // Mensajes del hilo
    mensajes.forEach(m => {
      html += buildBubble(m.tipo, m.autor, m.mensaje, m.created_at);
    });

    if (!t.respuesta && !mensajes.length) {
      html += '<p class="msd-no-resp" style="margin-top:12px;">Sin respuesta aún. Te notificaremos por correo cuando el equipo responda.</p>';
    }

    thread.innerHTML = html;

    // Período de gracia y campo de respuesta
    const graceWarn  = document.getElementById('msd-grace-warn');
    const replyWrap  = document.getElementById('msd-reply-wrap');
    const replyMsg   = document.getElementById('msd-reply-msg');
    const replyError = document.getElementById('msd-reply-error');

    replyMsg.innerHTML = '';
    replyError.textContent = '';
    graceWarn.classList.add('sop-hidden');
    replyWrap.classList.add('sop-hidden');
    btnReply.classList.add('sop-hidden');

    let canReply = false;

    if (t.estado !== 'Resuelto') {
      canReply = true;
    } else {
      const resolvedAt = new Date((t.updated_at || t.created_at).replace(' ', 'T')).getTime();
      const graceEnd   = resolvedAt + GRACE_HOURS * 3600 * 1000;
      const remaining  = graceEnd - Date.now();
      if (remaining > 0) {
        canReply = true;
        const hrs = Math.ceil(remaining / 3600000);
        graceWarn.textContent = '⏱ Puedes responder este ticket por ' + hrs + ' hora' + (hrs === 1 ? '' : 's') + ' más. Pasado ese plazo, abre un nuevo ticket si necesitas ayuda.';
        graceWarn.classList.remove('sop-hidden');
      }
    }

    if (canReply) {
      replyWrap.classList.remove('sop-hidden');
      btnReply.classList.remove('sop-hidden');
    }

    modalDetalle.classList.add('active');

    if (t.respuesta && !parseInt(t.visto)) {
      marcarVisto(id);
    }
  }

  // ── Enviar respuesta ────────────────────────────────────────
  if (btnReply) {
    btnReply.addEventListener('click', async () => {
      if (!currentTicketId) return;
      const replyMsg   = document.getElementById('msd-reply-msg');
      const replyError = document.getElementById('msd-reply-error');

      replyError.textContent = '';
      const text = (replyMsg.innerText || '').trim();
      if (text.length < 2) { replyError.textContent = 'El mensaje es demasiado corto.'; return; }
      if (replyMsg.querySelector('.ce-uploading')) { replyError.textContent = 'Espera a que termine de subir la imagen.'; return; }

      btnReply.disabled = true;
      const fd = new FormData();
      fd.append('action',    'reply');
      fd.append('id_ticket', currentTicketId);
      fd.append('mensaje',   replyMsg.innerHTML);

      try {
        const r = await apiFetch('/reparo/api/tickets.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          modalDetalle.classList.remove('active');
          loadTickets();
          toast('Respuesta enviada correctamente.');
        } else {
          replyError.textContent = j.msg || 'Error al enviar.';
        }
      } catch {
        replyError.textContent = 'Error de red. Intenta nuevamente.';
      }
      btnReply.disabled = false;
    });
  }

  // Imagen en campo de respuesta
  const replyMsgEl = document.getElementById('msd-reply-msg');
  if (replyMsgEl && typeof setupImagePaste === 'function') {
    setupImagePaste(replyMsgEl, function (fd) {
      return apiFetch('/reparo/api/upload_ticket_img.php', { method: 'POST', body: fd });
    });
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

  function cerrarDetalle() { modalDetalle.classList.remove('active'); currentTicketId = null; }
  if (btnMsdClose)   btnMsdClose.addEventListener('click', cerrarDetalle);
  if (btnMsdCloseFt) btnMsdCloseFt.addEventListener('click', cerrarDetalle);
  if (modalDetalle)  modalDetalle.addEventListener('click', e => { if (e.target === modalDetalle) cerrarDetalle(); });

  // ── Modal nuevo ticket ─────────────────────────────────────
  const btnNuevo   = document.getElementById('btn-nuevo-ticket');
  const modalSop   = document.getElementById('modal-soporte');
  const btnCerrar  = document.getElementById('btn-sop-close');
  const btnEnviar  = document.getElementById('btn-sop-enviar');
  const inpAsunto  = document.getElementById('sop-asunto');
  const inpMensaje = document.getElementById('sop-mensaje');
  const sopError   = document.getElementById('sop-error');

  if (btnNuevo) btnNuevo.addEventListener('click', () => {
    inpAsunto.value      = '';
    inpMensaje.innerHTML = '';
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
    const asunto      = inpAsunto.value.trim();
    const mensajeText = inpMensaje.innerText.trim();
    sopError.textContent = '';
    if (asunto.length < 3)       { sopError.textContent = 'El asunto es demasiado corto.'; return; }
    if (mensajeText.length < 10) { sopError.textContent = 'El mensaje es demasiado corto.'; return; }
    if (inpMensaje.querySelector('.ce-uploading')) { sopError.textContent = 'Espera a que termine de subir la imagen.'; return; }
    const mensaje = inpMensaje.innerHTML;

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
        toast('Ticket enviado. Te responderemos pronto.');
      } else {
        sopError.textContent = j.msg || 'Error al enviar.';
      }
    } catch {
      sopError.textContent = 'Error de red. Intenta nuevamente.';
    }
    btnEnviar.disabled = false;
  });

  if (inpMensaje && typeof setupImagePaste === 'function') {
    setupImagePaste(inpMensaje, function (fd) {
      return apiFetch('/reparo/api/upload_ticket_img.php', { method: 'POST', body: fd });
    });
  }

  // ── Activar cuando se muestre la vista ────────────────────
  document.addEventListener('viewchange', e => {
    if (e.detail === 'soporte') loadTickets();
  });

  if (document.getElementById('view-soporte')?.classList.contains('active')) loadTickets();
}());
