<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';

session_start_safe();

if (is_logged_in()) {
    redirect('views/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nido — Iniciar sesión</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>" />
  <style>
    .login-footer {
      text-align: center;
      margin-top: 1.5rem;
      font-size: 0.8125rem;
      color: rgba(255,255,255,0.25);
    }
    .dots-bg {
      position: absolute; inset: 0; z-index: 0;
      background-image: radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px);
      background-size: 28px 28px;
    }
  </style>
</head>
<body>

<div class="login-page">
  <div class="dots-bg"></div>

  <div class="login-card">

    <!-- Brand -->
    <div class="login-brand">
      <div class="brand-logo">🏠</div>
      <h1>Nido</h1>
      <p>Gestión financiera para dos</p>
    </div>

    <h2>Bienvenido/a de vuelta</h2>

    <!-- Alert de error -->
    <div class="alert alert-danger" id="loginAlert" role="alert"></div>

    <!-- Formulario -->
    <form id="loginForm" novalidate>

      <div class="form-group">
        <label for="email">Correo electrónico</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="tu@correo.com"
            autocomplete="email"
            required
          />
        </div>
      </div>

      <div class="form-group">
        <label for="password">Contraseña</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input
            type="password"
            id="password"
            name="password"
            class="form-control"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          />
          <button type="button" class="toggle-pass" id="togglePass" aria-label="Mostrar contraseña">👁</button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" id="btnLogin">
        <span class="spinner" id="loginSpinner"></span>
        <span id="btnText">Iniciar sesión</span>
      </button>

    </form>

    <p class="login-footer">Nido by Wolfora &copy; <?= date('Y') ?> — Uso privado</p>

  </div>
</div>

<script>
  const APP_URL   = <?= json_encode(APP_URL) ?>;
  const form      = document.getElementById('loginForm');
  const alertEl   = document.getElementById('loginAlert');
  const btn       = document.getElementById('btnLogin');
  const btnText   = document.getElementById('btnText');
  const spinner   = document.getElementById('loginSpinner');
  const togglePass = document.getElementById('togglePass');
  const passInput  = document.getElementById('password');

  togglePass.addEventListener('click', () => {
    const visible = passInput.type === 'text';
    passInput.type = visible ? 'password' : 'text';
    togglePass.textContent = visible ? '👁' : '🙈';
  });

  function showAlert(msg) {
    alertEl.textContent = msg;
    alertEl.classList.add('show');
  }

  function hideAlert() {
    alertEl.classList.remove('show');
  }

  function setLoading(loading) {
    btn.disabled = loading;
    spinner.style.display = loading ? 'block' : 'none';
    btnText.textContent   = loading ? 'Verificando...' : 'Iniciar sesión';
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();

    const email    = document.getElementById('email').value.trim();
    const password = passInput.value;

    if (!email || !password) {
      showAlert('Por favor completa todos los campos.');
      return;
    }

    setLoading(true);

    try {
      const res  = await fetch(APP_URL + '/api/auth.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ action: 'login', email, password }),
      });

      const data = await res.json();

      if (data.success) {
        window.location.href = APP_URL + '/views/dashboard.php';
      } else {
        showAlert(data.message || 'Credenciales incorrectas.');
      }
    } catch (err) {
      showAlert('Error de conexión. Intenta de nuevo.');
    } finally {
      setLoading(false);
    }
  });
</script>

</body>
</html>
