// public/sw.js

const CACHE_NAME = 'flighttracker-v1';
const ASSETS = [
  '/',
  '/public/index.php',
  '/public/assets/css/styles.css',
  '/public/assets/js/app.js',
  '/public/manifest.json',
  '/public/assets/images/icons/icon-192x192.png'
];

// Install: precachear assets críticos
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate: limpiar caches antiguos
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) => 
      Promise.all(
        names.filter((name) => name !== CACHE_NAME)
             .map((name) => caches.delete(name))
      )
    ).then(() => self.clients.claim())
  );
});

// Fetch: estrategia Network First con fallback a cache
self.addEventListener('fetch', (event) => {
  // Ignorar requests de API (siempre frescos)
  if (event.request.url.includes('/api/')) {
    return;
  }
  
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Clonar y guardar en cache si es exitoso
        const responseClone = response.clone();
        if (response.ok) {
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});

// Push notifications handler
self.addEventListener('push', (event) => {
  const data = event.data?.json() || {};
  
  self.registration.showNotification(data.title || 'FlightTracker', {
    body: data.body || 'Tienes una actualización de vuelo',
    icon: '/public/assets/images/icons/icon-192x192.png',
    badge: '/public/assets/images/icons/badge-72x72.png',
    data: { url: data.url || '/public/index.php' },
    actions: [
      { action: 'open', title: 'Ver vuelo' },
      { action: 'dismiss', title: 'Cerrar' }
    ]
  });
});

// Click en notificación
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  if (event.action === 'open' || !event.action) {
    event.waitUntil(
      clients.matchAll({ type: 'window' })
        .then((clients) => {
          if (clients.length > 0) {
            clients[0].focus();
          } else {
            clients.openWindow(event.notification.data.url);
          }
        })
    );
  }
});