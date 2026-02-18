<?php
if (!defined('ABSPATH')) exit;

class TTB_Admin_UI {

  public static function render() {
    $auth = new TTB_Auth();
    if (!$auth->is_admin()) {
      echo '<div class="ttb-card"><p>No autorizado.</p></div>';
      return;
    }

    $tab = sanitize_text_field($_GET['tab'] ?? 'forms');

    // actions
    self::handle_forms_save();
    self::handle_client_create();
    self::handle_client_edit();
    self::handle_client_delete();
    self::handle_resend_email();

    echo '<div class="ttb-container">';
    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2>Administrador Briefing</h2>';
    echo '</div>';

    echo '<div class="ttb-tabs">';
    self::tab_link('forms',   'Formularios', $tab);
    self::tab_link('clients', 'Clientes',    $tab);
    self::tab_link('answers', 'Respuestas',  $tab);
    echo '</div>';

    if ($tab === 'clients')      self::render_clients();
    elseif ($tab === 'answers')  self::render_answers();
    else                         self::render_forms();

    echo '</div>';
  }

  /* ── Tabs ── */
  private static function tab_link($key, $label, $active) {
    $url = esc_url(add_query_arg(['tab' => $key], home_url('/briefing')));
    $cls = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="'.$cls.'" href="'.$url.'">'.esc_html($label).'</a>';
  }

