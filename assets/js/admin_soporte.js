(function () {
  'use strict';

  function toast(msg, ok) {
    const t = document.getElementById('toast');
    t.innerHTML = '<span class="material-icons-round" style="font-size:16px;color:' +
      (ok ? '#4ade80' : '#f87171') + '">' + (ok ? 'check_circle' : 'error') + '</span>' + msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
  }

  let ticketActual = null;
  const modal    = document.getElementById('modal-ticket');
  const btnClose = document.getElementById('btn-modal-ticket-close');

  function openModal(btn) {
    ticketActual = btn.dataset.id;
    const empresa = btn.dataset.empresa || '';
    const estado  = btn.dataset.estado  || '';

    document.getElementById('mtk-titulo').textContent   = 'Ticket #' + ticketActual + ' — ' + btn.dataset.asunto;
    document.getElementById('mtk-empresa').textContent  = empresa;
    document.getElementById('mtk-usuario').textContent  = btn.dataset.usuario;

    // Avatar: iniciales de la empresa (1 o 2 palabras)
    const words = empresa.trim().split(/\s+/).filter(Boolean);
    document.getElementById('mtk-avatar').textContent = words.length >= 2
      ? (words[0][0] + words[1][0]).toUpperCase()
      : empresa.slice(0, 2).toUpperCase() || '?';

    // Badge de estado actual
    const badgeMap = { 'Abierto': 'adm-badge-off', 'En revision': 'adm-badge-warn', 'Resuelto': 'adm-badge-ok' };
    const badge = document.getElementById('mtk-estado-badge');
    badge.className = 'adm-badge ' + (badgeMap[estado] || '');
    badge.textContent = estado;

    // Mensaje: textContent evita XSS; white-space:pre-wrap preserva saltos de línea
    document.getElementById('mtk-mensaje').textContent = btn.dataset.mensaje;

    // Respuesta anterior: mostrar como read-only si existe, limpiar el campo editable
    const respAnterior  = btn.dataset.respuesta || '';
    const antWrap       = document.getElementById('mtk-ant-wrap');
    const antEl         = document.getElementById('mtk-ant');
    const lblEl         = document.getElementById('mtk-respuesta-lbl');
    if (respAnterior) {
      antEl.innerHTML        = respAnterior;
      antWrap.style.display  = '';
      lblEl.textContent      = 'Nueva respuesta (reemplaza la anterior)';
    } else {
      antWrap.style.display  = 'none';
      lblEl.textContent      = 'Respuesta del técnico';
    }
    document.getElementById('mtk-respuesta').innerHTML = '';

    document.getElementById('mtk-estado').value = estado;
    modal.classList.add('active');
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
