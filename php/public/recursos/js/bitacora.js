// Bitácora: filtro por mes, editar viaje en ventana (formulario del servidor) y borrar.
(function () {
  var L = window.Lebleu;

  document.addEventListener('change', function (e) {
    var f = e.target.closest('[data-filtro-mes]');
    if (!f) return;
    var p = f.value.split('-');
    window.location.href = '/bitacora?y=' + p[0] + '&m=' + p[1];
  });

  function cerrar(capa) { capa.remove(); }

  document.addEventListener('click', function (e) {
    var editar = e.target.closest('[data-editar-viaje]');
    if (editar) {
      editar.disabled = true;
      fetch('/movimientos/formulario?k=vi:' + encodeURIComponent(editar.getAttribute('data-editar-viaje')), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.text() : r.json().then(function (j) { throw new Error(j.error || 'No se pudo abrir.'); }); })
        .then(function (html) {
          var capa = document.createElement('div');
          capa.className = 'modal-overlay';
          capa.innerHTML = '<div class="modal" style="width:min(720px,96vw);max-width:min(720px,96vw);max-height:92vh;overflow-y:auto">' +
            '<h2 style="margin:0 0 12px;font-size:18px">Editar viaje</h2>' + html + '</div>';
          capa.addEventListener('click', function (ev) {
            if (ev.target === capa || ev.target.closest('[data-cancelar]')) cerrar(capa);
          });
          // Igual que el original: la ventana vive dentro de la celda de acciones (hereda su alineación).
          editar.closest('td').appendChild(capa);
          L.iniciarFormularios(capa);
        })
        .catch(function (err) { alert(err.message); })
        .then(function () { editar.disabled = false; });
      return;
    }
    var borrar = e.target.closest('[data-borrar-viaje]');
    if (borrar) {
      if (!confirm('¿Eliminar el viaje de ' + borrar.getAttribute('data-etiqueta') + '? Esta acción no se puede deshacer.')) return;
      borrar.disabled = true;
      fetch('/api/viajes?id=' + encodeURIComponent(borrar.getAttribute('data-borrar-viaje')), { method: 'DELETE' })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (j) {
          borrar.disabled = false;
          if (j.ok) L.recargar(); else alert(j.error || 'No se pudo eliminar el viaje.');
        });
    }
  });
})();
