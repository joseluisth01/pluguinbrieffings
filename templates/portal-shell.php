<?php
if (!defined('ABSPATH')) exit;

$auth = new TTB_Auth();
$flash = $auth->consume_flash();

get_header();

echo '<div class="ttb-portal">';
  echo '<div class="ttb-top">';
    echo '<div class="ttb-top__inner">';
      echo '<a class="ttb-brand" href="'.esc_url(home_url('/')).'">';
        echo '<img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="TicTac">';
      echo '</a>';

      if ($auth->current()) {
        echo '<a class="ttb-logout" href="'.esc_url(add_query_arg(['ttb_logout'=>1], home_url('/briefing'))).'">Cerrar sesión</a>';
      }
    echo '</div>';
  echo '</div>';

  echo '<div class="ttb-main">';
    if ($flash && is_array($flash)) {
      $cls = ($flash['type'] ?? '') === 'error' ? 'ttb-alert ttb-alert--error' : 'ttb-alert ttb-alert--success';
      echo '<div class="'.$cls.'">'.esc_html($flash['text'] ?? '').'</div>';
    }

    if (!$auth->current()) {
      include TTB_PATH.'templates/login.php';
    } else if ($auth->is_admin()) {
      include TTB_PATH.'templates/admin.php';
    } else {
      include TTB_PATH.'templates/client.php';
    }
  echo '</div>';
echo '</div>';

get_footer();
