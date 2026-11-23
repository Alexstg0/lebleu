// Interruptor "Pagado / Pendiente" de un gasto extraordinario (solo admin).
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pagado-id]');
    if (!b) return;
    e.stopPropagation();
    var pagado = b.getAttribute('data-pagado') === '1';
    b.disabled = true;
    b.textContent = '…';
    Lebleu.enviarJson('/api/extraordinarios/pagado', { id: Number(b.getAttribute('data-pagado-id')), liquidado: !pagado })
      .then(function (j) {
        if (j.ok) { Lebleu.recargar(); return; }
        alert(j.error || 'No se pudo actualizar.');
        b.disabled = false;
        b.textContent = pagado ? 'Pagado' : 'Pendiente';
      });
  });
})();
