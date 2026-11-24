// Pantalla Movimientos: editar una fila (el servidor entrega su formulario) y borrar.
(function () {
  var L = window.Lebleu;
  var wrap = document.querySelector('[data-periodo-id]');

  function cerrarEdicion() {
    var abierta = document.querySelector('tr[data-edicion]');
    if (abierta && abierta._original) abierta.replaceWith(abierta._original);
  }

  document.addEventListener('click', function (e) {
    var editar = e.target.closest('[data-editar]');
    if (editar) {
      var fila = editar.closest('tr');
      var columnas = fila.closest('table').querySelectorAll('thead th').length;
      var url = '/movimientos/formulario?k=' + encodeURIComponent(editar.getAttribute('data-editar')) +
                '&periodo=' + encodeURIComponent(wrap.getAttribute('data-periodo-id'));
      editar.disabled = true;
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.text() : r.json().then(function (j) { throw new Error(j.error || 'No se pudo abrir.'); }); })
        .then(function (html) {
          cerrarEdicion();
          var ed = document.createElement('tr');
          ed.setAttribute('data-edicion', '');
          ed.innerHTML = '<td colspan="' + columnas + '">' + html + '</td>';
          ed._original = fila;
          fila.replaceWith(ed);
          L.iniciarFormularios(ed);
        })
        .catch(function (err) { alert(err.message); editar.disabled = false; });
      return;
    }

    var generar = e.target.closest('[data-generar-recurrentes]');
    if (generar) {
      var aviso = document.querySelector('[data-msg-recurrentes]');
      generar.disabled = true;
      generar.textContent = 'Generando…';
      aviso.hidden = true;
      L.enviarJson('/api/recurrentes', { periodoId: Number(wrap.getAttribute('data-periodo-id')) }).then(function (j) {
        generar.disabled = false;
        generar.textContent = 'Generar nómina y administrativo';
        aviso.className = j.ok ? 'msg-ok' : 'msg-err';
        var total = (j.nomina || 0) + (j.admin || 0);
        aviso.textContent = !j.ok ? (j.error || 'No se pudo generar.')
          : total === 0 ? 'Ya está todo al día — no había cargos pendientes.'
          : 'Se generaron ' + j.nomina + ' nómina(s) y ' + j.admin + ' cargo(s) administrativo(s).';
        aviso.hidden = false;
        if (j.ok) L.refrescar();
      });
      return;
    }

    var recibo = e.target.closest('[data-generar-recibo]');
    if (recibo) {
      recibo.disabled = true;
      recibo.textContent = '…';
      L.enviarJson('/api/gastos-operativos/recibo', { gasto_id: Number(recibo.getAttribute('data-generar-recibo')) }).then(function (j) {
        if (j.ok) { L.recargar(); return; }
        recibo.disabled = false;
        recibo.textContent = 'Generar recibo';
        alert(j.error || 'No se pudo generar el recibo.');
      });
      return;
    }

    var cancelar = e.target.closest('[data-cancelar]');
    if (cancelar) {
      var ed = cancelar.closest('tr[data-edicion]');
      if (ed && ed._original) ed.replaceWith(ed._original);
      return;
    }

    var borrar = e.target.closest('[data-borrar]');
    if (borrar) {
      if (!confirm('¿Borrar ' + borrar.getAttribute('data-etiqueta') + '? Esta acción no se puede deshacer.')) return;
      fetch(borrar.getAttribute('data-url') + '?id=' + encodeURIComponent(borrar.getAttribute('data-id')), { method: 'DELETE' })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (j) {
          if (j.ok) L.recargar();
          else alert(j.error || 'No se pudo borrar.');
        });
    }
  });
})();
