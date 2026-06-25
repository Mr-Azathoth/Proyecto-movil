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
    err.style.display = 'none';

    try {
      const fd = new FormData();
      fd.append('user', user);
      const r = await fetch('/reparo/api/recuperar_password.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        document.getElementById('rec-form-wrap').style.display = 'none';
        document.getElementById('rec-ok').style.display = 'block';
      } else {
        err.textContent = j.msg || 'Error al procesar la solicitud.';
        err.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = 'Enviar enlace <span class="material-icons-round">send</span>';
      }
    } catch {
      err.textContent = 'Error de red. Verifica tu conexión.';
      err.style.display = 'block';
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

    err.style.display = 'none';

    if (pass !== confirm) {
      err.textContent = 'Las contraseñas no coinciden.';
      err.style.display = 'block';
      return;
    }
    if (pass.length < 6) {
      err.textContent = 'La contraseña debe tener al menos 6 caracteres.';
      err.style.display = 'block';
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
      const fd = new FormData();
      fd.append('token', token);
      fd.append('password', pass);
      fd.append('confirm', confirm);
      const r = await fetch('/reparo/api/reset_password.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        document.getElementById('rst-form-wrap').style.display = 'none';
        document.getElementById('rst-ok').style.display = 'block';
      } else {
        err.textContent = j.msg || 'Error al actualizar la contraseña.';
        err.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = 'Guardar contraseña <span class="material-icons-round">lock_reset</span>';
      }
    } catch {
      err.textContent = 'Error de red.';
      err.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = 'Guardar contraseña <span class="material-icons-round">lock_reset</span>';
    }
  });
}
