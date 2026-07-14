// CSRF token para todas las llamadas AJAX del super admin
const sadminCsrf = document.querySelector('meta[name="sadmin-csrf"]')?.content ?? '';
const SADMIN_BASE = document.querySelector('meta[name="base-path"]')?.content ?? '';

// Wrapper fetch autenticado: normaliza rutas /reparo/ → BASE_PATH y añade CSRF
function sadminFetch(url, fd) {
  const normalizedUrl = SADMIN_BASE + url.replace(/^\/reparo/, '');
  return fetch(normalizedUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': sadminCsrf } });
}

// Filas con data-href: doble clic navega al detalle
document.querySelectorAll('tr[data-href]').forEach(tr => {
  tr.addEventListener('dblclick', () => { window.location.href = tr.dataset.href; });
});

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
