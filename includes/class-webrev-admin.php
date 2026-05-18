<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebRev_Admin')) return;

/**
 * TTB_WebRev_Admin
 * Panel de administración para el módulo Revisiones Diseños
 * v2: campo title en proyectos
 */
class TTB_WebRev_Admin {

  private static $flash = null;

  public static function event_catalog() {
    return [
      'project_created'       => ['📁 Proyecto creado',            '#ecfdf5', '#6ee7b7', '#065f46'],
      'project_updated'       => ['✏️ Proyecto editado',            '#eff6ff', '#bfdbfe', '#1d4ed8'],
      'project_deleted'       => ['🗑️ Proyecto eliminado',          '#fff1f2', '#fecdd3', '#be123c'],
      'email_invitation_sent' => ['📧 Invitación enviada',          '#fdf4ff', '#e9d5ff', '#7e22ce'],
      'email_accepted_sent'   => ['📧 Email aceptación enviado',    '#ecfdf5', '#a7f3d0', '#065f46'],
      'email_changes_sent'    => ['📧 Email cambios enviado',       '#fffbeb', '#fde68a', '#92400e'],
      'client_view'           => ['👁️ Cliente vio el diseño',       '#f0f9ff', '#bae6fd', '#0369a1'],
      'design_accepted'       => ['✅ Diseño aceptado',             '#ecfdf5', '#6ee7b7', '#065f46'],
      'changes_requested'     => ['✏️ Cambios solicitados',         '#fffbeb', '#fcd34d', '#92400e'],
      'nonce_failed'          => ['⚠️ Nonce inválido',              '#fff1f2', '#fecdd3', '#be123c'],
      'invalid_token_access'  => ['🚫 Token inválido',              '#fff1f2', '#fecdd3', '#be123c'],
      'cron_reminder_sent'    => ['⏰ Recordatorio cron enviado',   '#f5f3ff', '#ddd6fe', '#5b21b6'],
      'admin_chat_message'    => ['💬 Respuesta enviada al cliente','#eff6ff', '#bfdbfe', '#1d4ed8'],
      'client_chat_message'   => ['💬 Mensaje del cliente',         '#fdf4ff', '#f9a8d4', '#9d174d'],
    ];
  }

  public static function render() {
    $tab = sanitize_text_field($_GET['wrtab'] ?? 'projects');

    self::handle_project_create($tab);
    self::handle_project_edit($tab);
    self::handle_project_delete($tab);
    self::handle_resend($tab);
    self::handle_chat_reply($tab);
    self::handle_settings_save($tab);

    if (self::$flash) {
      $cls = self::$flash['type'] === 'success' ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html(self::$flash['text']) . '</div>';
    }

    echo '<div class="ttb-tabs">';
    self::tab_link('projects',  'Proyectos',     $tab);
    self::tab_link('revisions', 'Revisiones',    $tab);
    self::tab_link('audit',     'Auditoría',     $tab);
    self::tab_link('settings',  'Configuración', $tab);
    echo '</div>';

    switch ($tab) {
      case 'revisions': self::render_revisions(); break;
      case 'audit':     self::render_audit();     break;
      case 'settings':  self::render_settings();  break;
      default:          self::render_projects();  break;
    }
  }

  private static function tab_link($key, $label, $active) {
    $icon_map = [
      'projects'  => 'projects',
      'revisions' => 'revisions',
      'audit'     => 'audit',
      'settings'  => 'settings',
    ];
    $icon = ttb_icon($icon_map[$key] ?? '');
    $url  = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=' . $key));
    $cls  = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . $url . '">' . $icon . esc_html($label) . '</a>';
  }

  private static function set_flash($type, $text) {
    self::$flash = ['type' => $type, 'text' => $text];
  }

  /* ════════════════════════════════
     ACCIONES POST
  ════════════════════════════════ */

  private static function handle_project_create(&$tab) {
    if (!isset($_POST['ttb_wr_create'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_create')) return;

    $central_client_id = (int)($_POST['wr_client_id']      ?? 0);
    $figma_url         = esc_url_raw($_POST['wr_figma']         ?? '');
    $figma_url_mobile  = esc_url_raw($_POST['wr_figma_mobile']  ?? '');
    $title             = sanitize_text_field($_POST['wr_title']  ?? '');
    $notify_seo        = !empty($_POST['wr_notify_seo']) ? 1 : 0;

    if (!$central_client_id || !$figma_url) {
      self::set_flash('error', 'Selecciona un cliente y proporciona el enlace Figma (desktop).');
      $tab = 'projects';
      return;
    }

    global $wpdb;
    $central = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", $central_client_id
    ));
    if (!$central) {
      self::set_flash('error', 'Cliente no encontrado.');
      $tab = 'projects';
      return;
    }

    $name   = (string)$central->name;
    $emails = json_decode((string)($central->emails ?? ''), true) ?: [$central->email];

    $table = TTB_WebRev_DB::projects_table();
    $token = TTB_WebRev_DB::generate_token();

    $wpdb->insert($table, [
      'name'             => $name,
      'title'            => $title ?: null,
      'emails'           => wp_json_encode(array_values($emails)),
      'figma_url'        => $figma_url,
      'figma_url_mobile' => $figma_url_mobile ?: null,
      'token'            => $token,
      'status'           => 'pending',
      'notify_seo'       => $notify_seo,
      'last_notified'    => TTB_WebRev_DB::now(),
      'notif_count'      => 1,
      'created_at'       => TTB_WebRev_DB::now(),
      'updated_at'       => TTB_WebRev_DB::now(),
    ]);

    $new_id  = (int)$wpdb->insert_id;
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $new_id));

