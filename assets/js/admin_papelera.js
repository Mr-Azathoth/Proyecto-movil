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

  function updateCount(tipo) {
    const tabKey  = tipo === 'reparacion' ? 'reparaciones' : 'repuestos';
    const tbody   = document.querySelector('#tab-' + tabKey + ' tbody');
    const remaining = tbody ? tbody.querySelectorAll('tr').length : 0;

    // Actualizar botón de tab
    const tabBtn = document.querySelector('.pap-tab[data-tab="' + tabKey + '"]');
    if (tabBtn) {
      const icon  = tabBtn.querySelector('.material-icons-round');
      const label = tipo === 'reparacion' ? 'Reparaciones' : 'Repuestos';
      tabBtn.textContent = label + ' (' + remaining + ')';
      if (icon) tabBtn.prepend(icon);
    }

    // Actualizar resumen de cabecera
    const summary = document.querySelector('span[data-summary]');
    if (summary) {
      const repCount = parseInt(summary.dataset.rep) - (tipo === 'reparacion' ? 1 : 0);
      const invCount = parseInt(summary.dataset.inv) - (tipo === 'repuesto'   ? 1 : 0);
      summary.dataset.rep = repCount;
      summary.dataset.inv = invCount;
      summary.textContent = repCount + ' reparación' + (repCount !== 1 ? 'es' : '')
        + ' · ' + invCount + ' repuesto' + (invCount !== 1 ? 's' : '') + ' en papelera';
    }

    // Si el panel quedó vacío, mostrar estado vacío
    if (remaining === 0 && tbody) {
      const panel  = document.getElementById('tab-' + tabKey);
      const card   = panel.querySelector('.ec-card');
      if (card) {
        card.outerHTML = '<div class="pap-empty">'
          + '<span class="material-icons-round">check_circle</span>'
          + '<p>No hay ' + (tipo === 'reparacion' ? 'reparaciones' : 'repuestos') + ' eliminados para esta empresa.</p>'
          + '</div>';
      }
    }
  }

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
          row.style.opacity = '0';
          setTimeout(() => {
            row.remove();
            updateCount(btn.dataset.tipo);
          }, 420);
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
