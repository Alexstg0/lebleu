// Formulario de renta: muestra la utilidad (ingreso − costos) mientras se escribe.
(function () {
  function pintar(f) {
    var u = Math.round((Number(f.elements.monto.value || 0) - Number(f.elements.costos_asociados.value || 0)) * 100) / 100;
    f.querySelector('[data-utilidad]').textContent = '$' + u.toLocaleString('es-MX', { minimumFractionDigits: 2 });
  }
  document.addEventListener('input', function (e) {
    var f = e.target.closest('[data-calculo="renta"]');
    if (f) pintar(f);
  });
  document.addEventListener('lebleu:iniciar', function (e) {
    if (e.target.matches('[data-calculo="renta"]')) pintar(e.target);
  });
})();
