<?php
require_once __DIR__ . '/includes/config.php';
if (logueado()) { header('Location: '.BASE.'/app.php'); exit; }

$plan_pre = preg_replace('/[^a-z0-9]/', '', $_GET['plan'] ?? '12meses');
$csrf     = csrf_token();

$planes = [
    ['key' => '1mes',    'nombre' => '1 mes',    'meses' => 1,  'precio' => 4990,  'featured' => false],
    ['key' => '3meses',  'nombre' => '3 meses',  'meses' => 3,  'precio' => 13990, 'featured' => false],
    ['key' => '6meses',  'nombre' => '6 meses',  'meses' => 6,  'precio' => 25990, 'featured' => false],
    ['key' => '12meses', 'nombre' => '12 meses', 'meses' => 12, 'precio' => 49990, 'featured' => true],
];
$precio_mensual = 4990;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Crear cuenta — Centrotec</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/landing.css?v=<?= filemtime(__DIR__.'/assets/css/landing.css') ?>">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/registro.css?v=<?= filemtime(__DIR__.'/assets/css/registro.css') ?>">
</head>
<body>

<!-- Navbar -->
<nav class="reg-nav-bar">
  <a href="<?= BASE ?>/landing.php" class="reg-nav-logo">
    <div class="reg-nav-logo-icon">C</div>
    <span>Centrotec</span>
  </a>
  <a href="<?= BASE ?>/" class="reg-nav-login">
    <span>¿Ya tienes cuenta?</span>
    <span class="material-icons-round">login</span>
    Ingresar
  </a>
</nav>

