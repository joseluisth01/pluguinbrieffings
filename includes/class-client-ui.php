<?php
if (!defined('ABSPATH')) exit;

class TTB_Client_UI {

  public static function render($client_id) {
    global $wpdb;
    $clients = TTB_DB::clients_table();

    $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients WHERE id=%d", $client_id));
    if (!$c) {
      echo '<div class="ttb-card"><p>Cliente no encontrado.</p></div>';
      return;
    }

    $services = json_decode((string)$c->services, true);
    if (!is_array($services)) $services = [];

    echo '<div class="ttb-container">';
    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2>Hola, '.esc_html($c->name).' 👋</h2>';
    echo '<p class="ttb-muted">Completa los briefings asignados. Puedes guardar y seguir más tarde o enviar cuando lo tengas.</p>';
    echo '</div>';

    if (!$services) {
      echo '<div class="ttb-card"><p class="ttb-muted">No tienes servicios asignados todavía.</p></div></div>';
      return;
    }

    $titles = [
      'design' => 'Briefing de Diseño',
      'social' => 'Briefing de Redes',
      'seo'    => 'Briefing de SEO',
      'web'    => 'Briefing de Web',
    ];

    foreach ($services as $svc) {
      $schema = TTB_Forms::get_schema($svc);
      $payload = TTB_Forms::get_client_answers($client_id, $svc);
      $answers = $payload['answers'];
      $sent = (int)$payload['sent'];
      $title = $titles[$svc] ?? strtoupper($svc);

      include TTB_PATH.'templates/form.php';
    }

    echo '</div>';
  }
}
