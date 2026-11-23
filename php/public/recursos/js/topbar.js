// Barra superior: menús desplegables (escritorio), menú lateral (móvil), selector de periodo y salir.
(function () {
  var nav = document.querySelector('[data-nav]');
  var drawer = document.querySelector('[data-drawer]');
  var overlay = document.querySelector('.drawer-overlay');

  function cerrarMenus(excepto) {
    document.querySelectorAll('[data-menu]').forEach(function (b) {
      if (b === excepto) return;
      b.setAttribute('aria-expanded', 'false');
      b.parentNode.querySelector('.nav-menu').hidden = true;
    });
  }

  document.querySelectorAll('[data-menu]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var menu = btn.parentNode.querySelector('.nav-menu');
      var abrir = menu.hidden;
      cerrarMenus(btn);
      menu.hidden = !abrir;
      btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    });
  });

  document.addEventListener('mousedown', function (e) {
    if (nav && !nav.contains(e.target)) cerrarMenus(null);
  });
  if (nav) nav.querySelectorAll('.nav-menu a').forEach(function (a) {
    a.addEventListener('click', function () { cerrarMenus(null); });
  });

  function abrirDrawer(abierto) {
    if (!drawer) return;
    drawer.classList.toggle('open', abierto);
    drawer.setAttribute('aria-hidden', abierto ? 'false' : 'true');
    if (overlay) overlay.classList.toggle('show', abierto);
  }
  document.querySelectorAll('[data-abrir-menu]').forEach(function (b) {
    b.addEventListener('click', function () { abrirDrawer(true); });
  });
  document.querySelectorAll('[data-cerrar-menu]').forEach(function (b) {
    b.addEventListener('click', function () { abrirDrawer(false); });
  });

  document.querySelectorAll('[data-periodo-select]').forEach(function (s) {
    s.addEventListener('change', function () { window.location.href = '/?periodo=' + encodeURIComponent(s.value); });
  });

  document.querySelectorAll('[data-salir]').forEach(function (b) {
    b.addEventListener('click', function () {
      fetch('/api/logout', { method: 'POST' }).finally(function () { window.location.href = '/login'; });
    });
  });
})();
