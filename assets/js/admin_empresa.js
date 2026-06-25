// Detalle de empresa — acciones
// Requiere sadminFetch() y sadminCsrf definidos en admin_common.js (cargado primero)

function showToast(msg, ok = true) {
  const t = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  const icon = document.getElementById('toast-icon');
  icon.textContent = ok ? 'check_circle' : 'error';
  icon.style.color = ok ? '#4ade80' : '#f87171';
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3200);
}

// Toggle empresa activa/inactiva
document.getElementById('btn-toggle-empresa')?.addEventListener('click', async function () {
  const activa = this.dataset.activa === '1';
  if (!confirm(`¿${activa ? 'Suspender' : 'Reactivar'} esta empresa?`)) return;

  const fd = new FormData();
  fd.append('id_empresa', this.dataset.id);
  const r = await sadminFetch('/reparo/api/admin/toggle_empresa.php', fd);
  const j = await r.json();

  if (j.ok) {
    const nueva = j.data.activa;
    this.dataset.activa = nueva ? '1' : '0';
    this.innerHTML = `<span class="material-icons-round">${nueva ? 'block' : 'check_circle'}</span><span id="lbl-toggle">${nueva ? 'Suspender' : 'Reactivar'}</span>`;
    this.className = `adm-btn ${nueva ? 'adm-btn-danger' : 'adm-btn-ghost'}`;
    const badge = document.getElementById('badge-activa');
    badge.textContent = nueva ? 'Activa' : 'Inactiva';
    badge.className = `adm-badge ${nueva ? 'adm-badge-ok' : 'adm-badge-off'}`;
    badge.style.cssText = 'font-size:12px;padding:5px 12px;';
    showToast(`Empresa ${nueva ? 'reactivada' : 'suspendida'}.`);
  } else {
    showToast(j.msg || 'Error al actualizar.', false);
  }
});

// Reset contraseña de usuario
document.querySelectorAll('.btn-reset-pass').forEach(btn => {
  btn.addEventListener('click', async function () {
    const correo = this.dataset.correo || '(correo de la empresa)';
    if (!confirm(`¿Enviar enlace de restablecimiento a ${this.dataset.nombre}?\nSe enviará a: ${correo}`)) return;
    const orig = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<span class="material-icons-round">hourglass_empty</span>Enviando...';

    const fd = new FormData();
    fd.append('id_usuario', this.dataset.id);
    const r = await sadminFetch('/reparo/api/admin/send_reset.php', fd);
    const j = await r.json();

    this.innerHTML = orig;
    this.disabled = false;
    showToast(j.ok ? j.data.msg : (j.msg || 'Error al enviar.'), j.ok);
  });
});

// Toggle usuario activo/inactivo
document.querySelectorAll('.btn-toggle-user').forEach(btn => {
  btn.addEventListener('click', async function () {
    const activo = this.dataset.activo === '1';
    if (!confirm(`¿${activo ? 'Suspender' : 'Reactivar'} este usuario?`)) return;

    const fd = new FormData();
    fd.append('id_usuario', this.dataset.id);
    const r = await sadminFetch('/reparo/api/admin/toggle_usuario.php', fd);
    const j = await r.json();

    if (j.ok) {
      const nuevo = j.data.activo;
      const id = this.dataset.id;

      if (nuevo) {
        this.className = 'adm-btn-xs adm-btn-xs-danger btn-toggle-user';
        this.innerHTML = '<span class="material-icons-round">block</span>Suspender';
        this.dataset.activo = '1';
      } else {
        this.className = 'adm-btn-xs adm-btn-xs-ok btn-toggle-user';
        this.innerHTML = '<span class="material-icons-round">check_circle</span>Reactivar';
        this.dataset.activo = '0';
      }

      const badge = document.getElementById(`badge-u-${id}`);
      badge.textContent = nuevo ? 'Activo' : 'Inactivo';
      badge.className = `adm-badge ${nuevo ? 'adm-badge-ok' : 'adm-badge-off'}`;
      badge.style.fontSize = '11px';
      showToast(`Usuario ${nuevo ? 'reactivado' : 'suspendido'}.`);
    } else {
      showToast(j.msg || 'Error al actualizar.', false);
    }
  });
});

