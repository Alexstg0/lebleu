// Formulario simplificado del capitán: viaje con familia o renta externa, horómetros y combustible en galones.
(function () {
  var L = window.Lebleu;
  var GAL_A_LITRO = 3.78541;
  var MARINERO = 800; // tarifa fija del marinero por viaje
  var r2 = function (x) { return Math.round(x * 100) / 100; };

  function datos(f) {
    var e = f.elements;
    var litrosIni = r2(Number(e.galIni.value || 0) * GAL_A_LITRO);
    var litrosFin = r2(Number(e.galFin.value || 0) * GAL_A_LITRO);
    return {
      litrosIni: litrosIni, litrosFin: litrosFin, consumo: r2(litrosIni - litrosFin),
      horasB: e.bFin.value && e.bIni.value ? r2(Number(e.bFin.value) - Number(e.bIni.value)) : null,
      horasE: e.eFin.value && e.eIni.value ? r2(Number(e.eFin.value) - Number(e.eIni.value)) : null,
    };
  }

  function pintar(f) {
    var e = f.elements, s = datos(f);
    f.querySelectorAll('[data-solo-renta]').forEach(function (c) { c.hidden = e.quien.value !== 'renta'; });
    [['ini', e.galIni.value, s.litrosIni], ['fin', e.galFin.value, s.litrosFin]].forEach(function (p) {
      var h = f.querySelector('[data-hint="' + p[0] + '"]');
      h.hidden = !p[1];
      if (p[1]) h.textContent = '≈ ' + p[2] + ' L';
    });
    [['b', s.horasB], ['e', s.horasE]].forEach(function (p) {
      var h = f.querySelector('[data-horas="' + p[0] + '"]');
      h.hidden = p[1] === null;
      if (p[1] !== null) h.textContent = p[1] + ' h de uso';
    });
    f.querySelector('[data-consumo]').textContent = (s.consumo > 0 ? s.consumo : 0) + ' L';
  }

  L.armadores['viaje-capitan'] = function (f) {
    var e = f.elements, s = datos(f), esRenta = e.quien.value === 'renta';
    var socioId = null, clienteId = null, clienteNombre = null;

    if (esRenta) {
      if (!e.rentaNombre.value.trim()) { alert('Escribe el nombre del cliente de la renta.'); return null; }
      clienteNombre = e.rentaNombre.value.trim();
    } else {
      var id = e.quien.value.replace('cli:', '');
      var socios = JSON.parse(f.getAttribute('data-clientes-socio') || '{}');
      if (!socios[id]) { alert('Selecciona una familia.'); return null; }
      socioId = Number(socios[id]);
      clienteId = Number(id);
    }

    var motB = Number(f.getAttribute('data-motor-b')) || undefined;
    var motE = Number(f.getAttribute('data-motor-e')) || undefined;
    if (e.bIni.value && e.bFin.value && Number(e.bFin.value) < Number(e.bIni.value)) {
      alert('El horómetro final del ' + f.getAttribute('data-etiqueta-b') + ' (' + e.bFin.value + ') no puede ser menor al inicial (' + e.bIni.value + '). Revisa la lectura.');
      return null;
    }
    if (e.eIni.value && e.eFin.value && Number(e.eFin.value) < Number(e.eIni.value)) {
      alert('El horómetro final del ' + f.getAttribute('data-etiqueta-e') + ' (' + e.eFin.value + ') no puede ser menor al inicial (' + e.eIni.value + '). Revisa la lectura.');
      return null;
    }
    if (e.galIni.value && e.galFin.value && Number(e.galFin.value) > Number(e.galIni.value)) {
      if (!confirm('El combustible final es MAYOR al inicial (¿se cargó combustible durante el viaje?). ¿Continuar de todas formas?')) return null;
    }
    var dup = L.viajes().filter(function (v) { return String(v.fecha).slice(0, 10) === e.fecha.value; })[0];
    if (dup && !confirm('Ya hay un viaje registrado el ' + e.fecha.value.split('-').reverse().join('/') + ' (' + dup.cliente + '). ¿Registrar OTRO viaje ese mismo día?')) return null;

    var precio = Number(f.getAttribute('data-precio')) || 0;
    var consumo = s.consumo > 0 ? s.consumo : 0;
    return {
      periodo_id: Number(e.periodo_id.value),
      socio_id: socioId,
      cliente_id: clienteId,
      cliente_nombre: clienteNombre,
      marinero: e.marinero.value.trim() || null,
      es_renta: esRenta,
      fecha: e.fecha.value,
      num_personas: e.num_personas.value ? Number(e.num_personas.value) : null,
      duracion_horas: e.duracion_horas.value ? Number(e.duracion_horas.value) : null,
      litros: consumo,
      precio_litro: precio,
      costo_combustible: r2(consumo * precio),
      costo_marinero: MARINERO,
      costo_consumibles: 0,
      combustible_inicio: s.litrosIni,
      combustible_fin: s.litrosFin,
      horometros: [
        { motor_id: motB, inicio: e.bIni.value, fin: e.bFin.value },
        { motor_id: motE, inicio: e.eIni.value, fin: e.eFin.value },
      ],
    };
  };

  L.despues['viaje-capitan'] = function (f) {
    var e = f.elements;
    e.bIni.value = e.bFin.value || e.bIni.value;
    e.bFin.value = '';
    e.eIni.value = e.eFin.value || e.eIni.value;
    e.eFin.value = '';
    pintar(f);
  };

  document.addEventListener('input', function (e) {
    var f = e.target.closest('[data-calculo="viaje-capitan"]');
    if (f) pintar(f);
  });
  document.addEventListener('change', function (e) {
    var f = e.target.closest('[data-calculo="viaje-capitan"]');
    if (f) pintar(f);
  });
  document.addEventListener('lebleu:iniciar', function (e) {
    if (e.target.matches('[data-calculo="viaje-capitan"]')) pintar(e.target);
  });
})();
