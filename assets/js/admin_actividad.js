(function () {
  var BASE = document.querySelector('meta[name="base-path"]').content;
  var modal    = document.getElementById('modal-log-detalle');
  var closeBtn = document.getElementById('btn-close-log');

  function syntaxHighlight(json) {
    var escaped = json
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    return escaped.replace(
      /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
      function (match) {
        var cls = 'json-num';
        if (/^"/.test(match)) {
          cls = /:$/.test(match) ? 'json-key' : 'json-str';
        } else if (/true|false/.test(match)) {
          cls = 'json-bool';
        } else if (/null/.test(match)) {
          cls = 'json-null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
      }
    );
  }

  function renderJson(containerId, sectionId, raw) {
    var section = document.getElementById(sectionId);
    var pre     = document.getElementById(containerId);
    if (!raw) { section.style.display = 'none'; return; }
    try {
      var pretty = JSON.stringify(JSON.parse(raw), null, 2);
      pre.innerHTML = syntaxHighlight(pretty);
    } catch (e) {
      pre.textContent = raw;
    }
    section.style.display = '';
  }

  function openModal(btn) {
    document.getElementById('ld-id').textContent    = '#' + btn.dataset.logId;
    document.getElementById('ld-fecha').textContent = btn.dataset.fecha;
    document.getElementById('ld-empresa').innerHTML =
      '<a href="' + BASE + '/admin_empresa.php?id=' + btn.dataset.empresaId + '">' +
      btn.dataset.empresa + '</a>';
    document.getElementById('ld-usuario').textContent = btn.dataset.usuario || '—';
    document.getElementById('ld-accion').textContent  = btn.dataset.accion;
    document.getElementById('ld-ip').textContent      = btn.dataset.ip || '—';

    var rowServ = document.getElementById('ld-row-servicio');
    if (btn.dataset.servicio) {
      document.getElementById('ld-servicio').textContent = btn.dataset.servicio;
      rowServ.style.display = 'flex';
    } else {
      rowServ.style.display = 'none';
    }

    renderJson('ld-entrada', 'ld-section-entrada', btn.dataset.entrada || '');
    renderJson('ld-salida',  'ld-section-salida',  btn.dataset.salida  || '');

    modal.style.display = 'flex';
  }

  function closeModal() { modal.style.display = ''; }

  document.getElementById('tbl').addEventListener('click', function (e) {
    var btn = e.target.closest('.act-ver-btn');
    if (btn) openModal(btn);
  });

  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
