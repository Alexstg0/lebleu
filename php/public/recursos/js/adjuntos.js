// Respaldos (PDF o foto) de un movimiento: subir, ver (visor-pdf.js) y eliminar.
(function () {
  var L = window.Lebleu;
  var MAX = 3.2 * 1024 * 1024;

  // Devuelve el motivo si el archivo no sirve; null si está bien.
  L.validarArchivo = function (archivo) {
    var esPdf = archivo.type === 'application/pdf' || /\.pdf$/i.test(archivo.name);
    var esImagen = /^image\/(jpe?g|png)$/i.test(archivo.type) || /\.(jpe?g|png)$/i.test(archivo.name);
    if (!esPdf && !esImagen) return 'Solo PDF o imagen (JPG/PNG).';
    if (archivo.size > MAX) return 'Máximo 3 MB. Si es foto, tómala en tamaño reducido.';
    return null;
  };

  // Sube un archivo a un registro (columna = gasto_id | insumo_id | extraordinario_id | caja_id). Promesa.
  L.subirAdjunto = function (columna, registroId, archivo) {
    return new Promise(function (ok, mal) {
      var lector = new FileReader();
      lector.onerror = function () { mal(new Error('No se pudo leer el archivo.')); };
      lector.onload = function () {
        var esPdf = archivo.type === 'application/pdf' || /\.pdf$/i.test(archivo.name);
        var carga = { nombre: archivo.name, mime: esPdf ? 'application/pdf' : archivo.type, base64: String(lector.result) };
        carga[columna] = registroId;
        L.enviarJson('/api/adjuntos', carga).then(function (j) { j.ok ? ok() : mal(new Error(j.error || 'No se pudo subir.')); });
      };
      lector.readAsDataURL(archivo);
    });
  };

  function error(caja, texto) {
    var e = caja.querySelector('.adj-err');
    e.textContent = texto;
    e.hidden = !texto;
  }

  function subir(caja, archivo) {
    error(caja, '');
    var esPdf = archivo.type === 'application/pdf' || /\.pdf$/i.test(archivo.name);
    var esImagen = /^image\/(jpe?g|png)$/i.test(archivo.type) || /\.(jpe?g|png)$/i.test(archivo.name);
    if (!esPdf && !esImagen) { error(caja, 'Solo PDF o imagen (JPG/PNG).'); return; }
    if (archivo.size > MAX) { error(caja, 'Máximo 3 MB. Si es foto, tómala en tamaño reducido.'); return; }
    var boton = caja.querySelector('.adj-add');
    boton.disabled = true;
    boton.textContent = 'Subiendo…';
    var lector = new FileReader();
    lector.onerror = function () { boton.disabled = false; boton.textContent = '+ PDF/Foto'; error(caja, 'No se pudo leer el archivo.'); };
    lector.onload = function () {
      var carga = { nombre: archivo.name, mime: esPdf ? 'application/pdf' : archivo.type, base64: String(lector.result) };
      carga[caja.getAttribute('data-columna')] = Number(caja.getAttribute('data-registro'));
      L.enviarJson('/api/adjuntos', carga).then(function (j) {
        caja.querySelector('input[type=file]').value = '';
        if (j.ok) { L.recargar(); return; }
        boton.disabled = false;
        boton.textContent = '+ PDF/Foto';
        error(caja, j.error || 'No se pudo subir.');
      });
    };
    lector.readAsDataURL(archivo);
  }

  document.addEventListener('click', function (e) {
    var agregar = e.target.closest('[data-subir-adjunto]');
    if (agregar) { e.stopPropagation(); agregar.closest('[data-adjuntos]').querySelector('input[type=file]').click(); return; }
    var borrar = e.target.closest('[data-borrar-adjunto]');
    if (borrar) {
      e.stopPropagation();
      if (!confirm('¿Eliminar este archivo?')) return;
      fetch('/api/adjuntos?id=' + encodeURIComponent(borrar.getAttribute('data-borrar-adjunto')), { method: 'DELETE' })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (j) { if (j.ok) L.recargar(); });
    }
  });

  document.addEventListener('change', function (e) {
    var caja = e.target.closest('[data-adjuntos]');
    if (caja && e.target.type === 'file' && e.target.files[0]) subir(caja, e.target.files[0]);
  });
})();
