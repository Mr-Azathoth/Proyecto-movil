<?php
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Política de Privacidad — Centrotec</title>
<link rel="icon" type="image/x-icon" href="<?= BASE ?>/assets/img/favicon.ico">
<meta name="description" content="Política de privacidad y tratamiento de datos personales de Centrotec.">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/landing.css?v=<?= filemtime(__DIR__.'/assets/css/landing.css') ?>">
<style>
.priv-wrap{max-width:780px;margin:0 auto;padding:calc(80px + 3rem) var(--inset) 5rem}
.priv-eyebrow{font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:var(--cyan-hi);opacity:.7;margin-bottom:1rem}
.priv-h1{font-size:clamp(1.75rem,4vw,3rem);font-weight:200;text-transform:uppercase;letter-spacing:.08em;color:var(--text-main);margin-bottom:.5rem;line-height:1.05}
.priv-meta{font-size:.8rem;color:var(--text-dim);margin-bottom:3rem;padding-bottom:1.5rem;border-bottom:1px solid rgba(80,180,255,.07)}
.priv-section{margin-bottom:2.5rem}
.priv-h2{font-size:1rem;font-weight:400;text-transform:uppercase;letter-spacing:.1em;color:var(--cyan-hi);margin-bottom:.875rem;opacity:.85}
.priv-p{font-size:.9rem;color:var(--text-sub);line-height:1.8;margin-bottom:.875rem;font-weight:300}
.priv-p strong{color:var(--text-main);font-weight:400}
.priv-list{list-style:none;margin:.5rem 0 .875rem;display:flex;flex-direction:column;gap:.5rem}
.priv-list li{font-size:.875rem;color:var(--text-sub);line-height:1.7;font-weight:300;display:flex;gap:.75rem}
.priv-list li::before{content:'—';color:var(--text-dim);flex-shrink:0}
.priv-sep{height:1px;background:rgba(80,180,255,.06);margin:2.5rem 0}
.priv-contact{background:rgba(4,10,20,.6);border:1px solid rgba(80,180,255,.08);border-radius:4px;padding:1.5rem 2rem;margin-top:2rem}
.priv-contact p{font-size:.875rem;color:var(--text-sub);line-height:1.75;font-weight:300}
.priv-contact a{color:var(--cyan-hi);text-decoration:none;opacity:.85}
.priv-contact a:hover{opacity:1}
</style>
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
      <text x="148" y="150" font-family="system-ui,-apple-system,'Segoe UI',Arial,sans-serif" font-size="82" font-weight="400" letter-spacing="4" fill="#e8f4ff">ENTR</text>
      <text x="408" y="150" font-family="system-ui,-apple-system,'Segoe UI',Arial,sans-serif" font-size="82" font-weight="400" letter-spacing="4" fill="#e8f4ff">TEC</text>
    </svg>
  </a>
  <ul class="nav-links">
    <li><a href="<?= BASE ?>/" class="nav-ghost nav-cta">← Volver al inicio</a></li>
  </ul>
</nav>