    if ($project) {
      (new TTB_WebRev_Mailer())->send_review_invitation($project);
      TTB_WebRev_DB::log($new_id, 'project_created', 'admin', [
        'name'       => $name,
        'title'      => $title,
        'emails'     => $emails,
        'figma_url'  => $figma_url,
        'notify_seo' => $notify_seo,
      ]);
      TTB_WebRev_DB::log($new_id, 'email_invitation_sent', 'admin', [
        'emails'  => $emails,
        'trigger' => 'project_created',
      ]);
    }

    self::set_flash('success', 'Proyecto creado y email enviado.');
    $tab = 'projects';
  }

  private static function handle_project_edit(&$tab) {
    if (!isset($_POST['ttb_wr_edit'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_edit')) return;

    $id               = (int)($_POST['wr_id']           ?? 0);
    $name             = sanitize_text_field($_POST['wr_name']          ?? '');
    $title            = sanitize_text_field($_POST['wr_title']         ?? '');
    $emails           = self::sanitize_emails($_POST['wr_emails']       ?? []);
    $figma_url        = esc_url_raw($_POST['wr_figma']                  ?? '');
    $figma_url_mobile = esc_url_raw($_POST['wr_figma_mobile']           ?? '');
    $notify_seo       = !empty($_POST['wr_notify_seo']) ? 1 : 0;

    if (!$id || !$name || !$emails || !$figma_url) {
      self::set_flash('error', 'Nombre, emails y el enlace Figma (desktop) son obligatorios.');
      $tab = 'projects';
      return;
    }

    global $wpdb;
    $wpdb->update(TTB_WebRev_DB::projects_table(), [
      'name'             => $name,
      'title'            => $title ?: null,
      'emails'           => wp_json_encode(array_values($emails)),
      'figma_url'        => $figma_url,
      'figma_url_mobile' => $figma_url_mobile ?: null,
      'notify_seo'       => $notify_seo,
      'updated_at'       => TTB_WebRev_DB::now(),
    ], ['id' => $id]);

    TTB_WebRev_DB::log($id, 'project_updated', 'admin', [
      'name'       => $name,
      'title'      => $title,
      'emails'     => $emails,
      'figma_url'  => $figma_url,
      'notify_seo' => $notify_seo,
    ]);

    self::set_flash('success', 'Proyecto actualizado.');
    $tab = 'projects';
  }

  private static function handle_project_delete(&$tab) {
    if (!isset($_POST['ttb_wr_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_delete')) return;

    $id = (int)($_POST['wr_id'] ?? 0);
    if (!$id) return;

    global $wpdb;
    $project = $wpdb->get_row($wpdb->prepare(
      "SELECT name FROM " . TTB_WebRev_DB::projects_table() . " WHERE id=%d", $id
    ));

    TTB_WebRev_DB::log($id, 'project_deleted', 'admin', [
      'name' => $project->name ?? '—',
    ]);

    $wpdb->delete(TTB_WebRev_DB::projects_table(),  ['id' => $id]);
    $wpdb->delete(TTB_WebRev_DB::revisions_table(), ['project_id' => $id]);

    self::set_flash('success', 'Proyecto eliminado.');
    $tab = 'projects';
  }

  private static function handle_resend(&$tab) {
    if (!isset($_POST['ttb_wr_resend'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_resend')) return;

    $id = (int)($_POST['wr_id'] ?? 0);
    if (!$id) return;

    global $wpdb;
    $table   = TTB_WebRev_DB::projects_table();
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
    if (!$project) return;

    (new TTB_WebRev_Mailer())->send_review_invitation($project);

    $new_count = (int)$project->notif_count + 1;
    $wpdb->update($table, [
      'last_notified' => TTB_WebRev_DB::now(),
      'notif_count'   => $new_count,
      'updated_at'    => TTB_WebRev_DB::now(),
    ], ['id' => $id]);

    TTB_WebRev_DB::log($id, 'email_invitation_sent', 'admin', [
      'trigger'     => 'manual_resend',
      'notif_count' => $new_count,
    ]);

    self::set_flash('success', 'Email reenviado.');
    $tab = 'projects';
  }

  private static function handle_chat_reply(&$tab) {
    if (!isset($_POST['ttb_wr_chat_reply'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_chat_reply')) return;

    $project_id = (int)($_POST['wr_project_id'] ?? 0);
    $message    = sanitize_textarea_field($_POST['wr_chat_message'] ?? '');

    if (!$project_id || trim($message) === '') {
      self::set_flash('error', 'Escribe un mensaje antes de responder al cliente.');
      $tab = 'revisions';
      return;
    }

    global $wpdb;
    $project = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_WebRev_DB::projects_table() . " WHERE id=%d",
      $project_id
    ));

    if (!$project) {
      self::set_flash('error', 'Proyecto no encontrado.');
      $tab = 'revisions';
      return;
    }

    TTB_WebRev_DB::add_message($project_id, 'admin', $message);

    if (class_exists('TTB_WebRev_Mailer')) {
      (new TTB_WebRev_Mailer())->send_admin_message_to_client($project, $message);
    }

    TTB_WebRev_DB::log($project_id, 'admin_chat_message', 'admin', [
      'message' => mb_substr($message, 0, 180),
    ]);

    self::set_flash('success', 'Respuesta enviada al cliente.');
    $tab = 'revisions';
    $_GET['project'] = $project_id;
  }

  private static function handle_settings_save(&$tab) {
    if (!isset($_POST['ttb_wr_settings'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_settings')) return;

    update_option('ttb_webrev_resend_days',      max(1, (int)($_POST['ttb_webrev_resend_days']      ?? 7)));
    update_option('ttb_webrev_max_resends',      max(0, (int)($_POST['ttb_webrev_max_resends']      ?? 3)));
    update_option('ttb_webrev_notify_hola',      sanitize_email($_POST['ttb_webrev_notify_hola']     ?? ''));
    update_option('ttb_webrev_notify_creativo',  sanitize_email($_POST['ttb_webrev_notify_creativo'] ?? ''));
    update_option('ttb_webrev_max_filesize',     max(1, (int)($_POST['ttb_webrev_max_filesize']     ?? 5)));
    update_option('ttb_webrev_max_files',        max(1, (int)($_POST['ttb_webrev_max_files']        ?? 10)));
    update_option('ttb_webrev_email_subject',    sanitize_text_field($_POST['ttb_webrev_email_subject'] ?? ''));
    update_option('ttb_webrev_email_intro',      sanitize_textarea_field($_POST['ttb_webrev_email_intro'] ?? ''));
    update_option('ttb_webrev_email_btn',        sanitize_text_field($_POST['ttb_webrev_email_btn'] ?? ''));

    self::set_flash('success', 'Configuración guardada.');
    $tab = 'settings';
  }

  /* ════════════════════════════════
     RENDER: PROYECTOS
  ════════════════════════════════ */

  private static function render_projects() {
    global $wpdb;

    $table = TTB_WebRev_DB::projects_table();
    $edit_id = (int)($_GET['edit'] ?? 0);
    $edit_p = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id)) : null;

    echo '<div class="ttb-card"><h2>' . ($edit_p ? 'Editar proyecto de revisión de diseño' : 'Nuevo proyecto de revisión de diseño') . '</h2></div>';

    if ($edit_p) {
      self::render_project_edit_form($edit_p);
    } else {
      self::render_project_create_form();
    }

    $projects = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

    echo '<div class="ttb-card"><h3>Proyectos</h3>';

    if (!$projects) {
      echo '<p class="ttb-muted">Todavía no hay proyectos de revisión de diseño.</p>';
      echo '</div>';
      return;
    }

    echo '<div class="ttb-tablewrap"><table class="ttb-table">';
    echo '<thead><tr><th>Cliente / Proyecto</th><th>Estado</th><th>Notificaciones</th><th>SEO</th><th>Enlaces</th><th>Acciones</th></tr></thead><tbody>';

    foreach ($projects as $p) {
      $client_url = TTB_WebRev_DB::client_url($p->token);
      $display = $p->name;
      if (!empty($p->title)) $display .= ' — ' . $p->title;

      echo '<tr>';
      echo '<td><strong>' . esc_html($display) . '</strong><br><small class="ttb-muted">Creado: ' . esc_html(date_i18n('d/m/Y H:i', strtotime($p->created_at))) . '</small></td>';
      echo '<td>' . self::status_badge($p->status) . '</td>';
      echo '<td><strong>' . (int)$p->notif_count . '</strong><br><small class="ttb-muted">' . ($p->last_notified ? esc_html(date_i18n('d/m/Y H:i', strtotime($p->last_notified))) : '—') . '</small></td>';
      echo '<td>' . (!empty($p->notify_seo) ? '<span style="display:inline-block;background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:800">SEO avisado</span>' : '<span class="ttb-muted">No</span>') . '</td>';
      echo '<td>';
      echo '<a href="' . esc_url($p->figma_url) . '" target="_blank" rel="noopener">Desktop</a>';
      if (!empty($p->figma_url_mobile)) {
        echo ' · <a href="' . esc_url($p->figma_url_mobile) . '" target="_blank" rel="noopener">Mobile</a>';
      }
      echo '<br><a href="' . esc_url($client_url) . '" target="_blank" rel="noopener">Portal cliente</a>';
      echo '</td>';

      echo '<td style="white-space:nowrap">';
      echo '<a class="ttb-btn ttb-btn--ghost ttb-btn--sm" href="' . esc_url(home_url('/briefing?section=revisiones-dis&wrtab=projects&edit=' . (int)$p->id)) . '">Editar</a> ';
      echo '<a class="ttb-btn ttb-btn--ghost ttb-btn--sm" href="' . esc_url(home_url('/briefing?section=revisiones-dis&wrtab=revisions&project=' . (int)$p->id)) . '">Revisiones</a> ';

      echo '<form method="post" style="display:inline">';
      wp_nonce_field('ttb_wr_resend');
      echo '<input type="hidden" name="wr_id" value="' . (int)$p->id . '">';
      echo '<button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_wr_resend" value="1">Reenviar</button>';
      echo '</form> ';

      echo '<form method="post" style="display:inline" onsubmit="return confirm(\'¿Eliminar este proyecto y sus revisiones?\')">';
      wp_nonce_field('ttb_wr_delete');
      echo '<input type="hidden" name="wr_id" value="' . (int)$p->id . '">';
      echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_wr_delete" value="1">Eliminar</button>';
      echo '</form>';

      echo '</td>';
      echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '</div>';
  }

  private static function render_project_create_form() {
    global $wpdb;

    $clients_table = TTB_DB::clients_table();

$all_clients = $wpdb->get_results(
  "SELECT * FROM $clients_table
   ORDER BY name ASC"
);

$clients = array_filter($all_clients, function($client) {
  $services_raw = (string)($client->services ?? '');

  if ($services_raw === '') {
    return false;
  }

  $services_decoded = json_decode($services_raw, true);

  if (is_array($services_decoded)) {
    $services_text = implode(' ', array_map('strval', $services_decoded));
  } else {
    $services_text = $services_raw;
  }

  $services_text = strtolower(remove_accents($services_text));

  return (
    strpos($services_text, 'design') !== false ||
    strpos($services_text, 'diseno') !== false ||
    strpos($services_text, 'disen') !== false ||
    strpos($services_text, 'revision disenos') !== false ||
    strpos($services_text, 'revisiones disenos') !== false
  );
});

    $action_url = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=projects'));

    echo '<form method="post" action="' . $action_url . '">';
    wp_nonce_field('ttb_wr_create');

    echo '<div class="ttb-card">';
    echo '<div class="ttb-formgrid">';

    echo '<div>';
    echo '<label>Cliente <span style="color:#d41472">*</span></label>';
    echo '<select class="ttb-input" name="wr_client_id" required>';
    echo '<option value="">— Selecciona cliente —</option>';
    foreach ($clients as $c) {
      echo '<option value="' . (int)$c->id . '">' . esc_html($c->name) . '</option>';
    }
    echo '</select>';
    echo '<small class="ttb-muted">Solo aparecen clientes con el servicio <strong>Diseño / Design</strong>. <a href="' . esc_url(home_url('/briefing?section=clientes')) . '">Gestionar clientes →</a></small>';
    echo '</div>';

    echo '<div>';
    echo '<label>Título del proyecto <span class="ttb-muted">(opcional)</span></label>';
    echo '<input class="ttb-input" type="text" name="wr_title" placeholder="Ej: Web Corporativa v2, Rediseño Logo...">';
    echo '<small class="ttb-muted">Se mostrará al cliente para identificar el proyecto. Si no se indica, se genera automáticamente.</small>';
    echo '</div>';

    echo '<div>';
    echo '<label>Enlace Figma / Presentación (desktop) <span style="color:#d41472">*</span></label>';
    echo '<input class="ttb-input" type="url" name="wr_figma" placeholder="https://www.figma.com/..." required>';
    echo '</div>';

    echo '<div>';
    echo '<label>Enlace Figma mobile <span class="ttb-muted">(opcional)</span></label>';
    echo '<input class="ttb-input" type="url" name="wr_figma_mobile" placeholder="https://www.figma.com/...">';
    echo '</div>';

    echo '<div style="grid-column:1/-1;background:#f9fafb;border:1px solid var(--ttb-border);border-radius:12px;padding:12px 14px">';
    echo '<label style="display:flex;align-items:center;gap:10px;font-weight:800;margin:0">';
    echo '<input type="checkbox" name="wr_notify_seo" value="1" style="width:18px;height:18px">';
    echo '<span>Notificar también de los cambios al departamento de SEO</span>';
    echo '</label>';
    echo '<small class="ttb-muted" style="display:block;margin-top:6px;margin-left:28px">Cuando el cliente solicite cambios o escriba en el chat, también se avisará a <strong>seo@tictac-comunicacion.es</strong>.</small>';
    echo '</div>';

    echo '</div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_wr_create" value="1">Crear y enviar invitación</button></div>';
    echo '</div>';
    echo '</form>';
  }

  private static function render_project_edit_form($p) {
    $action_url = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=projects&edit=' . (int)$p->id));
    $emails = json_decode((string)$p->emails, true);
    if (!is_array($emails)) $emails = [];

    echo '<form method="post" action="' . $action_url . '">';
    wp_nonce_field('ttb_wr_edit');
    echo '<input type="hidden" name="wr_id" value="' . (int)$p->id . '">';

    echo '<div class="ttb-card">';
    echo '<div class="ttb-formgrid">';

    echo '<div>';
    echo '<label>Cliente</label>';
    echo '<input class="ttb-input" type="text" name="wr_name" value="' . esc_attr($p->name) . '" required>';
    echo '</div>';

    echo '<div>';
    echo '<label>Título del proyecto <span class="ttb-muted">(opcional)</span></label>';
    echo '<input class="ttb-input" type="text" name="wr_title" value="' . esc_attr($p->title ?? '') . '" placeholder="Ej: Web Corporativa v2">';
    echo '</div>';

    echo '<div>';
    echo '<label>Enlace Figma desktop</label>';
    echo '<input class="ttb-input" type="url" name="wr_figma" value="' . esc_attr($p->figma_url) . '" required>';
    echo '</div>';

    echo '<div>';
    echo '<label>Enlace Figma mobile</label>';
    echo '<input class="ttb-input" type="url" name="wr_figma_mobile" value="' . esc_attr($p->figma_url_mobile ?? '') . '">';
    echo '</div>';

    echo '<div>';
    echo '<label>Emails del cliente</label>';
    echo '<div id="ttb-wr-emails-edit" style="display:flex;flex-direction:column;gap:8px">';
    if (!$emails) $emails = [''];
    foreach ($emails as $email) {
      echo '<div class="ttb-wr-email-row" style="display:flex;gap:8px;align-items:center">';
      echo '<input class="ttb-input" type="email" name="wr_emails[]" value="' . esc_attr($email) . '" placeholder="email@cliente.com" required style="flex:1">';
      echo '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wr-remove-email">✕</button>';
      echo '</div>';
    }
    echo '</div>';
    echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm" style="margin-top:8px" data-target="ttb-wr-emails-edit">+ Añadir email</button>';
    echo '</div>';

    echo '<div style="background:#f9fafb;border:1px solid var(--ttb-border);border-radius:12px;padding:12px 14px">';
    echo '<label style="display:flex;align-items:center;gap:10px;font-weight:800;margin:0">';
    echo '<input type="checkbox" name="wr_notify_seo" value="1" ' . checked(!empty($p->notify_seo), true, false) . ' style="width:18px;height:18px">';
    echo '<span>Notificar también al departamento de SEO</span>';
    echo '</label>';
    echo '<small class="ttb-muted" style="display:block;margin-top:6px;margin-left:28px">Avisos de cambios y mensajes del cliente a seo@tictac-comunicacion.es.</small>';
    echo '</div>';

    echo '</div>';
    echo '<div class="ttb-actions">';
    echo '<button class="ttb-btn" name="ttb_wr_edit" value="1">Guardar cambios</button>';
    echo '<a class="ttb-btn ttb-btn--ghost" href="' . esc_url(home_url('/briefing?section=revisiones-dis&wrtab=projects')) . '">Cancelar</a>';
    echo '</div>';
    echo '</div>';
    echo '</form>';

    self::email_js();
  }

  private static function status_badge($status) {
    $map = [
      'pending'           => ['Pendiente', '#fef3c7', '#92400e'],
      'changes_requested' => ['Cambios solicitados', '#fffbeb', '#92400e'],
      'accepted'          => ['Aceptado', '#dcfce7', '#166534'],
    ];

    [$label, $bg, $color] = $map[$status] ?? [$status, '#f3f4f6', '#374151'];

    return '<span style="display:inline-block;background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';border-radius:999px;padding:4px 9px;font-size:12px;font-weight:800">' . esc_html($label) . '</span>';
  }

  /* ════════════════════════════════
     RENDER: REVISIONES
  ════════════════════════════════ */

  private static function render_revisions() {
    global $wpdb;

    $projects_table  = TTB_WebRev_DB::projects_table();
    $revisions_table = TTB_WebRev_DB::revisions_table();

    $projects = $wpdb->get_results("SELECT * FROM $projects_table ORDER BY created_at DESC");
    $selected = (int)($_GET['project'] ?? 0);

    if (!$selected && $projects) $selected = (int)$projects[0]->id;

    echo '<div class="ttb-card">';
    echo '<h2>Revisiones</h2>';
    echo '<p class="ttb-muted">Selecciona un proyecto para ver su historial de revisiones.</p>';

    if ($projects) {
      echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
      echo '<input type="hidden" name="section" value="revisiones-dis">';
      echo '<input type="hidden" name="wrtab" value="revisions">';
      echo '<select class="ttb-input" name="project" style="max-width:360px">';
      foreach ($projects as $p) {
        $display = $p->name;
        if (!empty($p->title)) $display .= ' — ' . $p->title;
        echo '<option value="' . (int)$p->id . '" ' . selected($selected, (int)$p->id, false) . '>' . esc_html($display) . '</option>';
      }
      echo '</select>';
      echo '<button class="ttb-btn ttb-btn--ghost">Ver</button>';
      echo '</form>';
    }

    echo '</div>';

    if (!$selected) {
      echo '<div class="ttb-card"><p class="ttb-muted">No hay proyectos todavía.</p></div>';
      return;
    }

    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id=%d", $selected));
    if (!$project) {
      echo '<div class="ttb-card"><p class="ttb-muted">Proyecto no encontrado.</p></div>';
      return;
    }

    $display = $project->name;
    if (!empty($project->title)) $display .= ' — ' . $project->title;

    echo '<div class="ttb-card">';
    echo '<h2>' . esc_html($display) . '</h2>';
    echo '<p>';
    echo '<a href="' . esc_url($project->figma_url) . '" target="_blank" rel="noopener">🖥️ Ver Figma Desktop</a>';
    if (!empty($project->figma_url_mobile)) {
      echo ' · <a href="' . esc_url($project->figma_url_mobile) . '" target="_blank" rel="noopener">📱 Ver Figma Mobile</a>';
    }
    echo ' · <a href="' . esc_url(TTB_WebRev_DB::client_url($project->token)) . '" target="_blank" rel="noopener">👁️ Ver como cliente</a>';
    echo '</p>';

    $revisions = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $revisions_table WHERE project_id=%d ORDER BY round DESC, created_at DESC",
      $selected
    ));

    if (!$revisions) {
      echo '<p class="ttb-muted">Todavía no hay revisiones enviadas por el cliente.</p>';
    } else {
      foreach ($revisions as $rev) {
        self::render_revision_item($rev);
      }
    }

    self::render_project_chat($project);

    echo '</div>';
  }

  private static function render_revision_item($rev) {
    $type_label = $rev->type === 'accept' ? '✅ Diseño aceptado' : '✏️ Cambios solicitados';

    echo '<div style="border:1px solid #fcd34d;background:#fffbeb;border-radius:14px;padding:16px 18px;margin:14px 0">';
    echo '<div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px">';
    echo '<strong>' . esc_html($type_label) . ' — Ronda #' . (int)$rev->round . '</strong>';
    echo '<small>' . esc_html(date_i18n('d/m/Y H:i', strtotime($rev->created_at))) . '</small>';
    echo '</div>';

    if ($rev->type === 'accept') {
      echo '<p style="margin:0">El cliente ha aceptado el diseño.</p>';
      echo '</div>';
      return;
    }

    $blocks = json_decode((string)$rev->images, true);

    if (is_array($blocks) && $blocks) {
      foreach ($blocks as $block) {
        if (($block['type'] ?? '') === 'text') {
          echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;margin:10px 0;line-height:1.7">';
          echo wp_kses_post($block['html'] ?? '');
          echo '</div>';
        } elseif (($block['type'] ?? '') === 'image') {
          echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;margin:10px 0">';
          if (!empty($block['caption'])) {
            echo '<p style="white-space:pre-line;margin:0 0 10px">' . esc_html($block['caption']) . '</p>';
          }
          if (!empty($block['image_url'])) {
            echo '<a href="' . esc_url($block['image_url']) . '" target="_blank" rel="noopener">';
            echo '<img src="' . esc_url($block['image_url']) . '" style="max-width:320px;height:auto;border-radius:8px;border:1px solid #e5e7eb">';
            echo '</a>';
          }
          echo '</div>';
        }
      }
    } elseif (!empty($rev->message)) {
      echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;white-space:pre-line">';
      echo esc_html($rev->message);
      echo '</div>';
    }

    echo '</div>';
  }

  private static function render_project_chat($project) {
    $messages = TTB_WebRev_DB::get_messages((int)$project->id);
    $action_url = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=revisions&project=' . (int)$project->id));

    echo '<div style="margin-top:22px;border-top:1px solid var(--ttb-border);padding-top:22px">';
    echo '<h3 style="margin:0 0 8px">💬 Chat con el cliente</h3>';
    echo '<p class="ttb-muted" style="margin:0 0 14px">Usa este hilo para explicar qué se ha corregido, pedir aclaraciones o solicitar una nueva revisión. El cliente recibirá aviso por email y podrá responder desde su portal.</p>';

    echo '<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px">';

    if (!$messages) {
      echo '<p class="ttb-muted" style="margin:0">Todavía no hay mensajes en el chat.</p>';
    } else {
      foreach ($messages as $m) {
        $is_admin = $m->actor === 'admin';
        $bg = $is_admin ? '#eff6ff' : '#fdf4ff';
        $bc = $is_admin ? '#bfdbfe' : '#f9a8d4';
        $align = $is_admin ? 'margin-left:auto' : 'margin-right:auto';
        $label = $is_admin ? 'TicTac' : 'Cliente';

        echo '<div style="' . $align . ';max-width:78%;background:' . $bg . ';border:1px solid ' . $bc . ';border-radius:14px;padding:12px 14px">';
        echo '<div style="font-size:12px;font-weight:900;margin-bottom:6px;color:var(--ttb-text)">' . esc_html($label) . ' · ' . esc_html(date_i18n('d/m/Y H:i', strtotime($m->created_at))) . '</div>';
        echo '<div style="font-size:14px;line-height:1.6;color:var(--ttb-text);white-space:pre-line">' . esc_html($m->message) . '</div>';
        echo '</div>';
      }
    }

    echo '</div>';

    echo '<form method="post" action="' . $action_url . '" style="display:flex;flex-direction:column;gap:10px">';
    wp_nonce_field('ttb_wr_chat_reply');
    echo '<input type="hidden" name="wr_project_id" value="' . (int)$project->id . '">';
    echo '<textarea class="ttb-textarea" name="wr_chat_message" required style="min-height:100px" placeholder="Escribe tu respuesta para el cliente..."></textarea>';
    echo '<div class="ttb-actions" style="margin:0"><button class="ttb-btn" name="ttb_wr_chat_reply" value="1">💬 Enviar respuesta al cliente</button></div>';
    echo '</form>';

    echo '</div>';
  }

  /* ════════════════════════════════
     RENDER: AUDITORÍA
  ════════════════════════════════ */

  private static function render_audit() {
    global $wpdb;

    $audit_table    = TTB_WebRev_DB::audit_table();
    $projects_table = TTB_WebRev_DB::projects_table();
    $catalog        = self::event_catalog();

    $f_project = (int)($_GET['f_project'] ?? 0);
    $f_event   = sanitize_text_field($_GET['f_event'] ?? '');
    $f_actor   = sanitize_text_field($_GET['f_actor'] ?? '');
    $f_from    = sanitize_text_field($_GET['f_from'] ?? '');
    $f_to      = sanitize_text_field($_GET['f_to'] ?? '');
    $f_search  = sanitize_text_field($_GET['f_search'] ?? '');
    $f_page    = max(1, (int)($_GET['f_page'] ?? 1));
    $per_page  = 30;
    $offset    = ($f_page - 1) * $per_page;

    $where = ['1=1'];
    $args  = [];

    if ($f_project) {
      $where[] = 'a.project_id = %d';
      $args[] = $f_project;
    }

    if ($f_event) {
      $where[] = 'a.event = %s';
      $args[] = $f_event;
    }

    if ($f_actor) {
      $where[] = 'a.actor = %s';
      $args[] = $f_actor;
    }

    if ($f_from) {
      $where[] = 'a.created_at >= %s';
      $args[] = $f_from . ' 00:00:00';
    }

    if ($f_to) {
      $where[] = 'a.created_at <= %s';
      $args[] = $f_to . ' 23:59:59';
    }

    if ($f_search) {
      $where[] = '(a.detail LIKE %s OR p.name LIKE %s OR p.title LIKE %s)';
      $like = '%' . $wpdb->esc_like($f_search) . '%';
      $args[] = $like;
      $args[] = $like;
      $args[] = $like;
    }

    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*)
      FROM $audit_table a
      LEFT JOIN $projects_table p ON p.id = a.project_id
      WHERE $where_sql";

    $total = (int)$wpdb->get_var($args ? $wpdb->prepare($count_sql, $args) : $count_sql);
    $total_pages = max(1, (int)ceil($total / $per_page));

    $sql = "SELECT a.*, p.name AS project_name, p.title AS project_title
      FROM $audit_table a
      LEFT JOIN $projects_table p ON p.id = a.project_id
      WHERE $where_sql
      ORDER BY a.created_at DESC
      LIMIT %d OFFSET %d";

    $query_args = array_merge($args, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $query_args));

    $projects = $wpdb->get_results("SELECT id, name, title FROM $projects_table ORDER BY name ASC, title ASC");

    $actor_labels = [
      'admin'  => ['Admin', '#eef2ff', '#3730a3'],
      'client' => ['Cliente', '#fdf2f8', '#9d174d'],
      'system' => ['Sistema', '#f3f4f6', '#374151'],
    ];

    $base_url = home_url('/briefing?section=revisiones-dis&wrtab=audit');

    echo '<div class="ttb-card"><h2>Auditoría de Revisiones de Diseño</h2>';
    echo '<p class="ttb-muted">Historial de acciones: accesos del cliente, emails, solicitudes de cambios, aceptaciones, reenvíos y errores.</p>';

    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:10px;align-items:end">';
    echo '<input type="hidden" name="section" value="revisiones-dis">';
    echo '<input type="hidden" name="wrtab" value="audit">';

    echo '<div><label>Proyecto</label><select class="ttb-input" name="f_project"><option value="">Todos</option>';
    foreach ($projects as $p) {
      $display = $p->name;
      if (!empty($p->title)) $display .= ' — ' . $p->title;
      echo '<option value="' . (int)$p->id . '" ' . selected($f_project, (int)$p->id, false) . '>' . esc_html($display) . '</option>';
    }
    echo '</select></div>';

    echo '<div><label>Evento</label><select class="ttb-input" name="f_event"><option value="">Todos</option>';
    foreach ($catalog as $k => $v) {
      echo '<option value="' . esc_attr($k) . '" ' . selected($f_event, $k, false) . '>' . esc_html($v[0]) . '</option>';
    }
    echo '</select></div>';

    echo '<div><label>Actor</label><select class="ttb-input" name="f_actor"><option value="">Todos</option>';
    foreach ($actor_labels as $k => $v) {
      echo '<option value="' . esc_attr($k) . '" ' . selected($f_actor, $k, false) . '>' . esc_html($v[0]) . '</option>';
    }
    echo '</select></div>';

    echo '<div><label>Desde</label><input class="ttb-input" type="date" name="f_from" value="' . esc_attr($f_from) . '"></div>';
    echo '<div><label>Hasta</label><input class="ttb-input" type="date" name="f_to" value="' . esc_attr($f_to) . '"></div>';
    echo '<div><label>Buscar</label><input class="ttb-input" type="text" name="f_search" value="' . esc_attr($f_search) . '" placeholder="cliente, detalle..."></div>';

    echo '<div style="grid-column:1/-1;display:flex;gap:10px"><button class="ttb-btn">Filtrar</button><a href="' . esc_url($base_url) . '" class="ttb-btn ttb-btn--ghost">Limpiar</a></div>';
    echo '</form>';

    echo '<p style="margin:14px 0 0;font-size:13px;color:var(--ttb-muted)"><strong>' . number_format($total) . '</strong> registro' . ($total !== 1 ? 's' : '') . '</p></div>';

    if (!$rows) {
      echo '<div class="ttb-card"><p class="ttb-muted" style="text-align:center;padding:24px 0">No hay registros.</p></div>';
    } else {
      echo '<div class="ttb-card" style="padding:0;overflow:hidden"><div class="ttb-tablewrap">';
      echo '<table class="ttb-table" style="font-size:13px"><thead><tr>';
      echo '<th style="width:140px">Fecha</th><th>Evento</th><th style="width:90px">Actor</th><th>Proyecto</th><th>Detalle</th><th style="width:100px">IP</th>';
      echo '</tr></thead><tbody>';

      foreach ($rows as $row) {
        [$ev_label, $ev_bg, $ev_bc, $ev_color] = $catalog[$row->event] ?? [$row->event, '#f9fafb', '#e5e7eb', '#374151'];
        [$ac_label, $ac_bg, $ac_color]          = $actor_labels[$row->actor] ?? [$row->actor, '#f9fafb', '#374151'];

        $detail_html = '—';
        if ($row->detail) {
          $detail = json_decode($row->detail, true);
          if (is_array($detail)) {
            $parts = [];
            foreach ($detail as $k => $v) {
              if (is_array($v)) $v = implode(', ', $v);
              $parts[] = '<span style="color:var(--ttb-muted)">' . esc_html($k) . ':</span> <strong>' . esc_html((string)$v) . '</strong>';
            }
            $detail_html = implode(' &nbsp;·&nbsp; ', $parts);
          } else {
            $detail_html = esc_html($row->detail);
          }
        }

        $proj_display = $row->project_name ?? '';
        if (!empty($row->project_title)) $proj_display .= ' — ' . $row->project_title;

        $project_link = $proj_display
          ? '<a href="' . esc_url(home_url('/briefing?section=revisiones-dis&wrtab=audit&f_project=' . (int)$row->project_id)) . '" style="color:var(--ttb-pink);font-weight:700;text-decoration:none">' . esc_html($proj_display) . '</a>'
          : '<span style="color:var(--ttb-muted)">—</span>';

        echo '<tr>';
        echo '<td style="white-space:nowrap;color:var(--ttb-muted)">' . esc_html(date_i18n('d/m/Y H:i:s', strtotime($row->created_at))) . '</td>';
        echo '<td><span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;background:' . $ev_bg . ';border:1px solid ' . $ev_bc . ';color:' . $ev_color . ';white-space:nowrap">' . esc_html($ev_label) . '</span></td>';
        echo '<td><span style="display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:' . $ac_bg . ';color:' . $ac_color . '">' . esc_html($ac_label) . '</span></td>';
        echo '<td>' . $project_link . '</td>';
        echo '<td style="font-size:12px;max-width:300px">' . $detail_html . '</td>';
        echo '<td style="font-size:12px;color:var(--ttb-muted);font-family:monospace">' . esc_html($row->ip ?? '—') . '</td>';
        echo '</tr>';
      }

      echo '</tbody></table></div></div>';
    }

    if ($total_pages > 1) {
      echo '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:16px">';
      if ($f_page > 1) echo '<a href="' . esc_url(self::audit_page_url($f_page - 1, $f_project, $f_event, $f_actor, $f_from, $f_to, $f_search)) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">← Anterior</a>';

      for ($pg = max(1, $f_page - 3); $pg <= min($total_pages, $f_page + 3); $pg++) {
        echo '<a href="' . esc_url(self::audit_page_url($pg, $f_project, $f_event, $f_actor, $f_from, $f_to, $f_search)) . '" class="' . ($pg === $f_page ? 'ttb-btn ttb-btn--sm' : 'ttb-btn ttb-btn--ghost ttb-btn--sm') . '">' . $pg . '</a>';
      }

      if ($f_page < $total_pages) echo '<a href="' . esc_url(self::audit_page_url($f_page + 1, $f_project, $f_event, $f_actor, $f_from, $f_to, $f_search)) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Siguiente →</a>';
      echo '</div>';
    }
  }

  private static function audit_page_url($page, $project, $event, $actor, $from, $to, $search) {
    return add_query_arg(array_filter([
      'section'   => 'revisiones-dis',
      'wrtab'     => 'audit',
      'f_project' => $project ?: '',
      'f_event'   => $event,
      'f_actor'   => $actor,
      'f_from'    => $from,
      'f_to'      => $to,
      'f_search'  => $search,
      'f_page'    => $page > 1 ? $page : '',
    ]), home_url('/briefing'));
  }

  /* ════════════════════════════════
     RENDER: CONFIGURACIÓN
  ════════════════════════════════ */

  private static function render_settings() {
    $action_url  = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=settings'));
    $days        = (int)get_option('ttb_webrev_resend_days',      7);
    $max_resends = (int)get_option('ttb_webrev_max_resends',      3);
    $hola        = (string)get_option('ttb_webrev_notify_hola',     'hola@tictac-comunicacion.es');
    $creativo    = (string)get_option('ttb_webrev_notify_creativo',  'creativo@tictac-comunicacion.es');
    $max_mb      = (int)get_option('ttb_webrev_max_filesize',     5);
    $max_files   = (int)get_option('ttb_webrev_max_files',        10);
    $subj        = (string)get_option('ttb_webrev_email_subject',  '🎨 Tu diseño web está listo para revisar — TicTac Comunicación');
    $intro       = (string)get_option('ttb_webrev_email_intro',    'Hemos preparado el diseño de tu proyecto. Accede al enlace para revisarlo y darnos tu feedback.');
    $btn         = (string)get_option('ttb_webrev_email_btn',      'Ver mi diseño →');

    echo '<div class="ttb-card"><h3>Configuración</h3></div>';
    echo '<form method="post" action="' . $action_url . '">';
    wp_nonce_field('ttb_wr_settings');

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">⏰ Recordatorios automáticos</h4><div class="ttb-grid2">';
    echo '<div><label>Días entre reenvíos</label><input class="ttb-input" type="number" name="ttb_webrev_resend_days" value="' . $days . '" min="1" max="60"><small class="ttb-muted">Si el cliente no responde, se reenvía el email cada N días.</small></div>';
    echo '<div><label>Máximo de reenvíos</label><input class="ttb-input" type="number" name="ttb_webrev_max_resends" value="' . $max_resends . '" min="0" max="20"><small class="ttb-muted">0 = sin límite de reenvíos.</small></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">📬 Emails internos</h4><div class="ttb-grid2">';
    echo '<div><label>Email "hola@"</label><input class="ttb-input" type="email" name="ttb_webrev_notify_hola" value="' . esc_attr($hola) . '"></div>';
    echo '<div><label>Email departamento creativo</label><input class="ttb-input" type="email" name="ttb_webrev_notify_creativo" value="' . esc_attr($creativo) . '"></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">🖼️ Adjuntos del cliente</h4><div class="ttb-grid2">';
    echo '<div><label>Tamaño máximo por imagen (MB)</label><input class="ttb-input" type="number" name="ttb_webrev_max_filesize" value="' . $max_mb . '" min="1" max="50"></div>';
    echo '<div><label>Máximo de imágenes por revisión</label><input class="ttb-input" type="number" name="ttb_webrev_max_files" value="' . $max_files . '" min="1" max="20"></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">✉️ Contenido del email al cliente</h4><div class="ttb-formgrid">';
    echo '<div><label>Asunto del email</label><input class="ttb-input" type="text" name="ttb_webrev_email_subject" value="' . esc_attr($subj) . '"></div>';
    echo '<div><label>Texto de introducción</label><textarea class="ttb-textarea" name="ttb_webrev_email_intro" style="min-height:80px">' . esc_textarea($intro) . '</textarea></div>';
    echo '<div><label>Texto del botón</label><input class="ttb-input" type="text" name="ttb_webrev_email_btn" value="' . esc_attr($btn) . '"></div>';
    echo '</div></div>';

    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_wr_settings" value="1">Guardar configuración</button></div>';
    echo '</form>';
  }

  private static function sanitize_emails($raw) {
    if (!is_array($raw)) $raw = [$raw];

    return array_values(array_filter(
      array_map(fn($e) => sanitize_email(trim($e)), $raw),
      'is_email'
    ));
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
            row.className = 'ttb-wr-email-row';
            row.style.cssText = 'display:flex;gap:8px;align-items:center';
            row.innerHTML = '<input class="ttb-input" type="email" name="wr_emails[]" placeholder="email@cliente.com" required style="flex:1">'
                          + '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wr-remove-email">✕</button>';
            container.appendChild(row);
            updateRemoveButtons(container);
          });
        }

        container.addEventListener('click', function(e) {
          if (e.target.classList.contains('ttb-wr-remove-email')) {
            e.target.closest('.ttb-wr-email-row').remove();
            updateRemoveButtons(container);
          }
        });

        updateRemoveButtons(container);
      }

      function updateRemoveButtons(container) {
        var rows = container.querySelectorAll('.ttb-wr-email-row');

        rows.forEach(function(row) {
          var btn = row.querySelector('.ttb-wr-remove-email');
          if (btn) btn.style.display = (rows.length > 1) ? 'inline-flex' : 'none';
        });
      }

      initEmailWidget('ttb-wr-emails-edit');
    })();
    </script>
    <?php
  }
}