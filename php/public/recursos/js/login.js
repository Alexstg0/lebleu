// Formulario de inicio de sesión.
(function () {
  var form = document.querySelector('[data-login]');
  if (!form) return;
  var error = form.querySelector('[data-error]');
  var boton = form.querySelector('.auth-submit');
  var texto = form.querySelector('[data-texto]');
  var clave = form.elements.password;

  form.querySelector('[data-ver-clave]').addEventListener('click', function () {
    var ver = clave.type === 'password';
    clave.type = ver ? 'text' : 'password';
    this.setAttribute('aria-label', ver ? 'Ocultar' : 'Mostrar');
    this.querySelector('.tachon').hidden = !ver;
  });

  form.querySelector('[data-olvide]').addEventListener('click', function () {
    alert('Contacta al administrador para restablecer tu contraseña.');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    boton.disabled = true;
    texto.textContent = 'Entrando…';
    error.hidden = true;
    Lebleu.enviarJson('/api/login', { email: form.elements.email.value, password: clave.value })
      .then(function (j) {
        if (j.ok) {
          window.location.href = j.rol === 'capitan' ? '/calendario' : '/';
          return;
        }
        error.textContent = j.error || 'No se pudo iniciar sesión.';
        error.hidden = false;
      })
      .catch(function (err) {
        error.textContent = String((err && err.message) || err);
        error.hidden = false;
      })
      .finally(function () {
        boton.disabled = false;
        texto.textContent = 'Iniciar sesión';
      });
  });
})();
