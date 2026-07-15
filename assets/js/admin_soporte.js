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
    document.getElementById('mtk-titulo').textContent   = 'Ticket #' + ticketActual + ' — ' + btn.dataset.asunto;
    document.getElementById('mtk-empresa').textContent  = btn.dataset.empresa;
    document.getElementById('mtk-usuario').textContent  = btn.dataset.usuario;
    document.getElementById('mtk-mensaje').innerHTML   = btn.dataset.mensaje;
    document.getElementById('mtk-respuesta').innerHTML = btn.dataset.respuesta;
    document.getElementById('mtk-estado').value        = btn.dataset.estado;
    modal.classList.add('active');
  }

  function closeModal() {
    modal.classList.remove('active');
    ticketActual = null;
  }

  document.querySelectorAll('.btn-ver-ticket').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn));
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

    try {
      const r = await sadminFetch('/reparo/api/admin_tickets.php', fd);
      const j = await r.json();
      if (j.ok) {
        toast('Ticket actualizado.', true);
        setTimeout(() => location.reload(), 1200);
      } else {
        toast(j.msg || 'Error al guardar.', false);
        btn.disabled = false;
      }
    } catch {
      toast('Error de red.', false);
      btn.disabled = false;
    }
  });
}());
