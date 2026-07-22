<?php
require_once __DIR__ . '/includes/config.php';
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
<title>Centrotec — Gestión para servicios técnicos</title>
<meta name="description" content="Digitaliza tu servicio técnico con Centrotec. Órdenes de trabajo, clientes, repuestos y más en un solo lugar.">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/landing.css?v=<?= filemtime(__DIR__.'/assets/css/landing.css') ?>">
</head>
<body>

<!-- NAVBAR -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="#hero" class="nav-logo">
      <svg class="nav-logo-svg" viewBox="0 0 680 210" xmlns="http://www.w3.org/2000/svg">
        <defs><style>.nlt{font-family:Impact,'Arial Black',sans-serif;font-size:86px;fill:#fff;}</style></defs>
        <path d="M 120 75 A 38 38 0 1 0 120 127" stroke="#3ec96e" stroke-width="20" fill="none" stroke-linecap="round"/>
        <line x1="120" y1="75" x2="136" y2="75" stroke="#3ec96e" stroke-width="2.5"/>
        <circle cx="139" cy="75" r="3.5" fill="#3ec96e"/>
        <line x1="139" y1="75" x2="139" y2="61" stroke="#3ec96e" stroke-width="2"/>
        <line x1="120" y1="127" x2="136" y2="127" stroke="#3ec96e" stroke-width="2.5"/>
        <circle cx="139" cy="127" r="3.5" fill="#3ec96e"/>
        <line x1="139" y1="127" x2="139" y2="141" stroke="#3ec96e" stroke-width="2"/>
        <line x1="56" y1="95" x2="70" y2="95" stroke="#3ec96e" stroke-width="1.5"/>
        <circle cx="53" cy="95" r="2.5" fill="#3ec96e"/>
        <line x1="56" y1="109" x2="70" y2="109" stroke="#3ec96e" stroke-width="1.5"/>
        <circle cx="53" cy="109" r="2.5" fill="#3ec96e"/>
        <g transform="translate(71,26) scale(0.62)">
          <ellipse cx="11" cy="3" rx="13" ry="5" fill="#facc15"/>
          <rect x="0" y="6" width="22" height="3" rx="1" fill="#ca8a04"/>
          <rect x="3" y="9" width="16" height="13" rx="3" fill="#fcd9a0"/>
          <rect x="1" y="22" width="20" height="17" rx="2" fill="#f97316"/>
          <rect x="2" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
          <rect x="12" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
          <rect x="-8" y="24" width="9" height="5" rx="2" fill="#fcd9a0"/>
          <rect x="21" y="23" width="9" height="5" rx="2" fill="#fcd9a0"/>
        </g>
        <text x="145" y="137" class="nlt">ENTR</text>
        <rect x="334" y="54" width="64" height="94" rx="12" fill="none" stroke="#d97706" stroke-width="4"/>
        <rect x="340" y="61" width="52" height="70" rx="5" fill="#111e2e"/>
        <circle cx="366" cy="81" r="10" fill="#7a9cb5"/>
        <rect x="352" y="93" width="28" height="20" rx="5" fill="#7a9cb5"/>
        <circle cx="354" cy="130" r="8" fill="#22c55e"/>
        <circle cx="378" cy="130" r="8" fill="#ef4444"/>
        <rect x="351" y="57" width="30" height="5" rx="2.5" fill="#d97706"/>
        <polygon points="348,134 337,154 359,154" fill="#f97316"/>
        <rect x="335" y="152" width="26" height="5" rx="1" fill="#c2640a"/>
        <polygon points="384,134 373,154 395,154" fill="#f97316"/>
        <rect x="371" y="152" width="26" height="5" rx="1" fill="#c2640a"/>
        <text x="408" y="137" class="nlt">TEC</text>
        <line x1="564" y1="164" x2="544" y2="52" stroke="#92400e" stroke-width="4" stroke-linecap="round"/>
        <line x1="577" y1="164" x2="557" y2="52" stroke="#92400e" stroke-width="4" stroke-linecap="round"/>
        <line x1="546" y1="63" x2="559" y2="63" stroke="#92400e" stroke-width="3"/>
        <line x1="548" y1="80" x2="561" y2="80" stroke="#92400e" stroke-width="3"/>
        <line x1="550" y1="97" x2="563" y2="97" stroke="#92400e" stroke-width="3"/>
        <line x1="552" y1="114" x2="565" y2="114" stroke="#92400e" stroke-width="3"/>
        <line x1="554" y1="131" x2="567" y2="131" stroke="#92400e" stroke-width="3"/>
        <line x1="557" y1="149" x2="570" y2="149" stroke="#92400e" stroke-width="3"/>
        <g transform="translate(539,68) scale(0.62)">
          <ellipse cx="11" cy="3" rx="13" ry="5" fill="#facc15"/>
          <rect x="0" y="6" width="22" height="3" rx="1" fill="#ca8a04"/>
          <rect x="3" y="9" width="16" height="13" rx="3" fill="#fcd9a0"/>
          <rect x="1" y="22" width="20" height="17" rx="2" fill="#f97316"/>
          <rect x="2" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
          <rect x="12" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
          <rect x="-9" y="23" width="10" height="5" rx="2" fill="#fcd9a0"/>
          <rect x="21" y="23" width="10" height="5" rx="2" fill="#fcd9a0"/>
        </g>
      </svg>
    </a>
    <div class="nav-links" id="nav-links">
      <a href="#caracteristicas" class="nav-link">Características</a>
      <a href="#precios" class="nav-link">Precios</a>
      <a href="#contacto" class="nav-link">Contacto</a>
      <a href="<?= BASE ?>/seguimiento" class="nav-seguimiento">
        <span class="material-icons-round">search</span>
        Seguir mi reparación
      </a>
      <a href="<?= BASE ?>/registro.php" class="nav-cta nav-cta-ghost">Crear cuenta</a>
      <a href="<?= BASE ?>/" class="nav-cta">Zona Clientes</a>
    </div>
    <button class="nav-burger" id="nav-burger" aria-label="Abrir menú">
      <span class="material-icons-round" id="burger-icon">menu</span>
    </button>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-glow hero-glow-1"></div>
  <div class="hero-glow hero-glow-2"></div>
  <div class="container hero-inner">
    <div class="hero-text">
      <h1 class="hero-title anim anim-delay-1">Tu Servicio Técnico<br><span class="hero-accent">100% Digital</span></h1>
      <p class="hero-sub anim anim-delay-2">Digitaliza tu taller de servicio técnico electrónico. Tus órdenes de trabajo, clientes e inventario en un solo lugar — sin papeles, sin Excel, sin caos.</p>
      <div class="hero-btns anim anim-delay-3">
        <a href="#precios" class="btn-primary">
          <span class="material-icons-round">rocket_launch</span>
          Ver planes
        </a>
        <a href="#caracteristicas" class="btn-ghost">
          Conocer más
          <span class="material-icons-round">arrow_downward</span>
        </a>
      </div>
      <p class="hero-note anim anim-delay-3">
        <span class="material-icons-round">lock</span>
        Pago seguro vía Mercado Pago — cancela cuando quieras
      </p>
    </div>
    <div class="hero-mockup anim anim-delay-2">
      <div class="mockup-window">
        <div class="mockup-bar">
          <span class="mockup-dot"></span>
          <span class="mockup-dot"></span>
          <span class="mockup-dot"></span>
          <span class="mockup-url">centrotec.cl/app</span>
        </div>
        <img src="<?= BASE ?>/assets/img/banner 1.jpg?v=<?= filemtime(__DIR__.'/assets/img/banner 1.jpg') ?>"
             alt="Vista del panel de reparaciones de Centrotec"
             style="width:100%;display:block;border-radius:0 0 10px 10px;">
      </div>
    </div>
  </div>
  <a href="#problema" class="hero-scroll-hint" aria-label="Ver más">
    <span class="material-icons-round">keyboard_arrow_down</span>
  </a>
</section>

<!-- PROBLEMA -->
<section class="problema" id="problema">
  <div class="container">
    <h2 class="section-title text-center anim">¿Cómo gestionas tu servicio técnico hoy?</h2>
    <p class="section-sub text-center mx-auto anim anim-delay-1">La mayoría de los servicios técnicos siguen usando métodos que les cuestan tiempo y clientes.</p>
    <div class="problema-grid">
      <div class="problema-card anim anim-delay-1">
        <span class="material-icons-round problema-icon">description</span>
        <h3>Órdenes en papel</h3>
        <p>Se pierden, se manchan, no se pueden buscar. El cliente llama y no encuentras la orden.</p>
      </div>
      <div class="problema-card anim anim-delay-2">
        <span class="material-icons-round problema-icon">grid_on</span>
        <h3>Excel desactualizado</h3>
        <p>Nadie lo actualiza a tiempo, los datos no cuadran y no puedes acceder desde el celular.</p>
      </div>
      <div class="problema-card anim anim-delay-3">
        <span class="material-icons-round problema-icon">chat</span>
        <h3>WhatsApp como agenda</h3>
        <p>Los presupuestos se pierden entre mensajes y el cliente no sabe en qué estado está su equipo.</p>
      </div>
    </div>
  </div>
</section>

<!-- CARACTERÍSTICAS -->
<section class="features" id="caracteristicas">
  <div class="container">
    <div class="section-label text-center anim">Características</div>
    <h2 class="section-title text-center anim anim-delay-1">Todo lo que necesita tu servicio técnico</h2>
    <p class="section-sub text-center mx-auto anim anim-delay-2">Diseñado para servicios técnicos de electrónica. Simple de usar desde el primer día.</p>
    <div class="feat-split anim anim-delay-2">
      <nav class="feat-nav">
        <button class="feat-nav-btn active" data-feat="servicios">
          <span class="material-icons-round">build</span> Servicios
        </button>
        <button class="feat-nav-btn" data-feat="inventario">
          <span class="material-icons-round">inventory_2</span> Inventario
        </button>
        <button class="feat-nav-btn" data-feat="estadisticas">
          <span class="material-icons-round">bar_chart</span> Estadísticas
        </button>
        <button class="feat-nav-btn" data-feat="configuracion">
          <span class="material-icons-round">settings</span> Configuración
        </button>
        <button class="feat-nav-btn" data-feat="soporte">
          <span class="material-icons-round">headset_mic</span> Soporte
        </button>
      </nav>
      <div class="feat-content" id="feat-content">
        <h3 class="feat-title" id="feat-title">Servicios</h3>
        <p class="feat-desc" id="feat-desc">Crea y gestiona órdenes de trabajo digitales. Registra el equipo, la falla, el técnico asignado y el estado de cada reparación en tiempo real.</p>
        <div class="feat-pills" id="feat-pills">
          <span class="feat-pill">Órdenes de trabajo</span>
          <span class="feat-pill">Estados en tiempo real</span>
          <span class="feat-pill">Código de seguimiento</span>
          <span class="feat-pill">Historial por cliente</span>
          <span class="feat-pill">Creación de informes</span>
          <span class="feat-pill">Historial de trabajo</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PRECIOS -->
<section class="precios" id="precios">
  <div class="container">
    <div class="section-label text-center anim">Planes</div>
    <h2 class="section-title text-center anim anim-delay-1">Precio único, todas las funciones</h2>
    <p class="section-sub text-center mx-auto anim anim-delay-2">Sin límite de usuarios ni de órdenes. Elige el plan que más te acomoda.</p>
    <div class="planes-grid">
      <?php foreach ($planes as $i => $plan):
        $por_mes = (int) round($plan['precio'] / $plan['meses']);
        $ahorro  = $plan['meses'] > 1 ? (int) round((1 - $por_mes / $precio_mensual) * 100) : 0;
      ?>
      <div class="plan-card <?= $plan['featured'] ? 'plan-card-featured' : '' ?> anim anim-delay-<?= $i + 1 ?>">
        <?php if ($plan['featured']): ?>
          <div class="plan-badge">⭐ Mejor valor</div>
        <?php elseif ($ahorro > 0): ?>
          <div class="plan-badge plan-badge-ahorro">Ahorra <?= $ahorro ?>%</div>
        <?php endif; ?>
        <div class="plan-nombre"><?= $plan['nombre'] ?></div>
        <div class="plan-precio">$<?= number_format($plan['precio'], 0, ',', '.') ?></div>
        <div class="plan-por-mes">$<?= number_format($por_mes, 0, ',', '.') ?> / mes</div>
        <a href="<?= BASE ?>/registro.php?plan=<?= urlencode($plan['key']) ?>" class="btn-plan <?= $plan['featured'] ? 'btn-plan-featured' : '' ?>">
          Suscribirse <span class="material-icons-round">arrow_forward</span>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="plan-nota anim">
      <span class="material-icons-round">lock</span>
      Pago seguro vía Mercado Pago. Tus datos de tarjeta son gestionados por MP, nunca por nosotros.
    </p>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer" id="contacto">
  <div class="container footer-inner">
    <div class="footer-brand">
      <svg class="footer-logo-svg" viewBox="0 0 680 210" xmlns="http://www.w3.org/2000/svg">
        <defs><style>.flt{font-family:Impact,'Arial Black',sans-serif;font-size:86px;fill:#fff;}</style></defs>
        <path d="M 120 75 A 38 38 0 1 0 120 127" stroke="#3ec96e" stroke-width="20" fill="none" stroke-linecap="round"/>
        <line x1="120" y1="75" x2="136" y2="75" stroke="#3ec96e" stroke-width="2.5"/>
        <circle cx="139" cy="75" r="3.5" fill="#3ec96e"/>
        <line x1="139" y1="75" x2="139" y2="61" stroke="#3ec96e" stroke-width="2"/>
        <line x1="120" y1="127" x2="136" y2="127" stroke="#3ec96e" stroke-width="2.5"/>
        <circle cx="139" cy="127" r="3.5" fill="#3ec96e"/>
        <line x1="139" y1="127" x2="139" y2="141" stroke="#3ec96e" stroke-width="2"/>
        <line x1="56" y1="95" x2="70" y2="95" stroke="#3ec96e" stroke-width="1.5"/>
        <circle cx="53" cy="95" r="2.5" fill="#3ec96e"/>
        <line x1="56" y1="109" x2="70" y2="109" stroke="#3ec96e" stroke-width="1.5"/>
        <circle cx="53" cy="109" r="2.5" fill="#3ec96e"/>
        <g transform="translate(71,26) scale(0.62)">
          <ellipse cx="11" cy="3" rx="13" ry="5" fill="#facc15"/>
          <rect x="3" y="9" width="16" height="13" rx="3" fill="#fcd9a0"/>
          <rect x="1" y="22" width="20" height="17" rx="2" fill="#f97316"/>
          <rect x="2" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
          <rect x="12" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
        </g>
        <text x="145" y="137" class="flt">ENTR</text>
        <rect x="334" y="54" width="64" height="94" rx="12" fill="none" stroke="#d97706" stroke-width="4"/>
        <rect x="340" y="61" width="52" height="70" rx="5" fill="#111e2e"/>
        <circle cx="366" cy="81" r="10" fill="#7a9cb5"/>
        <rect x="352" y="93" width="28" height="20" rx="5" fill="#7a9cb5"/>
        <circle cx="354" cy="130" r="8" fill="#22c55e"/>
        <circle cx="378" cy="130" r="8" fill="#ef4444"/>
        <rect x="351" y="57" width="30" height="5" rx="2.5" fill="#d97706"/>
        <polygon points="348,134 337,154 359,154" fill="#f97316"/>
        <rect x="335" y="152" width="26" height="5" rx="1" fill="#c2640a"/>
        <polygon points="384,134 373,154 395,154" fill="#f97316"/>
        <rect x="371" y="152" width="26" height="5" rx="1" fill="#c2640a"/>
        <text x="408" y="137" class="flt">TEC</text>
        <line x1="564" y1="164" x2="544" y2="52" stroke="#92400e" stroke-width="4" stroke-linecap="round"/>
        <line x1="577" y1="164" x2="557" y2="52" stroke="#92400e" stroke-width="4" stroke-linecap="round"/>
        <line x1="546" y1="63" x2="559" y2="63" stroke="#92400e" stroke-width="3"/>
        <line x1="548" y1="80" x2="561" y2="80" stroke="#92400e" stroke-width="3"/>
        <line x1="550" y1="97" x2="563" y2="97" stroke="#92400e" stroke-width="3"/>
        <line x1="554" y1="131" x2="567" y2="131" stroke="#92400e" stroke-width="3"/>
        <g transform="translate(539,68) scale(0.62)">
          <ellipse cx="11" cy="3" rx="13" ry="5" fill="#facc15"/>
          <rect x="3" y="9" width="16" height="13" rx="3" fill="#fcd9a0"/>
          <rect x="1" y="22" width="20" height="17" rx="2" fill="#f97316"/>
          <rect x="2" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
          <rect x="12" y="39" width="8" height="16" rx="2" fill="#3b82f6"/>
        </g>
      </svg>
      <div>
        <div class="footer-sub">Software para servicios técnicos electrónicos</div>
      </div>
    </div>
    <nav class="footer-links">
      <a href="#caracteristicas">Características</a>
      <a href="#precios">Precios</a>
      <a href="<?= BASE ?>/seguimiento">Seguir mi reparación</a>
      <a href="mailto:centrotec@gmail.com">Contacto</a>
      <a href="<?= BASE ?>/">Ingresar al sistema</a>
    </nav>
    <div class="footer-legal">© <?= date('Y') ?> Centrotec — Todos los derechos reservados</div>
  </div>
</footer>

<script src="<?= BASE ?>/assets/js/landing.js?v=<?= filemtime(__DIR__.'/assets/js/landing.js') ?>"></script>
</body>
</html>
