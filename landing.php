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
      <div class="nav-logo-icon">C</div>
      <span>Centrotec</span>
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
          <span class="mockup-url">Centrotec/app</span>
        </div>
        <div class="mockup-body">
          <div class="mockup-kpis">
            <div class="mockup-kpi">
              <div class="mockup-kpi-val">18</div>
              <div class="mockup-kpi-lbl">Órdenes activas</div>
            </div>
            <div class="mockup-kpi">
              <div class="mockup-kpi-val">96</div>
              <div class="mockup-kpi-lbl">Clientes</div>
            </div>
            <div class="mockup-kpi">
              <div class="mockup-kpi-val">5</div>
              <div class="mockup-kpi-lbl">Listos hoy</div>
            </div>
          </div>
          <div class="mockup-rows">
            <div class="mockup-row">
              <div class="mockup-avatar">JG</div>
              <div class="mockup-info">
                <div class="mockup-name">Juan González</div>
                <div class="mockup-car">iPhone 14 Pro — Pantalla rota</div>
              </div>
              <div class="mockup-badge mockup-badge-ok">En proceso</div>
            </div>
            <div class="mockup-row">
              <div class="mockup-avatar mockup-avatar-purple">MR</div>
              <div class="mockup-info">
                <div class="mockup-name">María Rodríguez</div>
                <div class="mockup-car">Samsung S22 — No enciende</div>
              </div>
              <div class="mockup-badge mockup-badge-warn">Esperando repuesto</div>
            </div>
            <div class="mockup-row">
              <div class="mockup-avatar mockup-avatar-green">CP</div>
              <div class="mockup-info">
                <div class="mockup-name">Carlos Pérez</div>
                <div class="mockup-car">MacBook Pro — Teclado dañado</div>
              </div>
              <div class="mockup-badge mockup-badge-done">Listo para entrega</div>
            </div>
          </div>
        </div>
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
    <div class="features-grid">
      <div class="feature-card anim anim-delay-1">
        <div class="feature-icon-wrap feature-blue"><span class="material-icons-round">assignment</span></div>
        <h3>Órdenes de trabajo digitales</h3>
        <p>Crea, asigna y cierra órdenes en segundos. Con estado, técnico asignado y descripción del problema.</p>
      </div>
      <div class="feature-card anim anim-delay-2">
        <div class="feature-icon-wrap feature-green"><span class="material-icons-round">people</span></div>
        <h3>Historial de clientes</h3>
        <p>Todo el historial de reparaciones de cada cliente y equipo en un clic. Nunca más "¿qué le hicimos?"</p>
      </div>
      <div class="feature-card anim anim-delay-3">
        <div class="feature-icon-wrap feature-orange"><span class="material-icons-round">inventory_2</span></div>
        <h3>Control de repuestos</h3>
        <p>Inventario que se actualiza solo con cada servicio. Siempre sabes qué tienes y qué te falta.</p>
      </div>
      <div class="feature-card anim anim-delay-1">
        <div class="feature-icon-wrap feature-purple"><span class="material-icons-round">bar_chart</span></div>
        <h3>Reportes</h3>
        <p>Visualiza cuántas órdenes cierras al mes y qué servicios son más frecuentes.</p>
      </div>
      <div class="feature-card anim anim-delay-2">
        <div class="feature-icon-wrap feature-yellow"><span class="material-icons-round">group</span></div>
        <h3>Multi-usuario</h3>
        <p>Cada técnico con su propio acceso. Sin compartir contraseñas, con control de permisos por cargo.</p>
      </div>
      <div class="feature-card anim anim-delay-3">
        <div class="feature-icon-wrap feature-blue"><span class="material-icons-round">manage_search</span></div>
        <h3>Seguimiento para el cliente</h3>
        <p>Tu cliente puede ver en tiempo real el estado de su reparación con un link o código de seguimiento.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA ANTES DE PRECIOS -->
<section class="cta-final" id="cta">
  <div class="container cta-inner anim">
    <div class="cta-glow"></div>
    <h2>¿Listo para digitalizar tu servicio técnico?</h2>
    <p>Únete a los servicios técnicos que ya gestionan su negocio con Centrotec</p>
    <div class="cta-btns">
      <a href="#precios" class="btn-primary btn-lg">
        <span class="material-icons-round">rocket_launch</span>
        Ver planes y precios
      </a>
      <a href="<?= BASE ?>/seguimiento" class="btn-seguimiento-cta">
        <span class="material-icons-round">search</span>
        Seguir mi reparación
      </a>
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
      <div class="nav-logo-icon footer-logo-icon">C</div>
      <div>
        <div class="footer-name">Centrotec</div>
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
