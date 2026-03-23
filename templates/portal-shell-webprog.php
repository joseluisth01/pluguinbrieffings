<?php
if (!defined('ABSPATH')) exit;

ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * portal-shell-webprog.php
 * Shell para la vista pública del cliente de revisiones Prog. Web.
 * No requiere login — usa magic link con token (?webprog=TOKEN).
 */

$token = sanitize_text_field($_GET['webprog'] ?? '');

nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Revisión de programación web — TicTac Comunicación</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<meta name="robots" content="noindex, nofollow, noarchive">
<?php wp_head(); ?>
<style>
.ttb-header {
  background: #D72173;
  width: 100%;
  position: sticky;
  top: 0;
  z-index: 999999;
}
.ttb-header-inner {
  max-width: 1200px;
  margin: auto;
  height: 70px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ttb-logo { position: absolute; left: 50%; transform: translateX(-50%); }
.ttb-logo img { height: 40px; display: block; }
.ttb-main { max-width: 960px; margin: auto; padding: 30px 20px; }

.ttb-flash {
  border-radius: 14px;
  padding: 12px 16px;
  margin-bottom: 16px;
  font-weight: 700;
  font-size: 14px;
}
.ttb-flash--success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.ttb-flash--error   { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; }
</style>
</head>
<body <?php body_class('ttb-body'); ?>>

<header class="ttb-header">
  <div class="ttb-header-inner">
    <a class="ttb-logo" href="<?php echo esc_url(home_url('/')); ?>">
      <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="TicTac Comunicación">
    </a>
  </div>
</header>

<div class="ttb-main">
<?php

$flash = get_transient('ttb_webprog_flash_' . $token);
if ($flash) {
  delete_transient('ttb_webprog_flash_' . $token);
  $cls = $flash['type'] === 'success' ? 'ttb-flash--success' : 'ttb-flash--error';
  echo '<div class="ttb-flash ' . $cls . '">' . esc_html($flash['text']) . '</div>';
}

TTB_WebProg_Client::render($token);

?>
</div>

<?php wp_footer(); ?>
</body>
</html>