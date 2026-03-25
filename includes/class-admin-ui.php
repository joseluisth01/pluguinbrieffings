<?php
if (!defined('ABSPATH')) exit;

class TTB_Admin_UI {

  private static function flash_and_redirect($type, $text, $url = null) {
    set_transient('ttb_admin_flash', ['type' => $type, 'text' => $text], 60);
    if (!$url) {
      $section = sanitize_text_field($_GET['section'] ?? 'clientes');
      $url     = home_url('/briefing?section=' . $section);
    }
    wp_safe_redirect($url);
    exit;
  }

  public static function render() {
    $auth = new TTB_Auth();
    if (!$auth->is_admin()) {
      echo '<div class="ttb-card"><p>No autorizado.</p></div>';
      return;
    }

    $section = sanitize_text_field($_GET['section'] ?? 'clientes');
    $tab     = sanitize_text_field($_GET['tab']     ?? 'clients');

    if ($section === 'clientes') {
      TTB_Clients_UI::render_and_handle_forms();
    } elseif ($section === 'briefings') {
      self::handle_forms_save($tab);
    }

    TTB_Social_Admin::handle_client_edit_networks_action();

    echo '<div class="ttb-container">';
    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2 style="text-align:center">PORTAL CLIENTE</h2>';
    echo '</div>';

    $flash = get_transient('ttb_admin_flash');
    if ($flash) {
      delete_transient('ttb_admin_flash');
      $cls = ($flash['type'] === 'success') ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html($flash['text']) . '</div>';
    }

    echo '<div class="ttb-tabs ttb-tabs--main">';
    self::section_link('clientes',       'Clientes',              $section);
    self::section_link('briefings',      'Prebriefings',          $section);
    self::section_link('revisiones-dis', 'Revisiones Diseños',    $section);
    self::section_link('redes-sociales', 'Redes Sociales',        $section);
    self::section_link('revisiones-web', 'Revisiones Prog. Web',  $section);
    echo '</div>';

    switch ($section) {
      case 'clientes':
        TTB_Clients_UI::render();
        break;

      case 'briefings':
        echo '<div class="ttb-tabs">';
        self::tab_link('answers',  'Respuestas',       $tab, $section);
        self::tab_link('forms',    'Formularios ES',   $tab, $section);
        self::tab_link('forms_en', 'Formularios EN',   $tab, $section);
        echo '</div>';

        if ($tab === 'answers')       self::render_answers();
        elseif ($tab === 'forms')     self::render_forms('es');
        elseif ($tab === 'forms_en')  self::render_forms('en');
        else                          self::render_answers();
        break;

      case 'revisiones-dis':
        TTB_WebRev_Admin::render();
        break;

      case 'redes-sociales':
        TTB_Social_Admin::render();
        break;

      case 'revisiones-web':
        TTB_WebProg_Admin::render();
        break;
    }

    echo '</div>';
  }

  private static function section_link($key, $label, $active) {
    $icon_map = [
      'clientes'       => 'clients',
      'briefings'      => 'briefings',
      'revisiones-dis' => 'revisiones-dis',
      'redes-sociales' => 'redes-sociales',
      'revisiones-web' => 'revisiones-web',
    ];
    $icon = ttb_icon($icon_map[$key] ?? '');
    $url  = esc_url(home_url('/briefing?section=' . $key));
    $cls  = ($key === $active)
      ? 'ttb-tab ttb-tab--main ttb-tab--active'
      : 'ttb-tab ttb-tab--main';
    echo '<a class="' . $cls . '" href="' . $url . '">' . $icon . esc_html($label) . '</a>';
  }

  private static function tab_link($key, $label, $active, $section = 'briefings') {
    $icon_map = [
      'answers'   => 'answers',
      'forms'     => 'forms',
      'forms_en'  => 'forms',
    ];
    $icon = ttb_icon($icon_map[$key] ?? '');
    $url  = esc_url(home_url('/briefing?section=' . $section . '&tab=' . $key));
    $cls  = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . $url . '">' . $icon . esc_html($label) . '</a>';
  }

