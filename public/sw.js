const CACHE_NAME = 'tirana-solidare-v4';
const SW_BASE_PATH = (() => {
  const pathname = new URL(self.location.href).pathname;
  return pathname.endsWith('/sw.js') ? pathname.slice(0, -('/sw.js'.length)) : '';
})();
const swPath = (path = '') => {
  const trimmed = String(path || '').replace(/^\/+/, '');
  if (!trimmed) {
    return SW_BASE_PATH || '/';
  }
  return `${SW_BASE_PATH}/${trimmed}`.replace(/\/+/g, '/');
};
const ASSETS = [
  swPath('public/'),
  swPath('public/assets/styles/main.css'),
  swPath('public/assets/styles/index.css'),
  swPath('public/assets/scripts/main.js')
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
  // HTML pages - always from network
  if (event.request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(fetch(event.request));
    return;
  }
  // Images - network first, fall back to cache
  if (event.request.destination === 'image') {
    event.respondWith(
      fetch(event.request).then(response => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        return response;
      }).catch(() => caches.match(event.request))
    );
    return;
  }
  // Other assets (CSS, JS) - cache first, then network
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
    icon: swPath('public/assets/images/icon-192.png'),
    badge: swPath('public/assets/images/icon-192.png'),
    data: { url: payload.url || swPath('public/') },
    requireInteraction: false,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = event.notification.data?.url || swPath('public/');
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        const clientPath = new URL(client.url).pathname;
        const scopePrefix = SW_BASE_PATH === '' ? '/' : (SW_BASE_PATH + '/');
        if (clientPath.startsWith(scopePrefix) && 'focus' in client) {
          client.navigate(target);
          return client.focus();
        }
      }
      return clients.openWindow(target);
    })
  );
});