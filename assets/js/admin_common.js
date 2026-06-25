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
