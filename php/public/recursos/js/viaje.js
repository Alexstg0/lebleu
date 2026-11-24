// Formulario de viaje (administrador): el combustible se captura en galones y se guarda en litros;
// el consumo y el costo se calculan solos con el precio del litro del periodo.
(function () {
  var L = window.Lebleu;
  var GAL_A_LITRO = 3.78541;
  var r2 = function (x) { return Math.round(x * 100) / 100; };
  var num = function (v) { return Number(v || 0); };
  var dinero = function (x) { return '$' + x.toLocaleString('es-MX', { minimumFractionDigits: 2 }); };

  function estado(f) {
    var e = f.elements;
    var precio = Number(f.getAttribute('data-precio')) || 0;
    var litrosIni = e.gal_inicio.value !== '' ? r2(Number(e.gal_inicio.value) * GAL_A_LITRO) : null;
    var litrosFin = e.gal_fin.value !== '' ? r2(Number(e.gal_fin.value) * GAL_A_LITRO) : null;
    var auto = litrosIni != null && litrosFin != null && litrosIni >= litrosFin ? r2(litrosIni - litrosFin) : null;
    var litros = auto != null ? auto : num(e.litros.value);
    var costo = r2(litros * precio);
    return { precio: precio, litrosIni: litrosIni, litrosFin: litrosFin, auto: auto, litros: litros, costo: costo,
             total: costo + num(e.costo_marinero.value) + num(e.costo_consumibles.value) };
  }

  function pintar(f) {
    var s = estado(f), e = f.elements;
    [['ini', s.litrosIni], ['fin', s.litrosFin]].forEach(function (p) {
      var h = f.querySelector('[data-hint="' + p[0] + '"]');
      h.hidden = p[1] == null;
      if (p[1] != null) h.textContent = '≈ ' + p[1] + ' L';
    });
    e.consumo_auto.hidden = s.auto == null;
    e.litros.hidden = s.auto != null;
    if (s.auto != null) e.consumo_auto.value = s.auto;
    f.querySelector('[data-costo-combustible]').textContent = dinero(s.costo);
    f.querySelector('[data-total-viaje]').textContent = dinero(r2(s.total));
  }

  function horometros(f) {
    return Array.prototype.map.call(f.querySelectorAll('[data-motor-id]'), function (c) {
      return { motor_id: Number(c.getAttribute('data-motor-id')), etiqueta: c.getAttribute('data-etiqueta'),
               inicio: c.querySelector('[data-h="inicio"]').value, fin: c.querySelector('[data-h="fin"]').value };
    });
  }

  L.armadores.viaje = function (f) {
    var e = f.elements, s = estado(f), editando = f.getAttribute('data-metodo') === 'PATCH';
    var horos = horometros(f);
    for (var i = 0; i < horos.length; i++) {
      var h = horos[i];
      if (h.inicio !== '' && h.fin !== '' && Number(h.fin) < Number(h.inicio)) {
        alert('El horómetro final de ' + h.etiqueta + ' (' + h.fin + ') no puede ser menor al inicial (' + h.inicio + '). Revisa la lectura.');
        return null;
      }
    }
    if (e.gal_inicio.value !== '' && e.gal_fin.value !== '' && Number(e.gal_fin.value) > Number(e.gal_inicio.value)) {
      if (!confirm('El combustible final es MAYOR al inicial (¿se cargó combustible durante el viaje?). ¿Continuar de todas formas?')) return null;
    }
    if (!editando) {
      var dup = L.viajes().filter(function (x) { return String(x.fecha).slice(0, 10) === e.fecha.value; })[0];
      if (dup && !confirm('Ya hay un viaje registrado el ' + e.fecha.value.split('-').reverse().join('/') + ' (' + dup.cliente + '). ¿Registrar OTRO viaje ese mismo día?')) return null;
    }
    return {
      id: e.id ? Number(e.id.value) : undefined,
      periodo_id: Number(e.periodo_id.value),
      socio_id: Number(e.socio_id.value),
      cliente_id: e.cliente_id.value ? Number(e.cliente_id.value) : null,
      fecha: e.fecha.value,
      duracion_horas: e.duracion_horas.value ? Number(e.duracion_horas.value) : null,
      num_personas: e.num_personas.value ? Number(e.num_personas.value) : null,
      litros: s.litros,
      precio_litro: s.precio,
      costo_combustible: s.costo,
      combustible_inicio: s.litrosIni,
      combustible_fin: s.litrosFin,
      costo_marinero: num(e.costo_marinero.value),
      marinero: e.marinero.value || null,
      costo_consumibles: num(e.costo_consumibles.value),
      consumibles_comprobante: e.consumibles_comprobante.value,
      bandera: e.bandera.checked,
      horometros: horos,
    };
  };

  // Tras un alta, la lectura final de cada motor pasa a ser la inicial del siguiente viaje.
  L.despues.viaje = function (f) {
    f.querySelectorAll('[data-motor-id]').forEach(function (c) {
      var ini = c.querySelector('[data-h="inicio"]'), fin = c.querySelector('[data-h="fin"]');
      ini.value = fin.value || ini.value;
      fin.value = '';
    });
    f.elements.bandera.checked = false;
    pintar(f);
  };

  document.addEventListener('input', function (e) {
    var f = e.target.closest('[data-calculo="viaje"]');
    if (f) pintar(f);
  });
  document.addEventListener('lebleu:iniciar', function (e) {
    if (e.target.matches('[data-calculo="viaje"]')) pintar(e.target);
  });
})();
