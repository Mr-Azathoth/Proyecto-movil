'use strict';
(function () {
  var lb, lbImg;

  function init() {
    lb    = document.getElementById('_seg-lb');
    lbImg = document.getElementById('_seg-lb-img');
    if (!lb) return;

    document.getElementById('_seg-lb-bg').addEventListener('click', close);
    document.getElementById('_seg-lb-close').addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // Delegated click on all foto-strip images
    document.addEventListener('click', function (e) {
      var img = e.target.closest('.seg-foto-strip img');
      if (img) open(img.src);
    });
  }

  function open(src) {
    if (!lb) return;
    lbImg.src = src;
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function close() {
    if (!lb) return;
    lb.style.display = 'none';
    document.body.style.overflow = '';
  }

  window.segOpenLightbox  = open;
  window.segCloseLightbox = close;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
