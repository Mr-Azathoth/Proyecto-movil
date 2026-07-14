if ('serviceWorker' in navigator) {
  var base = document.querySelector('meta[name="base-path"]')?.content || '';
  navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' });
}
