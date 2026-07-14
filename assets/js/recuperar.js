// Lógica de recuperación y reset de contraseña
// Cargado por recuperar.php y reset_password.php

// ── Formulario de recuperación (recuperar.php) ────────────────
const recForm = document.getElementById('rec-form');
if (recForm) {
  recForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn  = document.getElementById('rec-btn');
    const err  = document.getElementById('rec-err');
    const user = document.getElementById('rec-user').value.trim();
    if (!user) return;

    btn.disabled = true;
    btn.textContent = 'Enviando...';
    err.setAttribute('hidden', '');

    try {
      const fd = new FormData();
      fd.append('user', user);
      const r = await fetch('/api/recuperar_password.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        document.getElementById('rec-form-wrap').style.display = 'none';
        document.getElementById('rec-ok').removeAttribute('hidden');
      } else {
        err.textContent = j.msg || 'Error al procesar la solicitud.';
        err.removeAttribute('hidden');
        btn.disabled = false;
        btn.innerHTML = 'Enviar enlace <span class="material-icons-round">send</span>';
      }
    } catch {
      err.textContent = 'Error de red. Verifica tu conexión.';
      err.removeAttribute('hidden');
      btn.disabled = false;
      btn.innerHTML = 'Enviar enlace <span class="material-icons-round">send</span>';
    }
  });
}

// ── Formulario de nueva contraseña (reset_password.php) ───────
const rstForm = document.getElementById('rst-form');
if (rstForm) {
  rstForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn     = document.getElementById('rst-btn');
    const err     = document.getElementById('rst-err');
    const token   = document.getElementById('rst-token').value;
    const pass    = document.getElementById('rst-pass').value;
    const confirm = document.getElementById('rst-confirm').value;

    err.setAttribute('hidden', '');

    if (pass !== confirm) {
      err.textContent = 'Las contraseñas no coinciden.';
      err.removeAttribute('hidden');
      return;
    }
    if (pass.length < 6) {
      err.textContent = 'La contraseña debe tener al menos 6 caracteres.';
      err.removeAttribute('hidden');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
      const fd = new FormData();
      fd.append('token', token);
      fd.append('password', pass);
      fd.append('confirm', confirm);
      const r = await fetch('/api/reset_password.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        window.location.href = '/index.php?reset=1';
      } else {
        err.textContent = j.msg || 'Error al actualizar la contraseña.';
        err.removeAttribute('hidden');
        btn.disabled = false;
        btn.innerHTML = 'Guardar contraseña <span class="material-icons-round">lock_reset</span>';
      }
    } catch {
      err.textContent = 'Error de red.';
      err.removeAttribute('hidden');
      btn.disabled = false;
      btn.innerHTML = 'Guardar contraseña <span class="material-icons-round">lock_reset</span>';
    }
  });
}
