// Pantalla Usuarios: el campo "Socio asociado" solo aplica al rol socio; cambiar contraseña y borrar cuentas.
(function () {
  var L = window.Lebleu;

  function pintarRol(f) {
    var socio = f.querySelector('[data-solo-socio]');
    if (socio) socio.hidden = f.querySelector('[data-rol]').value !== 'socio';
  }
  document.addEventListener('change', function (e) {
    var f = e.target.closest('[data-form]');
    if (f && e.target.closest('[data-rol]')) pintarRol(f);
  });

  document.addEventListener('click', function (e) {
    var clave = e.target.closest('[data-clave]');
    if (clave) {
      var nueva = prompt('Nueva contraseña para ' + clave.getAttribute('data-nombre') + ' (mínimo 6 caracteres):');
      if (!nueva) return;
      fetch('/api/usuarios', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: Number(clave.getAttribute('data-clave')), password: nueva }) })
        .then(function (r) { return r.json(); }).then(function (j) { alert(j.ok ? 'Contraseña actualizada.' : j.error || 'Error.'); });
      return;
    }
    var borrar = e.target.closest('[data-borrar-usuario]');
    if (borrar) {
      if (!confirm('¿Borrar la cuenta de ' + borrar.getAttribute('data-nombre') + '?')) return;
      fetch('/api/usuarios?id=' + encodeURIComponent(borrar.getAttribute('data-borrar-usuario')), { method: 'DELETE' })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j.ok) L.refrescar(); else alert(j.error || 'No se pudo borrar.'); });
    }
  });
})();