<!-- Página -->
<div class="reg-page">
  <div class="reg-container">

    <!-- Indicador de pasos -->
    <div class="reg-steps" id="reg-steps" aria-label="Progreso">
      <div class="reg-step-item active" id="step-ind-1">
        <div class="reg-step-num"><span>1</span></div>
        <div class="reg-step-label">Tus datos</div>
      </div>
      <div class="reg-step-connector" id="conn-1"></div>
      <div class="reg-step-item" id="step-ind-2">
        <div class="reg-step-num"><span>2</span></div>
        <div class="reg-step-label">Tu empresa</div>
      </div>
      <div class="reg-step-connector" id="conn-2"></div>
      <div class="reg-step-item" id="step-ind-3">
        <div class="reg-step-num"><span>3</span></div>
        <div class="reg-step-label">Tu plan</div>
      </div>
    </div>

    <!-- Tarjeta wizard -->
    <div class="reg-card">

      <div id="rg-err" class="rg-err" role="alert"></div>

      <form id="reg-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="plan" id="plan-hidden" value="<?= htmlspecialchars($plan_pre) ?>">

        <!-- ── Paso 1: Tus datos ────────────────────────────── -->
        <div class="reg-panel active" id="panel-1">
          <h2 class="reg-panel-title">Tus datos</h2>
          <p class="reg-panel-sub">Crea el acceso de administrador para tu cuenta.</p>

          <div class="rg">
            <label for="nombre_admin">Tu nombre completo</label>
            <input type="text" name="nombre_admin" id="nombre_admin"
                   placeholder="Jorge López" autocomplete="name">
          </div>

          <div class="rg">
            <label for="email_personal">Email</label>
            <input type="email" name="email_personal" id="email_personal"
                   placeholder="jorge@milocal.cl" autocomplete="email">
          </div>

          <div class="rg">
            <label for="tel_personal_num">Teléfono <span class="rg-opt">(opcional)</span></label>
            <div class="rg-phone">
              <span class="rg-phone-prefix">
                <span class="material-icons-round" style="font-size:14px" aria-hidden="true">smartphone</span>
                +56
              </span>
              <input type="tel" id="tel_personal_num" placeholder="9 1234 5678"
                     maxlength="11" autocomplete="tel-national">
            </div>
            <input type="hidden" name="telefono_personal" id="telefono_personal">
          </div>

          <div class="rg">
            <label for="pass">Contraseña</label>
            <div class="rg-pass">
              <input type="password" name="pass" id="pass"
                     placeholder="Mínimo 8 caracteres" minlength="8"
                     autocomplete="new-password">
              <button type="button" id="toggle-pass" class="rg-eye" aria-label="Mostrar contraseña">
                <span class="material-icons-round">visibility</span>
              </button>
            </div>
          </div>

          <div class="rg-nav">
            <button type="button" class="rg-btn-next" id="btn-next-1">
              Siguiente <span class="material-icons-round">arrow_forward</span>
            </button>
          </div>
        </div>

        <!-- ── Paso 2: Tu empresa ───────────────────────────── -->
        <div class="reg-panel" id="panel-2">
          <h2 class="reg-panel-title">Tu empresa</h2>
          <p class="reg-panel-sub">Los datos de tu local o servicio técnico.</p>

          <div class="rg">
            <label for="nombre_local">Nombre del local</label>
            <input type="text" name="nombre_local" id="nombre_local"
                   placeholder="Ej: Servicio Técnico López" autocomplete="organization">
          </div>

          <div class="rg">
            <label for="rut">RUT del local</label>
            <input type="text" name="rut" id="rut"
                   placeholder="12.345.678-9" maxlength="12" autocomplete="off">
          </div>

          <div class="rg-row">
            <div class="rg">
              <label for="direccion">Dirección</label>
              <input type="text" name="direccion" id="direccion"
                     placeholder="Av. O'Higgins 1234" autocomplete="street-address">
            </div>
            <div class="rg">
              <label for="comuna">Comuna</label>
              <input type="text" name="comuna" id="comuna"
                     placeholder="Ej: Providencia" autocomplete="address-level2">
            </div>
          </div>

          <div class="rg-row">
            <div class="rg">
              <div class="rg-label-row">
                <label for="email_local">Email del local</label>
                <label class="rg-inline-check active" id="use-same-email-lbl">
                  <input type="checkbox" id="use-same-email" checked>
                  <span>← mis datos</span>
                </label>
              </div>
              <input type="email" name="email_local" id="email_local"
                     placeholder="contacto@milocal.cl" autocomplete="email">
            </div>
            <div class="rg">
              <div class="rg-label-row">
                <label for="tel_local_num">Teléfono <span class="rg-opt">(opcional)</span></label>
                <label class="rg-inline-check" id="use-same-tel-lbl">
                  <input type="checkbox" id="use-same-tel">
                  <span>← mis datos</span>
                </label>
              </div>
              <div class="rg-phone">
                <span class="rg-phone-prefix">+56</span>
                <input type="tel" id="tel_local_num" placeholder="9 1234 5678" maxlength="11">
              </div>
              <input type="hidden" name="telefono_local" id="telefono_local">
            </div>
          </div>

          <div class="rg-nav">
            <button type="button" class="rg-btn-prev" id="btn-prev-2">
              <span class="material-icons-round">arrow_back</span> Anterior
            </button>
            <button type="button" class="rg-btn-next" id="btn-next-2">
              Siguiente <span class="material-icons-round">arrow_forward</span>
            </button>
          </div>
        </div>

        <!-- ── Paso 3: Tu plan ──────────────────────────────── -->
        <div class="reg-panel" id="panel-3">
          <h2 class="reg-panel-title">Elige tu plan</h2>
          <p class="reg-panel-sub">Sin límite de usuarios ni órdenes. Todas las funciones incluidas.</p>

          <div class="rg-planes" id="rg-planes">
            <?php foreach ($planes as $p):
              $por_mes = (int) round($p['precio'] / $p['meses']);
              $ahorro  = $p['meses'] > 1 ? (int) round((1 - $por_mes / $precio_mensual) * 100) : 0;
              $isSel   = $p['key'] === $plan_pre;
            ?>
            <label class="rg-plan <?= $p['featured'] ? 'featured' : '' ?> <?= $isSel ? 'selected' : '' ?>"
                   id="plan-card-<?= $p['key'] ?>">
              <input type="radio" name="plan_radio" value="<?= $p['key'] ?>"
                     <?= $isSel ? 'checked' : '' ?>>
              <div class="rg-plan-dot">
                <span class="material-icons-round">check</span>
              </div>
              <?php if ($p['featured']): ?>
                <div class="rg-plan-badge rg-plan-badge-top">⭐ Mejor valor</div>
              <?php elseif ($ahorro > 0): ?>
                <div class="rg-plan-badge rg-plan-badge-save">Ahorra <?= $ahorro ?>%</div>
              <?php else: ?>
                <div class="rg-plan-badge-spacer"></div>
              <?php endif; ?>
              <div class="rg-plan-nombre"><?= $p['nombre'] ?></div>
              <div class="rg-plan-precio">$<?= number_format($p['precio'], 0, ',', '.') ?></div>
              <div class="rg-plan-pormes">$<?= number_format($por_mes, 0, ',', '.') ?> / mes</div>
            </label>
            <?php endforeach; ?>
          </div>

          <p class="rg-security">
            <span class="material-icons-round">lock</span>
            Pago seguro vía Mercado Pago. Tus datos de tarjeta nunca pasan por nosotros.
          </p>

          <div class="rg-nav">
            <button type="button" class="rg-btn-prev" id="btn-prev-3">
              <span class="material-icons-round">arrow_back</span> Anterior
            </button>
            <button type="submit" class="rg-btn-next" id="btn-submit">
              <span class="material-icons-round">rocket_launch</span>
              Crear cuenta y pagar
            </button>
          </div>
        </div>

      </form>
    </div>

  </div>
</div>

<script src="<?= BASE ?>/assets/js/registro.js?v=<?= filemtime(__DIR__.'/assets/js/registro.js') ?>"></script>
</body>
</html>
