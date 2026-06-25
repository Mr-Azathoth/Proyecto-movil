// CSRF token para todas las llamadas AJAX del super admin
const sadminCsrf = document.querySelector('meta[name="sadmin-csrf"]')?.content ?? '';

// Wrapper fetch autenticado: añade el header X-CSRF-Token en todos los POST
function sadminFetch(url, fd) {
  return fetch(url, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': sadminCsrf } });
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
