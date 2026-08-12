<?php
require_once __DIR__ . '/includes/config.php';
$planes = [
    ['key' => '1mes',    'nombre' => '1 mes',    'meses' => 1,  'precio' => 4990,  'featured' => false],
    ['key' => '3meses',  'nombre' => '3 meses',  'meses' => 3,  'precio' => 13990, 'featured' => false],
    ['key' => '6meses',  'nombre' => '6 meses',  'meses' => 6,  'precio' => 25990, 'featured' => false],
    ['key' => '12meses', 'nombre' => '12 meses', 'meses' => 12, 'precio' => 49990, 'featured' => true],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Centrotec — Gestión para servicios técnicos</title>
<meta name="description" content="Digitaliza tu servicio técnico con Centrotec. Órdenes de trabajo, clientes, repuestos y estadísticas en un solo lugar.">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/landing.css?v=<?= filemtime(__DIR__.'/assets/css/landing.css') ?>">
</head>
<body>

<nav>
  <a href="<?= BASE ?>/" style="display:block;line-height:0">
    <svg class="nav-logo-svg" viewBox="0 0 680 210" xmlns="http://www.w3.org/2000/svg" aria-label="Centrotec">
      <path d="M 120 75 A 38 38 0 1 0 120 127" stroke="#50d2ff" stroke-width="11" fill="none" stroke-linecap="round"/>
      <line x1="120" y1="75" x2="138" y2="75" stroke="#50d2ff" stroke-width="1.8" stroke-linecap="round"/>
      <circle cx="141" cy="75" r="3" fill="#50d2ff"/>
      <line x1="141" y1="75" x2="141" y2="59" stroke="rgba(80,210,255,.5)" stroke-width="1.4"/>
      <line x1="120" y1="127" x2="138" y2="127" stroke="#50d2ff" stroke-width="1.8" stroke-linecap="round"/>
      <circle cx="141" cy="127" r="3" fill="#50d2ff"/>
      <line x1="141" y1="127" x2="141" y2="143" stroke="rgba(80,210,255,.5)" stroke-width="1.4"/>
      <line x1="54" y1="95" x2="72" y2="95" stroke="rgba(80,210,255,.45)" stroke-width="1.4"/>
      <circle cx="51" cy="95" r="3" fill="rgba(80,210,255,.6)"/>
      <line x1="54" y1="109" x2="72" y2="109" stroke="rgba(80,210,255,.45)" stroke-width="1.4"/>
      <circle cx="51" cy="109" r="3" fill="rgba(80,210,255,.6)"/>
      <g transform="translate(79,86) scale(0.62)">
        <path d="M-1 7 Q11 -3 23 7" stroke="rgba(80,210,255,.92)" stroke-width="2.8" fill="none" stroke-linecap="round"/>
        <rect x="-1" y="7" width="24" height="2.5" rx="1" fill="rgba(80,210,255,.7)"/>
        <rect x="3" y="9" width="16" height="12" rx="2.5" fill="none" stroke="rgba(210,235,255,.8)" stroke-width="1.5"/>
        <rect x="1" y="22" width="20" height="16" rx="2" fill="none" stroke="rgba(80,210,255,.75)" stroke-width="1.5"/>
        <rect x="2" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
        <rect x="12" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
        <rect x="-8" y="24" width="9" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
        <rect x="21" y="23" width="9" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
      </g>
      <text x="148" y="150" font-family="system-ui,-apple-system,'Segoe UI',Arial,sans-serif" font-size="82" font-weight="400" letter-spacing="4" fill="#e8f4ff">ENTR</text>
      <rect x="337" y="52" width="60" height="96" rx="3" fill="none" stroke="rgba(80,210,255,.58)" stroke-width="1.6"/>
      <rect x="343" y="59" width="48" height="72" rx="2" fill="#030507"/>
      <rect x="352" y="55" width="28" height="2.5" rx="1" fill="rgba(80,210,255,.35)"/>
      <circle cx="367" cy="80" r="8.5" fill="none" stroke="rgba(80,210,255,.52)" stroke-width="1.4"/>
      <circle cx="367" cy="80" r="3.5" fill="rgba(80,210,255,.45)"/>
      <line x1="355" y1="95" x2="379" y2="95" stroke="rgba(80,210,255,.32)" stroke-width="1.2"/>
      <line x1="355" y1="101" x2="367" y2="101" stroke="rgba(80,210,255,.22)" stroke-width="1.2"/>
      <circle cx="355" cy="117" r="4" fill="rgba(80,210,255,.65)"/>
      <circle cx="379" cy="117" r="4" fill="none" stroke="rgba(80,210,255,.45)" stroke-width="1.4"/>
      <line x1="351" y1="148" x2="341" y2="160" stroke="rgba(80,210,255,.35)" stroke-width="1.4" stroke-linecap="round"/>
      <line x1="385" y1="148" x2="395" y2="160" stroke="rgba(80,210,255,.35)" stroke-width="1.4" stroke-linecap="round"/>
      <line x1="337" y1="160" x2="356" y2="160" stroke="rgba(80,210,255,.3)" stroke-width="1.4" stroke-linecap="round"/>
      <line x1="378" y1="160" x2="399" y2="160" stroke="rgba(80,210,255,.3)" stroke-width="1.4" stroke-linecap="round"/>
      <text x="408" y="150" font-family="system-ui,-apple-system,'Segoe UI',Arial,sans-serif" font-size="82" font-weight="400" letter-spacing="4" fill="#e8f4ff">TEC</text>
      <line x1="563" y1="162" x2="544" y2="53" stroke="rgba(80,210,255,.42)" stroke-width="2" stroke-linecap="round"/>
      <line x1="576" y1="162" x2="557" y2="53" stroke="rgba(80,210,255,.42)" stroke-width="2" stroke-linecap="round"/>
      <line x1="546" y1="64" x2="559" y2="64" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
      <line x1="548" y1="81" x2="561" y2="81" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
      <line x1="549" y1="98" x2="562" y2="98" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
      <line x1="551" y1="115" x2="564" y2="115" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
      <line x1="553" y1="132" x2="566" y2="132" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
      <line x1="556" y1="149" x2="569" y2="149" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
      <g transform="translate(539,68) scale(0.62)">
        <path d="M-1 7 Q11 -3 23 7" stroke="rgba(80,210,255,.92)" stroke-width="2.8" fill="none" stroke-linecap="round"/>
        <rect x="-1" y="7" width="24" height="2.5" rx="1" fill="rgba(80,210,255,.7)"/>
        <rect x="3" y="9" width="16" height="12" rx="2.5" fill="none" stroke="rgba(210,235,255,.8)" stroke-width="1.5"/>
        <rect x="1" y="22" width="20" height="16" rx="2" fill="none" stroke="rgba(80,210,255,.75)" stroke-width="1.5"/>
        <rect x="2" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
        <rect x="12" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
        <rect x="-9" y="23" width="10" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
        <rect x="21" y="23" width="10" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
      </g>
    </svg>
  </a>
  <ul class="nav-links">
    <li><a href="<?= BASE ?>/seguimiento" class="nav-seguimiento">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      Seguir reparación
    </a></li>
    <li><a href="#precios" class="nav-ghost nav-cta">Precios</a></li>
    <li><a href="<?= BASE ?>/" class="nav-ghost nav-cta">Ingresar</a></li>
    <li><a href="<?= BASE ?>/registro.php" class="nav-cta">Empezar gratis</a></li>
  </ul>
</nav>

<!-- HERO -->
<section class="hero">
  <canvas id="gl-canvas"></canvas>
  <div class="hero-inner">
    <div class="hero-grid">
      <div>
        <h1>El sistema que<br><em>tu taller</em><br>necesitaba</h1>
        <p class="hero-sub">Órdenes de trabajo, diagnósticos, presupuestos, cobros y estadísticas. Todo en un solo lugar, sin papeles.</p>
        <div class="hero-actions">
          <a href="<?= BASE ?>/registro.php" class="btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            Crear cuenta gratis
          </a>
          <a href="#caracteristicas" class="btn-ghost">Ver características</a>
        </div>
        <p class="hero-note">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          7 días de prueba gratis · sin tarjeta de crédito
        </p>
      </div>
      <div class="hero-mockup">
        <div class="mock-bar">
          <span class="mock-dot"></span>
          <span class="mock-dot"></span>
          <span class="mock-dot"></span>
          <span class="mock-label">centrotec.cl</span>
        </div>
        <img src="<?= BASE ?>/assets/img/banner 1.jpg?v=<?= filemtime(__DIR__.'/assets/img/banner 1.jpg') ?>" alt="Captura de pantalla de Centrotec" loading="lazy" style="width:100%;display:block">
      </div>
    </div>
  </div>
</section>

<div class="rule"></div>

<!-- PROBLEMA -->
<section class="problema-section">
  <div class="problema-inner">
    <h2 class="problema-h2 reveal">¿Tu taller todavía opera con cuadernos?</h2>
    <div class="problema-grid">
      <div class="problema-card reveal">
        <svg class="pc-icon" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <p class="pc-title">Órdenes perdidas</p>
        <p class="pc-desc">Sin un sistema centralizado, las órdenes se pierden entre papeles, WhatsApp y correos. El cliente llama y no encuentras el equipo.</p>
      </div>
      <div class="problema-card reveal">
        <svg class="pc-icon" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <p class="pc-title">Tiempo perdido</p>
        <p class="pc-desc">Anotar, buscar, copiar y volver a anotar. Cada tarea administrativa es tiempo que no estás reparando ni atendiendo clientes.</p>
      </div>
      <div class="problema-card reveal">
        <svg class="pc-icon" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        <p class="pc-title">Cobros sin control</p>
        <p class="pc-desc">Sin registro claro de qué se cobró y qué no, los trabajos terminan regalados. La caja no cuadra y no sabes por qué.</p>
      </div>
    </div>
  </div>
</section>

<div class="rule"></div>

<!-- CARACTERÍSTICAS / FEATURES -->
<section class="features-section" id="caracteristicas">
  <div class="features-inner">
    <h2 class="features-h2 reveal">Todo lo que<br>necesitas</h2>
    <div class="cue-board reveal">
      <div class="cue-tabs" role="tablist">
        <button class="cue-tab active" data-tab="servicios" role="tab" aria-selected="true" aria-controls="tab-servicios">
          <svg class="cue-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
          Servicios
        </button>
        <button class="cue-tab" data-tab="inventario" role="tab" aria-selected="false" aria-controls="tab-inventario">
          <svg class="cue-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          Inventario
        </button>
        <button class="cue-tab" data-tab="estadisticas" role="tab" aria-selected="false" aria-controls="tab-estadisticas">
          <svg class="cue-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          Estadísticas
        </button>
        <button class="cue-tab" data-tab="configuracion" role="tab" aria-selected="false" aria-controls="tab-configuracion">
          <svg class="cue-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
          Configuración
        </button>
        <button class="cue-tab" data-tab="soporte" role="tab" aria-selected="false" aria-controls="tab-soporte">
          <svg class="cue-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Soporte
        </button>
      </div>

      <div class="cue-panel active" id="tab-servicios" role="tabpanel">
        <div>
          <h3 class="panel-title">Órdenes de trabajo digitales</h3>
          <p class="panel-desc">Crea, diagnostica, presupuesta y cobra desde una sola pantalla. El cliente recibe notificaciones automáticas en cada etapa.</p>
          <ul class="panel-features">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Recepción con foto del equipo</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Diagnóstico y presupuesto integrado</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Historial de estados en tiempo real</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Cobro y cierre de orden en un clic</li>
          </ul>
        </div>
        <div class="panel-visual">
          <p class="pv-head">Orden · #0042</p>
          <div class="pv-row"><span class="pv-k">Equipo</span><span class="pv-v">MacBook Pro 14"</span></div>
          <div class="pv-row"><span class="pv-k">Problema</span><span class="pv-v">No enciende</span></div>
          <div class="pv-row"><span class="pv-k">Estado</span><span class="pv-v"><span class="badge b-blue">En diagnóstico</span></span></div>
          <div class="pv-sep"></div>
          <div class="pv-row"><span class="pv-k">Presupuesto</span><span class="pv-v">$45.000</span></div>
          <div class="pv-row"><span class="pv-k">Aprobado</span><span class="pv-v"><span class="badge b-green">Sí</span></span></div>
          <div class="pv-sep"></div>
          <div class="pv-row"><span class="pv-k">Técnico</span><span class="pv-v">Andrés M.</span></div>
          <div class="pv-row"><span class="pv-k">Entrega estimada</span><span class="pv-v">Mañana 14:00</span></div>
        </div>
      </div>

      <div class="cue-panel" id="tab-inventario" role="tabpanel">
        <div>
          <h3 class="panel-title">Control de repuestos y stock</h3>
          <p class="panel-desc">Registra los repuestos que usas en cada reparación. El sistema descuenta automáticamente del stock y te avisa cuando estás bajo mínimos.</p>
          <ul class="panel-features">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Stock en tiempo real por repuesto</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Descuento automático por orden</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Alertas de stock mínimo</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Historial de movimientos</li>
          </ul>
        </div>
        <div class="panel-visual">
          <p class="pv-head">Repuestos · Stock actual</p>
          <div class="pv-row"><span class="pv-k">Pantalla iPhone 14</span><span class="pv-v"><span class="badge b-green">12 un.</span></span></div>
          <div class="pv-row"><span class="pv-k">Batería Samsung A52</span><span class="pv-v"><span class="badge b-orange">2 un.</span></span></div>
          <div class="pv-row"><span class="pv-k">Conector carga USB-C</span><span class="pv-v"><span class="badge b-green">8 un.</span></span></div>
          <div class="pv-sep"></div>
          <div class="pv-row"><span class="pv-k">Últimos 30 días</span><span class="pv-v">34 movimientos</span></div>
          <div class="pv-row"><span class="pv-k">Valor en stock</span><span class="pv-v">$284.000</span></div>
        </div>
      </div>

      <div class="cue-panel" id="tab-estadisticas" role="tabpanel">
        <div>
          <h3 class="panel-title">Estadísticas de tu negocio</h3>
          <p class="panel-desc">Entiende qué está pasando en tu taller: cuánto facturas, qué equipos entran más, cuánto tarda cada técnico y dónde se pierde tiempo.</p>
          <ul class="panel-features">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Ingresos por período</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Órdenes por técnico y estado</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Tiempo promedio de reparación</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Equipos más frecuentes</li>
          </ul>
        </div>
        <div class="panel-visual">
          <p class="pv-head">Resumen · Este mes</p>
          <div class="pv-row"><span class="pv-k">Ingresos</span><span class="pv-v">$1.240.000</span></div>
          <div class="pv-row"><span class="pv-k">Órdenes cerradas</span><span class="pv-v">87</span></div>
          <div class="pv-row"><span class="pv-k">Ticket promedio</span><span class="pv-v">$14.253</span></div>
          <div class="pv-sep"></div>
          <div class="pv-row"><span class="pv-k">Tiempo promedio</span><span class="pv-v">1.8 días</span></div>
          <div class="pv-row"><span class="pv-k">Tasa entrega OK</span><span class="pv-v"><span class="badge b-green">94%</span></span></div>
        </div>
      </div>

      <div class="cue-panel" id="tab-configuracion" role="tabpanel">
        <div>
          <h3 class="panel-title">Adaptado a tu taller</h3>
          <p class="panel-desc">Configura marcas, modelos, tipos de equipo, técnicos y categorías de trabajo. El sistema se adapta a cómo operas tú, no al revés.</p>
          <ul class="panel-features">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Gestión de técnicos y roles</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Marcas y modelos personalizados</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Tipos de trabajo y tarifas</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Plantillas de diagnóstico</li>
          </ul>
        </div>
        <div class="panel-visual">
          <p class="pv-head">Configuración · Técnicos</p>
          <div class="pv-row"><span class="pv-k">Andrés M.</span><span class="pv-v"><span class="badge b-blue">Admin</span></span></div>
          <div class="pv-row"><span class="pv-k">Carla V.</span><span class="pv-v"><span class="badge b-gray">Técnico</span></span></div>
          <div class="pv-row"><span class="pv-k">Felipe R.</span><span class="pv-v"><span class="badge b-gray">Técnico</span></span></div>
          <div class="pv-sep"></div>
          <div class="pv-row"><span class="pv-k">Marcas activas</span><span class="pv-v">24</span></div>
          <div class="pv-row"><span class="pv-k">Tipos de servicio</span><span class="pv-v">18</span></div>
        </div>
      </div>

      <div class="cue-panel" id="tab-soporte" role="tabpanel">
        <div>
          <h3 class="panel-title">Portal de seguimiento para clientes</h3>
          <p class="panel-desc">Tus clientes pueden seguir su reparación en tiempo real con el código de orden. Sin llamadas innecesarias, sin WhatsApps a medianoche.</p>
          <ul class="panel-features">
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Link de seguimiento por orden</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Estado actualizado automáticamente</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Presupuesto visible para el cliente</li>
            <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#50d2ff" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Historial completo de la reparación</li>
          </ul>
        </div>
        <div class="panel-visual">
          <p class="pv-head">Seguimiento · Orden #0042</p>
          <div class="pv-row"><span class="pv-k">Recibido</span><span class="pv-v"><span class="badge b-green">✓</span></span></div>
          <div class="pv-row"><span class="pv-k">En diagnóstico</span><span class="pv-v"><span class="badge b-blue">En curso</span></span></div>
          <div class="pv-row"><span class="pv-k">Reparación</span><span class="pv-v"><span class="badge b-gray">Pendiente</span></span></div>
          <div class="pv-row"><span class="pv-k">Listo para retirar</span><span class="pv-v"><span class="badge b-gray">Pendiente</span></span></div>
          <div class="pv-sep"></div>
          <div class="pv-row"><span class="pv-k">Presupuesto</span><span class="pv-v">$45.000</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="rule"></div>

<!-- PASOS -->
<section class="steps-section">
  <div class="steps-inner">
    <h2 class="steps-h2 reveal">Tres pasos.<br>Todo resuelto.</h2>
    <div class="steps-grid">
      <div class="step reveal">
        <div class="step-num">01</div>
        <h3 class="step-title">Crea la orden</h3>
        <p class="step-desc">Registra el equipo, el cliente y el problema en segundos. Imprime o comparte el ticket por WhatsApp.</p>
      </div>
      <div class="step reveal">
        <div class="step-num">02</div>
        <h3 class="step-title">Diagnostica y presupuesta</h3>
        <p class="step-desc">Añade el diagnóstico, los repuestos usados y el monto. El cliente aprueba desde su teléfono.</p>
      </div>
      <div class="step reveal">
        <div class="step-num">03</div>
        <h3 class="step-title">Cobra y entrega</h3>
        <p class="step-desc">Registra el pago, cierra la orden y actualiza el stock. Todo queda en el historial del cliente.</p>
      </div>
    </div>
  </div>
</section>

<div class="rule"></div>

<!-- PRECIOS -->
<section class="pricing-section" id="precios">
  <div class="pricing-inner">
    <h2 class="pricing-h2 reveal">Precio justo,<br>sin sorpresas</h2>
    <p class="pricing-sub reveal">Un solo plan con todo incluido. Elige el período que más te acomoda.</p>
    <div class="pricing-cards">
<?php
$descuentos = [1 => null, 3 => 7, 6 => 13, 12 => 17];
$labels = [12 => 'Mejor valor'];
foreach($planes as $plan):
  $meses = $plan['meses'];
  $precio_mes = round($plan['precio'] / $meses);
  $desc = $descuentos[$meses] ?? null;
  $label = $labels[$meses] ?? null;
  $featured = $plan['featured'];
?>
      <div class="pricing-card<?= $featured ? ' featured' : '' ?>">
        <?php if($label): ?><span class="pricing-badge"><?= htmlspecialchars($label) ?></span><?php endif; ?>
        <p class="pricing-plan"><?= htmlspecialchars($plan['nombre']) ?></p>
        <p class="pricing-price"><sup>$</sup><?= number_format($plan['precio'], 0, ',', '.') ?></p>
        <p class="pricing-por-mes">$<?= number_format($precio_mes, 0, ',', '.') ?>/mes</p>
        <p class="pricing-period">facturación <?= $meses === 1 ? 'mensual' : ($meses === 3 ? 'trimestral' : ($meses === 6 ? 'semestral' : 'anual · ahorras ' . $desc . '%')) ?></p>
        <ul class="pricing-features">
          <li>Órdenes ilimitadas</li>
          <li>Inventario de repuestos</li>
          <li>Portal de seguimiento</li>
          <li>Estadísticas completas</li>
          <?php if($desc): ?><li>Ahorra <?= $desc ?>% vs mensual</li><?php endif; ?>
        </ul>
        <a href="<?= BASE ?>/registro.php?plan=<?= urlencode($plan['key']) ?>" class="btn-primary" style="width:100%;justify-content:center">Empezar</a>
      </div>
<?php endforeach; ?>
    </div>
    <p class="pricing-nota">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Todos los planes incluyen 7 días de prueba gratis. Sin tarjeta de crédito.
    </p>
  </div>
</section>

<div class="rule"></div>

<!-- CTA FINAL -->
<section class="cta-section">
  <div class="cta-glow"></div>
  <div class="cta-inner">
    <div>
      <h2 class="cta-h2 reveal">Empieza hoy.<br>Gratis.</h2>
    </div>
    <div class="reveal">
      <p class="cta-desc">7 días para probar todo el sistema sin restricciones. Si no te convence, no pagas nada. Si te convence, ya no podrás trabajar sin él.</p>
      <a href="<?= BASE ?>/registro.php" class="btn-primary" style="font-size:1rem;padding:1rem 2.5rem">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        Crear cuenta gratis
      </a>
      <p class="cta-note">Sin tarjeta · Sin contrato · Cancela cuando quieras</p>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div>
      <a href="<?= BASE ?>/" style="display:inline-block;line-height:0">
        <svg class="footer-logo-svg" viewBox="0 0 680 210" xmlns="http://www.w3.org/2000/svg" aria-label="Centrotec">
          <path d="M 120 75 A 38 38 0 1 0 120 127" stroke="#50d2ff" stroke-width="11" fill="none" stroke-linecap="round"/>
          <line x1="120" y1="75" x2="138" y2="75" stroke="#50d2ff" stroke-width="1.8" stroke-linecap="round"/>
          <circle cx="141" cy="75" r="3" fill="#50d2ff"/>
          <line x1="141" y1="75" x2="141" y2="59" stroke="rgba(80,210,255,.5)" stroke-width="1.4"/>
          <line x1="120" y1="127" x2="138" y2="127" stroke="#50d2ff" stroke-width="1.8" stroke-linecap="round"/>
          <circle cx="141" cy="127" r="3" fill="#50d2ff"/>
          <line x1="141" y1="127" x2="141" y2="143" stroke="rgba(80,210,255,.5)" stroke-width="1.4"/>
          <line x1="54" y1="95" x2="72" y2="95" stroke="rgba(80,210,255,.45)" stroke-width="1.4"/>
          <circle cx="51" cy="95" r="3" fill="rgba(80,210,255,.6)"/>
          <line x1="54" y1="109" x2="72" y2="109" stroke="rgba(80,210,255,.45)" stroke-width="1.4"/>
          <circle cx="51" cy="109" r="3" fill="rgba(80,210,255,.6)"/>
          <g transform="translate(79,86) scale(0.62)">
            <path d="M-1 7 Q11 -3 23 7" stroke="rgba(80,210,255,.92)" stroke-width="2.8" fill="none" stroke-linecap="round"/>
            <rect x="-1" y="7" width="24" height="2.5" rx="1" fill="rgba(80,210,255,.7)"/>
            <rect x="3" y="9" width="16" height="12" rx="2.5" fill="none" stroke="rgba(210,235,255,.8)" stroke-width="1.5"/>
            <rect x="1" y="22" width="20" height="16" rx="2" fill="none" stroke="rgba(80,210,255,.75)" stroke-width="1.5"/>
            <rect x="2" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
            <rect x="12" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
            <rect x="-8" y="24" width="9" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
            <rect x="21" y="23" width="9" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
          </g>
          <text x="148" y="150" font-family="system-ui,-apple-system,'Segoe UI',Arial,sans-serif" font-size="82" font-weight="400" letter-spacing="4" fill="#e8f4ff">ENTR</text>
          <rect x="337" y="52" width="60" height="96" rx="3" fill="none" stroke="rgba(80,210,255,.58)" stroke-width="1.6"/>
          <rect x="343" y="59" width="48" height="72" rx="2" fill="#030507"/>
          <rect x="352" y="55" width="28" height="2.5" rx="1" fill="rgba(80,210,255,.35)"/>
          <circle cx="367" cy="80" r="8.5" fill="none" stroke="rgba(80,210,255,.52)" stroke-width="1.4"/>
          <circle cx="367" cy="80" r="3.5" fill="rgba(80,210,255,.45)"/>
          <line x1="355" y1="95" x2="379" y2="95" stroke="rgba(80,210,255,.32)" stroke-width="1.2"/>
          <line x1="355" y1="101" x2="367" y2="101" stroke="rgba(80,210,255,.22)" stroke-width="1.2"/>
          <circle cx="355" cy="117" r="4" fill="rgba(80,210,255,.65)"/>
          <circle cx="379" cy="117" r="4" fill="none" stroke="rgba(80,210,255,.45)" stroke-width="1.4"/>
          <line x1="351" y1="148" x2="341" y2="160" stroke="rgba(80,210,255,.35)" stroke-width="1.4" stroke-linecap="round"/>
          <line x1="385" y1="148" x2="395" y2="160" stroke="rgba(80,210,255,.35)" stroke-width="1.4" stroke-linecap="round"/>
          <line x1="337" y1="160" x2="356" y2="160" stroke="rgba(80,210,255,.3)" stroke-width="1.4" stroke-linecap="round"/>
          <line x1="378" y1="160" x2="399" y2="160" stroke="rgba(80,210,255,.3)" stroke-width="1.4" stroke-linecap="round"/>
          <text x="408" y="150" font-family="system-ui,-apple-system,'Segoe UI',Arial,sans-serif" font-size="82" font-weight="400" letter-spacing="4" fill="#e8f4ff">TEC</text>
          <line x1="563" y1="162" x2="544" y2="53" stroke="rgba(80,210,255,.42)" stroke-width="2" stroke-linecap="round"/>
          <line x1="576" y1="162" x2="557" y2="53" stroke="rgba(80,210,255,.42)" stroke-width="2" stroke-linecap="round"/>
          <line x1="546" y1="64" x2="559" y2="64" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
          <line x1="548" y1="81" x2="561" y2="81" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
          <line x1="549" y1="98" x2="562" y2="98" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
          <line x1="551" y1="115" x2="564" y2="115" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
          <line x1="553" y1="132" x2="566" y2="132" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
          <line x1="556" y1="149" x2="569" y2="149" stroke="rgba(80,210,255,.5)" stroke-width="1.6"/>
          <g transform="translate(539,68) scale(0.62)">
            <path d="M-1 7 Q11 -3 23 7" stroke="rgba(80,210,255,.92)" stroke-width="2.8" fill="none" stroke-linecap="round"/>
            <rect x="-1" y="7" width="24" height="2.5" rx="1" fill="rgba(80,210,255,.7)"/>
            <rect x="3" y="9" width="16" height="12" rx="2.5" fill="none" stroke="rgba(210,235,255,.8)" stroke-width="1.5"/>
            <rect x="1" y="22" width="20" height="16" rx="2" fill="none" stroke="rgba(80,210,255,.75)" stroke-width="1.5"/>
            <rect x="2" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
            <rect x="12" y="38" width="8" height="15" rx="2" fill="none" stroke="rgba(210,235,255,.6)" stroke-width="1.3"/>
            <rect x="-9" y="23" width="10" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
            <rect x="21" y="23" width="10" height="4" rx="2" fill="none" stroke="rgba(210,235,255,.52)" stroke-width="1.2"/>
          </g>
        </svg>
      </a>
      <p class="footer-tagline">Software de gestión para servicios técnicos. Hecho en Chile.</p>
    </div>
    <div>
      <p class="footer-col-head">Producto</p>
      <ul class="footer-links">
        <li><a href="#caracteristicas">Características</a></li>
        <li><a href="#precios">Precios</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-col-head">Clientes</p>
      <ul class="footer-links">
        <li><a href="<?= BASE ?>/app">Zona Clientes</a></li>
        <li><a href="<?= BASE ?>/seguimiento">Seguir mi reparación</a></li>
        <li><a href="<?= BASE ?>/registro.php">Crear cuenta</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-col-head">Contacto</p>
      <ul class="footer-links">
        <li><a href="mailto:centrotec@gmail.com">centrotec@gmail.com</a></li>
        <li><a href="<?= BASE ?>/app">Ingresar al sistema</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> Centrotec</span>
    <span>Hecho con dedicación en Chile</span>
  </div>
</footer>

<script src="<?= BASE ?>/assets/js/landing.js?v=<?= filemtime(__DIR__.'/assets/js/landing.js') ?>"></script>
</body>
</html>
