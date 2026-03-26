<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebProg_Admin')) return;

class TTB_WebProg_Admin {

  public static function event_catalog() {
    return [
      'project_created'       => ['🌐 Proyecto creado',             '#ecfdf5', '#6ee7b7', '#065f46'],
      'project_updated'       => ['✏️ Proyecto editado',             '#eff6ff', '#bfdbfe', '#1d4ed8'],
      'project_deleted'       => ['🗑️ Proyecto eliminado',           '#fff1f2', '#fecdd3', '#be123c'],
      'email_invitation_sent' => ['📧 Invitación enviada',           '#fdf4ff', '#e9d5ff', '#7e22ce'],
      'email_accepted_sent'   => ['📧 Email aceptación enviado',     '#ecfdf5', '#a7f3d0', '#065f46'],
      'email_changes_sent'    => ['📧 Email cambios enviado',        '#fffbeb', '#fde68a', '#92400e'],
      'client_view'           => ['👁️ Cliente vio la web',           '#f0f9ff', '#bae6fd', '#0369a1'],
      'web_accepted'          => ['✅ Web aceptada',                  '#ecfdf5', '#6ee7b7', '#065f46'],
      'changes_requested'     => ['✏️ Cambios solicitados',          '#fffbeb', '#fcd34d', '#92400e'],
      'nonce_failed'          => ['⚠️ Nonce inválido',               '#fff1f2', '#fecdd3', '#be123c'],
      'invalid_token_access'  => ['🚫 Token inválido',               '#fff1f2', '#fecdd3', '#be123c'],
      'cron_reminder_sent'    => ['⏰ Recordatorio cron enviado',    '#f5f3ff', '#ddd6fe', '#5b21b6'],
    ];
  }

    private static function flash_and_redirect($type, $text, $url = null) {
    set_transient('ttb_webprog_admin_flash', ['type' => $type, 'text' => $text], 60);
    if (!$url) $url = home_url('/briefing?section=revisiones-web&wptab=projects');
 
    // Limpiar TODOS los niveles del output buffer
    while (ob_get_level() > 0) {
      ob_end_clean();
    }
 
    if (!headers_sent()) {
      header('Location: ' . esc_url_raw($url), true, 302);
      exit;
    }
 
    // Fallback: si los headers ya fueron enviados, usar JS redirect
    echo '<!DOCTYPE html><html><head>';
    echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($url) . '">';
    echo '</head><body>';
    echo '<script>window.location.replace(' . wp_json_encode($url) . ');</script>';
    echo '</body></html>';
    exit;
  }

  public static function render() {
    $tab = sanitize_text_field($_GET['wptab'] ?? 'projects');

    self::handle_project_create($tab);
    self::handle_project_edit($tab);
    self::handle_project_delete($tab);
    self::handle_resend($tab);
    self::handle_settings_save($tab);

    $flash = get_transient('ttb_webprog_admin_flash');
    if ($flash) {
      delete_transient('ttb_webprog_admin_flash');
      $cls = $flash['type'] === 'success' ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html($flash['text']) . '</div>';
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
    $url  = esc_url(home_url('/briefing?section=revisiones-web&wptab=' . $key));
    $cls  = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . $url . '">' . $icon . esc_html($label) . '</a>';
  }

  private static function action_url($tab = 'projects') {
    return esc_url(home_url('/briefing?section=revisiones-web&wptab=' . $tab));
  }

  private static function handle_project_create(&$tab) {
    if (!isset($_POST['ttb_wp_create'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wp_create')) return;

    $central_client_id = (int)($_POST['wp_client_id'] ?? 0);
    $web_url           = esc_url_raw($_POST['wp_weburl'] ?? '');
    $title             = sanitize_text_field($_POST['wp_title'] ?? '');

    if (!$central_client_id || !$web_url) {
      self::flash_and_redirect('error', 'Selecciona un cliente y proporciona la URL de la web.',
        home_url('/briefing?section=revisiones-web&wptab=projects'));
    }

    // Obtener nombre y emails del cliente central (solo un global $wpdb)
    global $wpdb;
    $central = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", $central_client_id
    ));
    if (!$central) {
      self::flash_and_redirect('error', 'Cliente no encontrado.',
        home_url('/briefing?section=revisiones-web&wptab=projects'));
    }

    $name   = (string)$central->name;
    $emails = json_decode((string)($central->emails ?? ''), true) ?: [$central->email];

    $table = TTB_WebProg_DB::projects_table();
    $token = TTB_WebProg_DB::generate_token();

    $wpdb->insert($table, [
      'name'          => $name,
      'title'         => $title ?: null,
      'emails'        => wp_json_encode(array_values($emails)),
      'web_url'       => $web_url,
      'token'         => $token,
      'status'        => 'pending',
      'last_notified' => TTB_WebProg_DB::now(),
      'notif_count'   => 1,
      'created_at'    => TTB_WebProg_DB::now(),
      'updated_at'    => TTB_WebProg_DB::now(),
    ]);

    $new_id  = (int)$wpdb->insert_id;
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $new_id));

    if ($project) {
      (new TTB_WebProg_Mailer())->send_review_invitation($project);
      TTB_WebProg_DB::log($new_id, 'project_created', 'admin', ['name' => $name, 'emails' => $emails, 'web_url' => $web_url]);
      TTB_WebProg_DB::log($new_id, 'email_invitation_sent', 'admin', ['emails' => $emails, 'trigger' => 'project_created']);
    }

    self::flash_and_redirect('success', 'Proyecto creado y email enviado.',
      home_url('/briefing?section=revisiones-web&wptab=projects'));
  }

