/**
 * Sate Tulang Madu - Progressive Web App Service Worker
 */

const CACHE_NAME = 'satay-cache-v1.1';
const STATIC_ASSETS = [
  './',
  'index.php',
  'login.php',
  'kitchen.php',
  'admin.php',
  'assets/css/style.css',
  'assets/js/app.js',
  'assets/js/customer.js',
  'assets/js/client.js',
  'assets/js/admin.js',
  'manifest.json',
  'assets/images/icon-192.png',
  'assets/images/icon-512.png'
];

// Install Event: Pre-cache core shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('Some static assets failed to pre-cache:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate Event: Clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event: Network-first for dynamic API & PHP; Stale-while-revalidate for static assets
self.addEventListener('fetch', (event) => {
  const requestUrl = new URL(event.request.url);

  // Bypass service worker caching for API calls, POST requests, and external resources
  if (
    event.request.method !== 'GET' ||
    requestUrl.pathname.includes('/api/') ||
    requestUrl.searchParams.has('track') ||
    requestUrl.searchParams.has('action')
  ) {
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      const fetchPromise = fetch(event.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      }).catch(() => {
        return cachedResponse;
      });

      // For static assets (CSS, JS, images), return cached immediately if available
      if (
        requestUrl.pathname.endsWith('.css') ||
        requestUrl.pathname.endsWith('.js') ||
        requestUrl.pathname.endsWith('.png') ||
        requestUrl.pathname.endsWith('.jpg') ||
        requestUrl.pathname.endsWith('.svg')
      ) {
        return cachedResponse || fetchPromise;
      }

      // For HTML / PHP documents, prefer network, fallback to cache
      return fetchPromise.catch(() => cachedResponse);
    })
  );
});
