self.addEventListener('install', function(e) {
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  self.clients.claim();
});

self.addEventListener('fetch', function(event) {
  // Можно добавить кэширование ресурсов
});
