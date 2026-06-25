// Detalle de empresa — acciones

// Toggle empresa activa/inactiva
document.getElementById('btn-toggle-empresa')?.addEventListener('click', async function() {
  const id = this.dataset.id;
  const activa = this.dataset.activa === '1';
  const accion = activa ? 'suspender' : 'reactivar';
  if (!confirm(`¿Seguro que quieres ${accion} esta empresa?`)) return;

  const fd = new FormData();
  fd.append('id_empresa', id);
  const r = await fetch('/reparo/api/admin/toggle_empresa.php', { method: 'POST', body: fd });
  const j = await r.json();
  if (j.ok) {
    const nueva = j.data.activa;
    this.dataset.activa = nueva ? '1' : '0';
    this.innerHTML = `<span class="material-icons-round">${nueva ? 'block' : 'check_circle'}</span>${nueva ? 'Suspender' : 'Reactivar'}`;
    this.className = `adm-btn ${nueva ? 'adm-btn-danger' : 'adm-btn-ghost'}`;
    const badge = document.getElementById('badge-activa');
    badge.textContent = nueva ? 'Activa' : 'Inactiva';
    badge.className = `adm-badge ${nueva ? 'adm-badge-ok' : 'adm-badge-off'}`;
  } else {
    alert(j.msg || 'Error al actualizar.');
  }
});

// Reset de contraseña por usuario
document.querySelectorAll('.btn-reset-pass').forEach(btn => {
  btn.addEventListener('click', async function() {
    const id = this.dataset.id;
    const nombre = this.dataset.nombre;
    if (!confirm(`¿Enviar enlace de restablecimiento a ${nombre}?`)) return;

    const orig = this.innerHTML;
    this.disabled = true;
    this.textContent = 'Enviando...';

    const fd = new FormData();
    fd.append('id_usuario', id);
    const r = await fetch('/reparo/api/admin/send_reset.php', { method: 'POST', body: fd });
    const j = await r.json();

    this.innerHTML = orig;
    this.disabled = false;
    alert(j.ok ? j.data.msg : (j.msg || 'Error al enviar.'));
  });
});

// Toggle usuario activo/inactivo
document.querySelectorAll('.btn-toggle-user').forEach(btn => {
  btn.addEventListener('click', async function() {
    const id = this.dataset.id;
    const activo = this.dataset.activo === '1';
    if (!confirm(`¿${activo ? 'Suspender' : 'Reactivar'} este usuario?`)) return;

    const fd = new FormData();
    fd.append('id_usuario', id);
    const r = await fetch('/reparo/api/admin/toggle_usuario.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) {
      const nuevo = j.data.activo;
      this.dataset.activo = nuevo ? '1' : '0';
      this.innerHTML = `<span class="material-icons-round">${nuevo ? 'block' : 'check_circle'}</span>${nuevo ? 'Suspender' : 'Reactivar'}`;
      const badge = document.getElementById(`badge-u-${id}`);
      badge.textContent = nuevo ? 'Activo' : 'Inactivo';
      badge.className = `adm-badge ${nuevo ? 'adm-badge-ok' : 'adm-badge-off'}`;
    } else {
      alert(j.msg || 'Error al actualizar.');
    }
  });
});

// Guardar plan manualmente
document.getElementById('btn-save-plan')?.addEventListener('click', async function() {
  const id = this.dataset.id;
  const fd = new FormData();
  fd.append('id_empresa', id);
  fd.append('plan_tipo',        document.getElementById('plan-tipo').value.trim());
  fd.append('plan_estado',      document.getElementById('plan-estado').value);
  fd.append('plan_vencimiento', document.getElementById('plan-venc').value);

  this.disabled = true;
  const r = await fetch('/reparo/api/admin/update_plan.php', { method: 'POST', body: fd });
  const j = await r.json();
  this.disabled = false;

  const msg = document.getElementById('plan-msg');
  msg.style.display = 'block';
  msg.style.color   = j.ok ? '#4ade80' : '#f87171';
  msg.textContent   = j.ok ? '✓ Plan guardado.' : (j.msg || 'Error.');
  setTimeout(() => { msg.style.display = 'none'; }, 3000);
});