// ── Notas internas (debounce 1.5s) ───────────────────────────
const notaTA = document.getElementById('notas-internas');
if (notaTA) {
  let notaTimer;
  notaTA.addEventListener('input', function () {
    clearTimeout(notaTimer);
    document.getElementById('nota-saved').style.display  = 'none';
    document.getElementById('nota-saving').style.display = 'inline';
    notaTimer = setTimeout(async () => {
      const fd = new FormData();
      fd.append('id_empresa', this.dataset.id);
      fd.append('nota', this.value);
      const r = await sadminFetch('/reparo/api/admin/save_nota.php', fd);
      const j = await r.json();
      document.getElementById('nota-saving').style.display = 'none';
      document.getElementById('nota-saved').style.display  = 'inline';
      if (!j.ok) showToast('Error al guardar nota.', false);
    }, 1500);
  });
}

// ── Registro de pagos ─────────────────────────────────────────
document.getElementById('btn-abrir-pago')?.addEventListener('click', () => {
  document.getElementById('form-pago').removeAttribute('hidden');
  document.getElementById('pago-desc').focus();
});
document.getElementById('btn-cancelar-pago')?.addEventListener('click', () => {
  document.getElementById('form-pago').setAttribute('hidden', '');
});
document.getElementById('btn-guardar-pago')?.addEventListener('click', async function () {
  const desc   = document.getElementById('pago-desc').value.trim();
  const monto  = document.getElementById('pago-monto').value;
  const estado = document.getElementById('pago-estado').value;
  const fecha  = document.getElementById('pago-fecha').value;

  if (!desc || !monto || parseFloat(monto) <= 0) {
    showToast('Completa descripción y monto.', false); return;
  }

  const fd = new FormData();
  fd.append('id_empresa',  this.dataset.id);
  fd.append('descripcion', desc);
  fd.append('monto',       monto);
  fd.append('estado',      estado);
  fd.append('fecha',       fecha);

  const orig = this.innerHTML;
  this.disabled = true;
  this.innerHTML = '<span class="material-icons-round">hourglass_empty</span>Guardando...';

  const r = await sadminFetch('/reparo/api/admin/add_pago.php', fd);
  const j = await r.json();
  this.innerHTML = orig;
  this.disabled  = false;

  if (j.ok) {
    const p = j.data;
    const badgeClass = p.estado === 'Pagado' ? 'adm-badge-ok' : 'adm-badge-warn';
    const fmtFecha   = new Date(p.fecha + 'T12:00:00').toLocaleDateString('es-CL', {day:'2-digit',month:'2-digit',year:'numeric'});
    const fmtMonto   = '$' + Number(p.monto).toLocaleString('es-CL');

    const row = document.createElement('div');
    row.className = 'pago-row';
    row.innerHTML = `
      <div>
        <div class="pago-desc">${p.descripcion}</div>
        <div class="pago-fecha">${fmtFecha}</div>
      </div>
      <div style="text-align:right;">
        <div class="pago-monto">${fmtMonto}</div>
        <span class="adm-badge ${badgeClass}" style="margin-top:4px;display:inline-block;">${p.estado}</span>
      </div>`;

    const lista = document.getElementById('lista-pagos');
    const vacio = document.getElementById('pagos-vacio');
    if (vacio) vacio.remove();
    lista.prepend(row);

    const badge = document.getElementById('badge-pagos-count');
    badge.textContent = parseInt(badge.textContent || '0') + 1;

    document.getElementById('pago-desc').value  = '';
    document.getElementById('pago-monto').value = '';
    document.getElementById('form-pago').setAttribute('hidden', '');
    showToast(`Pago de ${fmtMonto} registrado.`);
  } else {
    showToast(j.msg || 'Error al guardar.', false);
  }
});

// ── Guardar plan ──────────────────────────────────────────────
document.getElementById('btn-save-plan')?.addEventListener('click', async function () {
  const fd = new FormData();
  fd.append('id_empresa',       this.dataset.id);
  fd.append('plan_tipo',        document.getElementById('plan-tipo').value.trim());
  fd.append('plan_estado',      document.getElementById('plan-estado').value);
  fd.append('plan_vencimiento', document.getElementById('plan-venc').value);

  const orig = this.innerHTML;
  this.disabled = true;
  this.innerHTML = '<span class="material-icons-round">hourglass_empty</span>Guardando...';

  const r = await sadminFetch('/reparo/api/admin/update_plan.php', fd);
  const j = await r.json();

  this.disabled = false;
  this.innerHTML = orig;
  showToast(j.ok ? 'Plan actualizado correctamente.' : (j.msg || 'Error.'), j.ok);
});
