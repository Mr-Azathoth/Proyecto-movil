const CACHE = 'centrotec-v1';
const OFFLINE_ASSETS = [
  'offline.html',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(OFFLINE_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Red primero, fallback a offline.html solo si falla la red
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  if (!e.request.url.startsWith(self.location.origin)) return;

  e.respondWith(
    fetch(e.request).catch(() =>
      caches.match('offline.html').then(r => r || new Response('Sin conexión', { status: 503 }))
    )
  );
});
