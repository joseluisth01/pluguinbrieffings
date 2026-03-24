<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Admin')) return;

/**
 * TTB_Social_Admin — v3
 * Fixes:
 *  - Chip del calendario muestra solo "Post" (sin caption con \r\n)
 *  - Modal del admin se abre correctamente: el HTML se lee del DOM por data-id,
 *    no se pasa inline en onclick (evita rotura por comillas en nonces)
 *  - Cliente: botones aprobar/rechazar correctamente inyectados en el modal
 */
class TTB_Social_Admin {

  private static $flash = null;

  public static function event_catalog() {
    return [
      'client_created'       => ['Cliente creado',           '#ecfdf5','#6ee7b7','#065f46'],
      'client_updated'       => ['Cliente editado',           '#eff6ff','#bfdbfe','#1d4ed8'],
      'client_deleted'       => ['Cliente eliminado',         '#fff1f2','#fecdd3','#be123c'],
      'email_welcome_sent'   => ['Email bienvenida enviado', '#fdf4ff','#e9d5ff','#7e22ce'],
      'email_approval_sent'  => ['Email aprobación enviado', '#fdf4ff','#e9d5ff','#7e22ce'],
      'content_uploaded'     => ['Contenido subido',         '#eff6ff','#bfdbfe','#1d4ed8'],
      'post_created'         => ['Post creado',              '#ecfdf5','#6ee7b7','#065f46'],
      'post_updated'         => ['Post editado',              '#eff6ff','#bfdbfe','#1d4ed8'],
      'post_deleted'         => ['Post eliminado',            '#fff1f2','#fecdd3','#be123c'],
      'post_notified'        => ['Notificación enviada',     '#fdf4ff','#e9d5ff','#7e22ce'],
      'post_approved'        => ['Post aprobado',             '#ecfdf5','#6ee7b7','#065f46'],
      'post_rejected'        => ['Post rechazado',            '#fff1f2','#fecdd3','#be123c'],
      'post_published'       => ['Post marcado publicado',   '#eff6ff','#bfdbfe','#1d4ed8'],
      'post_draft_restored'  => ['Post vuelto a borrador',   '#f9fafb','#e5e7eb','#374151'],
      'nonce_failed'         => ['Nonce inválido',           '#fff1f2','#fecdd3','#be123c'],
      'invalid_token_access' => ['Token inválido',           '#fff1f2','#fecdd3','#be123c'],
      'cron_reminder_sent'   => ['Recordatorio cron',        '#f5f3ff','#ddd6fe','#5b21b6'],
      'client_view'          => ['Cliente accedió',          '#f0f9ff','#bae6fd','#0369a1'],
    ];
  }

  public static function render() {
    $tab = sanitize_text_field($_GET['sstab'] ?? 'clients');

    self::handle_client_create($tab);
    self::handle_client_edit($tab);
    self::handle_client_delete($tab);
    self::handle_resend_welcome($tab);
    self::handle_post_create($tab);
    self::handle_post_edit($tab);
    self::handle_post_delete($tab);
    self::handle_post_status($tab);
    self::handle_settings_save($tab);
    self::handle_content_delete($tab);

    if (self::$flash) {
      $cls = self::$flash['type'] === 'success' ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html(self::$flash['text']) . '</div>';
    }

    echo '<div class="ttb-tabs">';
    self::tab_link('clients',  'Clientes',      $tab);
    self::tab_link('content',  'Contenido',     $tab);
    self::tab_link('calendar', 'Calendario',    $tab);
    self::tab_link('audit',    'Auditoría',     $tab);
    self::tab_link('settings', 'Configuración', $tab);
    echo '</div>';

    switch ($tab) {
      case 'content':  self::render_content();  break;
      case 'calendar': self::render_calendar(); break;
      case 'audit':    self::render_audit();    break;
      case 'settings': self::render_settings(); break;
      default:         self::render_clients();  break;
    }
  }

  private static function tab_link($key, $label, $active) {
    $url = esc_url(home_url('/briefing?section=redes-sociales&sstab=' . $key));
    $cls = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . $url . '">' . esc_html($label) . '</a>';
  }

  private static function set_flash($type, $text) {
    self::$flash = ['type' => $type, 'text' => $text];
  }

  private static function action_url($tab = '') {
    $t = $tab ?: (sanitize_text_field($_GET['sstab'] ?? 'clients'));
    return esc_url(home_url('/briefing?section=redes-sociales&sstab=' . $t));
  }

