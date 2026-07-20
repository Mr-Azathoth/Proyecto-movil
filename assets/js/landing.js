(function () {
  'use strict';

  var nav    = document.getElementById('nav');
  var burger = document.getElementById('nav-burger');
  var links  = document.getElementById('nav-links');
  var icon   = document.getElementById('burger-icon');

  // ── Navbar scrolled class ──────────────────────────────────
  function onScroll() {
    nav.classList.toggle('scrolled', window.scrollY > 20);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ── Hamburger ──────────────────────────────────────────────
  burger.addEventListener('click', function () {
    var open = links.classList.toggle('open');
    icon.textContent = open ? 'close' : 'menu';
    burger.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
  });

  links.addEventListener('click', function (e) {
    if (e.target.classList.contains('nav-link') || e.target.classList.contains('nav-cta')) {
      links.classList.remove('open');
      icon.textContent = 'menu';
    }
  });

  document.addEventListener('click', function (e) {
    if (!nav.contains(e.target) && links.classList.contains('open')) {
      links.classList.remove('open');
      icon.textContent = 'menu';
    }
  });

  // ── Active nav link on scroll ──────────────────────────────
  var sectionIds = ['hero', 'caracteristicas', 'cta', 'precios', 'contacto'];
  var navLinks   = document.querySelectorAll('.nav-link');

  function setActive() {
    var y = window.scrollY + 80;
    var current = 'hero';
    sectionIds.forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.offsetTop <= y) current = id;
    });
    navLinks.forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('href') === '#' + current);
    });
  }
  window.addEventListener('scroll', setActive, { passive: true });
  setActive();

  // ── Scroll animations (Intersection Observer) ──────────────
  var animEls = document.querySelectorAll('.anim');
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    animEls.forEach(function (el) { observer.observe(el); });
  } else {
    // Fallback: show all immediately
    animEls.forEach(function (el) { el.classList.add('visible'); });
  }

  // ── Características — panel interactivo ───────────────────
  var featData = {
    servicios: {
      title: 'Servicios',
      desc:  'Crea y gestiona órdenes de trabajo digitales. Registra el equipo, la falla, el técnico asignado y el estado de cada reparación en tiempo real.',
      pills: ['Órdenes de trabajo','Estados en tiempo real','Código de seguimiento','Historial por cliente','Creación de informes','Historial de trabajo']
    },
    inventario: {
      title: 'Inventario',
      desc:  'Controla tus repuestos y materiales. Importa tu inventario existente en masa, escanea piezas con QR y mantén el stock siempre actualizado.',
      pills: ['Stock de repuestos','Importar inventario masivamente','Escanear con QR','Creación de informes']
    },
    estadisticas: {
      title: 'Estadísticas',
      desc:  'Visualiza cuántas órdenes cierras por mes, los servicios más frecuentes y conoce cuáles son tus repuestos y marcas más vendidas.',
      pills: ['Órdenes por período','Servicios frecuentes','Repuestos más vendidos','Marcas más vendidas','Resumen del mes']
    },
    configuracion: {
      title: 'Configuración',
      desc:  'Personaliza los datos de tu empresa, gestiona tu plan de suscripción y administra el equipo de técnicos con roles y permisos.',
      pills: ['Datos de empresa','Gestionar plan','Crear y gestionar técnicos','Permisos por cargo']
    },
    soporte: {
      title: 'Soporte',
      desc:  'Contacta directamente al equipo de Centrotec ante cualquier duda o problema.',
      pills: ['Contacto directo']
    }
  };

  function renderFeat(key) {
    var d = featData[key];
    if (!d) return;
    document.getElementById('feat-title').textContent = d.title;
    document.getElementById('feat-desc').textContent  = d.desc;
    document.getElementById('feat-pills').innerHTML   = d.pills
      .map(function (p) { return '<span class="feat-pill">' + p + '</span>'; })
      .join('');
  }

  document.querySelectorAll('.feat-nav-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.feat-nav-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      renderFeat(btn.dataset.feat);
    });
  });

  // ── FAQ accordion ──────────────────────────────────────────
  document.querySelectorAll('.faq-q').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.faq-item');
      var isOpen = item.classList.contains('open');
      // Close all
      document.querySelectorAll('.faq-item.open').forEach(function (el) {
        el.classList.remove('open');
      });
      // Open clicked (toggle)
      if (!isOpen) item.classList.add('open');
    });
  });

})();
