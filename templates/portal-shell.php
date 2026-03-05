<?php
if (!defined('ABSPATH')) exit;

$auth  = new TTB_Auth();
$flash = $auth->consume_flash();

$lang = 'es';
if ($auth->is_client()) {
  $lang = TTB_Forms::get_client_lang($auth->client_id());
}

$logout_label = $lang === 'en' ? 'Log out' : 'Cerrar sesión';

nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Briefing — TicTac Comunicación</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

<meta name="robots" content="noindex, nofollow, noarchive">

<?php wp_head(); ?>

<style>

.ttb-header{
  background:#D72173;
  width:100%;
  position:sticky;
  top:0;
  z-index:999999;
}

.ttb-header-inner{
  max-width:1200px;
  margin:auto;
  height:70px;
  position:relative;
  display:flex;
  align-items:center;
  justify-content:center;
}

/* LOGO CENTRADO */
.ttb-logo{
  position:absolute;
  left:50%;
  transform:translateX(-50%);
}

.ttb-logo img{
  height:40px;
  display:block;
}

/* BOTON DERECHA */
.ttb-logout{
  position:absolute;
  right:20px;
  background:white;
  color:#D72173;
  padding:8px 14px;
  border-radius:8px;
  text-decoration:none;
  font-weight:600;
  font-family:Montserrat, Arial;
}

.ttb-logout:hover{
  background:#f4f4f4;
}

.ttb-main{
  max-width:1200px;
  margin:auto;
  padding:30px 20px;
}

</style>

</head>

<body <?php body_class('ttb-body'); ?>>

<header class="ttb-header">

<div class="ttb-header-inner">

<a class="ttb-logo" href="<?php echo esc_url(home_url('/briefing')); ?>">
<img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="TicTac Comunicación">
</a>

<?php if ($auth->current()): ?>

<a class="ttb-logout" href="<?php echo esc_url(add_query_arg(['ttb_logout'=>1],home_url('/briefing'))); ?>">
<?php echo esc_html($logout_label); ?>
</a>

<?php endif; ?>

</div>

</header>

<div class="ttb-main">

<?php if ($flash): ?>
<div class="ttb-flash ttb-flash--<?php echo esc_attr($flash['type']); ?>">
<?php echo esc_html($flash['text']); ?>
</div>
<?php endif; ?>

<?php

if (!$auth->current()){

include TTB_PATH.'templates/login.php';

}elseif ($auth->is_admin()){

include TTB_PATH.'templates/admin.php';

}else{

include TTB_PATH.'templates/client.php';

}

?>

</div>

<?php get_footer(); ?>