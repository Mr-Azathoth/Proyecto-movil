if ('serviceWorker' in navigator) {
  var base = document.querySelector('meta[name="base-path"]')?.content || '';
  navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' });
}

var _pwaPrompt = null;

window.addEventListener('beforeinstallprompt', function(e) {
  e.preventDefault();
  _pwaPrompt = e;
  document.querySelectorAll('.btn-pwa-install').forEach(function(btn) {
    btn.style.display = '';
  });
});

window.addEventListener('appinstalled', function() {
  _pwaPrompt = null;
  document.querySelectorAll('.btn-pwa-install').forEach(function(btn) {
    btn.style.display = 'none';
  });
});

window.pwaInstall = function() {
  if (!_pwaPrompt) return;
  _pwaPrompt.prompt();
  _pwaPrompt.userChoice.then(function() {
    _pwaPrompt = null;
    document.querySelectorAll('.btn-pwa-install').forEach(function(btn) {
      btn.style.display = 'none';
    });
  });
};
