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
    document.getElementById('mtk-mensaje').textContent  = btn.dataset.mensaje;
    document.getElementById('mtk-respuesta').value      = btn.dataset.respuesta;
    document.getElementById('mtk-estado').value         = btn.dataset.estado;
    modal.classList.remove('pap-hidden');
  }

  function closeModal() {
    modal.classList.add('pap-hidden');
    ticketActual = null;
  }

  document.querySelectorAll('.btn-ver-ticket').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn));
  });

  btnClose.addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  document.getElementById('btn-guardar-ticket').addEventListener('click', async () => {
    if (!ticketActual) return;
    const btn = document.getElementById('btn-guardar-ticket');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('id_ticket',  ticketActual);
    fd.append('estado',     document.getElementById('mtk-estado').value);
    fd.append('respuesta',  document.getElementById('mtk-respuesta').value.trim());

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