  /* ════════════════════════════════
     GUARDAR FORMULARIOS JSON
     — añadido ttb_form_reservas —
  ════════════════════════════════ */
  private static function handle_forms_save($tab) {
    if (!isset($_POST['ttb_admin_save_forms'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_forms')) return;

    $lang = sanitize_text_field($_POST['ttb_form_lang'] ?? 'es');
    $sfx  = ($lang === 'en') ? '_en' : '';

    update_option('ttb_form_design'   . $sfx, wp_unslash($_POST['ttb_form_design']   ?? ''));
    update_option('ttb_form_social'   . $sfx, wp_unslash($_POST['ttb_form_social']   ?? ''));
    update_option('ttb_form_seo'      . $sfx, wp_unslash($_POST['ttb_form_seo']      ?? ''));
    update_option('ttb_form_web'      . $sfx, wp_unslash($_POST['ttb_form_web']      ?? ''));
    update_option('ttb_form_reservas' . $sfx, wp_unslash($_POST['ttb_form_reservas'] ?? ''));

    $dest_tab = ($lang === 'en') ? 'forms_en' : 'forms';
    self::flash_and_redirect('success', 'Formularios guardados.',
      home_url('/briefing?section=briefings&tab=' . $dest_tab));
  }

  /* ════════════════════════════════
     RENDER: FORMULARIOS (ES / EN)
     — añadido bloque Reservas —
  ════════════════════════════════ */
  private static function render_forms($lang = 'es') {
    $sfx = ($lang === 'en') ? '_en' : '';

    $design   = (string)get_option('ttb_form_design'   . $sfx, '');
    $social   = (string)get_option('ttb_form_social'   . $sfx, '');
    $seo      = (string)get_option('ttb_form_seo'      . $sfx, '');
    $web      = (string)get_option('ttb_form_web'      . $sfx, '');
    $reservas = (string)get_option('ttb_form_reservas' . $sfx, '');

    $lang_label = $lang === 'en' ? 'English' : 'Español';

    echo '<div class="ttb-card"><h3>Formularios Prebriefing — ' . esc_html($lang_label) . ' (JSON)</h3>';
    echo '<p class="ttb-muted">Edita campos por servicio. Formato: lista de objetos con id/label/type/required/options.</p></div>';

    echo '<form method="post" action="' . esc_url(home_url('/briefing?section=briefings&tab=' . ($lang === 'en' ? 'forms_en' : 'forms'))) . '" class="ttb-card">';
    wp_nonce_field('ttb_admin_forms');
    echo '<input type="hidden" name="ttb_form_lang" value="' . esc_attr($lang) . '">';

    // Grid 2 columnas para los 4 formularios originales
    echo '<div class="ttb-grid2">';
    self::json_box('Design',  'ttb_form_design',  $design);
    self::json_box('Social',  'ttb_form_social',  $social);
    self::json_box('SEO',     'ttb_form_seo',     $seo);
    self::json_box('Web',     'ttb_form_web',     $web);
    echo '</div>';

    // Reservas en su propia fila a ancho completo
    echo '<div style="margin-top:16px">';
    echo '<h4 style="margin:0 0 8px;font-size:15px;color:var(--ttb-text)">🍽️ Gestor de Reservas Restaurante</h4>';
    echo '<textarea name="ttb_form_reservas" class="ttb-textarea" style="min-height:180px">' . esc_textarea($reservas) . '</textarea>';
    echo '</div>';

    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_admin_save_forms" value="1">Guardar formularios</button></div>';
    echo '</form>';
  }

  private static function json_box($title, $name, $val) {
    echo '<div class="ttb-jsonbox">';
    echo '<h4>' . esc_html($title) . '</h4>';
    echo '<textarea name="' . esc_attr($name) . '" class="ttb-textarea">' . esc_textarea($val) . '</textarea>';
    echo '</div>';
  }

  /* ════════════════════════════════
     RENDER: RESPUESTAS
  ════════════════════════════════ */
  private static function render_answers() {
    global $wpdb;
    $clients_table = TTB_DB::clients_table();
    $clients = $wpdb->get_results("SELECT id,name,email,emails,lang,status FROM $clients_table ORDER BY updated_at DESC LIMIT 200");

    echo '<div class="ttb-card"><h3>Respuestas de Prebriefing</h3><p class="ttb-muted">Selecciona un cliente para ver sus respuestas por servicio.</p></div>';

    echo '<div class="ttb-card"><div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
      <th>Cliente</th><th>Email(s)</th><th>Idioma</th><th>Estado</th><th>Ver</th>
    </tr></thead><tbody>';

    foreach ($clients as $c) {
      $url      = esc_url(home_url('/briefing?section=briefings&tab=answers&client=' . (int)$c->id));
      $c_lang   = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';
      $emails   = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];
      echo '<tr>';
      echo '<td><strong>' . esc_html($c->name) . '</strong></td>';
      echo '<td style="font-size:13px">' . implode('<br>', array_map('esc_html', $emails)) . '</td>';
      echo '<td>' . ($c_lang === 'en' ? '🇬🇧 EN' : '🇪🇸 ES') . '</td>';
      echo '<td>' . esc_html($c->status) . '</td>';
      echo '<td><a class="ttb-btn ttb-btn--ghost ttb-btn--sm" href="' . $url . '">Ver</a></td>';
      echo '</tr>';
    }

    echo '</tbody></table></div></div>';

    $client_id = (int)($_GET['client'] ?? 0);
    if (!$client_id) return;

    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients_table WHERE id=%d", $client_id));
    if (!$client) return;

    $services = json_decode((string)$client->services, true);
    if (!is_array($services)) $services = [];
    $c_lang = in_array($client->lang ?? '', ['es', 'en'], true) ? $client->lang : 'es';

    echo '<div class="ttb-card"><h3>Prebriefing: ' . esc_html($client->name) . ' ' . ($c_lang === 'en' ? '🇬🇧' : '🇪🇸') . '</h3></div>';

    foreach ($services as $svc) {
      $schema  = TTB_Forms::get_schema($svc, $c_lang);
      $payload = TTB_Forms::get_client_answers($client_id, $svc);
      $answers = $payload['answers'];
      $sent    = (int)$payload['sent'];

      echo '<div class="ttb-card">';
      echo '<h4>' . esc_html(strtoupper($svc)) . ' ' . ($sent ? '<span class="ttb-pill">ENVIADO</span>' : '<span class="ttb-pill ttb-pill--draft">BORRADOR</span>') . '</h4>';

      if (!$answers) { echo '<p class="ttb-muted">Sin respuestas todavía.</p></div>'; continue; }

      $drive_url = $answers['ttb_drive_url'] ?? '';
      if ($drive_url) {
        echo '<a href="' . esc_url($drive_url) . '" target="_blank" rel="noopener" class="ttb-btn ttb-btn--ghost ttb-btn--sm" style="margin-bottom:12px;display:inline-flex;align-items:center;gap:6px">📄 Ver en Google Drive</a>';
      }
      echo '<div class="ttb-qa">';
      foreach ($schema as $f) {
        $id    = $f['id'] ?? ''; if (!$id) continue;
        $label = $f['label'] ?? $id;
        $val   = $answers[$id] ?? '';
        if (is_array($val)) $val = implode(', ', $val);
        echo '<div class="ttb-q"><div class="ttb-q__l">' . esc_html($label) . '</div><div class="ttb-q__a">' . nl2br(esc_html((string)$val)) . '</div></div>';
      }
      echo '</div></div>';
    }
  }
}