  private static function handle_project_edit(&$tab) {
    if (!isset($_POST['ttb_wp_edit'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wp_edit')) return;

    $id      = (int)($_POST['wp_id']    ?? 0);
    $name    = sanitize_text_field($_POST['wp_name']   ?? '');
    $title   = sanitize_text_field($_POST['wp_title']  ?? '');
    $emails  = self::sanitize_emails($_POST['wp_emails'] ?? []);
    $web_url = esc_url_raw($_POST['wp_weburl'] ?? '');

    if (!$id || !$name || !$emails || !$web_url) {
      self::flash_and_redirect('error', 'Todos los campos son obligatorios.',
        home_url('/briefing?section=revisiones-web&wptab=projects'));
    }

    global $wpdb;
    $wpdb->update(TTB_WebProg_DB::projects_table(), [
      'name'       => $name,
      'title'      => $title ?: null,
      'emails'     => wp_json_encode(array_values($emails)),
      'web_url'    => $web_url,
      'updated_at' => TTB_WebProg_DB::now(),
    ], ['id' => $id]);

    TTB_WebProg_DB::log($id, 'project_updated', 'admin', ['name' => $name, 'emails' => $emails, 'web_url' => $web_url]);

    self::flash_and_redirect('success', 'Proyecto actualizado.',
      home_url('/briefing?section=revisiones-web&wptab=projects'));
  }

  private static function handle_project_delete(&$tab) {
    if (!isset($_POST['ttb_wp_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wp_delete')) return;

    $id = (int)($_POST['wp_id'] ?? 0);
    if (!$id) return;

    global $wpdb;
    $project = $wpdb->get_row($wpdb->prepare(
      "SELECT name FROM " . TTB_WebProg_DB::projects_table() . " WHERE id=%d", $id
    ));

    TTB_WebProg_DB::log($id, 'project_deleted', 'admin', ['name' => $project->name ?? '—']);

    $wpdb->delete(TTB_WebProg_DB::projects_table(),  ['id' => $id]);
    $wpdb->delete(TTB_WebProg_DB::revisions_table(), ['project_id' => $id]);

    self::flash_and_redirect('success', 'Proyecto eliminado.',
      home_url('/briefing?section=revisiones-web&wptab=projects'));
  }

  private static function handle_resend(&$tab) {
    if (!isset($_POST['ttb_wp_resend'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wp_resend')) return;

    $id = (int)($_POST['wp_id'] ?? 0);
    if (!$id) return;

    global $wpdb;
    $table   = TTB_WebProg_DB::projects_table();
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
    if (!$project) return;

    (new TTB_WebProg_Mailer())->send_review_invitation($project);

    $new_count = (int)$project->notif_count + 1;
    $wpdb->update($table, [
      'last_notified' => TTB_WebProg_DB::now(),
      'notif_count'   => $new_count,
      'updated_at'    => TTB_WebProg_DB::now(),
    ], ['id' => $id]);

    TTB_WebProg_DB::log($id, 'email_invitation_sent', 'admin', ['trigger' => 'manual_resend', 'notif_count' => $new_count]);

    self::flash_and_redirect('success', 'Email reenviado.',
      home_url('/briefing?section=revisiones-web&wptab=projects'));
  }

  private static function handle_settings_save(&$tab) {
    if (!isset($_POST['ttb_wp_settings'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wp_settings')) return;

    $fields = [
      'ttb_webprog_resend_days'       => (int)($_POST['ttb_webprog_resend_days']       ?? 7),
      'ttb_webprog_max_resends'       => (int)($_POST['ttb_webprog_max_resends']       ?? 3),
      'ttb_webprog_notify_hola'       => sanitize_email($_POST['ttb_webprog_notify_hola']       ?? ''),
      'ttb_webprog_notify_produccion' => sanitize_email($_POST['ttb_webprog_notify_produccion'] ?? ''),
      'ttb_webprog_max_filesize'      => max(1, min(50, (int)($_POST['ttb_webprog_max_filesize'] ?? 5))),
      'ttb_webprog_max_files'         => max(1, min(20, (int)($_POST['ttb_webprog_max_files']    ?? 10))),
      'ttb_webprog_email_subject'     => sanitize_text_field($_POST['ttb_webprog_email_subject']  ?? ''),
      'ttb_webprog_email_intro'       => sanitize_textarea_field($_POST['ttb_webprog_email_intro'] ?? ''),
      'ttb_webprog_email_btn'         => sanitize_text_field($_POST['ttb_webprog_email_btn']       ?? ''),
    ];

    foreach ($fields as $key => $val) update_option($key, $val);

    self::flash_and_redirect('success', 'Configuración guardada.',
      home_url('/briefing?section=revisiones-web&wptab=settings'));
  }

  private static function render_projects() {
    global $wpdb;
    $table    = TTB_WebProg_DB::projects_table();
    $projects = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");

    $edit_id = (int)($_GET['edit_wp'] ?? 0);
    $edit_p  = null;
    if ($edit_id) {
      $edit_p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id));
    }

    $action_url = self::action_url('projects');

    // ── Formulario nuevo proyecto ──
    echo '<div class="ttb-card"><h3>Nuevo proyecto web</h3></div>';
    echo '<form method="post" action="' . $action_url . '" class="ttb-card">';
    wp_nonce_field('ttb_wp_create');
    echo '<div class="ttb-formgrid">';

    echo '<div>';
    echo '<label>Cliente <span class="ttb-required">*</span></label>';
    TTB_Clients_UI::render_client_select('wp_client_id', 'web', 0, true);
    echo '<small class="ttb-muted" style="display:block;margin-top:4px">';
    echo 'Solo aparecen clientes con el servicio <strong>Web</strong>. ';
    echo '<a href="' . esc_url(home_url('/briefing?section=clientes')) . '">Gestionar clientes →</a>';
    echo '</small></div>';

    echo '<div>';
    echo '<label>Título del proyecto <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span></label>';
    echo '<input class="ttb-input" type="text" name="wp_title" placeholder="Ej: Web Corporativa, Landing Black Friday...">';
    echo '<small class="ttb-muted" style="display:block;margin-top:4px">Se mostrará al cliente para identificar el proyecto.</small>';
    echo '</div>';
 
    echo '<div>';
    echo '<label>URL de la web programada <span class="ttb-required">*</span></label>';
    echo '<input class="ttb-input" type="url" name="wp_weburl" required placeholder="https://cliente.com">';
    echo '</div>';

    echo '</div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_wp_create" value="1">Crear y enviar invitación</button></div>';
    echo '</form>';

    // ── Modal de edición ──
    if ($edit_p) {
      $edit_emails = json_decode((string)$edit_p->emails, true) ?: [];
      $cancel_url  = esc_url(home_url('/briefing?section=revisiones-web&wptab=projects'));

      echo '<div class="ttb-modal-overlay" id="ttbWpEditModal" role="dialog" aria-modal="true" style="display:flex">';
      echo '<div class="ttb-modal ttb-edit-modal">';
      echo '<h3 class="ttb-edit-modal__title">✏️ Editar proyecto web</h3>';
      echo '<form method="post" action="' . $action_url . '" class="ttb-formgrid">';
      wp_nonce_field('ttb_wp_edit');
      echo '<input type="hidden" name="wp_id" value="' . (int)$edit_p->id . '">';

      echo '<div class="ttb-grid2">';
    echo '<div><label>Nombre del cliente</label>';
    echo '<input class="ttb-input" type="text" name="wp_name" value="' . esc_attr($edit_p->name) . '" required></div>';
    echo '<div><label>URL de la web</label>';
    echo '<input class="ttb-input" type="url" name="wp_weburl" value="' . esc_attr($edit_p->web_url) . '" required></div>';
    echo '</div>';
 
    echo '<div style="margin-top:10px">';
    echo '<label>Título del proyecto <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span></label>';
    echo '<input class="ttb-input" type="text" name="wp_title" value="' . esc_attr($edit_p->title ?? '') . '" placeholder="Ej: Web Corporativa, Landing...">';
    echo '</div>';

      echo '<div style="margin-top:12px"><label>Emails del cliente</label>';
      echo '<div id="ttb-wp-emails-edit" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">';
      if (empty($edit_emails)) {
        echo '<div class="ttb-wp-email-row" style="display:flex;gap:8px;align-items:center">';
        echo '<input class="ttb-input" type="email" name="wp_emails[]" required style="flex:1">';
        echo '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wp-remove-email" style="display:none">✕</button>';
        echo '</div>';
      } else {
        foreach ($edit_emails as $i => $em) {
          $rm = $i > 0 ? '' : 'style="display:none"';
          echo '<div class="ttb-wp-email-row" style="display:flex;gap:8px;align-items:center">';
          echo '<input class="ttb-input" type="email" name="wp_emails[]" value="' . esc_attr($em) . '" required style="flex:1">';
          echo '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wp-remove-email" ' . $rm . '>✕</button>';
          echo '</div>';
        }
      }
      echo '</div>';
      echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm ttb-wp-add-email" data-target="ttb-wp-emails-edit" style="margin-top:8px">+ Añadir email</button>';
      echo '</div>';

      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_wp_edit" value="1">Guardar cambios</button>';
      echo '</div>';
      echo '</form></div></div>';

      self::email_js();
    }

    // ── Listado ──
    echo '<div class="ttb-card"><h3>Proyectos</h3>';
    if (!$projects) {
      echo '<p class="ttb-muted">No hay proyectos de programación web aún.</p></div>';
      return;
    }

    $status_labels = [
      'pending'           => ['Pendiente',           'ttb-status--pending'],
      'changes_requested' => ['Cambios solicitados', 'ttb-status--progress'],
      'accepted'          => ['Aceptada',            'ttb-status--sent'],
    ];

    echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>';
    echo '<th>Cliente</th><th>Título</th><th>Emails</th><th>Web</th><th>Estado</th><th>Fecha subida</th><th>Avisos</th><th>Últ. aviso</th><th>Acciones</th>';
    echo '</tr></thead><tbody>';

    foreach ($projects as $p) {
      $emails_arr = json_decode((string)$p->emails, true) ?: [];
      $emails_str = implode('<br>', array_map('esc_html', $emails_arr)) ?: esc_html($p->emails);
      [$sl, $sc]  = $status_labels[$p->status] ?? [$p->status, ''];
      $edit_url   = esc_url(home_url('/briefing?section=revisiones-web&wptab=projects&edit_wp=' . (int)$p->id));
      $client_url = esc_url(TTB_WebProg_DB::client_url($p->token));
      $rev_url    = esc_url(home_url('/briefing?section=revisiones-web&wptab=revisions&project=' . (int)$p->id));
      $audit_url  = esc_url(home_url('/briefing?section=revisiones-web&wptab=audit&f_project=' . (int)$p->id));
      $last_n     = $p->last_notified ? date_i18n('d/m/Y', strtotime($p->last_notified)) : '—';
      $web_short  = strlen($p->web_url) > 40 ? substr($p->web_url, 0, 40) . '…' : $p->web_url;

      $proj_title = !empty($p->title) ? esc_html($p->title) : '<span style="color:var(--ttb-muted);font-style:italic">Sin título</span>';
      echo '<tr>';
      echo '<td><strong>' . esc_html($p->name) . '</strong></td>';
      echo '<td style="font-size:13px">' . $proj_title . '</td>';
      echo '<td style="font-size:13px">' . $emails_str . '</td>';
      echo '<td style="font-size:12px"><a href="' . esc_url($p->web_url) . '" target="_blank" style="color:var(--ttb-pink)">' . esc_html($web_short) . '</a></td>';
      echo '<td><span class="ttb-status ' . $sc . '">' . esc_html($sl) . '</span></td>';

      $go_live_fmt = TTB_WebProg_DB::format_go_live($p->go_live_date ?? null);
      if ($go_live_fmt) {
        $days_left   = (int)ceil((strtotime($p->go_live_date) - time()) / 86400);
        $badge_style = $days_left <= 7
          ? 'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa'
          : 'background:#f9fafb;color:#374151;border:1px solid #e5e7eb';
        echo '<td><span style="display:inline-block;font-size:12px;font-weight:800;padding:4px 10px;border-radius:999px;' . $badge_style . '">'
          . esc_html(date_i18n('d/m/Y', strtotime($p->go_live_date)))
          . ($days_left <= 7 ? ' · ' . $days_left . 'd' : '')
          . '</span></td>';
      } else {
        echo '<td><span style="color:var(--ttb-muted);font-size:13px">—</span></td>';
      }

      echo '<td style="text-align:center">' . (int)$p->notif_count . '</td>';
      echo '<td>' . $last_n . '</td>';
      echo '<td><div class="ttb-row-actions">';
      echo '<a href="' . $client_url . '" target="_blank" class="ttb-btn ttb-btn--ghost ttb-btn--sm">👁️ Ver</a>';
      echo '<a href="' . $rev_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">📋 Revisiones</a>';
      echo '<a href="' . $audit_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">🔍 Log</a>';
      echo '<a href="' . $edit_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">✏️ Editar</a>';

      echo '<form method="post" action="' . $action_url . '" style="margin:0">';
      wp_nonce_field('ttb_wp_resend');
      echo '<input type="hidden" name="wp_id" value="' . (int)$p->id . '">';
      echo '<button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_wp_resend" value="1">📧 Reenviar</button>';
      echo '</form>';

      echo '<form method="post" action="' . $action_url . '" style="margin:0" onsubmit="return confirm(\'¿Eliminar el proyecto de ' . esc_js($p->name) . '? Se borrarán todas sus revisiones.\')">';
      wp_nonce_field('ttb_wp_delete');
      echo '<input type="hidden" name="wp_id" value="' . (int)$p->id . '">';
      echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_wp_delete" value="1">🗑️</button>';
      echo '</form>';

      echo '</div></td></tr>';
    }

    echo '</tbody></table></div></div>';
  }

private static function render_revisions() {
    global $wpdb;
    $projects_table  = TTB_WebProg_DB::projects_table();
    $revisions_table = TTB_WebProg_DB::revisions_table();

    // FIX: añadir title a la query
    $projects = $wpdb->get_results("SELECT id, name, title FROM $projects_table ORDER BY name ASC LIMIT 200");
    $pid      = (int)($_GET['project'] ?? 0);

    echo '<div class="ttb-card"><h3>Revisiones de programación web</h3>';
    echo '<p class="ttb-muted">Selecciona un proyecto para ver su historial de revisiones.</p>';
    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">';
    echo '<input type="hidden" name="section" value="revisiones-web">';
    echo '<input type="hidden" name="wptab" value="revisions">';
    // FIX: select más ancho para acomodar el título
    echo '<select name="project" class="ttb-input" style="max-width:360px">';
    echo '<option value="">— Selecciona proyecto —</option>';
    foreach ($projects as $p) {
      // FIX: mostrar título en el option si existe
      $label = $p->name;
      if (!empty($p->title)) $label .= ' — ' . $p->title;
      echo '<option value="' . (int)$p->id . '" ' . selected($pid, $p->id, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<button class="ttb-btn ttb-btn--ghost" type="submit">Ver</button>';
    echo '</form></div>';

    if (!$pid) return;

    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id=%d", $pid));
    if (!$project) return;

    $revisions = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $revisions_table WHERE project_id=%d ORDER BY created_at DESC", $pid
    ));

    // FIX: mostrar título en el h3 si existe
    $project_display = $project->name;
    if (!empty($project->title)) $project_display .= ' — ' . $project->title;

    echo '<div class="ttb-card">';
    echo '<h3>' . esc_html($project_display) . '</h3>';
    echo '<p class="ttb-muted">';
    echo '<a href="' . esc_url($project->web_url) . '" target="_blank">🌐 Ver web</a>';
    echo ' &nbsp;·&nbsp; <a href="' . esc_url(TTB_WebProg_DB::client_url($project->token)) . '" target="_blank">👁️ Ver como cliente</a>';
    echo '</p>';

    if (!empty($project->go_live_date)) {
      $go_live_fmt = TTB_WebProg_DB::format_go_live($project->go_live_date);
      $days_left   = (int)ceil((strtotime($project->go_live_date) - time()) / 86400);
      $urgency     = $days_left <= 7 ? '#fff7ed' : '#fffbeb';
      $urgency_bc  = $days_left <= 7 ? '#fed7aa' : '#fcd34d';
      $urgency_col = $days_left <= 7 ? '#9a3412' : '#92400e';
      echo '<div style="display:inline-block;margin-top:12px;background:' . $urgency . ';border:1.5px solid ' . $urgency_bc . ';border-radius:12px;padding:12px 18px">';
      echo '<p style="margin:0 0 2px;font-size:11px;font-weight:900;color:' . $urgency_col . ';text-transform:uppercase;letter-spacing:.07em">📅 Fecha preferida de subida</p>';
      echo '<p style="margin:0;font-size:16px;font-weight:900;color:' . $urgency_col . '">' . esc_html($go_live_fmt);
      if ($days_left > 0)       echo ' <span style="font-size:13px;font-weight:700">(' . $days_left . ' días)</span>';
      elseif ($days_left === 0) echo ' <span style="font-size:13px;font-weight:700">(hoy)</span>';
      else                      echo ' <span style="font-size:13px;font-weight:700;color:#e11d48">(pasada)</span>';
      echo '</p></div>';
    }

    if (!$revisions) {
      echo '<p class="ttb-muted">Sin revisiones todavía.</p>';
    } else {
      echo '<div style="display:flex;flex-direction:column;gap:12px;margin-top:16px">';
      foreach ($revisions as $rev) {
        $is_acc = $rev->type === 'accept';
        $bg     = $is_acc ? '#ecfdf5' : '#fffbeb';
        $bc     = $is_acc ? '#6ee7b7' : '#fcd34d';
        $lbl    = $is_acc ? '✅ Web aceptada' : '✏️ Cambios solicitados — Ronda #' . $rev->round;

        echo '<div style="background:' . $bg . ';border:1.5px solid ' . $bc . ';border-radius:14px;padding:16px 20px">';
        echo '<div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px">';
        echo '<strong>' . esc_html($lbl) . '</strong>';
        echo '<span style="font-size:13px;color:var(--ttb-muted)">' . esc_html(date_i18n('d/m/Y H:i', strtotime($rev->created_at))) . '</span>';
        echo '</div>';

        if ($rev->message || $rev->images) {
          $blocks_raw = json_decode((string)$rev->images, true);
          $is_blocks  = is_array($blocks_raw) && !empty($blocks_raw) && isset($blocks_raw[0]['type']);

          if ($is_blocks) {
            echo '<div style="margin-top:12px;display:flex;flex-direction:column;gap:10px">';
            foreach ($blocks_raw as $bl) {
              $btype = $bl['type'] ?? '';
              if ($btype === 'text' && !empty($bl['html'])) {
                echo '<div style="font-size:14px;color:var(--ttb-text);line-height:1.7;background:#fff;border-radius:10px;padding:10px 14px;border:1px solid rgba(0,0,0,.07)">'
                  . wp_kses_post($bl['html']) . '</div>';
              } elseif ($btype === 'image') {
                echo '<div style="border:1px solid var(--ttb-border);border-radius:10px;overflow:hidden">';
                if (!empty($bl['image_url'])) {
                  echo '<a href="' . esc_url($bl['image_url']) . '" target="_blank">'
                    . '<img src="' . esc_url($bl['image_url']) . '" style="width:100%;max-height:280px;object-fit:contain;display:block;background:#f4f4f4" alt="Adjunto">'
                    . '</a>';
                }
                if (!empty($bl['caption'])) {
                  echo '<div style="padding:8px 14px;font-size:14px;color:var(--ttb-text);line-height:1.6;border-top:1px solid var(--ttb-border)">'
                    . nl2br(esc_html($bl['caption'])) . '</div>';
                }
                echo '</div>';
              }
            }
            echo '</div>';
          } else {
            if ($rev->message) {
              echo '<p style="margin:10px 0 0;font-size:14px;color:var(--ttb-text);white-space:pre-line;line-height:1.6">' . nl2br(esc_html($rev->message)) . '</p>';
            }
            $old_imgs = json_decode((string)$rev->images, true);
            if (is_array($old_imgs) && $old_imgs) {
              echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">';
              foreach ($old_imgs as $img_url) {
                echo '<a href="' . esc_url($img_url) . '" target="_blank">'
                  . '<img src="' . esc_url($img_url) . '" style="height:80px;width:auto;border-radius:8px;border:1px solid rgba(0,0,0,.1);object-fit:cover" alt="Adjunto">'
                  . '</a>';
              }
              echo '</div>';
            }
          }
        }
        echo '</div>';
      }
      echo '</div>';
    }
    echo '</div>';
  }

  private static function render_audit() {
    global $wpdb;
    $audit_table    = TTB_WebProg_DB::audit_table();
    $projects_table = TTB_WebProg_DB::projects_table();
    $catalog        = self::event_catalog();

    $actor_labels = [
      'admin'  => ['👤 Admin',   '#eff6ff', '#1d4ed8'],
      'client' => ['🧑 Cliente', '#fdf4ff', '#7e22ce'],
      'cron'   => ['⏰ Cron',    '#f9fafb', '#374151'],
      'system' => ['⚙️ Sistema', '#f9fafb', '#374151'],
    ];

    $f_project = (int)($_GET['f_project'] ?? 0);
    $f_event   = sanitize_text_field($_GET['f_event']  ?? '');
    $f_actor   = sanitize_text_field($_GET['f_actor']  ?? '');
    $f_from    = sanitize_text_field($_GET['f_from']   ?? '');
    $f_to      = sanitize_text_field($_GET['f_to']     ?? '');
    $f_search  = sanitize_text_field($_GET['f_search'] ?? '');
    $f_page    = max(1, (int)($_GET['f_page'] ?? 1));
    $per_page  = 50;
    $offset    = ($f_page - 1) * $per_page;

    $where  = ['1=1'];
    $params = [];
    if ($f_project) { $where[] = 'a.project_id = %d'; $params[] = $f_project; }
    if ($f_event)   { $where[] = 'a.event = %s';      $params[] = $f_event; }
    if ($f_actor)   { $where[] = 'a.actor = %s';      $params[] = $f_actor; }
    if ($f_from)    { $where[] = 'a.created_at >= %s'; $params[] = $f_from . ' 00:00:00'; }
    if ($f_to)      { $where[] = 'a.created_at <= %s'; $params[] = $f_to . ' 23:59:59'; }
    if ($f_search) {
      $where[]  = '(p.name LIKE %s OR a.detail LIKE %s OR a.ip LIKE %s)';
      $like     = '%' . $wpdb->esc_like($f_search) . '%';
      $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $where_sql   = implode(' AND ', $where);
    $count_sql   = "SELECT COUNT(*) FROM $audit_table a LEFT JOIN $projects_table p ON p.id=a.project_id WHERE $where_sql";
    $total       = $params ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : (int)$wpdb->get_var($count_sql);
    $rows_sql    = "SELECT a.*, p.name AS project_name FROM $audit_table a LEFT JOIN $projects_table p ON p.id=a.project_id WHERE $where_sql ORDER BY a.created_at DESC LIMIT %d OFFSET %d";
    $rows        = $wpdb->get_results($wpdb->prepare($rows_sql, ...array_merge($params, [$per_page, $offset])));
    $total_pages = max(1, ceil($total / $per_page));
    $projects    = $wpdb->get_results("SELECT id, name FROM $projects_table ORDER BY name ASC");
    $base_url    = home_url('/briefing?section=revisiones-web&wptab=audit');

    echo '<div class="ttb-card">';
    echo '<h3 style="margin:0 0 4px">Auditoría — Prog. Web</h3>';
    echo '<p class="ttb-muted" style="margin:0 0 20px">Registro completo de actividad del módulo Revisiones Prog. Web.</p>';

    $stats = $wpdb->get_results("SELECT event, COUNT(*) as cnt FROM $audit_table GROUP BY event ORDER BY cnt DESC LIMIT 5");
    if ($stats) {
      echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px">';
      foreach ($stats as $s) {
        [$ev_label] = $catalog[$s->event] ?? [$s->event];
        echo '<div style="background:#f9fafb;border:1px solid var(--ttb-border);border-radius:10px;padding:8px 14px;font-size:13px">';
        echo '<span style="font-weight:900;color:var(--ttb-text)">' . (int)$s->cnt . '</span> ';
        echo '<span style="color:var(--ttb-muted)">' . esc_html($ev_label) . '</span>';
        echo '</div>';
      }
      echo '</div>';
    }

    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;align-items:end">';
    echo '<input type="hidden" name="section" value="revisiones-web"><input type="hidden" name="wptab" value="audit">';

    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">🏢 Cliente / Proyecto</label>';
    echo '<select name="f_project" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    foreach ($projects as $p) echo '<option value="' . (int)$p->id . '" ' . selected($f_project, $p->id, false) . '>' . esc_html($p->name) . '</option>';
    echo '</select></div>';

    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">🏷️ Tipo de evento</label>';
    echo '<select name="f_event" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    $event_groups = [
      'Proyectos' => ['project_created','project_updated','project_deleted'],
      'Emails'    => ['email_invitation_sent','email_accepted_sent','email_changes_sent'],
      'Cliente'   => ['client_view','web_accepted','changes_requested'],
      'Sistema'   => ['nonce_failed','invalid_token_access','cron_reminder_sent'],
    ];
    foreach ($event_groups as $group_label => $keys) {
      echo '<optgroup label="' . esc_attr($group_label) . '">';
      foreach ($keys as $key) {
        if (!isset($catalog[$key])) continue;
        [$ev_label] = $catalog[$key];
        echo '<option value="' . esc_attr($key) . '" ' . selected($f_event, $key, false) . '>' . esc_html($ev_label) . '</option>';
      }
      echo '</optgroup>';
    }
    echo '</select></div>';

    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">👤 Actor</label>';
    echo '<select name="f_actor" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    foreach ($actor_labels as $key => [$label]) echo '<option value="' . esc_attr($key) . '" ' . selected($f_actor, $key, false) . '>' . esc_html($label) . '</option>';
    echo '</select></div>';

    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">📅 Desde</label><input type="date" name="f_from" class="ttb-input" value="' . esc_attr($f_from) . '" style="font-size:13px"></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">📅 Hasta</label><input type="date" name="f_to" class="ttb-input" value="' . esc_attr($f_to) . '" style="font-size:13px"></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">🔎 Búsqueda</label><input type="text" name="f_search" class="ttb-input" value="' . esc_attr($f_search) . '" placeholder="Nombre, IP…" style="font-size:13px"></div>';
    echo '<div style="display:flex;gap:8px;align-items:flex-end"><button class="ttb-btn" type="submit">Filtrar</button><a href="' . esc_url($base_url) . '" class="ttb-btn ttb-btn--ghost">Limpiar</a></div>';
    echo '</form>';

    echo '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:14px">';
    echo '<p style="margin:0;font-size:13px;color:var(--ttb-muted)"><strong>' . number_format($total) . '</strong> registro' . ($total !== 1 ? 's' : '') . '</p>';
    $export_params = array_filter(['section'=>'revisiones-web','wptab'=>'audit','f_project'=>$f_project?:'','f_event'=>$f_event,'f_actor'=>$f_actor,'f_from'=>$f_from,'f_to'=>$f_to,'f_search'=>$f_search,'ttb_wp_audit_export'=>'1']);
    echo '<a href="' . esc_url(add_query_arg($export_params, home_url('/briefing'))) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">⬇️ Exportar CSV</a>';
    echo '</div></div>';

    if (!empty($_GET['ttb_wp_audit_export'])) { self::export_csv($rows, $catalog); return; }

    if (!$rows) {
      echo '<div class="ttb-card"><p class="ttb-muted" style="text-align:center;padding:24px 0">No hay registros.</p></div>';
    } else {
      echo '<div class="ttb-card" style="padding:0;overflow:hidden"><div class="ttb-tablewrap">';
      echo '<table class="ttb-table" style="font-size:13px"><thead><tr>';
      echo '<th style="width:140px;white-space:nowrap">Fecha y hora</th><th>Evento</th><th style="width:90px">Actor</th><th>Proyecto</th><th>Detalle</th><th style="width:100px">IP</th>';
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

        $project_link = $row->project_name
          ? '<a href="' . esc_url(home_url('/briefing?section=revisiones-web&wptab=audit&f_project=' . (int)$row->project_id)) . '" style="color:var(--ttb-pink);font-weight:700;text-decoration:none">' . esc_html($row->project_name) . '</a>'
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
      if ($f_page > 1) echo '<a href="' . esc_url(self::audit_page_url($f_page-1,$f_project,$f_event,$f_actor,$f_from,$f_to,$f_search)) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">← Anterior</a>';
      for ($p = max(1,$f_page-3); $p <= min($total_pages,$f_page+3); $p++) {
        echo '<a href="' . esc_url(self::audit_page_url($p,$f_project,$f_event,$f_actor,$f_from,$f_to,$f_search)) . '" class="' . ($p===$f_page?'ttb-btn ttb-btn--sm':'ttb-btn ttb-btn--ghost ttb-btn--sm') . '">' . $p . '</a>';
      }
      if ($f_page < $total_pages) echo '<a href="' . esc_url(self::audit_page_url($f_page+1,$f_project,$f_event,$f_actor,$f_from,$f_to,$f_search)) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Siguiente →</a>';
      echo '</div>';
      echo '<p style="text-align:center;font-size:13px;color:var(--ttb-muted);margin-top:8px">Página ' . $f_page . ' de ' . $total_pages . '</p>';
    }
  }

  private static function audit_page_url($page, $project, $event, $actor, $from, $to, $search) {
    return add_query_arg(array_filter([
      'section'   => 'revisiones-web',
      'wptab'     => 'audit',
      'f_project' => $project ?: '',
      'f_event'   => $event,
      'f_actor'   => $actor,
      'f_from'    => $from,
      'f_to'      => $to,
      'f_search'  => $search,
      'f_page'    => $page > 1 ? $page : '',
    ]), home_url('/briefing'));
  }

  private static function export_csv($rows, $catalog) {
    if (empty($rows)) {
      echo '<div class="ttb-card"><p class="ttb-muted">No hay datos para exportar.</p></div>';
      return;
    }
    $lines = ['"Fecha","Evento","Actor","Proyecto","Detalle","IP"'];
    foreach ($rows as $row) {
      [$ev_label] = $catalog[$row->event] ?? [$row->event];
      $detail_str = '';
      if ($row->detail) {
        $d = json_decode($row->detail, true);
        if (is_array($d)) {
          $parts = [];
          foreach ($d as $k => $v) { if (is_array($v)) $v = implode(', ', $v); $parts[] = $k . ': ' . $v; }
          $detail_str = implode(' | ', $parts);
        } else {
          $detail_str = $row->detail;
        }
      }
      $lines[] = implode(',', array_map(
        fn($s) => '"' . str_replace('"', '""', (string)$s) . '"',
        [date_i18n('d/m/Y H:i:s', strtotime($row->created_at)), $ev_label, $row->actor, $row->project_name ?? '', $detail_str, $row->ip ?? '']
      ));
    }
    $csv = implode("\n", $lines);
    $fn  = 'auditoria-prog-web-' . date('Y-m-d') . '.csv';
    echo '<script>(function(){var b=new Blob(["\uFEFF"+'
      . wp_json_encode($csv)
      . '],{type:"text/csv;charset=utf-8;"}),u=URL.createObjectURL(b),a=document.createElement("a");a.href=u;a.download='
      . wp_json_encode($fn)
      . ';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(u);a.remove();},1000);history.replaceState(null,"",location.href.replace(/[&?]ttb_wp_audit_export=1/,""));})();</script>';
  }

  private static function render_settings() {
    $action_url  = esc_url(home_url('/briefing?section=revisiones-web&wptab=settings'));
    $days        = (int)get_option('ttb_webprog_resend_days',       7);
    $max_resends = (int)get_option('ttb_webprog_max_resends',       3);
    $hola        = (string)get_option('ttb_webprog_notify_hola',      'hola@tictac-comunicacion.es');
    $produccion  = (string)get_option('ttb_webprog_notify_produccion', 'produccion@tictac-comunicacion.es');
    $max_mb      = (int)get_option('ttb_webprog_max_filesize',      5);
    $max_files   = (int)get_option('ttb_webprog_max_files',         10);
    $subj        = (string)get_option('ttb_webprog_email_subject', '🌐 Tu web está lista para revisar — TicTac Comunicación');
    $intro       = (string)get_option('ttb_webprog_email_intro',   'Hemos programado tu web y ya está disponible para que la revises. Accede al enlace, navégala con calma y danos tu feedback.');
    $btn         = (string)get_option('ttb_webprog_email_btn',     'Ver mi web →');

    echo '<div class="ttb-card"><h3>Configuración — Prog. Web</h3></div>';
    echo '<form method="post" action="' . $action_url . '">';
    wp_nonce_field('ttb_wp_settings');

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">⏰ Recordatorios automáticos</h4><div class="ttb-grid2">';
    echo '<div><label>Días entre reenvíos</label><input class="ttb-input" type="number" name="ttb_webprog_resend_days" value="' . $days . '" min="1" max="60"><small class="ttb-muted">Si el cliente no responde, se reenvía cada N días.</small></div>';
    echo '<div><label>Máximo de reenvíos</label><input class="ttb-input" type="number" name="ttb_webprog_max_resends" value="' . $max_resends . '" min="0" max="20"><small class="ttb-muted">0 = sin límite.</small></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">📬 Emails internos</h4><div class="ttb-grid2">';
    echo '<div><label>Email "hola@"</label><input class="ttb-input" type="email" name="ttb_webprog_notify_hola" value="' . esc_attr($hola) . '"></div>';
    echo '<div><label>Email departamento producción</label><input class="ttb-input" type="email" name="ttb_webprog_notify_produccion" value="' . esc_attr($produccion) . '"></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">🖼️ Adjuntos del cliente</h4><div class="ttb-grid2">';
    echo '<div><label>Tamaño máximo por imagen (MB)</label><input class="ttb-input" type="number" name="ttb_webprog_max_filesize" value="' . $max_mb . '" min="1" max="50"></div>';
    echo '<div><label>Máximo de imágenes por revisión</label><input class="ttb-input" type="number" name="ttb_webprog_max_files" value="' . $max_files . '" min="1" max="20"></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 16px">✉️ Contenido del email al cliente</h4><div class="ttb-formgrid">';
    echo '<div><label>Asunto del email</label><input class="ttb-input" type="text" name="ttb_webprog_email_subject" value="' . esc_attr($subj) . '"></div>';
    echo '<div><label>Texto de introducción</label><textarea class="ttb-textarea" name="ttb_webprog_email_intro" style="min-height:80px">' . esc_textarea($intro) . '</textarea></div>';
    echo '<div><label>Texto del botón</label><input class="ttb-input" type="text" name="ttb_webprog_email_btn" value="' . esc_attr($btn) . '"></div>';
    echo '</div></div>';

    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_wp_settings" value="1">Guardar configuración</button></div>';
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
            row.className = 'ttb-wp-email-row';
            row.style.cssText = 'display:flex;gap:8px;align-items:center';
            row.innerHTML = '<input class="ttb-input" type="email" name="wp_emails[]" placeholder="email@cliente.com" required style="flex:1">'
                          + '<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wp-remove-email">✕</button>';
            container.appendChild(row);
            updateRemoveButtons(container);
          });
        }
        container.addEventListener('click', function(e) {
          if (e.target.classList.contains('ttb-wp-remove-email')) {
            e.target.closest('.ttb-wp-email-row').remove();
            updateRemoveButtons(container);
          }
        });
        updateRemoveButtons(container);
      }
      function updateRemoveButtons(container) {
        var rows = container.querySelectorAll('.ttb-wp-email-row');
        rows.forEach(function(row) {
          var btn = row.querySelector('.ttb-wp-remove-email');
          if (btn) btn.style.display = (rows.length > 1) ? 'inline-flex' : 'none';
        });
      }
      initEmailWidget('ttb-wp-emails-edit');
    })();
    </script>
    <?php
  }
}