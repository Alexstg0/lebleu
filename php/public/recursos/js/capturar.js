// Pantalla Capturar (administrador): pestañas entre los formularios. La pestaña queda en la URL (#gasto).
(function () {
  var botones = document.querySelectorAll('[data-tab]');
  if (!botones.length) return;

  function activar(clave) {
    var existe = document.querySelector('[data-panel="' + clave + '"]');
    if (!existe) clave = botones[0].getAttribute('data-tab');
    botones.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tab') === clave); });
    document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== clave; });
    try { history.replaceState(null, '', '#' + clave); } catch (e) {}
  }

  botones.forEach(function (b) {
    b.addEventListener('click', function () { activar(b.getAttribute('data-tab')); });
  });
  activar(window.location.hash.replace('#', '') || botones[0].getAttribute('data-tab'));
})();
