const CACHE_NAME = 'athkar-web-shell-v6';
const FILES_TO_CACHE = [
  '/',
  '/index.php',
  '/section.php',
  '/css/style.css',
  '/js/home.js',
  '/js/app.js',
  '/js/storage.js',
  '/js/theme.js',
  '/js/i18n.js',
  '/manifest.php'
];
self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(FILES_TO_CACHE)));
});
self.addEventListener('fetch', (event) => {
  event.respondWith(caches.match(event.request).then((response) => response || fetch(event.request)));
});
self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.map((key) => (key !== CACHE_NAME ? caches.delete(key) : null)))));
});
