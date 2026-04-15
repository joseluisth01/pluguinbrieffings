<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Clients_UI')) return;

/**
 * TTB_Clients_UI
 * Pestaña central de CLIENTES.
 *
 * Cambios:
 * - handle_credentials(): al guardar nueva contraseña se almacena también en pass_raw
 *   (base64 ofuscado) para poder reenviarla correctamente por email.
 * - handle_resend(): usa pass_raw si existe, si no usa el nombre como fallback.
 * - handle_create(): guarda pass_raw al crear cliente.
 */
class TTB_Clients_UI {

  private static function flash_and_redirect($type, $text, $url = null) {
    set_transient('ttb_admin_flash', ['type' => $type, 'text' => $text], 60);
    if (!$url) $url = home_url('/briefing?section=clientes');

    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    if (!headers_sent()) {
      header('Location: ' . esc_url_raw($url), true, 302);
      exit;
    }

    echo '<!DOCTYPE html><html><head>';
    echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($url) . '">';
    echo '</head><body>';
    echo '<script>window.location.replace(' . wp_json_encode($url) . ');</script>';
    echo '</body></html>';
    exit;
  }

  public static function render_and_handle_forms() {
    // Hook point para el futuro.
  }

  public static function render() {
    self::handle_create();
    self::handle_edit();
    self::handle_delete();
    self::handle_resend();
    self::handle_credentials();
    self::sync_social_clients();
    self::render_create_form();
    self::render_list();
  }

  /* ════════════════════════════════════════
     EDITAR CREDENCIALES
  ════════════════════════════════════════ */

