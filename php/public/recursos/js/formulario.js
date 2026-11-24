// Comportamiento común de los formularios de captura (alta y edición):
// envío por fetch, mensajes, moneda USD, limpieza tras guardar. Los formularios con cálculos
// propios (viaje, renta, insumos) se enganchan con Lebleu.armadores / Lebleu.despues.
(function () {
  var L = window.Lebleu;

  function mostrar(form, ok, texto) {
    var m = form.querySelector('[data-mensaje]');
    if (!m) return;
    m.className = ok ? 'msg-ok' : 'msg-err';
    m.textContent = texto;
    m.hidden = false;
  }

  function moneda(form) {
    var sel = form.querySelector('[data-moneda]');
    if (!sel) return;
    var usd = sel.value === 'USD';
    form.querySelectorAll('[data-solo-usd]').forEach(function (c) { c.hidden = !usd; });
    var et = form.querySelector('[data-etiqueta-monto]');
    if (et) et.textContent = usd ? 'Monto (USD)' : 'Monto (MXN)';
  }

  // Carga genérica: todo campo con name; casillas como booleanos.
  function cargaGenerica(form) {
    var d = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'button' || el.type === 'submit' || el.type === 'file') return;
      d[el.name] = el.type === 'checkbox' ? el.checked : el.value;
    });
    return d;
  }

  function limpiar(form) {
    (form.getAttribute('data-limpiar') || '').split(',').forEach(function (n) {
      if (n && form.elements[n]) form.elements[n].value = '';
    });
  }

  // Archivo de soporte opcional (solo en altas): se valida antes de guardar y se sube después.
  function soporteDe(form) {
    var campo = form.querySelector('[data-soporte]');
    return campo && campo.files && campo.files[0] ? { campo: campo, archivo: campo.files[0] } : null;
  }

  function enviar(form) {
    var tipo = form.getAttribute('data-calculo');
    var carga = L.armadores[tipo] ? L.armadores[tipo](form) : cargaGenerica(form);
    if (!carga) return;
    var soporte = soporteDe(form);
    if (soporte && L.validarArchivo) {
      var motivo = L.validarArchivo(soporte.archivo);
      if (motivo) { mostrar(form, false, motivo); return; }
    }
    var editando = form.getAttribute('data-metodo') === 'PATCH';
    var boton = form.querySelector('[data-boton]');
    boton.disabled = true;
    boton.textContent = 'Guardando…';
    var m = form.querySelector('[data-mensaje]');
    if (m) m.hidden = true;

    fetch(form.getAttribute('data-url'), {
      method: form.getAttribute('data-metodo'),
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(carga),
    }).then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { mostrar(form, false, j.error || 'Error al guardar.'); return; }
        if (editando) { mostrar(form, true, 'Actualizado.'); L.recargar(); return; }
        var terminar = function (texto, ok) {
          mostrar(form, ok, texto);
          limpiar(form);
          if (soporte) soporte.campo.value = '';
          if (L.despues[tipo]) L.despues[tipo](form);
          L.refrescar();
        };
        if (!soporte || !j.id) { terminar('Guardado correctamente.', true); return; }
        return L.subirAdjunto(soporte.campo.getAttribute('data-soporte'), j.id, soporte.archivo)
          .then(function () { terminar('Guardado correctamente.', true); })
          .catch(function (e) { terminar('Guardado, pero el soporte no se pudo subir: ' + e.message + ' Agrégalo en Movimientos.', false); });
      })
      .catch(function (err) { mostrar(form, false, String((err && err.message) || err)); })
      .finally(function () {
        boton.disabled = false;
        boton.textContent = boton.getAttribute('data-texto');
      });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-form]');
    if (!form) return;
    e.preventDefault();
    enviar(form);
  });
  document.addEventListener('change', function (e) {
    if (e.target.closest('[data-moneda]')) moneda(e.target.closest('[data-form]'));
  });

  // Formularios que aparecen después (edición dentro de Movimientos) avisan con este evento.
  L.iniciarFormularios = function (raiz) {
    (raiz || document).querySelectorAll('[data-form]').forEach(function (f) {
      moneda(f);
      f.dispatchEvent(new CustomEvent('lebleu:iniciar', { bubbles: true }));
    });
  };
  document.addEventListener('DOMContentLoaded', function () { L.iniciarFormularios(); });
})();
