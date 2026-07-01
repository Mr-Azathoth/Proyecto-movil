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
