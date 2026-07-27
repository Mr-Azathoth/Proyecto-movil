(function () {
  'use strict';

  function toast(msg, ok) {
    const t = document.getElementById('toast');
    t.innerHTML = '<span class="material-icons-round" style="font-size:16px;color:' +
      (ok ? '#4ade80' : '#f87171') + '">' + (ok ? 'check_circle' : 'error') + '</span>' + msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
  }

  function sanitizeHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    tmp.querySelectorAll('script,style,iframe,object,embed,form,input,button,link').forEach(el => el.remove());
    tmp.querySelectorAll('*').forEach(el => {
      Array.from(el.attributes).forEach(attr => {
        if (/^on/i.test(attr.name)) el.removeAttribute(attr.name);
        if (attr.name === 'href' && /^\s*javascript:/i.test(attr.value)) el.removeAttribute(attr.name);
        if (attr.name === 'src'  && /^\s*javascript:/i.test(attr.value)) el.removeAttribute(attr.name);
      });
    });
    return tmp.innerHTML;
  }

  let ticketActual = null;
  const modal    = document.getElementById('modal-ticket');
  const btnClose = document.getElementById('btn-modal-ticket-close');

  function buildThreadBubble(tipo, autor, mensaje, dt) {
    const isCliente = tipo === 'cliente';
    const icon  = isCliente ? 'person' : 'support_agent';
    const label = isCliente ? autor : 'Tú (' + autor + ')';
    const d = new Date(dt.replace(' ', 'T'));
    const dtFmt = d.toLocaleDateString('es-CL') + ' ' + d.toLocaleTimeString('es-CL', { hour:'2-digit', minute:'2-digit' });
    return '<div class="mtk-thread-bubble mtk-thread-bubble-' + (isCliente ? 'cliente' : 'admin') + '">'
      + '<div class="mtk-thread-bubble-meta"><span class="material-icons-round">' + icon + '</span>'
      + label + ' · ' + dtFmt + '</div>'
      + '<div>' + mensaje + '</div>'
      + '</div>';
  }

  async function loadThread(ticketId, respAnterior, updatedAt) {
    const wrap     = document.getElementById('mtk-thread-wrap');
    const threadEl = document.getElementById('mtk-thread');

    wrap.style.display = 'none';
    threadEl.innerHTML = '<span class="mtk-thread-loading">Cargando...</span>';

    const fd = new FormData();
    fd.append('action',    'get_mensajes');
    fd.append('id_ticket', ticketId);

    let mensajes = [];
    try {
      const r = await sadminFetch('/reparo/api/admin_tickets.php', fd);
      const j = await r.json();
      if (j.ok) mensajes = j.data.mensajes || [];
    } catch (_) {}

    // Descartar respuesta obsoleta si el usuario ya abrió otro ticket
    if (ticketActual !== ticketId) return;

    // Determinar si mostrar el hilo
    const hasThread = respAnterior || mensajes.length > 0;
    if (!hasThread) return;

    // Construir HTML del hilo
    let html = '';

    // Respuesta legacy: sólo si no hay mensajes de admin en el hilo
    const hasAdminInMensajes = mensajes.some(m => m.tipo === 'admin');
    if (respAnterior && !hasAdminInMensajes) {
      const legacyDate = updatedAt || new Date().toISOString().slice(0, 19).replace('T', ' ');
      html += buildThreadBubble('admin', 'admin', respAnterior, legacyDate);
    }

    mensajes.forEach(m => {
      html += buildThreadBubble(m.tipo, m.autor, m.mensaje, m.created_at);
    });

    threadEl.innerHTML = html;
    wrap.style.display = '';
  }

  function openModal(btn) {
    ticketActual = btn.dataset.id;
    const empresa = btn.dataset.empresa || '';
    const estado  = btn.dataset.estado  || '';

    document.getElementById('mtk-titulo').textContent = 'Ticket #' + ticketActual + ' — ' + btn.dataset.asunto;
    document.getElementById('mtk-empresa').textContent = empresa;
    document.getElementById('mtk-usuario').textContent = btn.dataset.usuario;

    const words = empresa.trim().split(/\s+/).filter(Boolean);
    document.getElementById('mtk-avatar').textContent = words.length >= 2
      ? (words[0][0] + words[1][0]).toUpperCase()
      : empresa.slice(0, 2).toUpperCase() || '?';

    const badgeMap = { 'Abierto': 'adm-badge-off', 'En revision': 'adm-badge-warn', 'Resuelto': 'adm-badge-ok' };
    const badge = document.getElementById('mtk-estado-badge');
    badge.className = 'adm-badge ' + (badgeMap[estado] || '');
    badge.textContent = estado;

    document.getElementById('mtk-mensaje').innerHTML = sanitizeHtml(btn.dataset.mensaje);
    document.getElementById('mtk-respuesta').innerHTML = '';
    document.getElementById('mtk-estado').value = estado;

    modal.classList.add('active');

    // Cargar hilo de mensajes de forma asíncrona
    const respAnterior = btn.dataset.respuesta || '';
    const updatedAt    = btn.dataset.updated_at || '';
    loadThread(ticketActual, respAnterior, updatedAt);
  }

  function closeModal() {
    modal.classList.remove('active');
    ticketActual = null;
  }

  document.querySelectorAll('.adm-row-ticket').forEach(function (tr) {
    tr.addEventListener('dblclick', function () { openModal(tr); });
  });

  const respEl = document.getElementById('mtk-respuesta');
  if (respEl && typeof setupImagePaste === 'function') {
    setupImagePaste(respEl, function (fd) {
      return sadminFetch('/reparo/api/admin_upload_ticket_img.php', fd);
    });
  }

  btnClose.addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  document.getElementById('btn-guardar-ticket').addEventListener('click', async () => {
    if (!ticketActual) return;
    const btn = document.getElementById('btn-guardar-ticket');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('id_ticket',  ticketActual);
    fd.append('estado',     document.getElementById('mtk-estado').value);
    fd.append('respuesta',  document.getElementById('mtk-respuesta').innerHTML);

    let resp = null;
    try {
      resp = await sadminFetch('/reparo/api/admin_tickets.php', fd);
      const j = await resp.json();
      if (j.ok) {
        toast('Ticket actualizado.', true);
        setTimeout(() => location.reload(), 1200);
      } else {
        toast(j.msg || 'Error al guardar.', false);
        btn.disabled = false;
      }
    } catch (err) {
      const status = resp ? ' (HTTP ' + resp.status + ')' : ' (sin respuesta)';
      console.error('[tickets] Error al guardar ticket' + status, err);
      toast('Error al guardar' + status + '. Ver consola para detalle.', false);
      btn.disabled = false;
    }
  });
}());
