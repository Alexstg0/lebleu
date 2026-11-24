// Visor de PDF dentro de la app: filas con data-pdf y botones con data-visor-pdf abren un modal con iframe.
(function () {
  function abrir(href, titulo, soloDocumento) {
    var capa = document.createElement('div');
    capa.className = 'modal-overlay';
    capa.innerHTML =
      '<div class="adj-viewer"><div class="adj-viewer-head"><span></span>' +
      '<span style="display:flex;gap:10px"><a target="_blank" rel="noopener" class="btn-link"></a>' +
      '<button class="btn-link">Cerrar</button></span></div><iframe></iframe></div>';
    capa.querySelector('.adj-viewer-head span').textContent = titulo;
    var enlace = capa.querySelector('a');
    enlace.href = href;
    enlace.textContent = soloDocumento ? 'Abrir en pestaña' : 'Descargar / pestaña';
    var marco = capa.querySelector('iframe');
    marco.src = href;
    marco.title = titulo;
    function cerrar() { capa.remove(); }
    capa.addEventListener('click', cerrar);
    capa.querySelector('.adj-viewer').addEventListener('click', function (e) { e.stopPropagation(); });
    capa.querySelector('button').addEventListener('click', cerrar);
    document.body.appendChild(capa);
  }

  document.addEventListener('click', function (e) {
    var boton = e.target.closest('[data-visor-pdf]');
    if (boton) {
      abrir(boton.getAttribute('data-visor-pdf'), boton.getAttribute('data-titulo') || 'Reporte', boton.hasAttribute('data-solo-documento'));
      return;
    }
    var fila = e.target.closest('tr[data-pdf]');
    if (fila && !e.target.closest('button, a')) {
      abrir(fila.getAttribute('data-pdf'), 'Documento de respaldo', true);
    }
  });
})();
