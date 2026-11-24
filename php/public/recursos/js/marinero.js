// Menú de marineros con la opción "Agregar nuevo marinero…" (capitán y administrador).
(function () {
  function nombres(sel) {
    return Array.prototype.filter.call(sel.options, function (o) {
      return o.value && o.value !== '__nuevo__' && !o.hasAttribute('data-historico');
    }).map(function (o) { return o.value; });
  }

  function armarSelect(contenedor, lista, valor, historico) {
    var sel = document.createElement('select');
    sel.name = 'marinero';
    sel.setAttribute('data-marinero-select', '');
    var opciones = [['', '— Sin marinero —']];
    if (historico && lista.indexOf(historico) < 0) opciones.push([historico, historico, true]);
    lista.forEach(function (n) { opciones.push([n, n]); });
    opciones.push(['__nuevo__', '➕ Agregar nuevo marinero…']);
    opciones.forEach(function (o) {
      var op = document.createElement('option');
      op.value = o[0];
      op.textContent = o[1];
      if (o[2]) op.setAttribute('data-historico', '');
      sel.appendChild(op);
    });
    sel.value = valor;
    contenedor.replaceChildren(sel);
  }

  function formularioNuevo(contenedor, sel) {
    var lista = nombres(sel);
    var anterior = sel.value === '__nuevo__' ? '' : sel.value;
    contenedor.innerHTML =
      '<div><div style="display:flex;gap:8px"><input data-nuevo-nombre placeholder="Nombre del nuevo marinero">' +
      '<button type="button" class="btn" style="padding:8px 14px;white-space:nowrap" data-guardar-marinero>Guardar</button>' +
      '<button type="button" class="btn-ghost" style="padding:8px 10px" data-cancelar-marinero>✕</button></div>' +
      '<span class="msg-err" style="font-size:12px" data-error-marinero hidden></span></div>';
    var input = contenedor.querySelector('[data-nuevo-nombre]');
    var error = contenedor.querySelector('[data-error-marinero]');
    input.focus();

    function cancelar() { armarSelect(contenedor, lista, anterior); }
    function guardar() {
      error.hidden = true;
      if (input.value.trim().length < 3) { error.textContent = 'Escribe el nombre completo.'; error.hidden = false; return; }
      var boton = contenedor.querySelector('[data-guardar-marinero]');
      boton.disabled = true;
      boton.textContent = '…';
      Lebleu.enviarJson('/api/marineros', { nombre: input.value }).then(function (j) {
        if (j.ok) {
          if (lista.indexOf(j.nombre) < 0) lista.push(j.nombre);
          lista.sort(function (a, b) { return a.localeCompare(b); });
          armarSelect(contenedor, lista, j.nombre);
        } else {
          error.textContent = j.error || 'No se pudo agregar.';
          error.hidden = false;
          boton.disabled = false;
          boton.textContent = 'Guardar';
        }
      });
    }
    contenedor.querySelector('[data-guardar-marinero]').addEventListener('click', guardar);
    contenedor.querySelector('[data-cancelar-marinero]').addEventListener('click', function () { error.hidden = true; cancelar(); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); guardar(); }
    });
  }

  document.addEventListener('change', function (e) {
    var sel = e.target.closest('[data-marinero-select]');
    if (sel && sel.value === '__nuevo__') formularioNuevo(sel.closest('[data-marinero]'), sel);
  });
})();
