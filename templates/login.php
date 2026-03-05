<?php
if (!defined('ABSPATH')) exit;

// URL del handler independiente (en la raíz del plugin)
$handler_url = TTB_URL . 'ttb-login-handler.php';
?>

<div class="ttb-card ttb-login">
  <div class="ttb-login__head">
    <h1>Acceso Briefing</h1>
    <p class="ttb-muted">Introduce tus credenciales para continuar.</p>
  </div>

  <form method="post" action="<?php echo esc_url($handler_url); ?>" class="ttb-form">
    <label for="ttb_username">Usuario</label>
    <input
      id="ttb_username"
      class="ttb-input"
      type="text"
      name="username"
      autocomplete="username"
      required
    >

    <label for="ttb_password">Contraseña</label>
    <input
      id="ttb_password"
      class="ttb-input"
      type="password"
      name="password"
      autocomplete="current-password"
      required
    >

    <button class="ttb-btn" type="submit">Entrar</button>
  </form>

  <div class="ttb-help">
    <small>Portal privado. Si tienes problemas de acceso, responde al email de invitación.</small>
  </div>
</div>