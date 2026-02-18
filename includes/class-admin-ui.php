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
    self::handle_resend_email();

    echo '<div class="ttb-container">';
    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2>Administrador Briefing</h2>';
    echo '<p class="ttb-muted">Gestión completa desde /briefing (sin wp-admin).</p>';
    echo '</div>';

    echo '<div class="ttb-tabs">';
    self::tab_link('forms','Formularios',$tab);
    self::tab_link('clients','Clientes',$tab);
    self::tab_link('answers','Respuestas',$tab);
    echo '</div>';

    if ($tab === 'clients') self::render_clients();
    else if ($tab === 'answers') self::render_answers();
    else self::render_forms();

    echo '</div>';
  }

  private static function tab_link($key,$label,$active) {
    $url = esc_url(add_query_arg(['tab'=>$key], home_url('/briefing')));
    $cls = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="'.$cls.'" href="'.$url.'">'.esc_html($label).'</a>';
  }

  private static function handle_forms_save() {
    if (!isset($_POST['ttb_admin_save_forms'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_forms')) return;

    update_option('ttb_form_design', wp_unslash($_POST['ttb_form_design'] ?? ''));
    update_option('ttb_form_social', wp_unslash($_POST['ttb_form_social'] ?? ''));
    update_option('ttb_form_seo', wp_unslash($_POST['ttb_form_seo'] ?? ''));
    update_option('ttb_form_web', wp_unslash($_POST['ttb_form_web'] ?? ''));

    (new TTB_Auth())->flash('success', 'Formularios guardados.');
    wp_safe_redirect(add_query_arg(['tab'=>'forms'], home_url('/briefing')));
    exit;
  }

  private static function handle_client_create() {
    if (!isset($_POST['ttb_admin_create_client'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_admin_clients')) return;

    $name = sanitize_text_field($_POST['client_name'] ?? '');
    $email = sanitize_email($_POST['client_email'] ?? '');
    $services = array_map('sanitize_text_field', (array)($_POST['services'] ?? []));

    if (!$name || !$email) {
      (new TTB_Auth())->flash('error', 'Nombre y email son obligatorios.');
      wp_safe_redirect(add_query_arg(['tab'=>'clients'], home_url('/briefing')));
      exit;
    }

    $username = sanitize_user($name, true);
    if (!$username) $username = 'cliente';
    $password = $name; // tal como pediste

    global $wpdb;
    $table = TTB_DB::clients_table();

    // evitar duplicado
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username=%s", $username));
    if ($exists) $username .= '-' . wp_generate_password(4, false, false);

    $wpdb->insert($table, [
      'name' => $name,
      'email' => $email,
      'username' => $username,
      'pass_hash' => password_hash($password, PASSWORD_DEFAULT),
      'services' => wp_json_encode(array_values($services)),
      'status' => 'pendiente',
      'created_at' => TTB_DB::now(),
      'updated_at' => TTB_DB::now(),
    ]);

    $client_id = (int)$wpdb->insert_id;

    // email
    (new TTB_Mailer())->send_client_access($name, $email, $username, $password, $services);

    (new TTB_Auth())->flash('success', 'Cliente creado y email enviado.');
    wp_safe_redirect(add_query_arg(['tab'=>'clients'], home_url('/briefing')));
    exit;
  }

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

    // password no se puede recuperar (hash). Según tu norma, es el nombre del cliente:
    $password = (string)$c->name;

    (new TTB_Mailer())->send_client_access((string)$c->name, (string)$c->email, (string)$c->username, $password, $services);

    (new TTB_Auth())->flash('success', 'Email reenviado.');
    wp_safe_redirect(add_query_arg(['tab'=>'clients'], home_url('/briefing')));
    exit;
  }

  private static function render_forms() {
    $design = (string)get_option('ttb_form_design','');
    $social = (string)get_option('ttb_form_social','');
    $seo    = (string)get_option('ttb_form_seo','');
    $web    = (string)get_option('ttb_form_web','');

    echo '<div class="ttb-card"><h3>Formularios (JSON)</h3><p class="ttb-muted">Edita campos por servicio. Formato: lista de objetos con id/label/type/required/options.</p></div>';

    echo '<form method="post" class="ttb-card">';
    wp_nonce_field('ttb_admin_forms');
    echo '<div class="ttb-grid2">';
    self::json_box('Diseño','ttb_form_design',$design);
    self::json_box('Redes','ttb_form_social',$social);
    self::json_box('SEO','ttb_form_seo',$seo);
    self::json_box('Web','ttb_form_web',$web);
    echo '</div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_admin_save_forms" value="1">Guardar formularios</button></div>';
    echo '</form>';
  }

  private static function json_box($title,$name,$val) {
    echo '<div class="ttb-jsonbox">';
    echo '<h4>'.esc_html($title).'</h4>';
    echo '<textarea name="'.esc_attr($name).'" class="ttb-textarea">'.esc_textarea($val).'</textarea>';
    echo '</div>';
  }

  private static function render_clients() {
    global $wpdb;
    $table = TTB_DB::clients_table();
    $clients = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");

    echo '<div class="ttb-card"><h3>Crear cliente</h3></div>';

    echo '<form method="post" class="ttb-card">';
    wp_nonce_field('ttb_admin_clients');
    echo '<div class="ttb-grid2">';
    echo '<div><label>Nombre</label><input class="ttb-input" type="text" name="client_name" required></div>';
    echo '<div><label>Email</label><input class="ttb-input" type="email" name="client_email" required></div>';
    echo '</div>';

    echo '<div style="margin-top:10px"><label>Servicios</label><div class="ttb-checks">';
    foreach (['design'=>'Diseño','social'=>'Redes','seo'=>'SEO','web'=>'Web'] as $k=>$v) {
      echo '<label class="ttb-check"><input type="checkbox" name="services[]" value="'.esc_attr($k).'"> '.esc_html($v).'</label>';
    }
    echo '</div></div>';

    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_admin_create_client" value="1">Crear y enviar acceso</button></div>';
    echo '</form>';

    echo '<div class="ttb-card"><h3>Listado</h3>';
    if (!$clients) { echo '<p class="ttb-muted">No hay clientes aún.</p></div>'; return; }

    echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
      <th>Cliente</th><th>Email</th><th>Usuario</th><th>Servicios</th><th>Estado</th><th></th>
    </tr></thead><tbody>';

    foreach ($clients as $c) {
      $sv = json_decode((string)$c->services, true); if (!is_array($sv)) $sv=[];
      echo '<tr>';
      echo '<td><strong>'.esc_html($c->name).'</strong></td>';
      echo '<td>'.esc_html($c->email).'</td>';
      echo '<td>'.esc_html($c->username).'</td>';
      echo '<td>'.esc_html($sv ? implode(', ', $sv) : '—').'</td>';
      echo '<td>'.esc_html($c->status).'</td>';
      echo '<td>
        <form method="post" style="margin:0">';
          wp_nonce_field('ttb_admin_resend');
          echo '<input type="hidden" name="client_id" value="'.(int)$c->id.'">
          <button class="ttb-btn ttb-btn--ghost" name="ttb_admin_resend" value="1" type="submit">Reenviar email</button>
        </form>
      </td>';
      echo '</tr>';
    }

    echo '</tbody></table></div></div>';
  }

  private static function render_answers() {
    global $wpdb;
    $clients_table = TTB_DB::clients_table();
    $clients = $wpdb->get_results("SELECT id,name,email,status FROM $clients_table ORDER BY updated_at DESC LIMIT 200");

    echo '<div class="ttb-card"><h3>Respuestas</h3><p class="ttb-muted">Selecciona un cliente para ver sus respuestas por servicio.</p></div>';

    echo '<div class="ttb-card"><div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
      <th>Cliente</th><th>Email</th><th>Estado</th><th>Ver</th>
    </tr></thead><tbody>';

    foreach ($clients as $c) {
      $url = esc_url(add_query_arg(['tab'=>'answers','client'=>(int)$c->id], home_url('/briefing')));
      echo '<tr>';
      echo '<td><strong>'.esc_html($c->name).'</strong></td>';
      echo '<td>'.esc_html($c->email).'</td>';
      echo '<td>'.esc_html($c->status).'</td>';
      echo '<td><a class="ttb-btn ttb-btn--ghost" href="'.$url.'">Ver</a></td>';
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
      $schema = TTB_Forms::get_schema($svc);
      $payload = TTB_Forms::get_client_answers($client_id, $svc);
      $answers = $payload['answers'];
      $sent = (int)$payload['sent'];

      echo '<div class="ttb-card">';
      echo '<h4>'.esc_html(strtoupper($svc)).' '.($sent ? '<span class="ttb-pill">ENVIADO</span>' : '<span class="ttb-pill ttb-pill--draft">BORRADOR</span>').'</h4>';

      if (!$answers) {
        echo '<p class="ttb-muted">Sin respuestas todavía.</p></div>';
        continue;
      }

      echo '<div class="ttb-qa">';
      foreach ($schema as $f) {
        $id = $f['id'] ?? ''; if (!$id) continue;
        $label = $f['label'] ?? $id;
        $val = $answers[$id] ?? '';
        if (is_array($val)) $val = implode(', ', $val);
        echo '<div class="ttb-q"><div class="ttb-q__l">'.esc_html($label).'</div><div class="ttb-q__a">'.nl2br(esc_html((string)$val)).'</div></div>';
      }
      echo '</div></div>';
    }
  }
}
