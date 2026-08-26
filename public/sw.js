// Service worker mínimo: habilita la instalación como app (PWA).
// Sin caché agresivo para no servir páginas con sesión desactualizadas.
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));
self.addEventListener("fetch", () => {
  // passthrough: el navegador maneja la petición normalmente.
});
