// Detalle de empresa — acciones

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
  const r = await fetch('/reparo/api/admin/toggle_empresa.php', { method: 'POST', body: fd });
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
    if (!confirm(`¿Enviar enlace de restablecimiento a ${this.dataset.nombre}?`)) return;
    const orig = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<span class="material-icons-round">hourglass_empty</span>Enviando...';

    const fd = new FormData();
    fd.append('id_usuario', this.dataset.id);
    const r = await fetch('/reparo/api/admin/send_reset.php', { method: 'POST', body: fd });
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
    const r = await fetch('/reparo/api/admin/toggle_usuario.php', { method: 'POST', body: fd });
    const j = await r.json();

    if (j.ok) {
      const nuevo = j.data.activo;
      const id = this.dataset.id;
      this.dataset.activo = nuevo ? '1' : '0';

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

// Guardar plan
document.getElementById('btn-save-plan')?.addEventListener('click', async function () {
  const fd = new FormData();
  fd.append('id_empresa',       this.dataset.id);
  fd.append('plan_tipo',        document.getElementById('plan-tipo').value.trim());
  fd.append('plan_estado',      document.getElementById('plan-estado').value);
  fd.append('plan_vencimiento', document.getElementById('plan-venc').value);

  const orig = this.innerHTML;
  this.disabled = true;
  this.innerHTML = '<span class="material-icons-round">hourglass_empty</span>Guardando...';

  const r = await fetch('/reparo/api/admin/update_plan.php', { method: 'POST', body: fd });
  const j = await r.json();

  this.disabled = false;
  this.innerHTML = orig;
  showToast(j.ok ? 'Plan actualizado correctamente.' : (j.msg || 'Error.'), j.ok);
});
