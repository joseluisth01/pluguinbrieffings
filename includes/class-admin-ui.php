<?php
if (!defined('ABSPATH')) exit;

class TTB_Admin_UI {

  /* ── Flash via transient (PRG pattern) ── */
  private static function flash_and_redirect($type, $text, $url = null) {
    set_transient('ttb_admin_flash', ['type' => $type, 'text' => $text], 60);
    if (!$url) {
      $section = sanitize_text_field($_GET['section'] ?? 'briefings');
      $tab     = sanitize_text_field($_GET['tab']     ?? 'clients');
      $url     = home_url('/briefing?section=' . $section . '&tab=' . $tab);
    }
    echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    exit;
  }

  public static function render() {
    $auth = new TTB_Auth();
    if (!$auth->is_admin()) {
      echo '<div class="ttb-card"><p>No autorizado.</p></div>';
      return;
    }

    $section = sanitize_text_field($_GET['section'] ?? 'briefings');
    $tab     = sanitize_text_field($_GET['tab']     ?? 'clients');

    if ($section === 'briefings') {
      self::handle_forms_save($tab);
      self::handle_client_create($tab);
      self::handle_client_edit($tab);
      self::handle_client_delete($tab);
      self::handle_resend_email($tab);
    }

    echo '<div class="ttb-container">';
    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2 style="text-align:center">PORTAL CLIENTE</h2>';
    echo '</div>';

    // Leer flash del transient
    $flash = get_transient('ttb_admin_flash');
    if ($flash) {
      delete_transient('ttb_admin_flash');
      $cls = ($flash['type'] === 'success') ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html($flash['text']) . '</div>';
    }

    // ── Pestañas principales ──
    echo '<div class="ttb-tabs ttb-tabs--main">';
    self::section_link('briefings',       'Prebriefings',          $section);
    self::section_link('revisiones-dis',  'Revisiones Diseños',    $section);
    self::section_link('redes-sociales',  'Redes Sociales',        $section);
    self::section_link('revisiones-web',  'Revisiones Prog. Web',  $section);
    echo '</div>';

    switch ($section) {

      case 'briefings':
        echo '<div class="ttb-tabs">';
        self::tab_link('clients',  'Clientes',         $tab, $section);
        self::tab_link('answers',  'Respuestas',       $tab, $section);
        self::tab_link('forms',    'Formularios ES',   $tab, $section);
        self::tab_link('forms_en', 'Formularios EN',   $tab, $section);
        echo '</div>';

        if ($tab === 'answers')       self::render_answers();
        elseif ($tab === 'forms')     self::render_forms('es');
        elseif ($tab === 'forms_en')  self::render_forms('en');
        else                          self::render_clients();
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

  /* ── Pestaña principal ── */
  private static function section_link($key, $label, $active) {
    $icon_map = [
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

  /* ── Sub-pestaña ── */
  private static function tab_link($key, $label, $active, $section = 'briefings') {
    $icon_map = [
      'clients'   => 'clients',
      'answers'   => 'answers',
      'forms'     => 'forms',
      'forms_en'  => 'forms',
      'revisions' => 'revisions',
      'audit'     => 'audit',
      'settings'  => 'settings',
      'calendar'  => 'calendar',
      'content'   => 'content',
      'projects'  => 'projects',
    ];
    $icon = ttb_icon($icon_map[$key] ?? '');
    $url  = esc_url(home_url('/briefing?section=' . $section . '&tab=' . $key));
    $cls  = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . $url . '">' . $icon . esc_html($label) . '</a>';
  }

  /* ── Guardar formularios JSON ── */
  private static function handle_forms_save($tab) {
    if (!isset($_POST['ttb_admin_save_forms'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_forms')) return;

    $lang = sanitize_text_field($_POST['ttb_form_lang'] ?? 'es');
    $sfx  = ($lang === 'en') ? '_en' : '';

    update_option('ttb_form_design' . $sfx, wp_unslash($_POST['ttb_form_design'] ?? ''));
    update_option('ttb_form_social' . $sfx, wp_unslash($_POST['ttb_form_social'] ?? ''));
    update_option('ttb_form_seo'    . $sfx, wp_unslash($_POST['ttb_form_seo']    ?? ''));
    update_option('ttb_form_web'    . $sfx, wp_unslash($_POST['ttb_form_web']    ?? ''));

    $dest_tab = ($lang === 'en') ? 'forms_en' : 'forms';
    self::flash_and_redirect('success', 'Formularios guardados.',
      home_url('/briefing?section=briefings&tab=' . $dest_tab));
  }

  /* ── Crear cliente ── */
  private static function handle_client_create($tab) {
    if (!isset($_POST['ttb_admin_create_client'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_clients')) return;

    $name     = sanitize_text_field($_POST['client_name']  ?? '');
    $email    = sanitize_email($_POST['client_email']       ?? '');
    $lang     = in_array($_POST['client_lang'] ?? '', ['es', 'en'], true) ? $_POST['client_lang'] : 'es';
    $services = array_map('sanitize_text_field', (array)($_POST['services'] ?? []));

    if (!$name || !$email) {
      self::flash_and_redirect('error', 'Nombre y email son obligatorios.',
        home_url('/briefing?section=briefings&tab=clients'));
    }

    $username = sanitize_user($name, true);
    if (!$username) $username = 'cliente';
    $password = $name;

    global $wpdb;
    $table = TTB_DB::clients_table();

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username=%s", $username));
    if ($exists) $username .= '-' . wp_generate_password(4, false, false);

    $wpdb->insert($table, [
      'name'       => $name,
      'email'      => $email,
      'username'   => $username,
      'pass_hash'  => password_hash($password, PASSWORD_DEFAULT),
      'services'   => wp_json_encode(array_values($services)),
      'lang'       => $lang,
      'status'     => 'pendiente',
      'created_at' => TTB_DB::now(),
      'updated_at' => TTB_DB::now(),
    ]);

    (new TTB_Mailer())->send_client_access($name, $email, $username, $password, $services, $lang);

    self::flash_and_redirect('success', 'Cliente creado y email enviado.',
      home_url('/briefing?section=briefings&tab=clients'));
  }

  /* ── Editar cliente ── */
  private static function handle_client_edit($tab) {
    if (!isset($_POST['ttb_admin_edit_client'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_edit_client')) return;

    $client_id = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    $name     = sanitize_text_field($_POST['client_name']  ?? '');
    $email    = sanitize_email($_POST['client_email']       ?? '');
    $lang     = in_array($_POST['client_lang'] ?? '', ['es', 'en'], true) ? $_POST['client_lang'] : 'es';
    $services = array_map('sanitize_text_field', (array)($_POST['services'] ?? []));

    if (!$name || !$email) {
      self::flash_and_redirect('error', 'Nombre y email son obligatorios.',
        home_url('/briefing?section=briefings&tab=clients'));
    }

    global $wpdb;
    $table = TTB_DB::clients_table();

    $wpdb->update($table, [
      'name'       => $name,
      'email'      => $email,
      'services'   => wp_json_encode(array_values($services)),
      'lang'       => $lang,
      'updated_at' => TTB_DB::now(),
    ], ['id' => $client_id]);

    self::flash_and_redirect('success', 'Cliente actualizado correctamente.',
      home_url('/briefing?section=briefings&tab=clients'));
  }

  /* ── Eliminar cliente ── */
  private static function handle_client_delete($tab) {
    if (!isset($_POST['ttb_admin_delete_client'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_delete_client')) return;

    $client_id = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    global $wpdb;
    $wpdb->delete(TTB_DB::clients_table(), ['id' => $client_id]);
    $wpdb->delete(TTB_DB::answers_table(), ['client_id' => $client_id]);

    self::flash_and_redirect('success', 'Cliente eliminado.',
      home_url('/briefing?section=briefings&tab=clients'));
  }

  /* ── Reenviar email ── */
  private static function handle_resend_email($tab) {
    if (!isset($_POST['ttb_admin_resend'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_resend')) return;

    $client_id = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    global $wpdb;
    $table = TTB_DB::clients_table();
    $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $client_id));
    if (!$c) return;

    $services = json_decode((string)$c->services, true);
    if (!is_array($services)) $services = [];
    $password = (string)$c->name;
    $lang     = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';

    (new TTB_Mailer())->send_client_access((string)$c->name, (string)$c->email, (string)$c->username, $password, $services, $lang);

    self::flash_and_redirect('success', 'Email reenviado.',
      home_url('/briefing?section=briefings&tab=clients'));
  }

  /* ════════════════════════════════
     RENDER: FORMULARIOS (ES / EN)
  ════════════════════════════════ */
  private static function render_forms($lang = 'es') {
    $sfx = ($lang === 'en') ? '_en' : '';

    $design = (string)get_option('ttb_form_design' . $sfx, '');
    $social = (string)get_option('ttb_form_social' . $sfx, '');
    $seo    = (string)get_option('ttb_form_seo'    . $sfx, '');
    $web    = (string)get_option('ttb_form_web'    . $sfx, '');

    $lang_label = $lang === 'en' ? 'English' : 'Español';

    echo '<div class="ttb-card"><h3>Formularios Prebriefing — ' . esc_html($lang_label) . ' (JSON)</h3>';
    echo '<p class="ttb-muted">Edita campos por servicio. Formato: lista de objetos con id/label/type/required/options.</p></div>';

    echo '<form method="post" action="' . esc_url(home_url('/briefing?section=briefings&tab=' . ($lang === 'en' ? 'forms_en' : 'forms'))) . '" class="ttb-card">';
    wp_nonce_field('ttb_admin_forms');
    echo '<input type="hidden" name="ttb_form_lang" value="' . esc_attr($lang) . '">';
    echo '<div class="ttb-grid2">';
    self::json_box('Design',  'ttb_form_design', $design);
    self::json_box('Social',  'ttb_form_social', $social);
    self::json_box('SEO',     'ttb_form_seo',    $seo);
    self::json_box('Web',     'ttb_form_web',    $web);
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
     RENDER: CLIENTES
  ════════════════════════════════ */
  private static function render_clients() {
    global $wpdb;
    $table   = TTB_DB::clients_table();
    $clients = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");

    $edit_id = (int)($_GET['edit_client'] ?? 0);
    $edit_c  = null;
    if ($edit_id) {
      $edit_c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id));
    }

    echo '<div class="ttb-card"><h3>Crear cliente</h3></div>';
    echo '<form method="post" action="' . esc_url(home_url('/briefing?section=briefings&tab=clients')) . '" class="ttb-card">';
    wp_nonce_field('ttb_admin_clients');
    echo '<div class="ttb-grid2">';
    echo '<div><label>Nombre</label><input class="ttb-input" type="text" name="client_name" required></div>';
    echo '<div><label>Email</label><input class="ttb-input" type="email" name="client_email" required></div>';
    echo '</div>';

    echo '<div style="margin-top:10px"><label>Idioma del portal</label>';
    echo '<div class="ttb-checks" style="margin-top:6px">';
    echo '<label class="ttb-check"><input type="radio" name="client_lang" value="es" checked> 🇪🇸 Español</label>';
    echo '<label class="ttb-check"><input type="radio" name="client_lang" value="en"> 🇬🇧 English</label>';
    echo '</div></div>';

    echo '<div style="margin-top:10px"><label>Servicios</label><div class="ttb-checks">';
    foreach (['design' => 'Diseño / Design', 'social' => 'Redes / Social', 'seo' => 'SEO', 'web' => 'Web'] as $k => $v) {
      echo '<label class="ttb-check"><input type="checkbox" name="services[]" value="' . esc_attr($k) . '"> ' . esc_html($v) . '</label>';
    }
    echo '</div></div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_admin_create_client" value="1">Crear y enviar acceso</button></div>';
    echo '</form>';

    if ($edit_c) {
      $edit_services = json_decode((string)$edit_c->services, true);
      if (!is_array($edit_services)) $edit_services = [];
      $edit_lang  = in_array($edit_c->lang ?? '', ['es', 'en'], true) ? $edit_c->lang : 'es';
      $cancel_url = esc_url(home_url('/briefing?section=briefings&tab=clients'));

      echo '<div class="ttb-modal-overlay ttb-edit-modal-overlay" id="ttbEditModal" role="dialog" aria-modal="true" aria-labelledby="ttbEditTitle">';
      echo '<div class="ttb-modal ttb-edit-modal">';
      echo '<h3 class="ttb-edit-modal__title" id="ttbEditTitle">✏️ Editar cliente</h3>';

      echo '<form method="post" action="' . esc_url(home_url('/briefing?section=briefings&tab=clients')) . '" class="ttb-formgrid">';
      wp_nonce_field('ttb_admin_edit_client');
      echo '<input type="hidden" name="client_id" value="' . (int)$edit_c->id . '">';

      echo '<div class="ttb-grid2">';
      echo '<div><label>Nombre</label><input class="ttb-input" type="text" name="client_name" value="' . esc_attr($edit_c->name) . '" required></div>';
      echo '<div><label>Email</label><input class="ttb-input" type="email" name="client_email" value="' . esc_attr($edit_c->email) . '" required></div>';
      echo '</div>';

      echo '<div style="margin-top:10px"><label>Idioma del portal</label>';
      echo '<div class="ttb-checks" style="margin-top:6px">';
      echo '<label class="ttb-check"><input type="radio" name="client_lang" value="es"' . ($edit_lang === 'es' ? ' checked' : '') . '> 🇪🇸 Español</label>';
      echo '<label class="ttb-check"><input type="radio" name="client_lang" value="en"' . ($edit_lang === 'en' ? ' checked' : '') . '> 🇬🇧 English</label>';
      echo '</div></div>';

      echo '<div style="margin-top:10px"><label>Servicios</label><div class="ttb-checks">';
      foreach (['design' => 'Diseño / Design', 'social' => 'Redes / Social', 'seo' => 'SEO', 'web' => 'Web'] as $k => $v) {
        $checked = in_array($k, $edit_services, true) ? 'checked' : '';
        echo '<label class="ttb-check"><input type="checkbox" name="services[]" value="' . esc_attr($k) . '" ' . $checked . '> ' . esc_html($v) . '</label>';
      }
      echo '</div></div>';

      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_admin_edit_client" value="1">Guardar cambios</button>';
      echo '</div>';

      echo '</form></div></div>';
      echo '<script>document.getElementById("ttbEditModal").style.display="flex";</script>';
    }

    echo '<div class="ttb-card"><h3>Listado</h3>';
    if (!$clients) { echo '<p class="ttb-muted">No hay clientes aún.</p></div>'; return; }

    echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
      <th>Cliente</th><th>Email</th><th>Usuario</th><th>Idioma</th><th>Servicios</th><th>Estado</th><th></th>
    </tr></thead><tbody>';

    foreach ($clients as $c) {
      $sv = json_decode((string)$c->services, true);
      if (!is_array($sv)) $sv = [];
      $c_lang   = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';
      $edit_url = esc_url(home_url('/briefing?section=briefings&tab=clients&edit_client=' . (int)$c->id));

      echo '<tr>';
      echo '<td><strong>' . esc_html($c->name) . '</strong></td>';
      echo '<td>' . esc_html($c->email) . '</td>';
      echo '<td>' . esc_html($c->username) . '</td>';
      echo '<td>' . ($c_lang === 'en' ? '🇬🇧 EN' : '🇪🇸 ES') . '</td>';
      echo '<td>' . esc_html($sv ? implode(', ', $sv) : '—') . '</td>';
      echo '<td>';
      $status_labels = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'enviado' => 'Enviado'];
      $status_cls    = ['pendiente' => 'ttb-status--pending', 'en_progreso' => 'ttb-status--progress', 'enviado' => 'ttb-status--sent'];
      $sl = $status_labels[$c->status] ?? esc_html($c->status);
      $sc = $status_cls[$c->status]    ?? '';
      echo '<span class="ttb-status ' . $sc . '">' . esc_html($sl) . '</span>';
      echo '</td>';
      echo '<td><div class="ttb-row-actions">';

      echo '<a href="' . $edit_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm" title="Editar">✏️ Editar</a>';

      echo '<form method="post" action="' . esc_url(home_url('/briefing?section=briefings&tab=clients')) . '" style="margin:0">';
      wp_nonce_field('ttb_admin_resend');
      echo '<input type="hidden" name="client_id" value="' . (int)$c->id . '">
      <button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_admin_resend" value="1" title="Reenviar email">📧 Email</button>
      </form>';

      echo '<form method="post" action="' . esc_url(home_url('/briefing?section=briefings&tab=clients')) . '" style="margin:0"
                  onsubmit="return confirm(\'¿Eliminar a ' . esc_js($c->name) . '? Se borrarán también todas sus respuestas. Esta acción no se puede deshacer.\')">';
      wp_nonce_field('ttb_admin_delete_client');
      echo '<input type="hidden" name="client_id" value="' . (int)$c->id . '">
      <button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_admin_delete_client" value="1" title="Eliminar">🗑️ Eliminar</button>
      </form>';

      echo '</div></td></tr>';
    }

    echo '</tbody></table></div></div>';
  }

  /* ════════════════════════════════
     RENDER: RESPUESTAS
  ════════════════════════════════ */
  private static function render_answers() {
    global $wpdb;
    $clients_table = TTB_DB::clients_table();
    $clients = $wpdb->get_results("SELECT id,name,email,lang,status FROM $clients_table ORDER BY updated_at DESC LIMIT 200");

    echo '<div class="ttb-card"><h3>Respuestas de Prebriefing</h3><p class="ttb-muted">Selecciona un cliente para ver sus respuestas por servicio.</p></div>';

    echo '<div class="ttb-card"><div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
      <th>Cliente</th><th>Email</th><th>Idioma</th><th>Estado</th><th>Ver</th>
    </tr></thead><tbody>';

    foreach ($clients as $c) {
      $url    = esc_url(home_url('/briefing?section=briefings&tab=answers&client=' . (int)$c->id));
      $c_lang = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';
      echo '<tr>';
      echo '<td><strong>' . esc_html($c->name) . '</strong></td>';
      echo '<td>' . esc_html($c->email) . '</td>';
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
        $id    = $f['id'] ?? '';
        if (!$id) continue;
        $label = $f['label'] ?? $id;
        $val   = $answers[$id] ?? '';
        if (is_array($val)) $val = implode(', ', $val);
        echo '<div class="ttb-q"><div class="ttb-q__l">' . esc_html($label) . '</div><div class="ttb-q__a">' . nl2br(esc_html((string)$val)) . '</div></div>';
      }
      echo '</div></div>';
    }
  }
}