  /* ════════════════════════════════
     ACCIONES POST — Clientes
  ════════════════════════════════ */
  private static function handle_client_create(&$tab) {
    if (!isset($_POST['ttb_social_client_create'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_client_create')) return;

    $name     = sanitize_text_field($_POST['sc_name']    ?? '');
    $emails   = self::sanitize_emails($_POST['sc_emails'] ?? []);
    $networks = array_map('sanitize_text_field', (array)($_POST['sc_networks'] ?? []));
    $notes    = sanitize_textarea_field($_POST['sc_notes'] ?? '');

    if (!$name || !$emails) {
      self::set_flash('error', 'Nombre y al menos un email son obligatorios.');
      $tab = 'clients'; return;
    }

    global $wpdb;
    $table = TTB_Social_DB::clients_table();
    $token = TTB_Social_DB::generate_token();

    $wpdb->insert($table, [
      'name'       => $name,
      'emails'     => wp_json_encode(array_values($emails)),
      'token'      => $token,
      'networks'   => wp_json_encode(array_values($networks)),
      'notes'      => $notes,
      'status'     => 'active',
      'created_at' => TTB_Social_DB::now(),
      'updated_at' => TTB_Social_DB::now(),
    ]);

    $new_id = (int)$wpdb->insert_id;
    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $new_id));

    if ($client) {
      (new TTB_Social_Mailer())->send_welcome($client);
      TTB_Social_DB::log($new_id, null, 'client_created', 'admin', ['name' => $name, 'emails' => $emails]);
      TTB_Social_DB::log($new_id, null, 'email_welcome_sent', 'admin', ['emails' => $emails]);
    }

    self::set_flash('success', 'Cliente creado y email de bienvenida enviado.');
    $tab = 'clients';
  }

  private static function handle_client_edit(&$tab) {
    if (!isset($_POST['ttb_social_client_edit'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_client_edit')) return;

    $id       = (int)($_POST['sc_id']       ?? 0);
    $name     = sanitize_text_field($_POST['sc_name']    ?? '');
    $emails   = self::sanitize_emails($_POST['sc_emails'] ?? []);
    $networks = array_map('sanitize_text_field', (array)($_POST['sc_networks'] ?? []));
    $notes    = sanitize_textarea_field($_POST['sc_notes'] ?? '');
    $status   = in_array($_POST['sc_status'] ?? '', ['active','inactive'], true) ? $_POST['sc_status'] : 'active';

    if (!$id || !$name || !$emails) {
      self::set_flash('error', 'Todos los campos obligatorios son necesarios.');
      $tab = 'clients'; return;
    }

    global $wpdb;
    $wpdb->update(TTB_Social_DB::clients_table(), [
      'name'       => $name,
      'emails'     => wp_json_encode(array_values($emails)),
      'networks'   => wp_json_encode(array_values($networks)),
      'notes'      => $notes,
      'status'     => $status,
      'updated_at' => TTB_Social_DB::now(),
    ], ['id' => $id]);

    TTB_Social_DB::log($id, null, 'client_updated', 'admin', ['name' => $name]);
    self::set_flash('success', 'Cliente actualizado.');
    $tab = 'clients';
  }

  private static function handle_client_delete(&$tab) {
    if (!isset($_POST['ttb_social_client_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_client_delete')) return;

    $id = (int)($_POST['sc_id'] ?? 0);
    if (!$id) return;

    global $wpdb;
    $client = $wpdb->get_row($wpdb->prepare("SELECT name FROM " . TTB_Social_DB::clients_table() . " WHERE id=%d", $id));
    TTB_Social_DB::log($id, null, 'client_deleted', 'admin', ['name' => $client->name ?? '—']);

    $wpdb->delete(TTB_Social_DB::clients_table(), ['id' => $id]);
    $wpdb->delete(TTB_Social_DB::posts_table(),   ['client_id' => $id]);
    $wpdb->delete(TTB_Social_DB::content_table(), ['client_id' => $id]);

    self::set_flash('success', 'Cliente eliminado.');
    $tab = 'clients';
  }

  private static function handle_resend_welcome(&$tab) {
    if (!isset($_POST['ttb_social_resend_welcome'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_resend_welcome')) return;

    $id = (int)($_POST['sc_id'] ?? 0);
    if (!$id) return;

    global $wpdb;
    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . TTB_Social_DB::clients_table() . " WHERE id=%d", $id));
    if (!$client) return;

    (new TTB_Social_Mailer())->send_welcome($client);
    TTB_Social_DB::log($id, null, 'email_welcome_sent', 'admin', ['trigger' => 'manual_resend']);
    self::set_flash('success', 'Email de acceso reenviado.');
    $tab = 'clients';
  }

  /* ════════════════════════════════
     ACCIONES POST — Posts
  ════════════════════════════════ */
  private static function handle_post_create(&$tab) {
    if (!isset($_POST['ttb_social_post_create'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_post_create')) return;

    $client_id = (int)($_POST['sp_client_id'] ?? 0);
    $date      = sanitize_text_field($_POST['sp_date']    ?? '');
    $time      = sanitize_text_field($_POST['sp_time']    ?? '');
    $caption   = sanitize_textarea_field($_POST['sp_caption'] ?? '');
    $note      = sanitize_textarea_field($_POST['sp_note']    ?? '');

    if (!$client_id || !$date) {
      self::set_flash('error', 'Cliente y fecha son obligatorios.');
      $tab = 'calendar'; return;
    }

    $creative_url = '';
    if (!empty($_FILES['sp_creative']['tmp_name']) && $_FILES['sp_creative']['error'] === UPLOAD_ERR_OK) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';
      $att_id = media_handle_sideload([
        'name'     => $_FILES['sp_creative']['name'],
        'type'     => $_FILES['sp_creative']['type'],
        'tmp_name' => $_FILES['sp_creative']['tmp_name'],
        'error'    => $_FILES['sp_creative']['error'],
        'size'     => $_FILES['sp_creative']['size'],
      ], 0, null, ['post_title' => 'Social Creative - ' . $date, 'post_status' => 'private']);
      if (!is_wp_error($att_id)) $creative_url = wp_get_attachment_url($att_id) ?: '';
    }
    if (!$creative_url && !empty($_POST['sp_creative_url'])) {
      $creative_url = esc_url_raw($_POST['sp_creative_url']);
    }

    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $clients_table = TTB_Social_DB::clients_table();

    $wpdb->insert($posts_table, [
      'client_id'      => $client_id,
      'scheduled_date' => $date,
      'scheduled_time' => $time ?: null,
      'network'        => 'all',
      'post_type'      => 'post',
      'caption'        => $caption,
      'creative_url'   => $creative_url,
      'creative_note'  => $note,
      'status'         => 'pending_approval',
      'notified_at'    => TTB_Social_DB::now(),
      'created_at'     => TTB_Social_DB::now(),
      'updated_at'     => TTB_Social_DB::now(),
    ]);

    $post_id = (int)$wpdb->insert_id;
    $client  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients_table WHERE id=%d", $client_id));
    $post    = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d", $post_id));

    if ($client && $post) {
      (new TTB_Social_Mailer())->send_post_approval($client, $post);
      TTB_Social_DB::log($client_id, $post_id, 'post_notified',       'admin', ['trigger' => 'auto_on_create']);
      TTB_Social_DB::log($client_id, $post_id, 'email_approval_sent', 'admin', []);
    }

    TTB_Social_DB::log($client_id, $post_id, 'post_created', 'admin', ['date' => $date]);
    self::set_flash('success', 'Publicación creada y notificación enviada al cliente.');
    $tab = 'calendar';
  }

  private static function handle_post_edit(&$tab) {
    if (!isset($_POST['ttb_social_post_edit'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_post_edit')) return;

    $post_id  = (int)($_POST['sp_post_id'] ?? 0);
    $date     = sanitize_text_field($_POST['sp_date']    ?? '');
    $time     = sanitize_text_field($_POST['sp_time']    ?? '');
    $caption  = sanitize_textarea_field($_POST['sp_caption'] ?? '');
    $note     = sanitize_textarea_field($_POST['sp_note']    ?? '');
    $keep_url = esc_url_raw($_POST['sp_keep_creative_url'] ?? '');

    if (!$post_id || !$date) {
      self::set_flash('error', 'Datos incompletos.');
      $tab = 'calendar'; return;
    }

    $creative_url = $keep_url;
    if (!empty($_FILES['sp_creative']['tmp_name']) && $_FILES['sp_creative']['error'] === UPLOAD_ERR_OK) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';
      $att_id = media_handle_sideload([
        'name'     => $_FILES['sp_creative']['name'],
        'type'     => $_FILES['sp_creative']['type'],
        'tmp_name' => $_FILES['sp_creative']['tmp_name'],
        'error'    => $_FILES['sp_creative']['error'],
        'size'     => $_FILES['sp_creative']['size'],
      ], 0, null, ['post_title' => 'Social Creative - ' . $date, 'post_status' => 'private']);
      if (!is_wp_error($att_id)) $creative_url = wp_get_attachment_url($att_id) ?: $keep_url;
    }
    if (!$creative_url && !empty($_POST['sp_creative_url'])) {
      $creative_url = esc_url_raw($_POST['sp_creative_url']);
    }

    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d", $post_id));
    if (!$post) { self::set_flash('error', 'Post no encontrado.'); $tab = 'calendar'; return; }

    $new_status = $post->status;
    $renotify   = false;
    if ($post->status === 'rejected') { $new_status = 'pending_approval'; $renotify = true; }

    $wpdb->update($posts_table, [
      'scheduled_date' => $date,
      'scheduled_time' => $time ?: null,
      'caption'        => $caption,
      'creative_url'   => $creative_url,
      'creative_note'  => $note,
      'status'         => $new_status,
      'updated_at'     => TTB_Social_DB::now(),
    ], ['id' => $post_id]);

    TTB_Social_DB::log((int)$post->client_id, $post_id, 'post_updated', 'admin', ['date' => $date]);

    if ($renotify) {
      $client       = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . TTB_Social_DB::clients_table() . " WHERE id=%d", $post->client_id));
      $updated_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d", $post_id));
      if ($client && $updated_post) {
        (new TTB_Social_Mailer())->send_post_approval($client, $updated_post);
        $wpdb->update($posts_table, ['notified_at' => TTB_Social_DB::now()], ['id' => $post_id]);
        TTB_Social_DB::log((int)$post->client_id, $post_id, 'post_notified',       'admin', ['trigger' => 'auto_on_edit_rejected']);
        TTB_Social_DB::log((int)$post->client_id, $post_id, 'email_approval_sent', 'admin', []);
      }
    }

    self::set_flash('success', 'Post actualizado.' . ($renotify ? ' Notificación reenviada al cliente.' : ''));
    $tab = 'calendar';
  }

  private static function handle_post_delete(&$tab) {
    if (!isset($_POST['ttb_social_post_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_post_delete')) return;

    $post_id = (int)($_POST['sp_post_id'] ?? 0);
    if (!$post_id) return;

    global $wpdb;
    $post = $wpdb->get_row($wpdb->prepare("SELECT client_id FROM " . TTB_Social_DB::posts_table() . " WHERE id=%d", $post_id));
    TTB_Social_DB::log($post->client_id ?? null, $post_id, 'post_deleted', 'admin', []);
    $wpdb->delete(TTB_Social_DB::posts_table(), ['id' => $post_id]);
    self::set_flash('success', 'Post eliminado.');
    $tab = 'calendar';
  }

  private static function handle_post_status(&$tab) {
    if (!isset($_POST['ttb_social_post_status'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_post_status')) return;

    $post_id    = (int)($_POST['sp_post_id']    ?? 0);
    $new_status = sanitize_text_field($_POST['sp_new_status'] ?? '');
    $allowed    = ['pending_approval','approved','rejected','published'];
    if (!$post_id || !in_array($new_status, $allowed, true)) return;

    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $post        = $wpdb->get_row($wpdb->prepare("SELECT client_id FROM $posts_table WHERE id=%d", $post_id));
    if (!$post) return;

    $event = ($new_status === 'published') ? 'post_published' : 'post_draft_restored';
    $wpdb->update($posts_table, ['status' => $new_status, 'updated_at' => TTB_Social_DB::now()], ['id' => $post_id]);
    TTB_Social_DB::log($post->client_id, $post_id, $event, 'admin', ['new_status' => $new_status]);
    self::set_flash('success', 'Estado actualizado.');
    $tab = 'calendar';
  }

  private static function handle_content_delete(&$tab) {
    if (!isset($_POST['ttb_social_content_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_content_delete')) return;
    $id = (int)($_POST['content_id'] ?? 0);
    if (!$id) return;
    global $wpdb;
    $wpdb->delete(TTB_Social_DB::content_table(), ['id' => $id]);
    self::set_flash('success', 'Archivo eliminado.');
    $tab = 'content';
  }

  private static function handle_settings_save(&$tab) {
    if (!isset($_POST['ttb_social_settings'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_settings')) return;
    $fields = [
      'ttb_social_notify_social'    => sanitize_email($_POST['ttb_social_notify_social']    ?? ''),
      'ttb_social_notify_hola'      => sanitize_email($_POST['ttb_social_notify_hola']      ?? ''),
      'ttb_social_resend_days'      => max(1, (int)($_POST['ttb_social_resend_days']        ?? 2)),
      'ttb_social_max_resends'      => (int)($_POST['ttb_social_max_resends']               ?? 3),
      'ttb_social_max_filesize'     => max(1, min(200, (int)($_POST['ttb_social_max_filesize'] ?? 50))),
      'ttb_social_approval_subject' => sanitize_text_field($_POST['ttb_social_approval_subject'] ?? ''),
      'ttb_social_approval_note'    => sanitize_textarea_field($_POST['ttb_social_approval_note'] ?? ''),
    ];
    foreach ($fields as $key => $val) update_option($key, $val);
    self::set_flash('success', 'Configuración guardada.');
    $tab = 'settings';
  }

  /* ════════════════════════════════
     RENDER: CLIENTES
  ════════════════════════════════ */
  private static function render_clients() {
    global $wpdb;
    $table        = TTB_Social_DB::clients_table();
    $clients      = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");
    $networks_all = TTB_Social_DB::networks();

    $edit_id = (int)($_GET['edit_sc'] ?? 0);
    $edit_c  = null;
    if ($edit_id) $edit_c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id));

    $action_url = self::action_url('clients');

    echo '<div class="ttb-card"><h3>Nuevo cliente de redes</h3></div>';
    echo '<form method="post" action="' . $action_url . '" class="ttb-card">';
    wp_nonce_field('ttb_social_client_create');
    echo '<div class="ttb-grid2">';
    echo '<div><label>Nombre del cliente</label><input class="ttb-input" type="text" name="sc_name" required placeholder="Empresa Ejemplo S.L."></div>';
    echo '<div><label>Notas internas</label><input class="ttb-input" type="text" name="sc_notes" placeholder="2 posts/semana..."></div>';
    echo '</div>';
    echo '<div style="margin-top:12px"><label>Emails del cliente</label>';
    echo '<div id="ttb-sc-emails-create" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">';
    echo '<div class="ttb-sc-email-row" style="display:flex;gap:8px;align-items:center"><input class="ttb-input" type="email" name="sc_emails[]" placeholder="email@cliente.com" required style="flex:1"><button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-sc-remove-email" style="display:none">✕</button></div>';
    echo '</div>';
    echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm ttb-sc-add-email" data-target="ttb-sc-emails-create" style="margin-top:8px">+ Añadir email</button></div>';
    echo '<div style="margin-top:12px"><label>Redes que gestiona TicTac para este cliente</label><div class="ttb-checks" style="margin-top:8px">';
    foreach ($networks_all as $k => [$label]) {
      echo '<label class="ttb-check"><input type="checkbox" name="sc_networks[]" value="' . esc_attr($k) . '"> ' . esc_html($label) . '</label>';
    }
    echo '</div></div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_social_client_create" value="1">Crear cliente y enviar acceso</button></div>';
    echo '</form>';

    if ($edit_c) {
      $edit_emails   = json_decode((string)$edit_c->emails,   true) ?: [];
      $edit_networks = json_decode((string)$edit_c->networks, true) ?: [];
      $cancel_url    = esc_url(home_url('/briefing?section=redes-sociales&sstab=clients'));
      echo '<div class="ttb-modal-overlay" id="ttbScEditModal" role="dialog" aria-modal="true" style="display:flex">';
      echo '<div class="ttb-modal ttb-edit-modal"><h3 class="ttb-edit-modal__title">Editar cliente</h3>';
      echo '<form method="post" action="' . $action_url . '" class="ttb-formgrid">';
      wp_nonce_field('ttb_social_client_edit');
      echo '<input type="hidden" name="sc_id" value="' . (int)$edit_c->id . '">';
      echo '<div class="ttb-grid2">';
      echo '<div><label>Nombre</label><input class="ttb-input" type="text" name="sc_name" value="' . esc_attr($edit_c->name) . '" required></div>';
      echo '<div><label>Notas internas</label><input class="ttb-input" type="text" name="sc_notes" value="' . esc_attr($edit_c->notes ?? '') . '"></div>';
      echo '</div>';
      echo '<div style="margin-top:10px"><label>Emails</label><div id="ttb-sc-emails-edit" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">';
      foreach ($edit_emails as $i => $em) {
        $rm = $i > 0 ? '' : 'style="display:none"';
        echo '<div class="ttb-sc-email-row" style="display:flex;gap:8px;align-items:center"><input class="ttb-input" type="email" name="sc_emails[]" value="' . esc_attr($em) . '" required style="flex:1"><button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-sc-remove-email" ' . $rm . '>✕</button></div>';
      }
      if (empty($edit_emails)) echo '<div class="ttb-sc-email-row" style="display:flex;gap:8px;align-items:center"><input class="ttb-input" type="email" name="sc_emails[]" required style="flex:1"><button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-sc-remove-email" style="display:none">✕</button></div>';
      echo '</div><button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm ttb-sc-add-email" data-target="ttb-sc-emails-edit" style="margin-top:8px">+ Añadir email</button></div>';
      echo '<div style="margin-top:10px"><label>Redes</label><div class="ttb-checks" style="margin-top:8px">';
      foreach ($networks_all as $k => [$label]) {
        $checked = in_array($k, $edit_networks, true) ? 'checked' : '';
        echo '<label class="ttb-check"><input type="checkbox" name="sc_networks[]" value="' . esc_attr($k) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
      }
      echo '</div></div>';
      echo '<div style="margin-top:10px"><label>Estado</label><div class="ttb-checks" style="margin-top:8px">';
      echo '<label class="ttb-check"><input type="radio" name="sc_status" value="active"' . ($edit_c->status === 'active' ? ' checked' : '') . '> Activo</label>';
      echo '<label class="ttb-check"><input type="radio" name="sc_status" value="inactive"' . ($edit_c->status === 'inactive' ? ' checked' : '') . '> Inactivo</label>';
      echo '</div></div>';
      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_social_client_edit" value="1">Guardar</button>';
      echo '</div></form></div></div>';
    }

    echo '<div class="ttb-card"><h3>Clientes</h3>';
    if (!$clients) { echo '<p class="ttb-muted">No hay clientes aún.</p></div>'; self::email_js(); return; }
    echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr><th>Cliente</th><th>Emails</th><th>Redes</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
    foreach ($clients as $c) {
      $nets       = json_decode((string)$c->networks, true) ?: [];
      $nets_label = implode(', ', array_map(fn($n) => $networks_all[$n][0] ?? $n, $nets)) ?: '—';
      $emails_arr = json_decode((string)$c->emails, true) ?: [];
      $edit_url   = esc_url(home_url('/briefing?section=redes-sociales&sstab=clients&edit_sc=' . (int)$c->id));
      $cal_url    = esc_url(home_url('/briefing?section=redes-sociales&sstab=calendar&filter_client=' . (int)$c->id));
      $portal_url = esc_url(TTB_Social_DB::client_url($c->token));
      $status_lbl = $c->status === 'active' ? '<span class="ttb-status ttb-status--sent">Activo</span>' : '<span class="ttb-status ttb-status--pending">Inactivo</span>';
      echo '<tr>';
      echo '<td><strong>' . esc_html($c->name) . '</strong>' . ($c->notes ? '<br><small style="color:var(--ttb-muted)">' . esc_html(mb_substr($c->notes, 0, 50)) . '</small>' : '') . '</td>';
      echo '<td style="font-size:13px">' . implode('<br>', array_map('esc_html', $emails_arr)) . '</td>';
      echo '<td style="font-size:13px">' . esc_html($nets_label) . '</td>';
      echo '<td>' . $status_lbl . '</td>';
      echo '<td><div class="ttb-row-actions">';
      echo '<a href="' . $portal_url . '" target="_blank" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Ver portal</a>';
      echo '<a href="' . $cal_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Calendario</a>';
      echo '<a href="' . $edit_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Editar</a>';
      echo '<form method="post" action="' . $action_url . '" style="margin:0">';
      wp_nonce_field('ttb_social_resend_welcome');
      echo '<input type="hidden" name="sc_id" value="' . (int)$c->id . '">';
      echo '<button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_social_resend_welcome" value="1">Reenviar acceso</button></form>';
      echo '<form method="post" action="' . $action_url . '" style="margin:0" onsubmit="return confirm(\'¿Eliminar a ' . esc_js($c->name) . '?\')">';
      wp_nonce_field('ttb_social_client_delete');
      echo '<input type="hidden" name="sc_id" value="' . (int)$c->id . '">';
      echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_social_client_delete" value="1">Eliminar</button></form>';
      echo '</div></td></tr>';
    }
    echo '</tbody></table></div></div>';
    self::email_js();
  }

  /* ════════════════════════════════
     RENDER: CONTENIDO
  ════════════════════════════════ */
  private static function render_content() {
    global $wpdb;
    $clients_table = TTB_Social_DB::clients_table();
    $content_table = TTB_Social_DB::content_table();
    $action_url    = self::action_url('content');
    $clients       = $wpdb->get_results("SELECT id, name FROM $clients_table WHERE status='active' ORDER BY name ASC");
    $filter_cid    = (int)($_GET['filter_client'] ?? 0);

    echo '<div class="ttb-card"><h3>Contenido enviado por los clientes</h3>';
    echo '<p class="ttb-muted" style="margin:0 0 14px">Archivos y notas subidos para usar en las publicaciones.</p>';
    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
    echo '<input type="hidden" name="section" value="redes-sociales"><input type="hidden" name="sstab" value="content">';
    echo '<select name="filter_client" class="ttb-input" style="max-width:260px"><option value="">— Todos —</option>';
    foreach ($clients as $c) echo '<option value="' . (int)$c->id . '" ' . selected($filter_cid, $c->id, false) . '>' . esc_html($c->name) . '</option>';
    echo '</select><button class="ttb-btn ttb-btn--ghost" type="submit">Filtrar</button></form></div>';

    $where = $filter_cid ? $wpdb->prepare('WHERE ct.client_id = %d', $filter_cid) : '';
    $items = $wpdb->get_results("SELECT ct.*, cl.name AS client_name FROM $content_table ct INNER JOIN $clients_table cl ON cl.id=ct.client_id $where ORDER BY ct.created_at DESC LIMIT 200");
    if (!$items) { echo '<div class="ttb-card"><p class="ttb-muted">No hay contenido aún.</p></div>'; return; }

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;padding:4px 0">';
    foreach ($items as $item) {
      $is_video = in_array(strtolower(pathinfo($item->file_url ?? '', PATHINFO_EXTENSION)), ['mp4','mov','webm','avi'], true);
      echo '<div class="ttb-card" style="padding:12px">';
      if ($item->type === 'text' || !$item->file_url) {
        echo '<div style="background:#f9fafb;border-radius:10px;padding:14px;min-height:100px;font-size:14px;color:var(--ttb-text);line-height:1.6;margin-bottom:10px">' . nl2br(esc_html(mb_substr($item->caption ?? '', 0, 200))) . '</div>';
      } elseif ($is_video) {
        echo '<video src="' . esc_url($item->file_url) . '" controls style="width:100%;border-radius:10px;max-height:180px;background:#111;margin-bottom:10px"></video>';
      } else {
        echo '<a href="' . esc_url($item->file_url) . '" target="_blank"><img src="' . esc_url($item->file_url) . '" style="width:100%;border-radius:10px;aspect-ratio:1;object-fit:cover;display:block;margin-bottom:10px" alt=""></a>';
      }
      if ($item->note) echo '<p style="font-size:12px;color:var(--ttb-muted);margin:0 0 8px;line-height:1.5">' . esc_html(mb_substr($item->note, 0, 100)) . '</p>';
      echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:4px;margin-bottom:8px">';
      echo '<div><strong style="font-size:13px">' . esc_html($item->client_name) . '</strong><br><span style="font-size:12px;color:var(--ttb-muted)">' . esc_html(date_i18n('d/m/Y H:i', strtotime($item->created_at))) . '</span></div>';
      if ($item->used) echo '<span style="background:#ecfdf5;color:#065f46;font-size:11px;font-weight:900;padding:2px 8px;border-radius:999px;border:1px solid #6ee7b7">Usado</span>';
      echo '</div>';
      if ($item->file_url && !$is_video) echo '<a href="' . esc_url($item->file_url) . '" download class="ttb-btn ttb-btn--ghost ttb-btn--sm" style="width:100%;text-align:center;margin-bottom:6px;display:block">Descargar</a>';
      echo '<form method="post" action="' . $action_url . '" onsubmit="return confirm(\'¿Eliminar?\')"><input type="hidden" name="content_id" value="' . (int)$item->id . '">';
      wp_nonce_field('ttb_social_content_delete');
      echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_social_content_delete" value="1" style="width:100%">Eliminar</button></form>';
      echo '</div>';
    }
    echo '</div>';
  }

  /* ════════════════════════════════
     RENDER: CALENDARIO (admin)
  ════════════════════════════════ */
  private static function render_calendar() {
    global $wpdb;
    $clients_table = TTB_Social_DB::clients_table();
    $posts_table   = TTB_Social_DB::posts_table();
    $action_url    = self::action_url('calendar');
    $statuses      = TTB_Social_DB::post_statuses();
    $clients       = $wpdb->get_results("SELECT id, name FROM $clients_table WHERE status='active' ORDER BY name ASC");

    $filter_month  = sanitize_text_field($_GET['filter_month']  ?? date('Y-m'));
    $filter_client = (int)($_GET['filter_client'] ?? 0);

    [$year, $month] = array_map('intval', explode('-', $filter_month . '-01'));
    $first_day  = mktime(0, 0, 0, $month, 1, $year);
    $days_in    = (int)date('t', $first_day);
    $start_dow  = (int)date('N', $first_day);
    $prev_month = date('Y-m', strtotime('-1 month', $first_day));
    $next_month = date('Y-m', strtotime('+1 month', $first_day));
    $month_name = date_i18n('F Y', $first_day);

    $edit_post_id = (int)($_GET['edit_sp'] ?? 0);
    $edit_post    = null;
    if ($edit_post_id) $edit_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d", $edit_post_id));

    $where  = ['YEAR(p.scheduled_date) = %d', 'MONTH(p.scheduled_date) = %d'];
    $params = [$year, $month];
    if ($filter_client) { $where[] = 'p.client_id = %d'; $params[] = $filter_client; }
    $posts_raw = $wpdb->get_results($wpdb->prepare(
      "SELECT p.*, c.name AS client_name FROM $posts_table p INNER JOIN $clients_table c ON c.id=p.client_id WHERE " . implode(' AND ', $where) . " ORDER BY p.scheduled_time ASC",
      ...$params
    ));

    $posts_by_day = [];
    foreach ($posts_raw as $p) {
      $posts_by_day[(int)date('j', strtotime($p->scheduled_date))][] = $p;
    }

    // Formulario nuevo post
    echo '<div class="ttb-card"><h3 style="margin:0 0 4px">Nueva publicación</h3><p class="ttb-muted" style="margin:0 0 14px;font-size:13px">Al guardar se notifica automáticamente al cliente.</p></div>';
    echo '<form method="post" action="' . $action_url . '" class="ttb-card" enctype="multipart/form-data">';
    wp_nonce_field('ttb_social_post_create');
    echo '<div class="ttb-grid2">';
    echo '<div><label>Cliente <span class="ttb-required">*</span></label><select name="sp_client_id" class="ttb-input" required><option value="">— Selecciona —</option>';
    foreach ($clients as $c) echo '<option value="' . (int)$c->id . '">' . esc_html($c->name) . '</option>';
    echo '</select></div>';
    echo '<div><label>Fecha <span class="ttb-required">*</span></label><input class="ttb-input" type="date" name="sp_date" required value="' . esc_attr(date('Y-m-d')) . '"></div>';
    echo '</div>';
    echo '<div style="margin-top:10px"><label>Hora estimada <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span></label><input class="ttb-input" type="time" name="sp_time"></div>';
    echo '<div style="margin-top:10px"><label>Creatividad</label>';
    echo '<input class="ttb-input" type="file" name="sp_creative" accept="image/*,video/*" style="margin-bottom:6px">';
    echo '<input class="ttb-input" type="url" name="sp_creative_url" placeholder="o pega una URL..."></div>';
    echo '<div style="margin-top:10px"><label>Texto de la publicación <span style="font-weight:400;color:var(--ttb-muted)">(caption)</span></label>';
    echo '<textarea name="sp_caption" class="ttb-textarea" style="min-height:80px" placeholder="Escribe el copy del post..."></textarea></div>';
    echo '<div style="margin-top:10px"><label>Nota para el cliente <span style="font-weight:400;color:var(--ttb-muted)">(aparece en el email)</span></label>';
    echo '<input class="ttb-input" type="text" name="sp_note" placeholder="Ej: He usado las fotos que nos mandaste."></div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_social_post_create" value="1">Crear y notificar al cliente</button></div>';
    echo '</form>';

    // Modal editar
    if ($edit_post) {
      $cancel_url = esc_url(home_url('/briefing?section=redes-sociales&sstab=calendar&filter_month=' . $filter_month . ($filter_client ? '&filter_client=' . $filter_client : '')));
      echo '<div class="ttb-modal-overlay" id="ttbSpEditModal" role="dialog" aria-modal="true" style="display:flex">';
      echo '<div class="ttb-modal ttb-edit-modal" style="max-width:580px"><h3 class="ttb-edit-modal__title">Editar publicación</h3>';
      echo '<form method="post" action="' . $action_url . '" class="ttb-formgrid" enctype="multipart/form-data">';
      wp_nonce_field('ttb_social_post_edit');
      echo '<input type="hidden" name="sp_post_id" value="' . (int)$edit_post->id . '">';
      echo '<input type="hidden" name="sp_keep_creative_url" value="' . esc_attr($edit_post->creative_url ?? '') . '">';
      echo '<div class="ttb-grid2">';
      echo '<div><label>Fecha</label><input class="ttb-input" type="date" name="sp_date" value="' . esc_attr($edit_post->scheduled_date) . '" required></div>';
      echo '<div><label>Hora</label><input class="ttb-input" type="time" name="sp_time" value="' . esc_attr(substr($edit_post->scheduled_time ?? '', 0, 5)) . '"></div>';
      echo '</div>';
      echo '<div style="margin-top:10px"><label>Nueva creatividad <span style="font-weight:400;color:var(--ttb-muted)">(vacío = conservar)</span></label>';
      if ($edit_post->creative_url) echo '<a href="' . esc_url($edit_post->creative_url) . '" target="_blank" style="display:block;font-size:12px;color:var(--ttb-pink);margin-bottom:6px">Ver creatividad actual →</a>';
      echo '<input class="ttb-input" type="file" name="sp_creative" accept="image/*,video/*" style="margin-bottom:6px">';
      echo '<input class="ttb-input" type="url" name="sp_creative_url" placeholder="o pega una URL..."></div>';
      echo '<div style="margin-top:10px"><label>Caption</label><textarea name="sp_caption" class="ttb-textarea" style="min-height:80px">' . esc_textarea($edit_post->caption ?? '') . '</textarea></div>';
      echo '<div style="margin-top:10px"><label>Nota para el cliente</label><input class="ttb-input" type="text" name="sp_note" value="' . esc_attr($edit_post->creative_note ?? '') . '"></div>';
      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_social_post_edit" value="1">Guardar cambios</button>';
      echo '</div></form></div></div>';
    }

    // Calendario
    echo '<div class="ttb-card">';
    echo '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">';
    echo '<div style="display:flex;align-items:center;gap:10px">';
    echo '<a href="' . esc_url(home_url('/briefing?section=redes-sociales&sstab=calendar&filter_month=' . $prev_month . ($filter_client ? '&filter_client=' . $filter_client : ''))) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8592;</a>';
    echo '<h3 style="margin:0;font-size:18px;text-transform:capitalize">' . esc_html($month_name) . '</h3>';
    echo '<a href="' . esc_url(home_url('/briefing?section=redes-sociales&sstab=calendar&filter_month=' . $next_month . ($filter_client ? '&filter_client=' . $filter_client : ''))) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8594;</a>';
    echo '</div>';
    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:8px;align-items:center">';
    echo '<input type="hidden" name="section" value="redes-sociales"><input type="hidden" name="sstab" value="calendar"><input type="hidden" name="filter_month" value="' . esc_attr($filter_month) . '">';
    echo '<select name="filter_client" class="ttb-input" style="min-width:160px"><option value="">Todos los clientes</option>';
    foreach ($clients as $c) echo '<option value="' . (int)$c->id . '" ' . selected($filter_client, $c->id, false) . '>' . esc_html($c->name) . '</option>';
    echo '</select><button class="ttb-btn ttb-btn--ghost ttb-btn--sm" type="submit">Filtrar</button></form>';
    echo '</div>';

    // Renderizar posts ocultos en el DOM para el modal
    self::render_posts_data_store($posts_raw, $action_url, $statuses, $filter_month, $filter_client);

    // Grid
    self::render_calendar_grid($posts_by_day, $days_in, $start_dow, $year, $month, $action_url, $statuses, $filter_month, $filter_client, true);

    // Leyenda
    echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px;font-size:12px">';
    foreach ([['#fffbeb','#fde68a','#92400e','Pendiente aprobación'],['#ecfdf5','#6ee7b7','#065f46','Aprobado'],['#fff1f2','#fecdd3','#be123c','Rechazado'],['#eff6ff','#bfdbfe','#1d4ed8','Publicado']] as [$bg,$bc,$co,$lbl]) {
      echo '<span style="display:inline-flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:' . $bg . ';border:1px solid ' . $bc . ';display:inline-block"></span><span style="color:var(--ttb-muted)">' . esc_html($lbl) . '</span></span>';
    }
    echo '</div></div>';
  }

  /**
   * Renderiza el HTML de cada post en divs ocultos en el DOM.
   * El JS los lee por ID cuando el usuario hace clic en un chip.
   * Esto evita pasar HTML con nonces dentro de un onclick JSON (que rompe por comillas).
   */
  private static function render_posts_data_store($posts, $action_url, $statuses, $filter_month, $filter_client) {
    echo '<div id="ttb-posts-store" style="display:none">';
    foreach ($posts as $post) {
      [$sl,$sbg,$sbc,$sco] = $statuses[$post->status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];
      $date_fmt = date_i18n('l, j \d\e F \d\e Y', strtotime($post->scheduled_date));
      $time_str = $post->scheduled_time ? ' · ' . substr($post->scheduled_time, 0, 5) . 'h' : '';
      $back_qs  = '&filter_month=' . urlencode($filter_month) . ($filter_client ? '&filter_client=' . (int)$filter_client : '');
      $edit_url = esc_url(home_url('/briefing?section=redes-sociales&sstab=calendar&edit_sp=' . (int)$post->id . $back_qs));

      ob_start();
      ?>
      <div id="ttb-post-data-<?php echo (int)$post->id; ?>">
        <!-- Cabecera -->
        <div style="margin-bottom:14px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
            <strong style="font-size:16px"><?php echo esc_html($post->client_name ?? ''); ?></strong>
            <span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;background:<?php echo $sbg; ?>;border:1px solid <?php echo $sbc; ?>;color:<?php echo $sco; ?>"><?php echo esc_html($sl); ?></span>
          </div>
          <p style="margin:0;font-size:13px;color:var(--ttb-muted)"><?php echo esc_html($date_fmt . $time_str); ?></p>
        </div>

        <!-- Creatividad -->
        <?php if ($post->creative_url): ?>
          <?php $is_vid = in_array(strtolower(pathinfo($post->creative_url, PATHINFO_EXTENSION)), ['mp4','mov','webm'], true); ?>
          <div style="border-radius:12px;overflow:hidden;margin-bottom:14px;border:1px solid var(--ttb-border)">
            <?php if ($is_vid): ?>
              <video src="<?php echo esc_url($post->creative_url); ?>" controls style="width:100%;max-height:260px;display:block;background:#111"></video>
            <?php else: ?>
              <a href="<?php echo esc_url($post->creative_url); ?>" target="_blank">
                <img src="<?php echo esc_url($post->creative_url); ?>" style="width:100%;max-height:300px;object-fit:cover;display:block" alt="Creatividad">
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Caption -->
        <?php if ($post->caption): ?>
          <div style="background:#f9fafb;border-radius:10px;padding:12px;margin-bottom:12px;font-size:14px;color:var(--ttb-text);line-height:1.7;white-space:pre-line;border-left:3px solid var(--ttb-pink)"><?php echo esc_html($post->caption); ?></div>
        <?php endif; ?>

        <!-- Nota del equipo -->
        <?php if ($post->creative_note): ?>
          <div style="background:#fdf4ff;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#7e22ce;border:1px solid #e9d5ff"><?php echo esc_html($post->creative_note); ?></div>
        <?php endif; ?>

        <!-- Comentario del cliente si rechazó -->
        <?php if ($post->status === 'rejected' && $post->client_note): ?>
          <div style="background:#fff1f2;border-radius:10px;padding:12px;margin-bottom:12px;font-size:13px;color:#be123c;border:1px solid #fecdd3">
            <strong style="display:block;margin-bottom:4px">Comentario del cliente:</strong>
            <?php echo nl2br(esc_html($post->client_note)); ?>
          </div>
        <?php endif; ?>

        <!-- Acciones admin -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;border-top:1px solid var(--ttb-border);padding-top:14px">
          <a href="<?php echo $edit_url; ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Editar</a>

          <?php if ($post->status === 'approved'): ?>
            <form method="post" action="<?php echo $action_url; ?>" style="margin:0">
              <?php wp_nonce_field('ttb_social_post_status'); ?>
              <input type="hidden" name="sp_post_id" value="<?php echo (int)$post->id; ?>">
              <input type="hidden" name="sp_new_status" value="published">
              <button class="ttb-btn ttb-btn--sm" name="ttb_social_post_status" value="1" style="background:linear-gradient(135deg,#1d4ed8,#1e40af)">Marcar publicado</button>
            </form>
          <?php endif; ?>

          <?php if ($post->status !== 'pending_approval'): ?>
            <form method="post" action="<?php echo $action_url; ?>" style="margin:0">
              <?php wp_nonce_field('ttb_social_post_status'); ?>
              <input type="hidden" name="sp_post_id" value="<?php echo (int)$post->id; ?>">
              <input type="hidden" name="sp_new_status" value="pending_approval">
              <button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_social_post_status" value="1">Reenviar al cliente</button>
            </form>
          <?php endif; ?>

          <form method="post" action="<?php echo $action_url; ?>" style="margin:0" onsubmit="return confirm('¿Eliminar este post?')">
            <?php wp_nonce_field('ttb_social_post_delete'); ?>
            <input type="hidden" name="sp_post_id" value="<?php echo (int)$post->id; ?>">
            <button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_social_post_delete" value="1">Eliminar</button>
          </form>
        </div>
      </div>
      <?php
      echo ob_get_clean();
    }
    echo '</div>';
  }

  /* ── Helper: grid visual del calendario ─────────────────── */
  public static function render_calendar_grid($posts_by_day, $days_in, $start_dow, $year, $month, $action_url, $statuses, $filter_month, $filter_client, $is_admin) {
    $today_day    = (int)date('j');
    $today_month  = (int)date('m');
    $today_year   = (int)date('Y');
    $is_cur_month = ($today_year === $year && $today_month === $month);
    $day_names    = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    ?>
    <style>
    .ttb-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
    .ttb-cal-dayname { text-align:center; font-size:11px; font-weight:900; color:var(--ttb-muted); text-transform:uppercase; letter-spacing:.06em; padding:6px 0; }
    .ttb-cal-cell { min-height:90px; border-radius:10px; border:1px solid var(--ttb-border); background:#fff; padding:6px; overflow:hidden; }
    .ttb-cal-cell.is-today { border-color:var(--ttb-pink); background:rgba(215,33,115,.03); }
    .ttb-cal-cell.is-empty { background:#f9fafb; border-color:transparent; }
    .ttb-cal-daynumber { font-size:12px; font-weight:900; color:var(--ttb-muted); margin-bottom:4px; line-height:1; }
    .ttb-cal-cell.is-today .ttb-cal-daynumber { color:var(--ttb-pink); }
    .ttb-cal-post-chip { border-radius:6px; padding:3px 6px; margin-bottom:3px; font-size:11px; font-weight:700; line-height:1.3; cursor:pointer; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:opacity .15s; }
    .ttb-cal-post-chip:hover { opacity:.8; }
    .ttb-cal-chip-more { font-size:10px; font-weight:700; color:var(--ttb-muted); padding:2px 4px; display:block; }
    .ttb-post-detail-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:99999; padding:16px; }
    .ttb-post-detail { background:#fff; border-radius:20px; padding:28px; max-width:540px; width:100%; max-height:85vh; overflow-y:auto; box-shadow:0 24px 64px rgba(0,0,0,.2); position:relative; animation:ttbModalUp .3s cubic-bezier(.34,1.56,.64,1) both; }
    .ttb-post-detail::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,var(--ttb-pink),#e63a86); border-radius:20px 20px 0 0; }
    .ttb-post-detail-close { position:absolute; top:14px; right:16px; background:#f3f4f6; border:none; border-radius:50%; width:28px; height:28px; font-size:14px; cursor:pointer; line-height:28px; text-align:center; color:var(--ttb-muted); }
    .ttb-post-detail-close:hover { background:#e5e7eb; }
    </style>

    <!-- Modal detalle -->
    <div id="ttb-post-detail-overlay" style="display:none" role="dialog" aria-modal="true">
      <div class="ttb-post-detail">
        <button class="ttb-post-detail-close" onclick="ttbClosePostDetail()">✕</button>
        <div id="ttb-post-detail-body"></div>
      </div>
    </div>

    <div class="ttb-cal-grid">
      <?php
      foreach ($day_names as $dn) echo '<div class="ttb-cal-dayname">' . esc_html($dn) . '</div>';
      for ($blank = 1; $blank < $start_dow; $blank++) echo '<div class="ttb-cal-cell is-empty"></div>';

      for ($day = 1; $day <= $days_in; $day++) {
        $is_today  = $is_cur_month && $day === $today_day;
        $day_posts = $posts_by_day[$day] ?? [];
        echo '<div class="ttb-cal-cell' . ($is_today ? ' is-today' : '') . '">';
        echo '<div class="ttb-cal-daynumber">' . $day . '</div>';

        $shown = 0;
        foreach ($day_posts as $post) {
          if ($shown >= 3) break;
          [$sl,$sbg,$sbc,$sco] = $statuses[$post->status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];

          // Chip: siempre muestra "Post" — sin texto del caption
          echo '<span class="ttb-cal-post-chip"
            style="background:' . $sbg . ';border:1px solid ' . $sbc . ';color:' . $sco . '"
            data-post-id="' . (int)$post->id . '"
            title="' . esc_attr(($post->client_name ?? 'Post') . ' — ' . $sl) . '"
          >Post</span>';
          $shown++;
        }

        $remaining = count($day_posts) - $shown;
        if ($remaining > 0) echo '<span class="ttb-cal-chip-more">+' . $remaining . ' más</span>';
        echo '</div>';
      }

      $total_cells = $start_dow - 1 + $days_in;
      $remainder   = $total_cells % 7;
      if ($remainder > 0) for ($i = 0; $i < (7 - $remainder); $i++) echo '<div class="ttb-cal-cell is-empty"></div>';
      ?>
    </div>

    <script>
    (function(){
      // Delegación de eventos en todos los chips del calendario
      document.querySelectorAll('.ttb-cal-post-chip[data-post-id]').forEach(function(chip) {
        chip.addEventListener('click', function() {
          var postId = chip.getAttribute('data-post-id');
          ttbOpenPostDetail(postId);
        });
      });

      window.ttbOpenPostDetail = function(postId) {
        // Buscar el HTML del post en el store oculto (admin) o en los forms del cliente
        var store = document.getElementById('ttb-post-data-' + postId);

        // Si no está en el store admin, buscar en el store del cliente
        if (!store) {
          store = document.getElementById('ttb-client-post-data-' + postId);
        }

        if (!store) {
          console.warn('No se encontró datos del post ' + postId);
          return;
        }

        var overlay = document.getElementById('ttb-post-detail-overlay');
        var body    = document.getElementById('ttb-post-detail-body');

        // Clonar el nodo para preservar los forms/nonces originales
        body.innerHTML = '';
        var clone = store.cloneNode(true);
        clone.style.display = 'block';
        clone.removeAttribute('id');
        body.appendChild(clone);

        overlay.style.display = 'flex';
        document.addEventListener('keydown', ttbEscPostDetail);
      };

      window.ttbClosePostDetail = function() {
        document.getElementById('ttb-post-detail-overlay').style.display = 'none';
        document.removeEventListener('keydown', ttbEscPostDetail);
      };

      function ttbEscPostDetail(e) { if (e.key === 'Escape') ttbClosePostDetail(); }

      document.getElementById('ttb-post-detail-overlay').addEventListener('click', function(e) {
        if (e.target === this) ttbClosePostDetail();
      });
    })();
    </script>
    <?php
  }

  /* ════════════════════════════════
     RENDER: AUDITORÍA
  ════════════════════════════════ */
  private static function render_audit() {
    global $wpdb;
    $audit_table   = TTB_Social_DB::audit_table();
    $clients_table = TTB_Social_DB::clients_table();
    $catalog       = self::event_catalog();
    $actor_labels  = [
      'admin'  => ['Admin',   '#eff6ff','#1d4ed8'],
      'client' => ['Cliente', '#fdf4ff','#7e22ce'],
      'cron'   => ['Cron',    '#f9fafb','#374151'],
      'system' => ['Sistema', '#f9fafb','#374151'],
    ];

    $f_client  = (int)($_GET['f_client']  ?? 0);
    $f_event   = sanitize_text_field($_GET['f_event']  ?? '');
    $f_actor   = sanitize_text_field($_GET['f_actor']  ?? '');
    $f_from    = sanitize_text_field($_GET['f_from']   ?? '');
    $f_to      = sanitize_text_field($_GET['f_to']     ?? '');
    $f_search  = sanitize_text_field($_GET['f_search'] ?? '');
    $f_page    = max(1, (int)($_GET['f_page'] ?? 1));
    $per_page  = 50;
    $offset    = ($f_page - 1) * $per_page;

    $where = ['1=1']; $params = [];
    if ($f_client) { $where[] = 'a.client_id = %d'; $params[] = $f_client; }
    if ($f_event)  { $where[] = 'a.event = %s';     $params[] = $f_event; }
    if ($f_actor)  { $where[] = 'a.actor = %s';     $params[] = $f_actor; }
    if ($f_from)   { $where[] = 'a.created_at >= %s'; $params[] = $f_from . ' 00:00:00'; }
    if ($f_to)     { $where[] = 'a.created_at <= %s'; $params[] = $f_to . ' 23:59:59'; }
    if ($f_search) {
      $where[] = '(c.name LIKE %s OR a.detail LIKE %s OR a.ip LIKE %s)';
      $like = '%' . $wpdb->esc_like($f_search) . '%';
      $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $total     = $params ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $audit_table a LEFT JOIN $clients_table c ON c.id=a.client_id WHERE $where_sql", ...$params)) : (int)$wpdb->get_var("SELECT COUNT(*) FROM $audit_table a LEFT JOIN $clients_table c ON c.id=a.client_id WHERE $where_sql");
    $qp        = array_merge($params, [$per_page, $offset]);
    $rows      = $wpdb->get_results($wpdb->prepare("SELECT a.*, c.name AS client_name FROM $audit_table a LEFT JOIN $clients_table c ON c.id=a.client_id WHERE $where_sql ORDER BY a.created_at DESC LIMIT %d OFFSET %d", ...$qp));
    $total_pages = max(1, ceil($total / $per_page));
    $clients     = $wpdb->get_results("SELECT id, name FROM $clients_table ORDER BY name ASC");
    $base_url    = home_url('/briefing?section=redes-sociales&sstab=audit');

    echo '<div class="ttb-card"><h3 style="margin:0 0 4px">Auditoría — Redes Sociales</h3>';
    echo '<p class="ttb-muted" style="margin:0 0 20px">Registro completo de actividad.</p>';

    $stats = $wpdb->get_results("SELECT event, COUNT(*) as cnt FROM $audit_table GROUP BY event ORDER BY cnt DESC LIMIT 6");
    if ($stats) {
      echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px">';
      foreach ($stats as $s) {
        [$ev_label] = $catalog[$s->event] ?? [$s->event];
        echo '<div style="background:#f9fafb;border:1px solid var(--ttb-border);border-radius:10px;padding:8px 14px;font-size:13px"><span style="font-weight:900;color:var(--ttb-text)">' . (int)$s->cnt . '</span> <span style="color:var(--ttb-muted)">' . esc_html($ev_label) . '</span></div>';
      }
      echo '</div>';
    }

    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;align-items:end">';
    echo '<input type="hidden" name="section" value="redes-sociales"><input type="hidden" name="sstab" value="audit">';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Cliente</label><select name="f_client" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    foreach ($clients as $c) echo '<option value="' . (int)$c->id . '" ' . selected($f_client, $c->id, false) . '>' . esc_html($c->name) . '</option>';
    echo '</select></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Evento</label><select name="f_event" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    foreach ($catalog as $k => [$label]) echo '<option value="' . esc_attr($k) . '" ' . selected($f_event, $k, false) . '>' . esc_html($label) . '</option>';
    echo '</select></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Actor</label><select name="f_actor" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    foreach ($actor_labels as $k => [$label]) echo '<option value="' . esc_attr($k) . '" ' . selected($f_actor, $k, false) . '>' . esc_html($label) . '</option>';
    echo '</select></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Desde</label><input type="date" name="f_from" class="ttb-input" value="' . esc_attr($f_from) . '" style="font-size:13px"></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Hasta</label><input type="date" name="f_to" class="ttb-input" value="' . esc_attr($f_to) . '" style="font-size:13px"></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Búsqueda</label><input type="text" name="f_search" class="ttb-input" value="' . esc_attr($f_search) . '" placeholder="Nombre, IP..." style="font-size:13px"></div>';
    echo '<div style="display:flex;gap:8px"><button class="ttb-btn" type="submit">Filtrar</button><a href="' . esc_url($base_url) . '" class="ttb-btn ttb-btn--ghost">Limpiar</a></div>';
    echo '</form><p style="margin:14px 0 0;font-size:13px;color:var(--ttb-muted)"><strong>' . number_format($total) . '</strong> registros</p></div>';

    if (!$rows) {
      echo '<div class="ttb-card"><p class="ttb-muted" style="text-align:center;padding:24px 0">No hay registros.</p></div>';
    } else {
      echo '<div class="ttb-card" style="padding:0;overflow:hidden"><div class="ttb-tablewrap"><table class="ttb-table" style="font-size:13px"><thead><tr><th style="width:140px">Fecha y hora</th><th>Evento</th><th style="width:80px">Actor</th><th>Cliente</th><th>Detalle</th><th style="width:100px">IP</th></tr></thead><tbody>';
      foreach ($rows as $row) {
        [$ev_label,$ev_bg,$ev_bc,$ev_color] = $catalog[$row->event] ?? [$row->event,'#f9fafb','#e5e7eb','#374151'];
        [$ac_label,$ac_bg,$ac_color]        = $actor_labels[$row->actor] ?? [$row->actor,'#f9fafb','#374151'];
        $detail_html = '—';
        if ($row->detail) {
          $d = json_decode($row->detail, true);
          if (is_array($d)) { $parts=[]; foreach ($d as $k=>$v) { if(is_array($v))$v=implode(',',$v); $parts[]='<span style="color:var(--ttb-muted)">'.esc_html($k).':</span> <strong>'.esc_html((string)$v).'</strong>'; } $detail_html=implode(' · ',$parts); }
          else $detail_html = esc_html($row->detail);
        }
        $client_link = $row->client_name ? '<a href="' . esc_url($base_url . '&f_client=' . (int)$row->client_id) . '" style="color:var(--ttb-pink);font-weight:700;text-decoration:none">' . esc_html($row->client_name) . '</a>' : '<span style="color:var(--ttb-muted)">—</span>';
        echo '<tr>';
        echo '<td style="white-space:nowrap;color:var(--ttb-muted)">' . esc_html(date_i18n('d/m/Y H:i:s', strtotime($row->created_at))) . '</td>';
        echo '<td><span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;background:' . $ev_bg . ';border:1px solid ' . $ev_bc . ';color:' . $ev_color . ';white-space:nowrap">' . esc_html($ev_label) . '</span></td>';
        echo '<td><span style="display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:' . $ac_bg . ';color:' . $ac_color . '">' . esc_html($ac_label) . '</span></td>';
        echo '<td>' . $client_link . '</td>';
        echo '<td style="font-size:12px;max-width:280px">' . $detail_html . '</td>';
        echo '<td style="font-size:12px;color:var(--ttb-muted);font-family:monospace">' . esc_html($row->ip ?? '—') . '</td>';
        echo '</tr>';
      }
      echo '</tbody></table></div></div>';
    }

    if ($total_pages > 1) {
      echo '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:16px">';
      if ($f_page > 1) echo '<a href="' . esc_url(add_query_arg(array_filter(['section'=>'redes-sociales','sstab'=>'audit','f_client'=>$f_client?:'','f_event'=>$f_event,'f_actor'=>$f_actor,'f_from'=>$f_from,'f_to'=>$f_to,'f_search'=>$f_search,'f_page'=>$f_page-1]), home_url('/briefing'))) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">← Anterior</a>';
      for ($p = max(1,$f_page-3); $p <= min($total_pages,$f_page+3); $p++) echo '<a href="' . esc_url(add_query_arg(array_filter(['section'=>'redes-sociales','sstab'=>'audit','f_client'=>$f_client?:'','f_event'=>$f_event,'f_actor'=>$f_actor,'f_from'=>$f_from,'f_to'=>$f_to,'f_search'=>$f_search,'f_page'=>$p>1?$p:'']), home_url('/briefing'))) . '" class="' . ($p===$f_page?'ttb-btn ttb-btn--sm':'ttb-btn ttb-btn--ghost ttb-btn--sm') . '">' . $p . '</a>';
      if ($f_page < $total_pages) echo '<a href="' . esc_url(add_query_arg(array_filter(['section'=>'redes-sociales','sstab'=>'audit','f_client'=>$f_client?:'','f_event'=>$f_event,'f_actor'=>$f_actor,'f_from'=>$f_from,'f_to'=>$f_to,'f_search'=>$f_search,'f_page'=>$f_page+1]), home_url('/briefing'))) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Siguiente →</a>';
      echo '</div>';
    }
  }

  /* ════════════════════════════════
     RENDER: CONFIGURACIÓN
  ════════════════════════════════ */
  private static function render_settings() {
    $action_url    = self::action_url('settings');
    $notify_social = (string)get_option('ttb_social_notify_social', 'comunicacion@tictac-comunicacion.es');
    $notify_hola   = (string)get_option('ttb_social_notify_hola',   'hola@tictac-comunicacion.es');
    $resend_days   = (int)get_option('ttb_social_resend_days',      2);
    $max_resends   = (int)get_option('ttb_social_max_resends',      3);
    $max_mb        = (int)get_option('ttb_social_max_filesize',     50);
    $approval_subj = (string)get_option('ttb_social_approval_subject', 'Creatividad lista para revisar — TicTac Comunicación');
    $approval_note = (string)get_option('ttb_social_approval_note', '');

    echo '<div class="ttb-card"><h3>Configuración — Redes Sociales</h3></div>';
    echo '<form method="post" action="' . $action_url . '">';
    wp_nonce_field('ttb_social_settings');
    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Emails internos</h4><div class="ttb-grid2">';
    echo '<div><label>Email departamento redes</label><input class="ttb-input" type="email" name="ttb_social_notify_social" value="' . esc_attr($notify_social) . '"></div>';
    echo '<div><label>Email general (hola@)</label><input class="ttb-input" type="email" name="ttb_social_notify_hola" value="' . esc_attr($notify_hola) . '"></div>';
    echo '</div></div>';
    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Recordatorios automáticos</h4><div class="ttb-grid2">';
    echo '<div><label>Días entre recordatorios</label><input class="ttb-input" type="number" name="ttb_social_resend_days" value="' . $resend_days . '" min="1" max="30"><small class="ttb-muted">Si el cliente no aprueba, se reenvía cada N días.</small></div>';
    echo '<div><label>Máximo de recordatorios</label><input class="ttb-input" type="number" name="ttb_social_max_resends" value="' . $max_resends . '" min="0" max="20"><small class="ttb-muted">0 = sin límite.</small></div>';
    echo '</div></div>';
    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Contenido del cliente</h4>';
    echo '<div><label>Tamaño máximo por archivo (MB)</label><input class="ttb-input" type="number" name="ttb_social_max_filesize" value="' . $max_mb . '" min="1" max="200"></div></div>';
    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Email de aprobación al cliente</h4><div class="ttb-formgrid">';
    echo '<div><label>Asunto</label><input class="ttb-input" type="text" name="ttb_social_approval_subject" value="' . esc_attr($approval_subj) . '"></div>';
    echo '<div><label>Nota extra (opcional)</label><textarea class="ttb-textarea" name="ttb_social_approval_note" style="min-height:70px">' . esc_textarea($approval_note) . '</textarea></div>';
    echo '</div></div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_social_settings" value="1">Guardar configuración</button></div>';
    echo '</form>';
  }

  /* ════════════════════════════════
     HELPERS
  ════════════════════════════════ */
  private static function sanitize_emails($raw) {
    if (!is_array($raw)) $raw = [$raw];
    return array_values(array_filter(array_map(fn($e) => sanitize_email(trim($e)), $raw), 'is_email'));
  }

  private static function email_js() {
    ?>
    <script>
    (function(){
      function initEmailWidget(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var addBtn = document.querySelector('[data-target="' + containerId + '"]');
        if (addBtn) {
          addBtn.addEventListener('click', function() {
            var row = document.createElement('div');
            row.className = 'ttb-sc-email-row';
            row.style.cssText = 'display:flex;gap:8px;align-items:center';
            row.innerHTML = '<input class="ttb-input" type="email" name="sc_emails[]" placeholder="email@cliente.com" required style="flex:1"><button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-sc-remove-email">✕</button>';
            container.appendChild(row);
            update(container);
          });
        }
        container.addEventListener('click', function(e) {
          if (e.target.classList.contains('ttb-sc-remove-email')) { e.target.closest('.ttb-sc-email-row').remove(); update(container); }
        });
        update(container);
      }
      function update(container) {
        var rows = container.querySelectorAll('.ttb-sc-email-row');
        rows.forEach(function(row) { var btn = row.querySelector('.ttb-sc-remove-email'); if (btn) btn.style.display = (rows.length > 1) ? 'inline-flex' : 'none'; });
      }
      initEmailWidget('ttb-sc-emails-create');
      initEmailWidget('ttb-sc-emails-edit');
    })();
    </script>
    <?php
  }
}