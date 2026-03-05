const CACHE_NAME = 'tirana-solidare-v1';
const ASSETS = [
  '/TiranaSolidare/public/',
  '/TiranaSolidare/public/assets/styles/main.css',
  '/TiranaSolidare/public/assets/styles/index.css',
  '/TiranaSolidare/public/assets/scripts/main.js'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
});

self.addEventListener('fetch', event => {
  // HTML faqet - gjithmonë nga rrjeti
  if (event.request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(fetch(event.request));
    return;
  }
  // Assets (CSS, JS) - nga cache
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request);
    })
  );
});