  /* ── Guardar formularios JSON ── */
  private static function handle_forms_save() {
    if (!isset($_POST['ttb_admin_save_forms'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_forms')) return;

    update_option('ttb_form_design', wp_unslash($_POST['ttb_form_design'] ?? ''));
    update_option('ttb_form_social', wp_unslash($_POST['ttb_form_social'] ?? ''));
    update_option('ttb_form_seo',    wp_unslash($_POST['ttb_form_seo']    ?? ''));
    update_option('ttb_form_web',    wp_unslash($_POST['ttb_form_web']    ?? ''));

    (new TTB_Auth())->flash('success', 'Formularios guardados.');
    wp_safe_redirect(add_query_arg(['tab' => 'forms'], home_url('/briefing')));
    exit;
  }

  /* ── Crear cliente ── */
  private static function handle_client_create() {
    if (!isset($_POST['ttb_admin_create_client'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_clients')) return;

    $name     = sanitize_text_field($_POST['client_name']  ?? '');
    $email    = sanitize_email($_POST['client_email']       ?? '');
    $services = array_map('sanitize_text_field', (array)($_POST['services'] ?? []));

    if (!$name || !$email) {
      (new TTB_Auth())->flash('error', 'Nombre y email son obligatorios.');
      wp_safe_redirect(add_query_arg(['tab' => 'clients'], home_url('/briefing')));
      exit;
    }

    $username = sanitize_user($name, true);
    if (!$username) $username = 'cliente';
    $password = $name;

    global $wpdb;
    $table = TTB_DB::clients_table();

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username=%s", $username));
    if ($exists) $username .= '-' . wp_generate_password(4, false, false);

    $wpdb->insert($table, [
      'name'      => $name,
      'email'     => $email,
      'username'  => $username,
      'pass_hash' => password_hash($password, PASSWORD_DEFAULT),
      'services'  => wp_json_encode(array_values($services)),
      'status'    => 'pendiente',
      'created_at'=> TTB_DB::now(),
      'updated_at'=> TTB_DB::now(),
    ]);

    (new TTB_Mailer())->send_client_access($name, $email, $username, $password, $services);

    (new TTB_Auth())->flash('success', 'Cliente creado y email enviado.');
    wp_safe_redirect(add_query_arg(['tab' => 'clients'], home_url('/briefing')));
    exit;
  }

  /* ── Editar cliente ── */
  private static function handle_client_edit() {
    if (!isset($_POST['ttb_admin_edit_client'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_edit_client')) return;

    $client_id = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    $name     = sanitize_text_field($_POST['client_name']  ?? '');
    $email    = sanitize_email($_POST['client_email']       ?? '');
    $services = array_map('sanitize_text_field', (array)($_POST['services'] ?? []));

    if (!$name || !$email) {
      (new TTB_Auth())->flash('error', 'Nombre y email son obligatorios.');
      wp_safe_redirect(add_query_arg(['tab' => 'clients'], home_url('/briefing')));
      exit;
    }

    global $wpdb;
    $table = TTB_DB::clients_table();

    $wpdb->update($table, [
      'name'       => $name,
      'email'      => $email,
      'services'   => wp_json_encode(array_values($services)),
      'updated_at' => TTB_DB::now(),
    ], ['id' => $client_id]);

    (new TTB_Auth())->flash('success', 'Cliente actualizado correctamente.');
    wp_safe_redirect(add_query_arg(['tab' => 'clients'], home_url('/briefing')));
    exit;
  }

  /* ── Eliminar cliente ── */
  private static function handle_client_delete() {
    if (!isset($_POST['ttb_admin_delete_client'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_delete_client')) return;

    $client_id = (int)($_POST['client_id'] ?? 0);
    if (!$client_id) return;

    global $wpdb;
    $wpdb->delete(TTB_DB::clients_table(), ['id' => $client_id]);
    $wpdb->delete(TTB_DB::answers_table(), ['client_id' => $client_id]);

    (new TTB_Auth())->flash('success', 'Cliente eliminado.');
    wp_safe_redirect(add_query_arg(['tab' => 'clients'], home_url('/briefing')));
    exit;
  }

  /* ── Reenviar email ── */
  private static function handle_resend_email() {
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

    (new TTB_Mailer())->send_client_access((string)$c->name, (string)$c->email, (string)$c->username, $password, $services);

    (new TTB_Auth())->flash('success', 'Email reenviado.');
    wp_safe_redirect(add_query_arg(['tab' => 'clients'], home_url('/briefing')));
    exit;
  }

  /* ════════════════════════════════
     RENDER: FORMULARIOS
  ════════════════════════════════ */
  private static function render_forms() {
    $design = (string)get_option('ttb_form_design', '');
    $social = (string)get_option('ttb_form_social', '');
    $seo    = (string)get_option('ttb_form_seo',    '');
    $web    = (string)get_option('ttb_form_web',    '');

    echo '<div class="ttb-card"><h3>Formularios (JSON)</h3><p class="ttb-muted">Edita campos por servicio. Formato: lista de objetos con id/label/type/required/options.</p></div>';

    echo '<form method="post" class="ttb-card">';
    wp_nonce_field('ttb_admin_forms');
    echo '<div class="ttb-grid2">';
    self::json_box('Diseño', 'ttb_form_design', $design);
    self::json_box('Redes',  'ttb_form_social', $social);
    self::json_box('SEO',    'ttb_form_seo',    $seo);
    self::json_box('Web',    'ttb_form_web',    $web);
    echo '</div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_admin_save_forms" value="1">Guardar formularios</button></div>';
    echo '</form>';
  }

  private static function json_box($title, $name, $val) {
    echo '<div class="ttb-jsonbox">';
    echo '<h4>'.esc_html($title).'</h4>';
    echo '<textarea name="'.esc_attr($name).'" class="ttb-textarea">'.esc_textarea($val).'</textarea>';
    echo '</div>';
  }

  /* ════════════════════════════════
     RENDER: CLIENTES
  ════════════════════════════════ */
  private static function render_clients() {
    global $wpdb;
    $table   = TTB_DB::clients_table();
    $clients = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");

    // ¿Hay un cliente en modo edición?
    $edit_id = (int)($_GET['edit_client'] ?? 0);
    $edit_c  = null;
    if ($edit_id) {
      $edit_c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id));
    }

    /* ── Formulario crear ── */
    echo '<div class="ttb-card"><h3>Crear cliente</h3></div>';
    echo '<form method="post" class="ttb-card">';
    wp_nonce_field('ttb_admin_clients');
    echo '<div class="ttb-grid2">';
    echo '<div><label>Nombre</label><input class="ttb-input" type="text" name="client_name" required></div>';
    echo '<div><label>Email</label><input class="ttb-input" type="email" name="client_email" required></div>';
    echo '</div>';
    echo '<div style="margin-top:10px"><label>Servicios</label><div class="ttb-checks">';
    foreach (['design' => 'Diseño', 'social' => 'Redes', 'seo' => 'SEO', 'web' => 'Web'] as $k => $v) {
      echo '<label class="ttb-check"><input type="checkbox" name="services[]" value="'.esc_attr($k).'"> '.esc_html($v).'</label>';
    }
    echo '</div></div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_admin_create_client" value="1">Crear y enviar acceso</button></div>';
    echo '</form>';

    /* ── Modal de edición (si procede) ── */
    if ($edit_c) {
      $edit_services = json_decode((string)$edit_c->services, true);
      if (!is_array($edit_services)) $edit_services = [];
      $cancel_url = esc_url(add_query_arg(['tab' => 'clients'], home_url('/briefing')));

      echo '<div class="ttb-modal-overlay ttb-edit-modal-overlay" id="ttbEditModal" role="dialog" aria-modal="true" aria-labelledby="ttbEditTitle">';
      echo '<div class="ttb-modal ttb-edit-modal">';
      echo '<h3 class="ttb-edit-modal__title" id="ttbEditTitle">✏️ Editar cliente</h3>';

      echo '<form method="post" class="ttb-formgrid">';
      wp_nonce_field('ttb_admin_edit_client');
      echo '<input type="hidden" name="client_id" value="'.(int)$edit_c->id.'">';

      echo '<div class="ttb-grid2">';
      echo '<div><label>Nombre</label><input class="ttb-input" type="text" name="client_name" value="'.esc_attr($edit_c->name).'" required></div>';
      echo '<div><label>Email</label><input class="ttb-input" type="email" name="client_email" value="'.esc_attr($edit_c->email).'" required></div>';
      echo '</div>';

      echo '<div style="margin-top:10px"><label>Servicios</label><div class="ttb-checks">';
      foreach (['design' => 'Diseño', 'social' => 'Redes', 'seo' => 'SEO', 'web' => 'Web'] as $k => $v) {
        $checked = in_array($k, $edit_services, true) ? 'checked' : '';
        echo '<label class="ttb-check"><input type="checkbox" name="services[]" value="'.esc_attr($k).'" '.$checked.'> '.esc_html($v).'</label>';
      }
      echo '</div></div>';

      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="'.$cancel_url.'" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_admin_edit_client" value="1">Guardar cambios</button>';
      echo '</div>';

      echo '</form></div></div>';

      // Abrir modal automáticamente
      echo '<script>document.getElementById("ttbEditModal").style.display="flex";</script>';
    }

    /* ── Listado ── */
    echo '<div class="ttb-card"><h3>Listado</h3>';
    if (!$clients) { echo '<p class="ttb-muted">No hay clientes aún.</p></div>'; return; }

    echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
      <th>Cliente</th><th>Email</th><th>Usuario</th><th>Servicios</th><th>Estado</th><th></th>
    </tr></thead><tbody>';

    foreach ($clients as $c) {
      $sv = json_decode((string)$c->services, true);
      if (!is_array($sv)) $sv = [];

      $edit_url = esc_url(add_query_arg(['tab' => 'clients', 'edit_client' => (int)$c->id], home_url('/briefing')));

      echo '<tr>';
      echo '<td><strong>'.esc_html($c->name).'</strong></td>';
      echo '<td>'.esc_html($c->email).'</td>';
      echo '<td>'.esc_html($c->username).'</td>';
      echo '<td>'.esc_html($sv ? implode(', ', $sv) : '—').'</td>';
      echo '<td>';
      $status_labels = ['pendiente'=>'Pendiente','en_progreso'=>'En progreso','enviado'=>'Enviado'];
      $status_cls    = ['pendiente'=>'ttb-status--pending','en_progreso'=>'ttb-status--progress','enviado'=>'ttb-status--sent'];
      $sl  = $status_labels[$c->status] ?? esc_html($c->status);
      $sc  = $status_cls[$c->status]    ?? '';
      echo '<span class="ttb-status '.$sc.'">'.esc_html($sl).'</span>';
      echo '</td>';
      echo '<td>
        <div class="ttb-row-actions">';

          /* Editar */
          echo '<a href="'.$edit_url.'" class="ttb-btn ttb-btn--ghost ttb-btn--sm" title="Editar">✏️ Editar</a>';

          /* Reenviar email */
          echo '<form method="post" style="margin:0">';
          wp_nonce_field('ttb_admin_resend');
          echo '<input type="hidden" name="client_id" value="'.(int)$c->id.'">
          <button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_admin_resend" value="1" title="Reenviar email">📧 Email</button>
          </form>';

          /* Eliminar — con confirmación JS */
          echo '<form method="post" style="margin:0"
                      onsubmit="return confirm(\'¿Eliminar a '.esc_js($c->name).'? Se borrarán también todas sus respuestas. Esta acción no se puede deshacer.\')">';
          wp_nonce_field('ttb_admin_delete_client');
          echo '<input type="hidden" name="client_id" value="'.(int)$c->id.'">
          <button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_admin_delete_client" value="1" title="Eliminar">🗑️ Eliminar</button>
          </form>';

      echo '</div></td>';
      echo '</tr>';
    }

    echo '</tbody></table></div></div>';
  }

  /* ════════════════════════════════
     RENDER: RESPUESTAS
  ════════════════════════════════ */
  private static function render_answers() {
    global $wpdb;
    $clients_table = TTB_DB::clients_table();
    $clients = $wpdb->get_results("SELECT id,name,email,status FROM $clients_table ORDER BY updated_at DESC LIMIT 200");

    echo '<div class="ttb-card"><h3>Respuestas</h3><p class="ttb-muted">Selecciona un cliente para ver sus respuestas por servicio.</p></div>';

    echo '<div class="ttb-card"><div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
      <th>Cliente</th><th>Email</th><th>Estado</th><th>Ver</th>
    </tr></thead><tbody>';

    foreach ($clients as $c) {
      $url = esc_url(add_query_arg(['tab' => 'answers', 'client' => (int)$c->id], home_url('/briefing')));
      echo '<tr>';
      echo '<td><strong>'.esc_html($c->name).'</strong></td>';
      echo '<td>'.esc_html($c->email).'</td>';
      echo '<td>'.esc_html($c->status).'</td>';
      echo '<td><a class="ttb-btn ttb-btn--ghost ttb-btn--sm" href="'.$url.'">Ver</a></td>';
      echo '</tr>';
    }

    echo '</tbody></table></div></div>';

    $client_id = (int)($_GET['client'] ?? 0);
    if (!$client_id) return;

    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients_table WHERE id=%d", $client_id));
    if (!$client) return;

    $services = json_decode((string)$client->services, true);
    if (!is_array($services)) $services = [];

    echo '<div class="ttb-card"><h3>Detalle: '.esc_html($client->name).'</h3></div>';

    foreach ($services as $svc) {
      $schema  = TTB_Forms::get_schema($svc);
      $payload = TTB_Forms::get_client_answers($client_id, $svc);
      $answers = $payload['answers'];
      $sent    = (int)$payload['sent'];

      echo '<div class="ttb-card">';
      echo '<h4>'.esc_html(strtoupper($svc)).' '.($sent ? '<span class="ttb-pill">ENVIADO</span>' : '<span class="ttb-pill ttb-pill--draft">BORRADOR</span>').'</h4>';

      if (!$answers) { echo '<p class="ttb-muted">Sin respuestas todavía.</p></div>'; continue; }

      $drive_url = $answers['ttb_drive_url'] ?? '';
      if ($drive_url) {
        echo '<a href="'.esc_url($drive_url).'" target="_blank" rel="noopener" class="ttb-btn ttb-btn--ghost ttb-btn--sm" style="margin-bottom:12px;display:inline-flex;align-items:center;gap:6px">📄 Ver en Google Drive</a>';
      }
      echo '<div class="ttb-qa">';
      foreach ($schema as $f) {
        $id    = $f['id'] ?? ''; if (!$id) continue;
        $label = $f['label'] ?? $id;
        $val   = $answers[$id] ?? '';
        if (is_array($val)) $val = implode(', ', $val);
        echo '<div class="ttb-q"><div class="ttb-q__l">'.esc_html($label).'</div><div class="ttb-q__a">'.nl2br(esc_html((string)$val)).'</div></div>';
      }
      echo '</div></div>';
    }
  }
}