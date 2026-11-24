// Calendario: elegir un día (móvil), crear/editar/borrar reservas en una ventana.
(function () {
  var L = window.Lebleu;
  var datos = JSON.parse(document.getElementById('datos-calendario').textContent);
  var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var pad = function (n) { return String(n).padStart(2, '0'); };
  var esc = function (t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

  function hora12(hhmm) {
    var m = /^(\d{1,2}):(\d{2})/.exec(hhmm || '');
    if (!m) return '';
    var h = Number(m[1]);
    return (h % 12 === 0 ? 12 : h % 12) + ':' + m[2] + ' ' + (h >= 12 ? 'PM' : 'AM');
  }
  var color = function (r) { return r.socio_id === 1 ? 'var(--ocean)' : r.socio_id === 2 ? 'var(--teal)' : 'var(--muted)'; };

  var hoy = new Date();
  var hoyISO = hoy.getFullYear() + '-' + pad(hoy.getMonth() + 1) + '-' + pad(hoy.getDate());
  var esMesActual = hoy.getFullYear() === datos.anio && hoy.getMonth() + 1 === datos.mes;
  var porDia = {};
  datos.reservas.forEach(function (r) { var d = Number(r.fecha.slice(8, 10)); (porDia[d] = porDia[d] || []).push(r); });
  var primero = Object.keys(porDia).map(Number).sort(function (a, b) { return a - b; })[0];
  var sel = esMesActual ? hoy.getDate() : primero || 1;

  // ---------- Móvil: detalle del día elegido
  function detalleDe(r) {
    var tieneDur = r.duracion_horas != null;
    return r.fecha < hoyISO ? (tieneDur ? r.duracion_horas + ' h' : (r.hora ? hora12(r.hora) : '—')) : (r.hora ? hora12(r.hora) : 'Sin hora');
  }
  function pintarDetalle() {
    document.querySelectorAll('.ical-day[data-dia]').forEach(function (b) { b.classList.toggle('sel', Number(b.getAttribute('data-dia')) === sel); });
    var lista = porDia[sel] || [];
    var h = '<div class="ical-detail-head"><h3>' + sel + ' de ' + MESES[datos.mes - 1] + '</h3><button class="ical-add" data-nueva="' + datos.anio + '-' + pad(datos.mes) + '-' + pad(sel) + '">+ Reserva</button></div>';
    h += lista.length === 0 ? '<div class="ical-empty">Sin reservas este día.</div>' : '<div class="ical-list">' + lista.map(function (r) {
      return '<button class="ical-item" data-reserva="' + r.id + '"><span class="ical-item-dot" style="background:' + color(r) + '"></span>' +
        '<span class="ical-item-info"><span class="ical-item-name">' + esc(r.cliente || r.socio_nombre || 'Reserva') + '</span>' +
        '<span class="ical-item-meta">' + esc(detalleDe(r) + (r.num_personas ? ' · ' + r.num_personas + ' pers' : '')) + '</span></span>' +
        '<span class="ical-item-chevron">›</span></button>';
    }).join('') + '</div>';
    document.querySelector('[data-detalle]').innerHTML = h;
  }

  // ---------- Ventana de reserva
  function abrir(r) {
    var opciones = '<option value="">— (externo)</option>' + datos.socios.map(function (s) {
      return '<option value="' + s.id + '"' + (r.socio_id === s.id ? ' selected' : '') + '>' + esc(s.nombre) + '</option>';
    }).join('');
    var capa = document.createElement('div');
    capa.className = 'modal-overlay';
    capa.innerHTML =
      '<div class="modal"><h2 style="margin:0 0 14px;font-size:18px">' + (r.id ? 'Editar reserva' : 'Nueva reserva') + '</h2><form><div class="grid">' +
      '<div class="field"><label>Fecha</label><input type="date" name="fecha" required value="' + esc(r.fecha) + '"></div>' +
      '<div class="field"><label>Hora de salida</label><input type="time" name="hora" value="' + esc(r.hora) + '"></div>' +
      '<div class="field"><label>Cliente</label><input name="cliente" value="' + esc(r.cliente) + '" placeholder="Familia / nombre"></div>' +
      '<div class="field"><label>Socio</label><select name="socio_id">' + opciones + '</select></div>' +
      '<div class="field"><label>Personas</label><input type="number" name="num_personas" value="' + esc(r.num_personas) + '"></div>' +
      '<div class="field"><label>Duración (horas)</label><input type="number" step="0.5" name="duracion_horas" value="' + esc(r.duracion_horas) + '" placeholder="Al pasar el viaje"></div>' +
      '<div class="field full"><label>Notas</label><input name="notas" value="' + esc(r.notas) + '"></div></div>' +
      '<div class="msg-err" data-error style="margin-top:12px" hidden></div>' +
      '<div style="margin-top:18px;display:flex;align-items:center;gap:10px"><button class="btn" data-guardar>' + (r.id ? 'Actualizar' : 'Crear reserva') + '</button>' +
      '<button type="button" class="btn-ghost" data-cancelar>Cancelar</button>' +
      (r.id ? '<button type="button" class="btn-link danger" style="margin-left:auto" data-borrar>Borrar</button>' : '') + '</div></form></div>';
    document.body.appendChild(capa);
    var form = capa.querySelector('form'), error = capa.querySelector('[data-error]'), guardar = capa.querySelector('[data-guardar]');
    var cerrar = function () { capa.remove(); };
    capa.addEventListener('click', cerrar);
    capa.querySelector('.modal').addEventListener('click', function (e) { e.stopPropagation(); });
    capa.querySelector('[data-cancelar]').addEventListener('click', cerrar);

    function fallo(j) { error.textContent = j.error || 'No se pudo guardar.'; error.hidden = false; guardar.disabled = false; }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      guardar.disabled = true;
      guardar.textContent = 'Guardando…';
      error.hidden = true;
      var f = form.elements;
      fetch('/api/reservas', {
        method: r.id ? 'PATCH' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: r.id, fecha: f.fecha.value, hora: f.hora.value, cliente: f.cliente.value,
          socio_id: f.socio_id.value ? Number(f.socio_id.value) : null,
          num_personas: f.num_personas.value ? Number(f.num_personas.value) : null,
          duracion_horas: f.duracion_horas.value ? Number(f.duracion_horas.value) : null,
          notas: f.notas.value,
        }),
      }).then(function (x) { return x.json(); }).then(function (j) { if (j.ok) L.recargar(); else { fallo(j); guardar.textContent = r.id ? 'Actualizar' : 'Crear reserva'; } });
    });
    var borrar = capa.querySelector('[data-borrar]');
    if (borrar) borrar.addEventListener('click', function () {
      if (!confirm('¿Borrar esta reserva?')) return;
      fetch('/api/reservas?id=' + r.id, { method: 'DELETE' }).then(function (x) { return x.json(); })
        .then(function (j) { if (j.ok) L.recargar(); else fallo(j); });
    });
  }

  document.addEventListener('click', function (e) {
    var dia = e.target.closest('[data-dia]');
    if (dia) { sel = Number(dia.getAttribute('data-dia')); pintarDetalle(); return; }
    var nueva = e.target.closest('[data-nueva]');
    if (nueva) {
      abrir({ id: null, fecha: nueva.getAttribute('data-nueva'), hora: '', cliente: '', socio_id: null, num_personas: '', duracion_horas: '', notas: '' });
      return;
    }
    var chip = e.target.closest('[data-reserva]');
    if (chip) {
      var id = Number(chip.getAttribute('data-reserva'));
      var r = datos.reservas.filter(function (x) { return x.id === id; })[0];
      if (r) abrir(r);
    }
  });

  pintarDetalle();
})();
