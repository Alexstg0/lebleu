// Utilidades compartidas: registro del service worker (PWA) y envío de JSON.
(function () {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  }

  window.Lebleu = window.Lebleu || {};

  // POST de JSON; devuelve {ok, ...} aunque el servidor responda con error o texto.
  window.Lebleu.enviarJson = function (url, cuerpo) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(cuerpo || {}),
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false }; });
    });
  };

  window.Lebleu.recargar = function () { window.location.reload(); };
})();
