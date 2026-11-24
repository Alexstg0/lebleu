// Caja chica: filtro por caja, totales, y alta/edición/baja de movimientos en una ventana.
(function () {
  var L = window.Lebleu;
  var raiz = document.querySelector('[data-caja-chica]');
  if (!raiz) return;
  var datos = JSON.parse(document.getElementById('datos-caja').textContent);
  var movs = datos.movimientos;
  var pad = function (n) { return String(n).padStart(2, '0'); };
  var esc = function (t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var mxn = function (centavos) { var n = Math.round(centavos) / 100; return (n < 0 ? '-' : '') + '$' + Math.abs(n).toLocaleString('es-MX', { minimumFractionDigits: 2 }); };
  var estado = { filtro: 'todas', responsable: 'Gabriel Preciado' };
  var todasLasFilas = Array.prototype.slice.call(raiz.querySelectorAll('tr[data-mov]'));

  function aplicar() {
    var esTodas = estado.filtro === 'todas';
    var cajaSel = esTodas ? null : Number(estado.filtro);
    // Las filas que no corresponden se QUITAN de la tabla (no solo se ocultan): el color de la franja de
    // cada tarjeta en móvil depende de su posición entre las filas visibles.
    var visibles = todasLasFilas.filter(function (tr) { return esTodas || Number(tr.getAttribute('data-caja')) === cajaSel; });
    raiz.querySelector('.cc-table tbody').replaceChildren.apply(raiz.querySelector('.cc-table tbody'), visibles);
    var gastos = 0, abonos = 0;
    visibles.forEach(function (tr) { gastos += Number(tr.getAttribute('data-monto')); abonos += Number(tr.getAttribute('data-abono')); });
    var aPagar = gastos - abonos;
    var balance = movs.length ? Math.round(movs[movs.length - 1].balance * 100) : 0;

    raiz.querySelectorAll('[data-filtro]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-filtro') === String(estado.filtro)); });
    raiz.querySelectorAll('.cc-bal').forEach(function (c) { c.hidden = !esTodas; });
    raiz.querySelector('.cc-table').classList.toggle('cc-soporte', !esTodas);

    var et = esTodas ? ['Gastos', 'Abonos', 'Balance actual'] : ['Total', 'Abonado', 'Total a pagar'];
    var val = esTodas ? [gastos, abonos, balance] : [gastos, abonos, aPagar];
    [1, 2, 3].forEach(function (i) {
      raiz.querySelector('[data-et="' + i + '"]').textContent = et[i - 1];
      raiz.querySelector('[data-v="' + i + '"]').textContent = mxn(val[i - 1]);
    });
    var final = raiz.querySelector('[data-tarjeta-final]');
    var malo = esTodas ? balance < 0 : aPagar > 0;
    final.classList.toggle('neg', malo);
    final.classList.toggle('pos', !malo);

    raiz.querySelector('[data-t-monto]').textContent = mxn(gastos);
    raiz.querySelector('[data-t-abono]').textContent = mxn(abonos);
    raiz.querySelector('[data-t-balance]').textContent = mxn(balance);
    raiz.querySelector('[data-t-apagar]').innerHTML = esTodas ? '' : '<b>A pagar: ' + mxn(aPagar) + '</b>';

    raiz.querySelector('[data-vacio]').hidden = visibles.length > 0;
    raiz.querySelector('[data-vacio-caja]').textContent = esTodas ? '' : ' en la Caja ' + cajaSel;
    raiz.querySelector('[data-tabla]').hidden = visibles.length === 0;

    var sufijo = esTodas ? '' : ' — Caja ' + cajaSel;
    raiz.querySelector('[data-titulo-impresion]').textContent = 'Balance de caja chica' + sufijo;
    raiz.querySelector('[data-responsable-impresion]').textContent = estado.responsable;
    var pdf = raiz.querySelector('[data-reporte-pdf]');
    pdf.setAttribute('data-visor-pdf', '/api/reportes/caja-chica?caja=' + (esTodas ? 'todas' : cajaSel) + '&responsable=' + encodeURIComponent(estado.responsable));
    pdf.setAttribute('data-titulo', 'Reporte de caja chica' + sufijo);
  }

  // ---------- Ventana de movimiento
  function abrir(m) {
    var capa = document.createElement('div');
    capa.className = 'modal-overlay';
    capa.innerHTML =
      '<div class="modal"><h2 style="margin:0 0 14px;font-size:18px">' + (m.id ? 'Editar movimiento' : 'Nuevo movimiento') + '</h2><form>' +
      '<div class="cc-tipo"><button type="button" data-tipo="gasto">Gasto</button><button type="button" data-tipo="abono">Abono</button></div><div class="grid">' +
      '<div class="field"><label>Fecha</label><input type="date" name="fecha" required value="' + esc(m.fecha) + '"></div>' +
      '<div class="field"><label>Número de caja</label><input type="number" name="caja_numero" value="' + esc(m.caja_numero) + '" placeholder="13"></div>' +
      '<div class="field" data-campo="gasto"><label>Monto</label><input type="number" step="0.01" name="monto" value="' + esc(m.monto) + '" placeholder="0.00"></div>' +
      '<div class="field" data-campo="abono"><label>Abono</label><input type="number" step="0.01" name="abono" value="' + esc(m.abono) + '" placeholder="0.00"></div>' +
      '<div class="field"><label>Factura / folio</label><input name="factura" value="' + esc(m.factura) + '" placeholder="Opcional"></div>' +
      '<div class="field"><label>Proveedor</label><input name="proveedor" value="' + esc(m.proveedor) + '" placeholder="Opcional"></div>' +
      '<div class="field full"><label>Concepto</label><input name="concepto" value="' + esc(m.concepto) + '" placeholder="¿En qué se usó?"></div>' +
      '<div class="field full"><label>Observaciones</label><input name="observaciones" value="' + esc(m.observaciones) + '"></div></div>' +
      '<div class="msg-err" data-error style="margin-top:12px" hidden></div>' +
      '<div style="margin-top:18px;display:flex;align-items:center;gap:10px"><button class="btn" data-guardar>' + (m.id ? 'Actualizar' : 'Registrar') + '</button>' +
      '<button type="button" class="btn-ghost" data-cancelar>Cancelar</button>' +
      (m.id ? '<button type="button" class="btn-link danger" style="margin-left:auto" data-borrar>Borrar</button>' : '') + '</div></form></div>';
    document.body.appendChild(capa);
    var form = capa.querySelector('form'), error = capa.querySelector('[data-error]'), guardar = capa.querySelector('[data-guardar]');
    var tipo = m.tipo;
    function pintarTipo() {
      capa.querySelectorAll('[data-tipo]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-tipo') === tipo); });
      capa.querySelectorAll('[data-campo]').forEach(function (c) { c.hidden = c.getAttribute('data-campo') !== tipo; });
    }
    pintarTipo();
    capa.querySelectorAll('[data-tipo]').forEach(function (b) { b.addEventListener('click', function () { tipo = b.getAttribute('data-tipo'); pintarTipo(); }); });
    var cerrar = function () { capa.remove(); };
    capa.addEventListener('click', cerrar);
    capa.querySelector('.modal').addEventListener('click', function (e) { e.stopPropagation(); });
    capa.querySelector('[data-cancelar]').addEventListener('click', cerrar);
    function fallo(j) { error.textContent = j.error || 'No se pudo guardar.'; error.hidden = false; guardar.disabled = false; guardar.textContent = m.id ? 'Actualizar' : 'Registrar'; }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      guardar.disabled = true;
      guardar.textContent = 'Guardando…';
      error.hidden = true;
      var f = form.elements;
      fetch('/api/caja-chica', {
        method: m.id ? 'PATCH' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: m.id, fecha: f.fecha.value, tipo: tipo, caja_numero: f.caja_numero.value ? Number(f.caja_numero.value) : null,
          factura: f.factura.value, proveedor: f.proveedor.value, concepto: f.concepto.value,
          monto: tipo === 'abono' ? 0 : Number(f.monto.value || 0), abono: tipo === 'abono' ? Number(f.abono.value || 0) : 0,
          observaciones: f.observaciones.value,
        }),
      }).then(function (x) { return x.json(); }).then(function (j) { if (j.ok) L.recargar(); else fallo(j); });
    });
    var borrar = capa.querySelector('[data-borrar]');
    if (borrar) borrar.addEventListener('click', function () {
      if (!confirm('¿Borrar este movimiento?')) return;
      fetch('/api/caja-chica?id=' + m.id, { method: 'DELETE' }).then(function (x) { return x.json(); }).then(function (j) { if (j.ok) L.recargar(); else fallo(j); });
    });
  }

  function hoyISO() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

  raiz.addEventListener('click', function (e) {
    var filtro = e.target.closest('[data-filtro]');
    if (filtro) { estado.filtro = filtro.getAttribute('data-filtro'); aplicar(); return; }
    if (e.target.closest('[data-nuevo-mov]')) {
      abrir({ id: null, fecha: hoyISO(), tipo: 'gasto', caja_numero: estado.filtro === 'todas' ? datos.ultimaCaja : Number(estado.filtro),
              factura: '', proveedor: '', concepto: '', monto: '', abono: '', observaciones: '' });
      return;
    }
    var fila = e.target.closest('tr[data-mov]');
    if (fila && !e.target.closest('[data-sin-fila]')) {
      var id = Number(fila.getAttribute('data-mov'));
      var m = movs.filter(function (x) { return x.id === id; })[0];
      if (m) abrir({ id: m.id, fecha: m.fecha, tipo: m.tipo, caja_numero: m.caja_numero, factura: m.factura || '', proveedor: m.proveedor || '',
                     concepto: m.concepto || '', monto: m.monto, abono: m.abono, observaciones: m.observaciones || '' });
    }
  });
  raiz.querySelector('[data-responsable]').addEventListener('input', function (e) { estado.responsable = e.target.value; aplicar(); });

  aplicar();
})();