<div class="priv-wrap">
  <p class="priv-eyebrow">Legal</p>
  <h1 class="priv-h1">Política de<br>Privacidad</h1>
  <p class="priv-meta">Última actualización: <?= date('d') ?> de <?= ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][date('n')-1] ?> de <?= date('Y') ?></p>

  <div class="priv-section">
    <p class="priv-p">Centrotec (<strong>"la Plataforma"</strong>) es un servicio de software orientado a servicios técnicos y talleres de reparación. Esta política describe cómo recopilamos, usamos y protegemos los datos personales de quienes usan nuestra plataforma, de conformidad con la <strong>Ley N° 19.628</strong> sobre Protección de la Vida Privada de Chile y sus modificaciones vigentes.</p>
    <p class="priv-p">Al registrarte o utilizar Centrotec, aceptas las prácticas descritas en este documento.</p>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">1. Quién es el responsable</h2>
    <p class="priv-p">El responsable del tratamiento de los datos de los <strong>clientes de la plataforma</strong> (dueños y técnicos de talleres) es Centrotec, contactable en <a href="mailto:centrotec@gmail.com" style="color:var(--cyan-hi);opacity:.85;text-decoration:none">centrotec@gmail.com</a>.</p>
    <p class="priv-p">Los datos de los <strong>clientes finales de cada taller</strong> (personas que llevan equipos a reparar) son responsabilidad del taller que los ingresa. Centrotec actúa como encargado de tratamiento para esos datos.</p>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">2. Qué datos recopilamos</h2>
    <p class="priv-p"><strong>De los talleres registrados:</strong></p>
    <ul class="priv-list">
      <li>Nombre del local, RUT, dirección, comuna</li>
      <li>Correo electrónico y teléfono de contacto</li>
      <li>Nombre del administrador de la cuenta</li>
      <li>Datos de pago procesados por MercadoPago (no almacenamos datos de tarjetas)</li>
      <li>Logo del negocio (opcional)</li>
      <li>Registros de actividad y accesos al sistema</li>
    </ul>
    <p class="priv-p"><strong>De los clientes finales de cada taller (ingresados por el taller):</strong></p>
    <ul class="priv-list">
      <li>Nombre completo y RUT (opcional)</li>
      <li>Teléfono de contacto</li>
      <li>Información del equipo en reparación (marca, modelo, falla reportada)</li>
      <li>Fotografías del equipo (opcionales)</li>
      <li>Historial de estados y observaciones técnicas</li>
    </ul>
    <p class="priv-p"><strong>De visitantes del sitio web:</strong></p>
    <ul class="priv-list">
      <li>Dirección IP (para protección contra abuso y rate limiting)</li>
      <li>Cookies de sesión estrictamente necesarias</li>
    </ul>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">3. Para qué usamos los datos</h2>
    <ul class="priv-list">
      <li>Proveer y mantener el servicio de gestión de reparaciones</li>
      <li>Gestionar la facturación y suscripciones de los talleres</li>
      <li>Enviar notificaciones relacionadas con el servicio (vencimiento de plan, confirmaciones)</li>
      <li>Permitir que los clientes finales hagan seguimiento de sus reparaciones mediante un código único</li>
      <li>Detectar y prevenir fraudes o usos abusivos de la plataforma</li>
      <li>Mejorar el servicio a través de estadísticas agregadas y anónimas</li>
    </ul>
    <p class="priv-p">No utilizamos los datos para publicidad de terceros ni los vendemos a ninguna empresa.</p>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">4. Terceros que procesan datos</h2>
    <ul class="priv-list">
      <li><strong>MercadoPago</strong> — procesamiento de pagos de suscripciones. Consulta su política en mercadopago.cl</li>
      <li><strong>Servidor de correo SMTP</strong> — envío de emails transaccionales (confirmaciones, recuperación de contraseña)</li>
      <li><strong>Proveedor de hosting</strong> — almacenamiento de los datos en servidores ubicados en Chile o Latinoamérica</li>
    </ul>
    <p class="priv-p">Todos los terceros están comprometidos contractualmente a tratar los datos solo para los fines descritos.</p>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">5. Cuánto tiempo guardamos los datos</h2>
    <ul class="priv-list">
      <li>Datos de talleres activos: mientras la cuenta esté activa</li>
      <li>Tras cancelación de cuenta: los datos se eliminan dentro de 90 días, salvo obligación legal de conservarlos</li>
      <li>Datos de clientes finales: son gestionados por el taller. El taller puede eliminarlos desde el panel en cualquier momento</li>
      <li>Registros de actividad (logs): máximo 12 meses</li>
    </ul>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">6. Seguridad</h2>
    <p class="priv-p">Aplicamos medidas técnicas y organizativas para proteger los datos:</p>
    <ul class="priv-list">
      <li>Transmisión cifrada mediante HTTPS/TLS</li>
      <li>Contraseñas almacenadas con hash bcrypt (nunca en texto plano)</li>
      <li>Tokens de sesión seguros con expiración automática</li>
      <li>Acceso a la base de datos restringido por red y credenciales</li>
      <li>Separación de datos entre talleres (cada empresa solo ve sus propios datos)</li>
    </ul>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">7. Tus derechos</h2>
    <p class="priv-p">De acuerdo con la legislación chilena vigente, tienes derecho a:</p>
    <ul class="priv-list">
      <li><strong>Acceder</strong> a los datos personales que tenemos sobre ti</li>
      <li><strong>Rectificar</strong> datos incorrectos o desactualizados</li>
      <li><strong>Eliminar</strong> tus datos cuando ya no sean necesarios para el fin con que fueron recopilados</li>
      <li><strong>Oponerte</strong> al tratamiento de tus datos en determinadas circunstancias</li>
    </ul>
    <p class="priv-p">Para ejercer estos derechos, escríbenos a <a href="mailto:centrotec@gmail.com" style="color:var(--cyan-hi);opacity:.85;text-decoration:none">centrotec@gmail.com</a>. Respondemos en un plazo máximo de 15 días hábiles.</p>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">8. Cookies</h2>
    <p class="priv-p">Usamos únicamente cookies estrictamente necesarias para el funcionamiento del servicio (sesión de usuario, token CSRF). No usamos cookies de rastreo, analítica de terceros ni publicidad.</p>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-section">
    <h2 class="priv-h2">9. Cambios a esta política</h2>
    <p class="priv-p">Podemos actualizar esta política cuando sea necesario. Cuando hagamos cambios relevantes, lo notificaremos a los administradores de talleres registrados por correo electrónico con al menos 15 días de anticipación.</p>
  </div>

  <div class="priv-sep"></div>

  <div class="priv-contact">
    <h2 class="priv-h2" style="margin-bottom:.625rem">Contacto</h2>
    <p>Para consultas sobre privacidad o ejercicio de tus derechos:<br>
      <a href="mailto:centrotec@gmail.com">centrotec@gmail.com</a><br>
      Centrotec · Chile
    </p>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <div>
      <p class="footer-tagline">Software de gestión para servicios técnicos. Hecho en Chile.</p>
    </div>
    <div>
      <p class="footer-col-head">Producto</p>
      <ul class="footer-links">
        <li><a href="<?= BASE ?>/landing.php#caracteristicas">Características</a></li>
        <li><a href="<?= BASE ?>/landing.php#precios">Precios</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-col-head">Legal</p>
      <ul class="footer-links">
        <li><a href="<?= BASE ?>/privacidad.php">Política de Privacidad</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-col-head">Contacto</p>
      <ul class="footer-links">
        <li><a href="mailto:centrotec@gmail.com">centrotec@gmail.com</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> Centrotec</span>
    <span>Hecho con dedicación en Chile</span>
  </div>
</footer>

</body>
</html>
