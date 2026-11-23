// Pantalla de Periodos: abrir un periodo nuevo y cerrar/reabrir uno existente.
(function () {
  var form = document.querySelector('[data-nuevo-periodo]');
  if (form) {
    var boton = form.querySelector('[data-boton]');
    var mensaje = form.querySelector('[data-mensaje]');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      boton.disabled = true;
      boton.textContent = 'Creando…';
      mensaje.hidden = true;
      var f = form.elements;
      Lebleu.enviarJson('/api/periodos', {
        embarcacion_id: 1,
        anio: Number(f.anio.value), mes: Number(f.mes.value),
        tipo_cambio: Number(f.tipo_cambio.value), precio_litro: Number(f.precio_litro.value),
        cargo_administracion: Number(f.cargo_administracion.value),
      }).then(function (j) {
        if (j.ok) { Lebleu.recargar(); return; }
        mensaje.className = 'msg-err';
        mensaje.textContent = j.error || 'No se pudo crear el periodo.';
        mensaje.hidden = false;
      }).finally(function () {
        boton.disabled = false;
        boton.textContent = 'Abrir periodo';
      });
    });
  }

  document.querySelectorAll('[data-accion-periodo]').forEach(function (b) {
    b.addEventListener('click', function () {
      var cont = b.closest('[data-periodo-id]');
      var accion = b.getAttribute('data-accion-periodo');
      var etiqueta = cont.getAttribute('data-etiqueta');
      var pregunta = accion === 'cerrar'
        ? '¿Cerrar ' + etiqueta + '? Ya no se podrá capturar ni editar en este periodo y se archivará su estado de cuenta en PDF.'
        : '¿Reabrir ' + etiqueta + '? Se podrá volver a capturar y editar.';
      if (!confirm(pregunta)) return;
      b.disabled = true;
      var texto = b.textContent;
      b.textContent = '…';
      Lebleu.enviarJson('/api/periodos/estado', { id: Number(cont.getAttribute('data-periodo-id')), accion: accion })
        .then(function (j) {
          if (j.ok) { Lebleu.recargar(); return; }
          alert(j.error || 'No se pudo cambiar el estado.');
          b.disabled = false;
          b.textContent = texto;
        });
    });
  });
})();
