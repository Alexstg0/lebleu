// Selector "Periodo a registrar": navega a la misma pantalla con otro periodo.
document.addEventListener('change', function (e) {
  var s = e.target.closest('[data-ir-periodo]');
  if (s) window.location.href = s.getAttribute('data-ir-periodo') + '?periodo=' + encodeURIComponent(s.value);
});
