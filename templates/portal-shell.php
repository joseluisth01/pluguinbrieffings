<?php
if (!defined('ABSPATH')) exit;

$auth  = new TTB_Auth();
$flash = $auth->consume_flash();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Briefing — TicTac Comunicación</title>
  <meta name="robots" content="noindex, nofollow, noarchive">
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <?php wp_head(); ?>
</head>
<body <?php body_class('ttb-body'); ?>>

<div class="ttb-portal">

  <div class="ttb-top">
    <div class="ttb-top__inner">
      <a class="ttb-brand" href="<?php echo esc_url(home_url('/')); ?>">
        <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="TicTac">
      </a>
      <?php if ($auth->current()): ?>
        <a class="ttb-logout" href="<?php echo esc_url(add_query_arg(['ttb_logout' => 1], home_url('/briefing'))); ?>">
          Cerrar sesión
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="ttb-main">

    <?php if ($flash && is_array($flash)):
      $cls = ($flash['type'] ?? '') === 'error' ? 'ttb-alert ttb-alert--error' : 'ttb-alert ttb-alert--success';
    ?>
      <div class="<?php echo $cls; ?>"><?php echo esc_html($flash['text'] ?? ''); ?></div>
    <?php endif; ?>

    <?php
    if (!$auth->current()):
      include TTB_PATH . 'templates/login.php';
    elseif ($auth->is_admin()):
      include TTB_PATH . 'templates/admin.php';
    else:
      include TTB_PATH . 'templates/client.php';
    endif;
    ?>

  </div><!-- .ttb-main -->

</div><!-- .ttb-portal -->

<?php get_footer(); ?>