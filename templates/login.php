<?php
if (!defined('ABSPATH')) exit;
?>
<div class="ttb-card ttb-login">
  <div class="ttb-login__head">
    <h1>Acceso Briefing</h1>
    <p class="ttb-muted">Introduce tus credenciales para continuar.</p>
  </div>

  <form method="post" class="ttb-form">
    <?php wp_nonce_field('ttb_login'); ?>
    <label>Usuario</label>
    <input class="ttb-input" type="text" name="username" required>

    <label>Contraseña</label>
    <input class="ttb-input" type="password" name="password" required>

    <button class="ttb-btn" type="submit" name="ttb_login" value="1">Entrar</button>
  </form>

  <div class="ttb-help">
    <small>Portal privado. Si tienes problemas de acceso, responde al email de invitación.</small>
  </div>
</div>
