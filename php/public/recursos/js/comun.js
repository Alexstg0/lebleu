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

  // Registros que llenan los módulos de cada formulario (clave = data-calculo del <form>).
  window.Lebleu.armadores = {}; // armadores[k](form) -> carga JSON, o null para cancelar el envío
  window.Lebleu.despues = {};   // despues[k](form)    -> limpieza especial tras guardar un alta

  // Equivale a router.refresh(): vuelve a pedir la página y actualiza los datos que cambian solos
  // (la lista de viajes del periodo) sin tocar lo que el usuario está escribiendo.
  window.Lebleu.refrescar = function () {
    return fetch(window.location.href, { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        document.querySelectorAll('[data-refrescable]').forEach(function (zona) {
          var nueva = doc.querySelector('[data-refrescable="' + zona.getAttribute('data-refrescable') + '"]');
          if (nueva) zona.innerHTML = nueva.innerHTML;
        });
        var nuevo = doc.getElementById('datos-viajes');
        var actual = document.getElementById('datos-viajes');
        if (nuevo && actual) {
          actual.textContent = nuevo.textContent;
          document.dispatchEvent(new CustomEvent('lebleu:datos'));
        }
      })
      .catch(function () {});
  };

  window.Lebleu.viajes = function () {
    var el = document.getElementById('datos-viajes');
    try { return el ? JSON.parse(el.textContent) : []; } catch (e) { return []; }
  };
})();
