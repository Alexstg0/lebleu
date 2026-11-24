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

  // ---------- Arrastrar y soltar: sobre la caja de respaldos (Movimientos, Caja chica) o sobre un formulario con campo de soporte.
  function zonaDe(el) {
    var caja = el.closest && el.closest('[data-adjuntos]');
    if (caja) return { tipo: 'caja', el: caja };
    var form = el.closest && el.closest('[data-form]');
    return form && form.querySelector('[data-soporte]') ? { tipo: 'form', el: form } : null;
  }
  function llevaArchivos(e) {
    return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1;
  }
  function aviso(form, texto) {
    var campo = form.querySelector('[data-soporte]');
    var e = form.querySelector('.adj-err[data-soltar]');
    if (!e) {
      e = document.createElement('span');
      e.className = 'adj-err';
      e.setAttribute('data-soltar', '');
      campo.insertAdjacentElement('afterend', e);
    }
    e.textContent = texto;
    e.hidden = !texto;
  }
  var resaltada = null;
  function resaltar(el) {
    if (resaltada && resaltada !== el) resaltada.classList.remove('soltando');
    resaltada = el;
    if (el) el.classList.add('soltando');
  }
  document.addEventListener('dragover', function (e) {
    var z = llevaArchivos(e) ? zonaDe(e.target) : null;
    if (!z) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    resaltar(z.el);
  });
  document.addEventListener('dragleave', function (e) {
    if (resaltada && (!e.relatedTarget || !resaltada.contains(e.relatedTarget))) resaltar(null);
  });
  document.addEventListener('drop', function (e) {
    var z = llevaArchivos(e) ? zonaDe(e.target) : null;
    resaltar(null);
    if (!z) return;
    e.preventDefault();
    var archivos = e.dataTransfer.files;
    if (z.tipo === 'caja') {
      error(z.el, archivos.length > 1 ? 'Suelta un archivo a la vez.' : '');
      if (archivos.length === 1) subir(z.el, archivos[0]);
      return;
    }
    if (archivos.length !== 1) { aviso(z.el, 'Suelta un solo archivo.'); return; }
    var motivo = L.validarArchivo(archivos[0]);
    aviso(z.el, motivo || '');
    if (motivo) return;
    var campo = z.el.querySelector('[data-soporte]');
    var lista = new DataTransfer();
    lista.items.add(archivos[0]);
    campo.files = lista.files; // el formulario lo sube al guardar, igual que si se hubiera elegido con el botón
  });

  document.addEventListener('change', function (e) {
    var caja = e.target.closest('[data-adjuntos]');
    if (caja && e.target.type === 'file' && e.target.files[0]) subir(caja, e.target.files[0]);
  });
})();
