// Botones de notificaciones de vencimiento

async function runCron(dry) {
  const btnDry = document.getElementById('btn-cron-dry');
  const btnRun = document.getElementById('btn-cron-run');
  const box    = document.getElementById('cron-result');

  btnDry.disabled = btnRun.disabled = true;
  const orig = dry ? btnDry.innerHTML : btnRun.innerHTML;
  if (dry) btnDry.innerHTML = '<span class="material-icons-round">hourglass_empty</span>Comprobando...';
  else     btnRun.innerHTML = '<span class="material-icons-round">hourglass_empty</span>Enviando...';

  const fd = new FormData();
  if (dry) fd.append('dry_run', '1');
  const r = await fetch('/reparo/api/admin/run_cron.php', { method: 'POST', body: fd });
  const j = await r.json();

  btnDry.disabled = btnRun.disabled = false;
  if (dry) btnDry.innerHTML = orig;
  else     btnRun.innerHTML = orig;

  if (!j.ok) {
    box.removeAttribute('hidden');
    box.innerHTML = `<div style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);border-radius:8px;padding:14px 18px;color:#f87171;font-size:13px;">${j.msg || 'Error inesperado.'}</div>`;
    return;
  }

  const log = j.data?.log || [];
  const pronto   = j.data?.pronto   ?? 0;
  const vencidas = j.data?.vencidas ?? 0;

  if (pronto === 0 && vencidas === 0) {
    box.removeAttribute('hidden');
    box.innerHTML = `<div class="ec-card"><div class="ec-card-hdr"><span class="material-icons-round" style="color:#4ade80;">check_circle</span>${dry ? 'Vista previa' : 'Resultado'}: sin notificaciones pendientes</div></div>`;
    return;
  }

  let filas = log.map(l => {
    const icon  = l.enviado ? '✓' : (dry ? '—' : '✗');
    const color = l.enviado ? '#4ade80' : (dry ? 'var(--txt2)' : '#f87171');
    const tipo  = l.tipo === 'pronto' ? `Vence en ${l.dias}d` : 'Vencida';
    return `<tr>
      <td style="padding:8px 16px;font-weight:600;">${l.empresa}</td>
      <td style="padding:8px 16px;color:var(--txt2);font-size:12px;">${l.correo || '—'}</td>
      <td style="padding:8px 16px;"><span class="adm-badge ${l.tipo==='pronto'?'adm-badge-warn':'adm-badge-off'}">${tipo}</span></td>
      <td style="padding:8px 16px;color:${color};font-weight:700;">${icon}</td>
    </tr>`;
  }).join('');

  box.removeAttribute('hidden');
  box.innerHTML = `
    <div class="ec-card">
      <div class="ec-card-hdr">
        <span class="material-icons-round" style="color:${dry?'#fbbf24':'#4ade80'};">${dry?'preview':'send'}</span>
        ${dry ? 'Vista previa — sin correos enviados' : `Enviadas: ${pronto + vencidas} notificaciones`}
      </div>
      <table class="adm-table">
        <thead><tr><th>Empresa</th><th>Correo</th><th>Estado</th><th>${dry?'Pendiente':'Enviado'}</th></tr></thead>
        <tbody>${filas}</tbody>
      </table>
    </div>`;
}

document.getElementById('btn-cron-dry')?.addEventListener('click', () => runCron(true));
document.getElementById('btn-cron-run')?.addEventListener('click', () => {
  if (!confirm('¿Enviar correos de notificación a las empresas por vencer o vencidas?')) return;
  runCron(false);
});
