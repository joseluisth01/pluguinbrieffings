<?php
if (!defined('ABSPATH')) exit;

class TTB_Client_UI {

  public static function render($client_id) {
    global $wpdb;
    $clients = TTB_DB::clients_table();

    $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients WHERE id=%d", $client_id));
    if (!$c) {
      echo '<div class="ttb-card"><p>Client not found / Cliente no encontrado.</p></div>';
      return;
    }

    $services = json_decode((string)$c->services, true);
    if (!is_array($services)) $services = [];

    $lang = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';

    $greeting  = $lang === 'en' ? 'Hello' : 'Hola';
    $sub_text  = $lang === 'en'
      ? 'Complete the assigned pre-briefings. You can save and continue later, or submit when ready.'
      : 'Completa los prebriefings asignados. Puedes guardar y seguir más tarde o enviar cuando lo tengas.';
    $no_svc    = $lang === 'en'
      ? 'No services assigned yet.'
      : 'No tienes servicios asignados todavía.';

    echo '<div class="ttb-container">';
    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2>' . esc_html($greeting) . ', ' . esc_html($c->name) . ' 👋</h2>';
    echo '<p class="ttb-muted">' . esc_html($sub_text) . '</p>';
    echo '</div>';

    if (!$services) {
      echo '<div class="ttb-card"><p class="ttb-muted">' . esc_html($no_svc) . '</p></div></div>';
      return;
    }

    $titles_es = [
      'design' => 'Prebriefing de Diseño',
      'social' => 'Prebriefing de Redes',
      'seo'    => 'Prebriefing de SEO',
      'web'    => 'Prebriefing de Web',
    ];
    $titles_en = [
      'design' => 'Design Pre-briefing',
      'social' => 'Social Media Pre-briefing',
      'seo'    => 'SEO Pre-briefing',
      'web'    => 'Web Pre-briefing',
    ];
    $titles = $lang === 'en' ? $titles_en : $titles_es;

    foreach ($services as $svc) {
      $schema  = TTB_Forms::get_schema($svc, $lang);
      $payload = TTB_Forms::get_client_answers($client_id, $svc);
      $answers = $payload['answers'];
      $sent    = (int)$payload['sent'];
      $title   = $titles[$svc] ?? strtoupper($svc);

      include TTB_PATH . 'templates/form.php';
    }

    echo '</div>';
  }
}