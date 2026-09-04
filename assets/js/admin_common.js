// CSRF token para todas las llamadas AJAX del super admin
const sadminCsrf = document.querySelector('meta[name="sadmin-csrf"]')?.content ?? '';
const SADMIN_BASE = document.querySelector('meta[name="base-path"]')?.content ?? '';

// Wrapper fetch autenticado: normaliza rutas /reparo/ → BASE_PATH y añade CSRF
function sadminFetch(url, fd) {
  const normalizedUrl = SADMIN_BASE + url.replace(/^\/reparo/, '');
  return fetch(normalizedUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': sadminCsrf } });
}

// Filas con data-href: doble clic navega al detalle (desktop) o tap (mobile)
document.querySelectorAll('tr[data-href]').forEach(tr => {
  tr.addEventListener('dblclick', () => { window.location.href = tr.dataset.href; });
  tr.addEventListener('click', () => {
    if (window.innerWidth <= 768) window.location.href = tr.dataset.href;
  });
});

// ── Mobile sidebar drawer ─────────────────────────────────────────
(function () {
  var sidebar = document.querySelector('.adm-sidebar');
  if (!sidebar) return;

  var overlay = document.createElement('div');
  overlay.id = 'adm-overlay';
  overlay.className = 'adm-overlay';
  document.body.appendChild(overlay);

  var ham = document.createElement('button');
  ham.id = 'adm-ham';
  ham.className = 'adm-ham';
  ham.setAttribute('aria-label', 'Menú');
  ham.innerHTML = '<span class="material-icons-round">menu</span>';
  document.body.appendChild(ham);

  // Botón instalar visible en móvil (fuera del sidebar)
  var hamInstall = document.createElement('button');
  hamInstall.className = 'btn-pwa-install hidden adm-ham-install';
  hamInstall.setAttribute('title', 'Instalar app');
  hamInstall.innerHTML = '<span class="material-icons-round">install_mobile</span>';
  document.body.appendChild(hamInstall);

  function open()  { sidebar.classList.add('adm-sidebar-open');  overlay.classList.add('adm-overlay-show'); }
  function close() { sidebar.classList.remove('adm-sidebar-open'); overlay.classList.remove('adm-overlay-show'); }

  ham.addEventListener('click', open);
  overlay.addEventListener('click', close);

  // Cerrar al navegar (link dentro del sidebar)
  sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
})();

// Búsqueda en tabla con data-srch
const srch = document.getElementById('srch');
if (srch) {
  srch.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tbl tbody tr').forEach(r => {
      r.style.display = (r.dataset.q || '').includes(q) ? '' : 'none';
    });
  });
}
