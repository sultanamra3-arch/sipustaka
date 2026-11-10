/* Service worker sederhana: simpan berkas tampilan, halaman selalu diambil dari server. */
const CACHE = 'kelola-v1.13';
const ASET = ['aset/gaya.css?v=1.13', 'aset/app.js?v=1.13', 'aset/logo.png', 'aset/favicon.png', 'offline.html'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASET)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(k => Promise.all(k.filter(n => n !== CACHE).map(n => caches.delete(n))))
    .then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const r = e.request;
  if (r.method !== 'GET') return;
  const url = new URL(r.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.includes('/aset/')) {
    e.respondWith(caches.match(r).then(c => c || fetch(r)));
    return;
  }
  if (r.mode === 'navigate') {
    e.respondWith(fetch(r).catch(() => caches.match('offline.html')));
  }
});
