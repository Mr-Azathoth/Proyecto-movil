(function () {
  'use strict';

  function toast(msg, ok) {
    const t = document.getElementById('toast');
    t.innerHTML = '<span class="material-icons-round" style="font-size:16px;color:' + (ok ? '#4ade80' : '#f87171') + '">' + (ok ? 'check_circle' : 'error') + '</span>' + msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
  }

  // Tabs
  document.querySelectorAll('.pap-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pap-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('.pap-panel').forEach(p => p.classList.add('pap-hidden'));
      document.getElementById('tab-' + btn.dataset.tab).classList.remove('pap-hidden');
    });
  });

  // Restaurar
  document.querySelectorAll('.btn-restaurar').forEach(btn => {
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      const fd = new FormData();
      fd.append('accion',     'restaurar');
      fd.append('tipo',       btn.dataset.tipo);
      fd.append('id',         btn.dataset.id);
      fd.append('id_empresa', btn.dataset.eid);
      try {
        const r = await sadminFetch('/reparo/api/papelera.php', fd);
        const j = await r.json();
        if (j.ok) {
          toast(j.data.msg, true);
          const row = btn.closest('tr');
          row.style.transition = 'opacity .4s';
          row.style.opacity = '0.3';
          btn.innerHTML = '<span class="material-icons-round">check</span> Restaurado';
        } else {
          toast(j.msg || 'Error al restaurar.', false);
          btn.disabled = false;
        }
      } catch (_) {
        toast('Error de red.', false);
        btn.disabled = false;
      }
    });
  });
}());
