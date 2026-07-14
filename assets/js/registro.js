(function () {
  'use strict';

  var currentStep = 1;
  var totalSteps  = 3;

  /* ── Indicador de pasos ─────────────────────────────────────── */
  function updateStepIndicator(step) {
    for (var i = 1; i <= totalSteps; i++) {
      var ind = document.getElementById('step-ind-' + i);
      if (!ind) continue;
      ind.classList.remove('active', 'done');
      if (i < step)  ind.classList.add('done');
      if (i === step) ind.classList.add('active');
    }
    for (var j = 1; j < totalSteps; j++) {
      var conn = document.getElementById('conn-' + j);
      if (!conn) continue;
      conn.classList.toggle('done', j < step);
    }
  }

  /* ── Mostrar panel ──────────────────────────────────────────── */
  function showPanel(step) {
    document.querySelectorAll('.reg-panel').forEach(function (p) {
      p.classList.remove('active');
    });
    var panel = document.getElementById('panel-' + step);
    if (panel) panel.classList.add('active');
    updateStepIndicator(step);
    currentStep = step;
    clearError();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /* ── Error ──────────────────────────────────────────────────── */
  var errDiv = document.getElementById('rg-err');
  function showError(msg) {
    if (!errDiv) return;
    errDiv.textContent = msg;
    errDiv.classList.add('rg-err-visible');
    errDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  function clearError() {
    if (!errDiv) return;
    errDiv.classList.remove('rg-err-visible');
    errDiv.textContent = '';
  }

  /* ── Construir teléfono con prefijo ─────────────────────────── */
  function buildPhone(numId, hiddenId) {
    var num    = document.getElementById(numId);
    var hidden = document.getElementById(hiddenId);
    if (!num || !hidden) return;
    var raw = num.value.trim();
    hidden.value = raw ? '+56 ' + raw : '';
  }

  /* ── Auto-rellenar paso 2 desde paso 1 ─────────────────────── */
  function prefillStep2() {
    var emailPersonal = (document.getElementById('email_personal') || {}).value || '';
    var telNum        = (document.getElementById('tel_personal_num') || {}).value || '';

    var emailLocal       = document.getElementById('email_local');
    var useSameEmailCb   = document.getElementById('use-same-email');
    var useSameEmailLbl  = document.getElementById('use-same-email-lbl');
    if (emailLocal && emailPersonal) {
      emailLocal.value = emailPersonal;
      if (useSameEmailCb)  useSameEmailCb.checked = true;
      if (useSameEmailLbl) useSameEmailLbl.classList.add('active');
    }

    var telLocalNum     = document.getElementById('tel_local_num');
    var useSameTelCb    = document.getElementById('use-same-tel');
    var useSameTelLbl   = document.getElementById('use-same-tel-lbl');
    if (telNum) {
      if (telLocalNum)   telLocalNum.value = telNum;
      if (useSameTelCb)  useSameTelCb.checked = true;
      if (useSameTelLbl) useSameTelLbl.classList.add('active');
    } else {
      if (useSameTelCb)  { useSameTelCb.checked = false; useSameTelCb.disabled = true; }
      if (useSameTelLbl) useSameTelLbl.classList.add('disabled');
    }
  }

  /* ── Checkboxes "usar mis datos" ────────────────────────────── */
  var useSameEmailCb  = document.getElementById('use-same-email');
  var useSameEmailLbl = document.getElementById('use-same-email-lbl');
  if (useSameEmailCb) {
    useSameEmailCb.addEventListener('change', function () {
      var emailLocal = document.getElementById('email_local');
      if (this.checked) {
        var ep = (document.getElementById('email_personal') || {}).value || '';
        if (emailLocal) emailLocal.value = ep;
        if (useSameEmailLbl) useSameEmailLbl.classList.add('active');
      } else {
        if (emailLocal) emailLocal.value = '';
        if (useSameEmailLbl) useSameEmailLbl.classList.remove('active');
      }
    });
  }

  var useSameTelCb  = document.getElementById('use-same-tel');
  var useSameTelLbl = document.getElementById('use-same-tel-lbl');
  if (useSameTelCb) {
    useSameTelCb.addEventListener('change', function () {
      var telLocalNum = document.getElementById('tel_local_num');
      if (this.checked) {
        var tp = (document.getElementById('tel_personal_num') || {}).value || '';
        if (telLocalNum) telLocalNum.value = tp;
        if (useSameTelLbl) useSameTelLbl.classList.add('active');
      } else {
        if (telLocalNum) telLocalNum.value = '';
        if (useSameTelLbl) useSameTelLbl.classList.remove('active');
      }
    });
  }

  /* ── Validación por paso ────────────────────────────────────── */
  function validateStep(step) {
    if (step === 1) {
      var nombre = (document.getElementById('nombre_admin').value || '').trim();
      var email  = (document.getElementById('email_personal').value || '').trim();
      var pass   = document.getElementById('pass').value || '';
      if (!nombre) { showError('Ingresa tu nombre completo.'); return false; }
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showError('Ingresa un email válido.'); return false;
      }
      if (pass.length < 8) { showError('La contraseña debe tener al menos 8 caracteres.'); return false; }
      buildPhone('tel_personal_num', 'telefono_personal');
    }
    if (step === 2) {
      var local = (document.getElementById('nombre_local').value || '').trim();
      var slug  = (document.getElementById('subdominio').value || '').trim();
      var rut   = (document.getElementById('rut').value || '').trim();
      if (!local) { showError('Ingresa el nombre de tu local.'); return false; }
      if (!slug || !/^[a-z0-9][a-z0-9\-]{1,58}[a-z0-9]$/.test(slug)) {
        showError('El subdominio debe tener al menos 3 caracteres (letras minúsculas, números y guiones).'); return false;
      }
      if (!rut) { showError('Ingresa el RUT del local.'); return false; }
      buildPhone('tel_local_num', 'telefono_local');
    }
    return true;
  }

  /* ── Slug automático desde nombre del local ─────────────────── */
  function toSlug(str) {
    return str
      .toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9\s]/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .slice(0, 60);
  }

  var nombreInput = document.getElementById('nombre_local');
  var slugInput   = document.getElementById('subdominio');
  var slugManual  = false;

  if (nombreInput && slugInput) {
    slugInput.addEventListener('input', function () { slugManual = true; });
    slugInput.addEventListener('blur',  function () { if (!this.value) slugManual = false; });
    nombreInput.addEventListener('input', function () {
      if (!slugManual) slugInput.value = toSlug(this.value);
    });
  }

  /* ── Formato RUT chileno ────────────────────────────────────── */
  var rutInput = document.getElementById('rut');
  if (rutInput) {
    rutInput.addEventListener('input', function () {
      var raw = this.value.replace(/[^0-9kK]/g, '').toUpperCase();
      if (raw.length < 2) { this.value = raw; return; }
      var body = raw.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      this.value = body + '-' + raw.slice(-1);
    });
  }

  /* ── Mostrar/ocultar contraseña ─────────────────────────────── */
  var toggleBtn = document.getElementById('toggle-pass');
  var passInput = document.getElementById('pass');
  if (toggleBtn && passInput) {
    toggleBtn.addEventListener('click', function () {
      var ic = this.querySelector('.material-icons-round');
      passInput.type = passInput.type === 'password' ? 'text' : 'password';
      if (ic) ic.textContent = passInput.type === 'password' ? 'visibility' : 'visibility_off';
    });
  }

  /* ── Selección de plan ──────────────────────────────────────── */
  var planHidden = document.getElementById('plan-hidden');
  document.querySelectorAll('.rg-plan').forEach(function (card) {
    card.addEventListener('click', function () {
      document.querySelectorAll('.rg-plan').forEach(function (c) { c.classList.remove('selected'); });
      this.classList.add('selected');
      var radio = this.querySelector('input[type="radio"]');
      if (radio) {
        radio.checked = true;
        if (planHidden) planHidden.value = radio.value;
      }
    });
  });

  /* ── Navegación entre pasos ─────────────────────────────────── */
  var btn1 = document.getElementById('btn-next-1');
  if (btn1) btn1.addEventListener('click', function () {
    if (!validateStep(1)) return;
    prefillStep2();
    showPanel(2);
  });

  var btnPrev2 = document.getElementById('btn-prev-2');
  if (btnPrev2) btnPrev2.addEventListener('click', function () { showPanel(1); });

  var btnNext2 = document.getElementById('btn-next-2');
  if (btnNext2) btnNext2.addEventListener('click', function () {
    if (validateStep(2)) showPanel(3);
  });

  var btnPrev3 = document.getElementById('btn-prev-3');
  if (btnPrev3) btnPrev3.addEventListener('click', function () { showPanel(2); });

  /* ── Envío del formulario ───────────────────────────────────── */
  var form      = document.getElementById('reg-form');
  var btnSubmit = document.getElementById('btn-submit');

  function resetBtn() {
    if (!btnSubmit) return;
    btnSubmit.disabled = false;
    btnSubmit.innerHTML = '<span class="material-icons-round">rocket_launch</span> Crear cuenta y pagar';
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearError();

      var plan = planHidden ? planHidden.value : '';
      if (!plan) { showError('Selecciona un plan para continuar.'); return; }

      buildPhone('tel_personal_num', 'telefono_personal');
      buildPhone('tel_local_num',    'telefono_local');

      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Creando cuenta...';

      fetch('/api/registro.php', { method: 'POST', body: new FormData(form) })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) {
            btnSubmit.textContent = 'Redirigiendo al pago...';
            window.location.href = j.data.redirect;
          } else {
            showError(j.msg || 'Error al crear la cuenta.');
            resetBtn();
          }
        })
        .catch(function () {
          showError('Error de conexión. Revisa tu internet e intenta nuevamente.');
          resetBtn();
        });
    });
  }

})();