  private static function handle_credentials() {
    if (!isset($_POST['ttb_central_client_credentials'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_central_client_credentials')) return;

    $client_id    = (int)($_POST['client_id']       ?? 0);
    $new_username = sanitize_user(trim($_POST['new_username']    ?? ''), true);
    $new_password = trim($_POST['new_password']      ?? '');
    $confirm_pass = trim($_POST['confirm_password']  ?? '');

    if (!$client_id) {
      self::flash_and_redirect('error', 'Cliente no encontrado.');
    }

    if (empty($new_username)) {
      self::flash_and_redirect('error', 'El nombre de usuario no puede estar vacío.');
    }

    if (!empty($new_password) && $new_password !== $confirm_pass) {
      self::flash_and_redirect('error', 'Las contraseñas no coinciden.');
    }

    global $wpdb;
    $table = TTB_DB::clients_table();

    // Comprobar que el username no está en uso por otro cliente
    $conflict = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $table WHERE username = %s AND id != %d LIMIT 1",
      $new_username, $client_id
    ));
    if ($conflict) {
      self::flash_and_redirect('error', 'Ese nombre de usuario ya está en uso por otro cliente.');
    }

    $update = [
      'username'   => $new_username,
      'updated_at' => TTB_DB::now(),
    ];

    if (!empty($new_password)) {
      $update['pass_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
      // Guardamos la contraseña en base64 para poder reenviarla por email
      $update['pass_raw']  = base64_encode($new_password);
    }

    $wpdb->update($table, $update, ['id' => $client_id]);

    $msg = !empty($new_password)
      ? 'Usuario y contraseña actualizados correctamente.'
      : 'Nombre de usuario actualizado correctamente.';

    self::flash_and_redirect('success', $msg, home_url('/briefing?section=clientes'));
  }

  /* ════════════════════════════════════════
     ACCIONES POST EXISTENTES
  ════════════════════════════════════════ */

  private static function sync_social_clients() {
    global $wpdb;
    $clients_table = TTB_DB::clients_table();
    $sc_table      = TTB_Social_DB::clients_table();

    $clients = $wpdb->get_results("SELECT * FROM $clients_table LIMIT 500");

    foreach ($clients as $c) {
      $services = json_decode((string)$c->services, true) ?: [];
      if (!in_array('social', $services, true)) continue;

      $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $sc_table WHERE ttb_client_id = %d LIMIT 1",
        (int)$c->id
      ));

      if ($existing) {
        $emails = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];
        $wpdb->update($sc_table, [
          'name'       => $c->name,
          'emails'     => wp_json_encode(array_values($emails)),
          'updated_at' => TTB_Social_DB::now(),
        ], ['ttb_client_id' => (int)$c->id]);
        continue;
      }

      $legacy = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $sc_table WHERE name = %s AND (ttb_client_id IS NULL OR ttb_client_id = 0) LIMIT 1",
        $c->name
      ));

      if ($legacy) {
        $emails = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];
        $wpdb->update($sc_table, [
          'ttb_client_id' => (int)$c->id,
          'emails'        => wp_json_encode(array_values($emails)),
          'updated_at'    => TTB_Social_DB::now(),
        ], ['id' => (int)$legacy->id]);
      } else {
        $emails = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];
        self::maybe_create_social_client((int)$c->id, (string)$c->name, $emails);
      }
    }
  }

  private static function handle_create() {
    if (!isset($_POST['ttb_central_client_create'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_central_client_create')) return;

    $name          = sanitize_text_field($_POST['client_name']  ?? '');
    $emails        = self::sanitize_emails($_POST['client_emails'] ?? []);
    $lang          = in_array($_POST['client_lang'] ?? '', ['es', 'en'], true) ? $_POST['client_lang'] : 'es';
    $services      = array_map('sanitize_text_field', (array)($_POST['services'] ?? []));
    $skip_briefing = !empty($_POST['skip_briefing']);

    if (!$name || empty($emails)) {
      self::flash_and_redirect('error', 'Nombre y al menos un email son obligatorios.');
    }

    $primary_email = $emails[0];
    $username      = sanitize_user($name, true) ?: 'cliente';
    $password      = $name;

    global $wpdb;
    $table = TTB_DB::clients_table();

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username=%s", $username));
    if ($exists) {
      $username .= '-' . wp_generate_password(4, false, false);
    }

    $wpdb->insert($table, [
      'name'       => $name,
      'email'      => $primary_email,
      'emails'     => wp_json_encode(array_values($emails)),
      'username'   => $username,
      'pass_hash'  => password_hash($password, PASSWORD_DEFAULT),
      'pass_raw'   => base64_encode($password), // guardamos para reenvíos futuros
      'services'   => wp_json_encode(array_values($services)),
      'lang'       => $lang,
      'status'     => 'pendiente',
      'created_at' => TTB_DB::now(),
      'updated_at' => TTB_DB::now(),
    ]);

    $new_client_id = (int)$wpdb->insert_id;

    if ($skip_briefing && $new_client_id && !empty($services)) {
      self::mark_all_briefings_as_sent($new_client_id, $services, $lang);
    }

    if (in_array('social', $services, true) && $new_client_id) {
      self::maybe_create_social_client($new_client_id, $name, $emails);
    }

    if (!$skip_briefing && !empty($services)) {
      self::send_access_to_all_emails($name, $emails, $username, $password, $services, $lang);
      self::flash_and_redirect('success', 'Cliente creado y email de acceso enviado.',
        home_url('/briefing?section=clientes'));
    } else {
      self::flash_and_redirect('success', 'Cliente creado. Módulos desbloqueados directamente (sin prebriefing).',
        home_url('/briefing?section=clientes'));
    }
  }

  private static function handle_edit() {
    if (!isset($_POST['ttb_central_client_edit'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_central_client_edit')) return;

    $client_id     = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    $name          = sanitize_text_field($_POST['client_name']  ?? '');
    $emails        = self::sanitize_emails($_POST['client_emails'] ?? []);
    $lang          = in_array($_POST['client_lang'] ?? '', ['es', 'en'], true) ? $_POST['client_lang'] : 'es';
    $services      = array_map('sanitize_text_field', (array)($_POST['services'] ?? []));
    $skip_briefing = !empty($_POST['skip_briefing']);

    if (!$name || empty($emails)) {
      self::flash_and_redirect('error', 'Nombre y al menos un email son obligatorios.');
    }

    $primary_email = $emails[0];

    global $wpdb;
    $wpdb->update(TTB_DB::clients_table(), [
      'name'       => $name,
      'email'      => $primary_email,
      'emails'     => wp_json_encode(array_values($emails)),
      'services'   => wp_json_encode(array_values($services)),
      'lang'       => $lang,
      'updated_at' => TTB_DB::now(),
    ], ['id' => $client_id]);

    if (in_array('social', $services, true)) {
      self::maybe_create_social_client($client_id, $name, $emails);
    }

    if ($skip_briefing && !empty($services)) {
      self::mark_all_briefings_as_sent($client_id, $services, $lang);
    }

    self::propagate_update($client_id, $name, $emails);

    $msg = $skip_briefing
      ? 'Cliente actualizado. Módulos desbloqueados directamente (sin prebriefing).'
      : 'Cliente actualizado correctamente.';

    self::flash_and_redirect('success', $msg, home_url('/briefing?section=clientes'));
  }

  private static function handle_delete() {
    if (!isset($_POST['ttb_central_client_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_central_client_delete')) return;

    $client_id = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    global $wpdb;
    $wpdb->delete(TTB_DB::clients_table(), ['id' => $client_id]);
    $wpdb->delete(TTB_DB::answers_table(), ['client_id' => $client_id]);
    $wpdb->delete(TTB_Social_DB::clients_table(), ['ttb_client_id' => $client_id]);

    self::flash_and_redirect('success', 'Cliente eliminado.',
      home_url('/briefing?section=clientes'));
  }

  private static function handle_resend() {
    if (!isset($_POST['ttb_central_resend'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_central_resend')) return;

    $client_id = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    global $wpdb;
    $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", $client_id));
    if (!$c) return;

    $services = json_decode((string)$c->services, true) ?: [];
    $lang     = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';
    $emails   = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];

    // Recuperar la contraseña: usar pass_raw si existe, si no el nombre del cliente
    $password = !empty($c->pass_raw)
      ? base64_decode($c->pass_raw)
      : (string)$c->name;

    self::send_access_to_all_emails(
      (string)$c->name,
      $emails,
      (string)$c->username,
      $password,
      $services,
      $lang
    );

    self::flash_and_redirect('success', 'Email de acceso reenviado.',
      home_url('/briefing?section=clientes'));
  }

  /* ════════════════════════════════════════
     MARCAR PREBRIEFINGS COMO ENVIADOS
  ════════════════════════════════════════ */

  private static function mark_all_briefings_as_sent($client_id, $services, $lang = 'es') {
    global $wpdb;
    $table = TTB_DB::answers_table();

    foreach ($services as $svc) {
      $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, sent FROM $table WHERE client_id=%d AND service=%s",
        $client_id, $svc
      ));

      if ($existing) {
        if ((int)$existing->sent !== 1) {
          $wpdb->update($table, [
            'sent'       => 1,
            'updated_at' => TTB_DB::now(),
          ], ['id' => (int)$existing->id]);
        }
      } else {
        $empty_answers = wp_json_encode([
          'ttb_skip_briefing' => '1',
          'note'              => 'Prebriefing saltado por el administrador.',
        ], JSON_UNESCAPED_UNICODE);

        $wpdb->insert($table, [
          'client_id'  => $client_id,
          'service'    => $svc,
          'answers'    => $empty_answers,
          'sent'       => 1,
          'updated_at' => TTB_DB::now(),
        ]);
      }
    }

    $wpdb->update(TTB_DB::clients_table(), [
      'status'     => 'enviado',
      'updated_at' => TTB_DB::now(),
    ], ['id' => $client_id]);
  }

  /* ════════════════════════════════════════
     HELPERS
  ════════════════════════════════════════ */

  private static function send_access_to_all_emails($name, $emails, $username, $password, $services, $lang) {
    $mailer = new TTB_Mailer();
    foreach ($emails as $email) {
      if (!is_email($email)) continue;
      $mailer->send_client_access($name, $email, $username, $password, $services, $lang);
    }
  }

  public static function maybe_create_social_client($client_id, $name, $emails) {
    global $wpdb;
    $sc_table = TTB_Social_DB::clients_table();

    $existing = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $sc_table WHERE ttb_client_id = %d LIMIT 1",
      $client_id
    ));

    if ($existing) {
      $wpdb->update($sc_table, [
        'name'       => $name,
        'emails'     => wp_json_encode(array_values($emails)),
        'updated_at' => TTB_Social_DB::now(),
      ], ['ttb_client_id' => $client_id]);
      return;
    }

    $token = TTB_Social_DB::generate_token();

    $wpdb->insert($sc_table, [
      'ttb_client_id' => $client_id,
      'name'          => $name,
      'emails'        => wp_json_encode(array_values($emails)),
      'token'         => $token,
      'networks'      => wp_json_encode([]),
      'notes'         => '',
      'status'        => 'active',
      'created_at'    => TTB_Social_DB::now(),
      'updated_at'    => TTB_Social_DB::now(),
    ]);

    $sc_id = (int)$wpdb->insert_id;
    if ($sc_id) {
      TTB_Social_DB::log($sc_id, null, 'client_created', 'admin', [
        'source'    => 'ttb_clients_sync',
        'client_id' => $client_id,
      ]);
    }
  }

  public static function propagate_update($client_id, $name, $emails) {
    global $wpdb;
    $emails_json  = wp_json_encode(array_values($emails));
    $social_table = TTB_Social_DB::clients_table();
    if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $social_table WHERE ttb_client_id=%d", $client_id))) {
      $wpdb->update($social_table, [
        'name'       => $name,
        'emails'     => $emails_json,
        'updated_at' => TTB_Social_DB::now(),
      ], ['ttb_client_id' => $client_id]);
    }
  }

  public static function get_clients_by_services($services) {
    global $wpdb;
    if (!is_array($services)) $services = [$services];
    $all = $wpdb->get_results("SELECT * FROM " . TTB_DB::clients_table() . " ORDER BY name ASC LIMIT 500");
    return array_values(array_filter($all, function($c) use ($services) {
      $sv = json_decode((string)$c->services, true) ?: [];
      foreach ($services as $s) {
        if (in_array($s, $sv, true)) return true;
      }
      return false;
    }));
  }

  public static function render_client_select($field_name, $services, $selected_id = 0, $required = true) {
    $clients      = self::get_clients_by_services($services);
    $service_label = is_array($services) ? implode('/', $services) : $services;

    if (empty($clients)) {
      echo '<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:10px 14px;font-size:13px;color:#92400e">';
      echo 'No hay clientes con el servicio <strong>' . esc_html($service_label) . '</strong> contratado. ';
      echo '<a href="' . esc_url(home_url('/briefing?section=clientes')) . '" style="color:var(--ttb-pink);font-weight:700">Crear cliente →</a>';
      echo '</div>';
      echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="">';
      return;
    }

    echo '<select name="' . esc_attr($field_name) . '" class="ttb-input"' . ($required ? ' required' : '') . '>';
    echo '<option value="">— Selecciona cliente —</option>';
    foreach ($clients as $c) {
      $emails = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];
      $label  = $c->name . ' (' . ($emails[0] ?? '') . ')';
      echo '<option value="' . (int)$c->id . '"' . selected($selected_id, (int)$c->id, false) . '>'
        . esc_html($label) . '</option>';
    }
    echo '</select>';
  }

  /* ════════════════════════════════════════
     RENDER: FORMULARIO ALTA
  ════════════════════════════════════════ */

  private static function render_create_form() {
    $action_url = esc_url(home_url('/briefing?section=clientes'));

    echo '<div class="ttb-card">';
    echo '<h3>Crear nuevo cliente</h3>';
    echo '<p class="ttb-muted" style="margin:0">Una vez creado, el cliente aparecerá en los selectores de cada módulo según sus servicios contratados.</p>';
    echo '</div>';

    echo '<form method="post" action="' . $action_url . '" class="ttb-card">';
    wp_nonce_field('ttb_central_client_create');

    echo '<div class="ttb-grid2">';
    echo '<div><label>Nombre del cliente <span class="ttb-required">*</span></label>';
    echo '<input class="ttb-input" type="text" name="client_name" required placeholder="Empresa Ejemplo S.L."></div>';
    echo '<div><label>Idioma del portal</label>';
    echo '<div class="ttb-checks" style="margin-top:8px">';
    echo '<label class="ttb-check"><input type="radio" name="client_lang" value="es" checked> 🇪🇸 Español</label>';
    echo '<label class="ttb-check"><input type="radio" name="client_lang" value="en"> 🇬🇧 English</label>';
    echo '</div></div>';
    echo '</div>';

    echo '<div style="margin-top:12px">';
    echo '<label>Emails del cliente <span class="ttb-required">*</span></label>';
    echo '<p class="ttb-muted" style="font-size:13px;margin:2px 0 8px">El primer email es el principal. Los demás recibirán copias de los emails.</p>';
    echo '<div id="ttb-cc-emails-create" style="display:flex;flex-direction:column;gap:8px">';
    echo '<div class="ttb-cc-email-row" style="display:flex;gap:8px;align-items:center">';
    echo '<input class="ttb-input" type="email" name="client_emails[]" placeholder="email@cliente.com" required style="flex:1">';
    echo '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-cc-remove-email" style="display:none">✕</button>';
    echo '</div></div>';
    echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm ttb-cc-add-email" data-target="ttb-cc-emails-create" style="margin-top:8px">+ Añadir email</button>';
    echo '</div>';

    echo '<div style="margin-top:14px">';
    echo '<label>Servicios contratados</label>';
    echo '<p class="ttb-muted" style="font-size:13px;margin:2px 0 8px">Determina en qué módulos aparecerá este cliente al crear proyectos.</p>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:8px;margin-top:4px">';
    foreach ([
      'design'   => ['🎨', 'Diseño / Design',    'Revisiones Diseños'],
      'social'   => ['📣', 'Redes / Social',      'Redes Sociales'],
      'seo'      => ['🚀', 'SEO',                 'Solo Prebriefing SEO'],
      'web'      => ['🌐', 'Web',                 'Revisiones Prog. Web'],
      'reservas' => ['🍽️', 'Reservas',            'Gestor de Reservas Restaurante'],
    ] as $k => [$icon, $label, $module]) {
      echo '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;background:#f9fafb;border:1.5px solid var(--ttb-border);border-radius:12px;padding:10px 12px">';
      echo '<input type="checkbox" name="services[]" value="' . esc_attr($k) . '" style="margin-top:2px">';
      echo '<span>';
      echo '<strong style="font-size:14px">' . $icon . ' ' . esc_html($label) . '</strong>';
      echo '<br><small style="color:var(--ttb-muted);font-size:12px">' . esc_html($module) . '</small>';
      echo '</span></label>';
    }
    echo '</div></div>';

    echo '<div style="margin-top:18px;background:linear-gradient(135deg,#fff7ed,#fffbeb);border:2px solid #fed7aa;border-radius:16px;padding:16px 20px">';
    echo '<label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer">';
    echo '<input type="checkbox" name="skip_briefing" value="1" id="ttb-skip-briefing-create" style="margin-top:3px;width:18px;height:18px;flex-shrink:0;accent-color:#ea580c">';
    echo '<span>';
    echo '<strong style="font-size:14px;color:#9a3412;display:block;margin-bottom:4px">⏭️ Saltar paso de prebriefing</strong>';
    echo '<span style="font-size:13px;color:#c2410c;line-height:1.5;display:block">Activa esta opción para clientes que ya conocemos y no necesitan rellenar el formulario de prebriefing. <strong>No se enviará el email de prebriefing.</strong></span>';
    echo '</span></label>';
    echo '</div>';

    echo '<div class="ttb-actions">';
    echo '<button class="ttb-btn" name="ttb_central_client_create" value="1" id="ttb-create-btn">Crear cliente y enviar acceso</button>';
    echo '</div>';
    echo '</form>';

    self::email_js('ttb-cc-emails-create');

    ?>
    <script>
    (function(){
      var cb  = document.getElementById('ttb-skip-briefing-create');
      var btn = document.getElementById('ttb-create-btn');
      if (!cb || !btn) return;
      cb.addEventListener('change', function(){
        btn.textContent = cb.checked
          ? 'Crear cliente (sin prebriefing)'
          : 'Crear cliente y enviar acceso';
      });
    })();
    </script>
    <?php
  }

  /* ════════════════════════════════════════
     RENDER: LISTADO
  ════════════════════════════════════════ */

  private static function render_list() {
    global $wpdb;
    $table   = TTB_DB::clients_table();
    $answers = TTB_DB::answers_table();
    $clients = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");

    $action_url = esc_url(home_url('/briefing?section=clientes'));

    // ── Modal editar datos generales ──
    $edit_id = (int)($_GET['edit_cc'] ?? 0);
    $edit_c  = null;
    if ($edit_id) {
      $edit_c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id));
    }

    if ($edit_c) {
      $edit_emails   = json_decode((string)($edit_c->emails ?? ''), true) ?: [$edit_c->email];
      $edit_services = json_decode((string)$edit_c->services, true) ?: [];
      $edit_lang     = in_array($edit_c->lang ?? '', ['es', 'en'], true) ? $edit_c->lang : 'es';
      $cancel_url    = esc_url(home_url('/briefing?section=clientes'));

      $all_sent = false;
      if (!empty($edit_services)) {
        $sent_count = (int)$wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM $answers WHERE client_id=%d AND sent=1 AND service IN (" .
          implode(',', array_fill(0, count($edit_services), '%s')) . ")",
          array_merge([$edit_c->id], $edit_services)
        ));
        $all_sent = ($sent_count >= count($edit_services));
      }

      echo '<div class="ttb-modal-overlay" id="ttbCcEditModal" role="dialog" aria-modal="true" style="display:flex">';
      echo '<div class="ttb-modal ttb-edit-modal" style="max-width:560px">';
      echo '<h3 class="ttb-edit-modal__title">✏️ Editar cliente</h3>';
      echo '<form method="post" action="' . $action_url . '" class="ttb-formgrid">';
      wp_nonce_field('ttb_central_client_edit');
      echo '<input type="hidden" name="client_id" value="' . (int)$edit_c->id . '">';

      echo '<div class="ttb-grid2">';
      echo '<div><label>Nombre</label>';
      echo '<input class="ttb-input" type="text" name="client_name" value="' . esc_attr($edit_c->name) . '" required></div>';
      echo '<div><label>Idioma</label><div class="ttb-checks" style="margin-top:8px">';
      echo '<label class="ttb-check"><input type="radio" name="client_lang" value="es"' . ($edit_lang === 'es' ? ' checked' : '') . '> 🇪🇸 ES</label>';
      echo '<label class="ttb-check"><input type="radio" name="client_lang" value="en"' . ($edit_lang === 'en' ? ' checked' : '') . '> 🇬🇧 EN</label>';
      echo '</div></div>';
      echo '</div>';

      echo '<div style="margin-top:10px"><label>Emails</label>';
      echo '<p class="ttb-muted" style="font-size:12px;margin:2px 0 6px">El primero es el principal. Todos recibirán copias de los emails.</p>';
      echo '<div id="ttb-cc-emails-edit" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">';
      foreach ($edit_emails as $i => $em) {
        $rm = $i > 0 ? '' : 'style="display:none"';
        echo '<div class="ttb-cc-email-row" style="display:flex;gap:8px;align-items:center">';
        echo '<input class="ttb-input" type="email" name="client_emails[]" value="' . esc_attr($em) . '" required style="flex:1">';
        echo '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-cc-remove-email" ' . $rm . '>✕</button>';
        echo '</div>';
      }
      echo '</div>';
      echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm ttb-cc-add-email" data-target="ttb-cc-emails-edit" style="margin-top:8px">+ Añadir email</button>';
      echo '</div>';

      echo '<div style="margin-top:10px"><label>Servicios</label><div class="ttb-checks" style="margin-top:8px">';
      foreach (['design' => '🎨 Diseño', 'social' => '📣 Redes', 'seo' => '🚀 SEO', 'web' => '🌐 Web', 'reservas' => '🍽️ Reservas'] as $k => $v) {
        $checked = in_array($k, $edit_services, true) ? 'checked' : '';
        echo '<label class="ttb-check"><input type="checkbox" name="services[]" value="' . esc_attr($k) . '" ' . $checked . '> ' . esc_html($v) . '</label>';
      }
      echo '</div></div>';

      if (!$all_sent) {
        echo '<div style="margin-top:14px;background:linear-gradient(135deg,#fff7ed,#fffbeb);border:2px solid #fed7aa;border-radius:14px;padding:14px 18px">';
        echo '<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">';
        echo '<input type="checkbox" name="skip_briefing" value="1" style="margin-top:2px;width:16px;height:16px;flex-shrink:0;accent-color:#ea580c">';
        echo '<span>';
        echo '<strong style="font-size:13px;color:#9a3412;display:block;margin-bottom:3px">⏭️ Desbloquear módulos sin prebriefing</strong>';
        echo '<span style="font-size:12px;color:#c2410c;line-height:1.5;display:block">Marca como enviado el prebriefing de los servicios pendientes.</span>';
        echo '</span></label>';
        echo '</div>';
      } else {
        echo '<div style="margin-top:14px;background:#ecfdf5;border:1.5px solid #a7f3d0;border-radius:14px;padding:12px 16px">';
        echo '<p style="margin:0;font-size:13px;color:#065f46;font-weight:700">✅ Todos los módulos ya están desbloqueados.</p>';
        echo '</div>';
      }

      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_central_client_edit" value="1">Guardar cambios</button>';
      echo '</div>';
      echo '</form></div></div>';

      self::email_js('ttb-cc-emails-edit');
    }

    // ── Modal editar credenciales ──
    $cred_id = (int)($_GET['edit_creds'] ?? 0);
    $cred_c  = null;
    if ($cred_id) {
      $cred_c = $wpdb->get_row($wpdb->prepare("SELECT id, name, username FROM $table WHERE id=%d", $cred_id));
    }

    if ($cred_c) {
      $cancel_url = esc_url(home_url('/briefing?section=clientes'));
      echo '<div class="ttb-modal-overlay" id="ttbCredModal" role="dialog" aria-modal="true" style="display:flex">';
      echo '<div class="ttb-modal ttb-edit-modal" style="max-width:480px">';
      echo '<h3 class="ttb-edit-modal__title">🔑 Editar credenciales: ' . esc_html($cred_c->name) . '</h3>';

      echo '<div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:12px 16px;margin-bottom:16px">';
      echo '<p style="margin:0;font-size:13px;color:#1d4ed8;line-height:1.5">';
      echo '<strong>Usuario actual:</strong> <code style="background:#fff;border:1px solid #bfdbfe;border-radius:6px;padding:2px 8px;font-size:13px">' . esc_html($cred_c->username) . '</code>';
      echo '</p></div>';

      echo '<form method="post" action="' . $action_url . '" class="ttb-formgrid">';
      wp_nonce_field('ttb_central_client_credentials');
      echo '<input type="hidden" name="client_id" value="' . (int)$cred_c->id . '">';

      echo '<div>';
      echo '<label>Nuevo nombre de usuario <span class="ttb-required">*</span></label>';
      echo '<input class="ttb-input" type="text" name="new_username" value="' . esc_attr($cred_c->username) . '" required autocomplete="off" spellcheck="false">';
      echo '<small class="ttb-muted" style="display:block;margin-top:4px">Solo letras, números, guiones y puntos. Sin espacios.</small>';
      echo '</div>';

      echo '<div style="margin-top:12px">';
      echo '<label>Nueva contraseña <span style="font-weight:400;color:var(--ttb-muted)">(dejar en blanco para no cambiar)</span></label>';
      echo '<div style="position:relative">';
      echo '<input class="ttb-input" type="password" name="new_password" id="ttb-cred-pass" autocomplete="new-password" style="padding-right:44px" placeholder="••••••••">';
      echo '<button type="button" onclick="ttbTogglePass(\'ttb-cred-pass\')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--ttb-muted);font-size:16px" title="Mostrar/ocultar">👁</button>';
      echo '</div></div>';

      echo '<div style="margin-top:12px">';
      echo '<label>Confirmar contraseña</label>';
      echo '<div style="position:relative">';
      echo '<input class="ttb-input" type="password" name="confirm_password" id="ttb-cred-pass2" autocomplete="new-password" style="padding-right:44px" placeholder="••••••••">';
      echo '<button type="button" onclick="ttbTogglePass(\'ttb-cred-pass2\')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--ttb-muted);font-size:16px" title="Mostrar/ocultar">👁</button>';
      echo '</div></div>';

      // Generador de contraseña aleatoria
      echo '<div style="margin-top:10px">';
      echo '<button type="button" onclick="ttbGenPassword()" class="ttb-btn ttb-btn--ghost ttb-btn--sm">🎲 Generar contraseña aleatoria</button>';
      echo '<span id="ttb-gen-pass-display" style="display:none;margin-left:10px;font-family:monospace;font-size:14px;font-weight:700;color:var(--ttb-pink)"></span>';
      echo '</div>';

      echo '<div style="margin-top:14px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:12px 16px">';
      echo '<p style="margin:0;font-size:13px;color:#92400e;line-height:1.5">⚠️ <strong>Recuerda:</strong> si cambias las credenciales, el cliente ya no podrá acceder con las anteriores. Asegúrate de comunicarle los nuevos datos.</p>';
      echo '</div>';

      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_central_client_credentials" value="1">Guardar credenciales</button>';
      echo '</div>';
      echo '</form></div></div>';

      echo '<script>
      function ttbTogglePass(id) {
        var i = document.getElementById(id);
        i.type = (i.type === "password") ? "text" : "password";
      }
      function ttbGenPassword() {
        var chars = "abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$";
        var pass  = "";
        for (var i = 0; i < 12; i++) pass += chars[Math.floor(Math.random() * chars.length)];
        document.getElementById("ttb-cred-pass").type  = "text";
        document.getElementById("ttb-cred-pass2").type = "text";
        document.getElementById("ttb-cred-pass").value  = pass;
        document.getElementById("ttb-cred-pass2").value = pass;
        var display = document.getElementById("ttb-gen-pass-display");
        display.textContent = pass;
        display.style.display = "inline";
      }
      </script>';
    }

    // ── Tabla de clientes ──
    echo '<div class="ttb-card"><h3>Listado de clientes</h3>';

    if (!$clients) {
      echo '<p class="ttb-muted">No hay clientes aún. Crea el primero con el formulario de arriba.</p>';
      echo '</div>';
      return;
    }

    $status_labels = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'enviado' => 'Enviado'];
    $status_cls    = ['pendiente' => 'ttb-status--pending', 'en_progreso' => 'ttb-status--progress', 'enviado' => 'ttb-status--sent'];
    $service_icons = ['design' => '🎨', 'social' => '📣', 'seo' => '🚀', 'web' => '🌐', 'reservas' => '🍽️'];
    $service_names = ['design' => 'Diseño', 'social' => 'Redes', 'seo' => 'SEO', 'web' => 'Web', 'reservas' => 'Reservas'];

    echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>';
    echo '<th>Cliente</th><th>Emails</th><th>Idioma</th><th>Servicios</th><th>Estado</th><th>Prebriefing</th><th>Acciones</th>';
    echo '</tr></thead><tbody>';

    foreach ($clients as $c) {
      $sv       = json_decode((string)$c->services, true) ?: [];
      $emails   = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];
      $c_lang   = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';
      $edit_url  = esc_url(home_url('/briefing?section=clientes&edit_cc=' . (int)$c->id));
      $creds_url = esc_url(home_url('/briefing?section=clientes&edit_creds=' . (int)$c->id));

      $sv_badges = '';
      foreach ($sv as $s) {
        $sv_badges .= '<span title="' . esc_attr($service_names[$s] ?? $s) . '" style="font-size:16px;margin-right:2px">'
          . ($service_icons[$s] ?? $s) . '</span>';
      }

      $briefing_indicator = '';
      if (!empty($sv)) {
        $sent_count = (int)$wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM $answers WHERE client_id=%d AND sent=1",
          (int)$c->id
        ));
        $total_svc = count($sv);
        if ($sent_count >= $total_svc) {
          $briefing_indicator = '<span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46">✅ Completo</span>';
        } elseif ($sent_count > 0) {
          $briefing_indicator = '<span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;background:#fffbeb;border:1px solid #fde68a;color:#92400e">' . $sent_count . '/' . $total_svc . ' servicios</span>';
        } else {
          $briefing_indicator = '<span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;color:#6b7280">⏳ Pendiente</span>';
        }
      }

      echo '<tr>';
      echo '<td><strong>' . esc_html($c->name) . '</strong>';
      echo '<br><small style="color:var(--ttb-muted);font-family:monospace;font-size:11px">' . esc_html($c->username) . '</small></td>';
      echo '<td style="font-size:13px">' . implode('<br>', array_map('esc_html', $emails)) . '</td>';
      echo '<td>' . ($c_lang === 'en' ? '🇬🇧 EN' : '🇪🇸 ES') . '</td>';
      echo '<td>' . ($sv_badges ?: '<span style="color:var(--ttb-muted)">—</span>') . '</td>';
      echo '<td><span class="ttb-status ' . ($status_cls[$c->status] ?? '') . '">' . esc_html($status_labels[$c->status] ?? $c->status) . '</span></td>';
      echo '<td>' . $briefing_indicator . '</td>';
      echo '<td><div class="ttb-row-actions">';

      echo '<a href="' . esc_url(home_url('/briefing?section=briefings&tab=answers&client=' . (int)$c->id)) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">📋 Respuestas</a>';
      echo '<a href="' . $edit_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">✏️ Editar</a>';
      echo '<a href="' . $creds_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">🔑 Credenciales</a>';

      echo '<form method="post" action="' . $action_url . '" style="margin:0">';
      wp_nonce_field('ttb_central_resend');
      echo '<input type="hidden" name="client_id" value="' . (int)$c->id . '">';
      echo '<button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_central_resend" value="1">📧 Acceso</button>';
      echo '</form>';

      echo '<form method="post" action="' . $action_url . '" style="margin:0" onsubmit="return confirm(\'¿Eliminar a ' . esc_js($c->name) . '? Se borrarán sus respuestas y accesos a módulos.\')">';
      wp_nonce_field('ttb_central_client_delete');
      echo '<input type="hidden" name="client_id" value="' . (int)$c->id . '">';
      echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_central_client_delete" value="1">🗑️</button>';
      echo '</form>';

      echo '</div></td></tr>';
    }

    echo '</tbody></table></div></div>';
  }

  /* ════════════════════════════════════════
     HELPERS PRIVADOS
  ════════════════════════════════════════ */

  private static function sanitize_emails($raw) {
    if (!is_array($raw)) $raw = [$raw];
    return array_values(array_filter(
      array_map(fn($e) => sanitize_email(trim($e)), $raw),
      'is_email'
    ));
  }

  private static function email_js($container_id) {
    ?>
    <script>
    (function(){
      var container = document.getElementById('<?php echo esc_js($container_id); ?>');
      if (!container || container._ttbCcInit) return;
      container._ttbCcInit = true;

      document.querySelectorAll('[data-target="<?php echo esc_js($container_id); ?>"]').forEach(function(addBtn){
        addBtn.addEventListener('click', function(){
          var row = document.createElement('div');
          row.className = 'ttb-cc-email-row';
          row.style.cssText = 'display:flex;gap:8px;align-items:center';
          row.innerHTML =
            '<input class="ttb-input" type="email" name="client_emails[]" placeholder="email@cliente.com" required style="flex:1">' +
            '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-cc-remove-email">✕</button>';
          container.appendChild(row);
          updateButtons();
        });
      });

      container.addEventListener('click', function(e){
        if (e.target.classList.contains('ttb-cc-remove-email')) {
          e.target.closest('.ttb-cc-email-row').remove();
          updateButtons();
        }
      });

      function updateButtons() {
        var rows = container.querySelectorAll('.ttb-cc-email-row');
        rows.forEach(function(row){
          var btn = row.querySelector('.ttb-cc-remove-email');
          if (btn) btn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
        });
      }
      updateButtons();
    })();
    </script>
    <?php
  }
}