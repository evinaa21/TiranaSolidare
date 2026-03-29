const CACHE_NAME = 'tirana-solidare-v2';
const ASSETS = [
  '/TiranaSolidare/public/',
  '/TiranaSolidare/public/assets/styles/main.css',
  '/TiranaSolidare/public/assets/styles/index.css',
  '/TiranaSolidare/public/assets/scripts/main.js'
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
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

// ── Web Push ─────────────────────────────────────────────────────────────────

self.addEventListener('push', event => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    payload = { title: 'Tirana Solidare', body: event.data.text() };
  }

  const title   = payload.title || 'Tirana Solidare';
  const options = {
    body: payload.body || '',
    icon: '/TiranaSolidare/public/assets/images/icon-192.png',
    badge: '/TiranaSolidare/public/assets/images/icon-192.png',
    data: { url: payload.url || '/TiranaSolidare/public/' },
    requireInteraction: false,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = event.notification.data?.url || '/TiranaSolidare/public/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if (client.url.includes('/TiranaSolidare/') && 'focus' in client) {
          client.navigate(target);
          return client.focus();
        }
      }
      return clients.openWindow(target);
    })
  );
});