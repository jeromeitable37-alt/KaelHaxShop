const CACHE_NAME = 'kaelhax-shell-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  // Network-first. The portal is dynamic and authentication-sensitive,
  // so we avoid caching HTML or API responses as persistent app data.
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
