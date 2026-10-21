/* Service worker de la web pública: permite instalarla como app y abrirla sin conexión. */
const CACHE = 'web-v24';
const SHELL = ['./', 'assets/web.css?v=24', 'assets/web.js?v=24', 'icon.php?s=192', 'manifest.php',
  'assets/fonts/bodoni-moda-latin-opsz-normal.woff2', 'assets/fonts/hanken-grotesk-latin-wght-normal.woff2'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys()
    .then((keys) => Promise.all(keys.filter((k) => k.startsWith('web-') && k !== CACHE).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin) return;
  // Nunca guardar la API, el panel, la cuenta ni el instalador: siempre datos frescos
  if (/\/(api|panel)\//.test(url.pathname) || /(install|cuenta)\.php$/.test(url.pathname)) return;
  // Páginas: primero la red (carta y horario al día); sin conexión, la copia guardada
  e.respondWith(
    fetch(e.request).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(e.request, copy)); }
      return res;
    }).catch(() => caches.match(e.request).then((r) => r || caches.match('./')))
  );
});
