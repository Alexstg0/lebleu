// Botón PDF: abre el diálogo de impresión del navegador.
document.addEventListener('click', function (e) {
  if (e.target.closest('[data-imprimir]')) window.print();
});
