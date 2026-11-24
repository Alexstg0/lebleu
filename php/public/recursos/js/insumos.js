// Formulario de insumos: el viaje es obligatorio y la lista de viajes se actualiza sola.
(function () {
  var L = window.Lebleu;

  function fechaCorta(iso) {
    var p = String(iso).slice(0, 10).split('-');
    return p.length === 3 ? p[2] + '/' + p[1] : String(iso);
  }

  function pintarViajes() {
    var viajes = L.viajes();
    document.querySelectorAll('[data-calculo="insumo"]').forEach(function (f) {
      var sel = f.querySelector('[data-viajes]');
      var actual = sel.value;
      sel.innerHTML = '<option value="">— Selecciona un viaje —</option>';
      viajes.forEach(function (v) {
        var o = document.createElement('option');
        o.value = v.id;
        o.textContent = fechaCorta(v.fecha) + ' · ' + v.cliente;
        sel.appendChild(o);
      });
      sel.value = actual || (viajes[0] ? String(viajes[0].id) : '');
      var aviso = f.querySelector('[data-sin-viajes]');
      if (aviso) aviso.hidden = viajes.length > 0;
      f.querySelector('[data-boton]').disabled = viajes.length === 0;
    });
  }

  L.armadores.insumo = function (f) {
    if (!f.elements.viaje_id.value) {
      alert('Selecciona el viaje al que pertenecen los insumos.');
      return null;
    }
    var d = {};
    Array.prototype.forEach.call(f.elements, function (el) {
      if (el.name) d[el.name] = el.value;
    });
    return d;
  };

  // En edición (Movimientos) la lista de viajes la trae el formulario ya armado: no se toca.
  document.addEventListener('lebleu:datos', function () {
    pintarViajes();
  });
})();
