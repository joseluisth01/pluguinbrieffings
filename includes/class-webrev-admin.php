<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebRev_Admin')) return;

/**
 * TTB_WebRev_Admin
 * Panel de administración para el módulo Revisiones Prog. Web
 */
class TTB_WebRev_Admin {

  private static $flash = null;

  public static function render() {
    $tab = sanitize_text_field($_GET['wrtab'] ?? 'projects');

    // Procesar POST
    self::handle_project_create($tab);
    self::handle_project_edit($tab);
    self::handle_project_delete($tab);
    self::handle_resend($tab);
    self::handle_settings_save($tab);

    if (self::$flash) {
      $cls = self::$flash['type'] === 'success' ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html(self::$flash['text']) . '</div>';
    }

    // Sub-pestañas
    echo '<div class="ttb-tabs">';
    self::tab_link('projects',  'Proyectos',      $tab);
    self::tab_link('revisions', 'Revisiones',     $tab);
    self::tab_link('audit',     '🔍 Auditoría',   $tab);
    self::tab_link('settings',  'Configuración',  $tab);
    echo '</div>';

    switch ($tab) {
      case 'revisions': self::render_revisions(); break;
      case 'audit':     self::render_audit();     break;
      case 'settings':  self::render_settings();  break;
      default:          self::render_projects();   break;
    }
  }

  /* ── nav helper ── */
  private static function tab_link($key, $label, $active) {
    $url = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=' . $key));
    $cls = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . $url . '">' . esc_html($label) . '</a>';
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

    $name      = sanitize_text_field($_POST['wr_name'] ?? '');
    $emails    = self::sanitize_emails($_POST['wr_emails'] ?? []);
    $figma_url = esc_url_raw($_POST['wr_figma'] ?? '');

    if (!$name || !$emails || !$figma_url) {
      self::set_flash('error', 'Nombre, al menos un email y el enlace Figma son obligatorios.');
      $tab = 'projects';
      return;
    }

    global $wpdb;
    $table = TTB_WebRev_DB::projects_table();
    $token = TTB_WebRev_DB::generate_token();

    $wpdb->insert($table, [
      'name'          => $name,
      'emails'        => wp_json_encode(array_values($emails)),
      'figma_url'     => $figma_url,
      'token'         => $token,
      'status'        => 'pending',
      'last_notified' => TTB_WebRev_DB::now(),
      'notif_count'   => 1,
      'created_at'    => TTB_WebRev_DB::now(),
      'updated_at'    => TTB_WebRev_DB::now(),
    ]);

    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $wpdb->insert_id));
    if ($project) {
      (new TTB_WebRev_Mailer())->send_review_invitation($project);
      TTB_WebRev_DB::log($project->id, 'project_created', 'admin', ['name' => $name, 'emails' => $emails, 'figma_url' => $figma_url]);
      TTB_WebRev_DB::log($project->id, 'email_invitation_sent', 'admin', ['emails' => $emails, 'trigger' => 'project_created']);
    }

    self::set_flash('success', 'Proyecto creado y email enviado.');
    $tab = 'projects';
  }

  private static function handle_project_edit(&$tab) {
    if (!isset($_POST['ttb_wr_edit'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_edit')) return;

    $id        = (int)($_POST['wr_id'] ?? 0);
    $name      = sanitize_text_field($_POST['wr_name'] ?? '');
    $emails    = self::sanitize_emails($_POST['wr_emails'] ?? []);
    $figma_url = esc_url_raw($_POST['wr_figma'] ?? '');

    if (!$id || !$name || !$emails || !$figma_url) {
      self::set_flash('error', 'Todos los campos son obligatorios.');
      $tab = 'projects';
      return;
    }

    global $wpdb;
    $wpdb->update(TTB_WebRev_DB::projects_table(), [
      'name'       => $name,
      'emails'     => wp_json_encode(array_values($emails)),
      'figma_url'  => $figma_url,
      'updated_at' => TTB_WebRev_DB::now(),
    ], ['id' => $id]);

    TTB_WebRev_DB::log($id, 'project_updated', 'admin', ['name' => $name, 'figma_url' => $figma_url]);

    self::set_flash('success', 'Proyecto actualizado.');
    $tab = 'projects';
  }

  private static function handle_project_delete(&$tab) {
    if (!isset($_POST['ttb_wr_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_delete')) return;

    $id = (int)($_POST['wr_id'] ?? 0);
    if (!$id) return;

    global $wpdb;
    // Loguear antes de borrar para tener el nombre
    $project = $wpdb->get_row($wpdb->prepare("SELECT name FROM " . TTB_WebRev_DB::projects_table() . " WHERE id=%d", $id));
    TTB_WebRev_DB::log($id, 'project_deleted', 'admin', ['name' => $project->name ?? '—']);

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

    $wpdb->update($table, [
      'last_notified' => TTB_WebRev_DB::now(),
      'notif_count'   => (int)$project->notif_count + 1,
      'updated_at'    => TTB_WebRev_DB::now(),
    ], ['id' => $id]);

    TTB_WebRev_DB::log($id, 'email_invitation_sent', 'admin', [
      'trigger'      => 'manual_resend',
      'notif_count'  => (int)$project->notif_count + 1,
    ]);

    self::set_flash('success', 'Email reenviado.');
    $tab = 'projects';
  }

  private static function handle_settings_save(&$tab) {
    if (!isset($_POST['ttb_wr_settings'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_wr_settings')) return;

    $fields = [
      'ttb_webrev_resend_days'      => (int)($_POST['ttb_webrev_resend_days']      ?? 7),
      'ttb_webrev_max_resends'      => (int)($_POST['ttb_webrev_max_resends']      ?? 3),
      'ttb_webrev_notify_hola'      => sanitize_email($_POST['ttb_webrev_notify_hola']      ?? ''),
      'ttb_webrev_notify_creativo'  => sanitize_email($_POST['ttb_webrev_notify_creativo']  ?? ''),
      'ttb_webrev_max_filesize'     => max(1, min(50, (int)($_POST['ttb_webrev_max_filesize'] ?? 5))),
      'ttb_webrev_max_files'        => max(1, min(20, (int)($_POST['ttb_webrev_max_files']   ?? 10))),
      'ttb_webrev_email_subject'    => sanitize_text_field($_POST['ttb_webrev_email_subject']  ?? ''),
      'ttb_webrev_email_intro'      => sanitize_textarea_field($_POST['ttb_webrev_email_intro'] ?? ''),
      'ttb_webrev_email_btn'        => sanitize_text_field($_POST['ttb_webrev_email_btn']       ?? ''),
    ];

    foreach ($fields as $key => $val) {
      update_option($key, $val);
    }

    self::set_flash('success', 'Configuración guardada.');
    $tab = 'settings';
  }

  /* ════════════════════════════════
     RENDER: PROYECTOS
  ════════════════════════════════ */
  private static function render_projects() {
    global $wpdb;
    $table    = TTB_WebRev_DB::projects_table();
    $projects = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200");

    $edit_id = (int)($_GET['edit_wr'] ?? 0);
    $edit_p  = null;
    if ($edit_id) {
      $edit_p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id));
    }

    $action_url = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=projects'));

    /* ── Formulario crear ── */
    echo '<div class="ttb-card"><h3>Nuevo proyecto</h3></div>';
    echo '<form method="post" action="' . $action_url . '" class="ttb-card">';
    wp_nonce_field('ttb_wr_create');

    echo '<div class="ttb-grid2">';
    echo '<div><label>Nombre del cliente</label><input class="ttb-input" type="text" name="wr_name" required placeholder="Empresa Ejemplo S.L."></div>';
    echo '<div><label>Enlace Figma / Presentación</label><input class="ttb-input" type="url" name="wr_figma" required placeholder="https://www.figma.com/..."></div>';
    echo '</div>';

    echo '<div style="margin-top:12px"><label>Emails del cliente</label>';
    echo '<div id="ttb-wr-emails-create" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">';
    echo '<div class="ttb-wr-email-row" style="display:flex;gap:8px;align-items:center"><input class="ttb-input" type="email" name="wr_emails[]" placeholder="email@cliente.com" required style="flex:1"><button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wr-remove-email" style="display:none">✕</button></div>';
    echo '</div>';
    echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm ttb-wr-add-email" data-target="ttb-wr-emails-create" style="margin-top:8px">+ Añadir email</button>';
    echo '</div>';

    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_wr_create" value="1">Crear y enviar invitación</button></div>';
    echo '</form>';

    /* ── Modal de edición ── */
    if ($edit_p) {
      $edit_emails = json_decode((string)$edit_p->emails, true);
      if (!is_array($edit_emails)) $edit_emails = [];
      $cancel_url = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=projects'));

      echo '<div class="ttb-modal-overlay" id="ttbWrEditModal" role="dialog" aria-modal="true" style="display:flex">';
      echo '<div class="ttb-modal ttb-edit-modal">';
      echo '<h3 class="ttb-edit-modal__title">✏️ Editar proyecto</h3>';
      echo '<form method="post" action="' . $action_url . '" class="ttb-formgrid">';
      wp_nonce_field('ttb_wr_edit');
      echo '<input type="hidden" name="wr_id" value="' . (int)$edit_p->id . '">';

      echo '<div class="ttb-grid2">';
      echo '<div><label>Nombre del cliente</label><input class="ttb-input" type="text" name="wr_name" value="' . esc_attr($edit_p->name) . '" required></div>';
      echo '<div><label>Enlace Figma</label><input class="ttb-input" type="url" name="wr_figma" value="' . esc_attr($edit_p->figma_url) . '" required></div>';
      echo '</div>';

      echo '<div style="margin-top:12px"><label>Emails del cliente</label>';
      echo '<div id="ttb-wr-emails-edit" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">';
      foreach ($edit_emails as $i => $em) {
        $removable = $i > 0 ? '' : 'style="display:none"';
        echo '<div class="ttb-wr-email-row" style="display:flex;gap:8px;align-items:center"><input class="ttb-input" type="email" name="wr_emails[]" value="' . esc_attr($em) . '" required style="flex:1"><button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wr-remove-email" ' . $removable . '>✕</button></div>';
      }
      if (empty($edit_emails)) {
        echo '<div class="ttb-wr-email-row" style="display:flex;gap:8px;align-items:center"><input class="ttb-input" type="email" name="wr_emails[]" required style="flex:1"><button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm ttb-wr-remove-email" style="display:none">✕</button></div>';
      }
      echo '</div>';
      echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm ttb-wr-add-email" data-target="ttb-wr-emails-edit" style="margin-top:8px">+ Añadir email</button>';
      echo '</div>';

      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_wr_edit" value="1">Guardar cambios</button>';
      echo '</div>';
      echo '</form></div></div>';
    }

    /* ── Listado ── */
    echo '<div class="ttb-card"><h3>Proyectos</h3>';
    if (!$projects) {
      echo '<p class="ttb-muted">No hay proyectos aún.</p></div>';
    } else {
      $status_labels = [
        'pending'           => ['Pendiente',          'ttb-status--pending'],
        'changes_requested' => ['Cambios solicitados', 'ttb-status--progress'],
        'accepted'          => ['Aceptado',            'ttb-status--sent'],
      ];

      echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>
        <th>Cliente</th><th>Emails</th><th>Estado</th><th>Avisos</th><th>Últ. aviso</th><th>Acciones</th>
      </tr></thead><tbody>';

      foreach ($projects as $p) {
        $emails_arr  = json_decode((string)$p->emails, true);
        $emails_str  = is_array($emails_arr) ? implode('<br>', array_map('esc_html', $emails_arr)) : esc_html($p->emails);
        [$sl, $sc]   = $status_labels[$p->status] ?? [$p->status, ''];
        $edit_url    = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=projects&edit_wr=' . (int)$p->id));
        $client_url  = esc_url(TTB_WebRev_DB::client_url($p->token));
        $rev_url     = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=revisions&project=' . (int)$p->id));
        $last_n      = $p->last_notified ? date_i18n('d/m/Y', strtotime($p->last_notified)) : '—';

        echo '<tr>';
        echo '<td><strong>' . esc_html($p->name) . '</strong></td>';
        echo '<td style="font-size:13px">' . $emails_str . '</td>';
        echo '<td><span class="ttb-status ' . $sc . '">' . esc_html($sl) . '</span></td>';
        echo '<td style="text-align:center">' . (int)$p->notif_count . '</td>';
        echo '<td>' . $last_n . '</td>';
        echo '<td><div class="ttb-row-actions">';

        // Ver enlace cliente
        echo '<a href="' . $client_url . '" target="_blank" class="ttb-btn ttb-btn--ghost ttb-btn--sm" title="Ver como cliente">👁️ Ver</a>';

        // Ver revisiones
        echo '<a href="' . $rev_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">📋 Revisiones</a>';

        // Editar
        echo '<a href="' . $edit_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">✏️ Editar</a>';

        // Reenviar
        echo '<form method="post" action="' . $action_url . '" style="margin:0">';
        wp_nonce_field('ttb_wr_resend');
        echo '<input type="hidden" name="wr_id" value="' . (int)$p->id . '">';
        echo '<button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_wr_resend" value="1">📧 Reenviar</button>';
        echo '</form>';

        // Eliminar
        echo '<form method="post" action="' . $action_url . '" style="margin:0"
                    onsubmit="return confirm(\'¿Eliminar el proyecto de ' . esc_js($p->name) . '? Se borrarán todas sus revisiones.\')">';
        wp_nonce_field('ttb_wr_delete');
        echo '<input type="hidden" name="wr_id" value="' . (int)$p->id . '">';
        echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_wr_delete" value="1">🗑️</button>';
        echo '</form>';

        echo '</div></td></tr>';
      }

      echo '</tbody></table></div></div>';
    }

    // JS para emails dinámicos
    self::email_js();
  }

  /* ════════════════════════════════
     RENDER: REVISIONES
  ════════════════════════════════ */
  private static function render_revisions() {
    global $wpdb;
    $projects_table  = TTB_WebRev_DB::projects_table();
    $revisions_table = TTB_WebRev_DB::revisions_table();

    $projects = $wpdb->get_results("SELECT id, name FROM $projects_table ORDER BY name ASC LIMIT 200");
    $pid      = (int)($_GET['project'] ?? 0);

    echo '<div class="ttb-card"><h3>Revisiones</h3>';
    echo '<p class="ttb-muted">Selecciona un proyecto para ver su historial de revisiones.</p>';

    // Selector de proyecto
    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">';
    echo '<input type="hidden" name="section" value="revisiones-dis">';
    echo '<input type="hidden" name="wrtab" value="revisions">';
    echo '<select name="project" class="ttb-input" style="max-width:300px">';
    echo '<option value="">— Selecciona proyecto —</option>';
    foreach ($projects as $p) {
      echo '<option value="' . (int)$p->id . '" ' . selected($pid, $p->id, false) . '>' . esc_html($p->name) . '</option>';
    }
    echo '</select>';
    echo '<button class="ttb-btn ttb-btn--ghost" type="submit">Ver</button>';
    echo '</form></div>';

    if (!$pid) return;

    $project   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id=%d", $pid));
    if (!$project) return;

    $revisions = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $revisions_table WHERE project_id=%d ORDER BY created_at DESC",
      $pid
    ));

    echo '<div class="ttb-card">';
    echo '<h3>' . esc_html($project->name) . '</h3>';
    echo '<p class="ttb-muted"><a href="' . esc_url($project->figma_url) . '" target="_blank">🔗 Ver Figma</a> &nbsp;·&nbsp; <a href="' . esc_url(TTB_WebRev_DB::client_url($project->token)) . '" target="_blank">👁️ Ver como cliente</a></p>';

    if (!$revisions) {
      echo '<p class="ttb-muted">Sin revisiones todavía.</p>';
    } else {
      echo '<div style="display:flex;flex-direction:column;gap:12px;margin-top:16px">';
      foreach ($revisions as $rev) {
        $is_acc = $rev->type === 'accept';
        $bg     = $is_acc ? '#ecfdf5' : '#fffbeb';
        $bc     = $is_acc ? '#6ee7b7' : '#fcd34d';
        $lbl    = $is_acc ? '✅ Diseño aceptado' : '✏️ Cambios solicitados — Ronda #' . $rev->round;

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
                  . wp_kses_post($bl['html'])
                  . '</div>';
              } elseif ($btype === 'image') {
                echo '<div style="border:1px solid var(--ttb-border);border-radius:10px;overflow:hidden">';
                if (!empty($bl['image_url'])) {
                  echo '<a href="' . esc_url($bl['image_url']) . '" target="_blank">'
                    . '<img src="' . esc_url($bl['image_url']) . '" style="width:100%;max-height:280px;object-fit:contain;display:block;background:#f4f4f4" alt="Adjunto">'
                    . '</a>';
                }
                if (!empty($bl['caption'])) {
                  echo '<div style="padding:8px 14px;font-size:14px;color:var(--ttb-text);line-height:1.6;border-top:1px solid var(--ttb-border)">'
                    . nl2br(esc_html($bl['caption']))
                    . '</div>';
                }
                echo '</div>';
              }
            }
            echo '</div>';
          } else {
            // Formato antiguo
            if ($rev->message) {
              echo '<p style="margin:10px 0 0;font-size:14px;color:var(--ttb-text);white-space:pre-line;line-height:1.6">' . nl2br(esc_html($rev->message)) . '</p>';
            }
            $old_imgs = json_decode((string)$rev->images, true);
            if (is_array($old_imgs) && $old_imgs) {
              echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">';
              foreach ($old_imgs as $img_url) {
                echo '<a href="' . esc_url($img_url) . '" target="_blank"><img src="' . esc_url($img_url) . '" style="height:80px;width:auto;border-radius:8px;border:1px solid rgba(0,0,0,.1);object-fit:cover" alt="Adjunto"></a>';
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

  /* ════════════════════════════════
     RENDER: CONFIGURACIÓN
  ════════════════════════════════ */
  private static function render_settings() {
    $action_url = esc_url(home_url('/briefing?section=revisiones-dis&wrtab=settings'));

    $days        = (int)get_option('ttb_webrev_resend_days',     7);
    $max_resends = (int)get_option('ttb_webrev_max_resends',     3);
    $hola        = (string)get_option('ttb_webrev_notify_hola',    'hola@tictac-comunicacion.es');
    $creativo    = (string)get_option('ttb_webrev_notify_creativo', 'creativo@tictac-comunicacion.es');
    $max_mb      = (int)get_option('ttb_webrev_max_filesize',    5);
    $max_files   = (int)get_option('ttb_webrev_max_files',       10);
    $subj        = (string)get_option('ttb_webrev_email_subject', '🎨 Tu diseño web está listo para revisar — TicTac Comunicación');
    $intro       = (string)get_option('ttb_webrev_email_intro',   'Hemos preparado el diseño de tu proyecto. Accede al enlace para revisarlo y darnos tu feedback.');
    $btn         = (string)get_option('ttb_webrev_email_btn',     'Ver mi diseño →');

    echo '<div class="ttb-card"><h3>Configuración</h3></div>';
    echo '<form method="post" action="' . $action_url . '">';
    wp_nonce_field('ttb_wr_settings');

    // ── Recordatorios automáticos ──
    echo '<div class="ttb-card">';
    echo '<h4 style="margin:0 0 16px">⏰ Recordatorios automáticos</h4>';
    echo '<div class="ttb-grid2">';
    echo '<div><label>Días entre reenvíos</label>
      <input class="ttb-input" type="number" name="ttb_webrev_resend_days" value="' . $days . '" min="1" max="60">
      <small class="ttb-muted">Si el cliente no responde, se reenvía el email cada N días.</small></div>';
    echo '<div><label>Máximo de reenvíos</label>
      <input class="ttb-input" type="number" name="ttb_webrev_max_resends" value="' . $max_resends . '" min="0" max="20">
      <small class="ttb-muted">0 = sin límite de reenvíos.</small></div>';
    echo '</div></div>';

    // ── Emails de notificación ──
    echo '<div class="ttb-card">';
    echo '<h4 style="margin:0 0 16px">📬 Emails internos de notificación</h4>';
    echo '<div class="ttb-grid2">';
    echo '<div><label>Email "hola@" (dirección general)</label>
      <input class="ttb-input" type="email" name="ttb_webrev_notify_hola" value="' . esc_attr($hola) . '"></div>';
    echo '<div><label>Email departamento creativo</label>
      <input class="ttb-input" type="email" name="ttb_webrev_notify_creativo" value="' . esc_attr($creativo) . '"></div>';
    echo '</div></div>';

    // ── Adjuntos ──
    echo '<div class="ttb-card">';
    echo '<h4 style="margin:0 0 16px">🖼️ Adjuntos del cliente</h4>';
    echo '<div class="ttb-grid2">';
    echo '<div><label>Tamaño máximo por imagen (MB)</label>
      <input class="ttb-input" type="number" name="ttb_webrev_max_filesize" value="' . $max_mb . '" min="1" max="50"></div>';
    echo '<div><label>Máximo de imágenes por revisión</label>
      <input class="ttb-input" type="number" name="ttb_webrev_max_files" value="' . $max_files . '" min="1" max="20"></div>';
    echo '</div></div>';

    // ── Email al cliente ──
    echo '<div class="ttb-card">';
    echo '<h4 style="margin:0 0 16px">✉️ Contenido del email al cliente</h4>';
    echo '<div class="ttb-formgrid">';
    echo '<div><label>Asunto del email</label>
      <input class="ttb-input" type="text" name="ttb_webrev_email_subject" value="' . esc_attr($subj) . '"></div>';
    echo '<div><label>Texto de introducción</label>
      <textarea class="ttb-textarea" name="ttb_webrev_email_intro" style="min-height:80px">' . esc_textarea($intro) . '</textarea></div>';
    echo '<div><label>Texto del botón</label>
      <input class="ttb-input" type="text" name="ttb_webrev_email_btn" value="' . esc_attr($btn) . '"></div>';
    echo '</div></div>';

    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_wr_settings" value="1">Guardar configuración</button></div>';
    echo '</form>';
  }

  /* ════════════════════════════════
     HELPERS
  ════════════════════════════════ */

  private static function sanitize_emails($raw) {
    if (!is_array($raw)) $raw = [$raw];
    return array_values(array_filter(array_map(function($e) {
      return sanitize_email(trim($e));
    }, $raw), 'is_email'));
  }

  private static function email_js() {
    ?>
    <script>
    (function(){
      function initEmailWidget(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;

        // Add email row
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

        // Remove on click (delegated)
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
        rows.forEach(function(row, i) {
          var btn = row.querySelector('.ttb-wr-remove-email');
          if (btn) btn.style.display = (rows.length > 1) ? 'inline-flex' : 'none';
        });
      }

      initEmailWidget('ttb-wr-emails-create');
      initEmailWidget('ttb-wr-emails-edit');
    })();
    </script>
    <?php
  }

  /* ════════════════════════════════
     RENDER: AUDITORÍA
  ════════════════════════════════ */
  private static function render_audit() {
    global $wpdb;
    $audit_table    = TTB_WebRev_DB::audit_table();
    $projects_table = TTB_WebRev_DB::projects_table();

    // ── Etiquetas legibles de eventos ──
    $event_labels = [
      'project_created'       => ['📁 Proyecto creado',          '#ecfdf5', '#6ee7b7', '#065f46'],
      'project_updated'       => ['✏️ Proyecto editado',          '#eff6ff', '#bfdbfe', '#1d4ed8'],
      'project_deleted'       => ['🗑️ Proyecto eliminado',        '#fff1f2', '#fecdd3', '#be123c'],
      'email_invitation_sent' => ['📧 Invitación enviada',        '#fdf4ff', '#e9d5ff', '#7e22ce'],
      'email_accepted_sent'   => ['📧 Email aceptación enviado',  '#ecfdf5', '#a7f3d0', '#065f46'],
      'email_changes_sent'    => ['📧 Email cambios enviado',     '#fffbeb', '#fde68a', '#92400e'],
      'design_accepted'       => ['✅ Diseño aceptado',           '#ecfdf5', '#6ee7b7', '#065f46'],
      'changes_requested'     => ['✏️ Cambios solicitados',       '#fffbeb', '#fcd34d', '#92400e'],
    ];

    $actor_labels = [
      'admin'  => ['👤 Admin',   '#eff6ff', '#1d4ed8'],
      'client' => ['🧑 Cliente', '#fdf4ff', '#7e22ce'],
      'cron'   => ['⏰ Cron',    '#f9fafb', '#374151'],
      'system' => ['⚙️ Sistema', '#f9fafb', '#374151'],
    ];

    // ── Filtros GET ──
    $f_project = (int)($_GET['f_project'] ?? 0);
    $f_event   = sanitize_text_field($_GET['f_event']   ?? '');
    $f_actor   = sanitize_text_field($_GET['f_actor']   ?? '');
    $f_from    = sanitize_text_field($_GET['f_from']    ?? '');
    $f_to      = sanitize_text_field($_GET['f_to']      ?? '');
    $f_page    = max(1, (int)($_GET['f_page'] ?? 1));
    $per_page  = 50;
    $offset    = ($f_page - 1) * $per_page;

    // ── Query dinámica ──
    $where  = ['1=1'];
    $params = [];

    if ($f_project) {
      $where[]  = 'a.project_id = %d';
      $params[] = $f_project;
    }
    if ($f_event) {
      $where[]  = 'a.event = %s';
      $params[] = $f_event;
    }
    if ($f_actor) {
      $where[]  = 'a.actor = %s';
      $params[] = $f_actor;
    }
    if ($f_from) {
      $where[]  = 'a.created_at >= %s';
      $params[] = $f_from . ' 00:00:00';
    }
    if ($f_to) {
      $where[]  = 'a.created_at <= %s';
      $params[] = $f_to . ' 23:59:59';
    }

    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*) FROM $audit_table a WHERE $where_sql";
    $total     = $params
      ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$params))
      : (int)$wpdb->get_var($count_sql);

    $rows_sql = "SELECT a.*, p.name AS project_name
                 FROM $audit_table a
                 LEFT JOIN $projects_table p ON p.id = a.project_id
                 WHERE $where_sql
                 ORDER BY a.created_at DESC
                 LIMIT %d OFFSET %d";

    $query_params = array_merge($params, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($rows_sql, ...$query_params));

    $total_pages = max(1, ceil($total / $per_page));

    // ── Proyectos para el selector ──
    $projects = $wpdb->get_results("SELECT id, name FROM $projects_table ORDER BY name ASC");

    // ── Formulario de filtros ──
    $base_url = home_url('/briefing?section=revisiones-dis&wrtab=audit');

    echo '<div class="ttb-card">';
    echo '<h3 style="margin:0 0 16px">🔍 Auditoría de eventos</h3>';
    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;align-items:end">';
    echo '<input type="hidden" name="section" value="revisiones-dis">';
    echo '<input type="hidden" name="wrtab" value="audit">';

    // Filtro: proyecto
    echo '<div>';
    echo '<label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Cliente / Proyecto</label>';
    echo '<select name="f_project" class="ttb-input" style="font-size:13px">';
    echo '<option value="">Todos</option>';
    foreach ($projects as $p) {
      echo '<option value="' . (int)$p->id . '" ' . selected($f_project, $p->id, false) . '>' . esc_html($p->name) . '</option>';
    }
    echo '</select></div>';

    // Filtro: evento
    echo '<div>';
    echo '<label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Tipo de evento</label>';
    echo '<select name="f_event" class="ttb-input" style="font-size:13px">';
    echo '<option value="">Todos</option>';
    foreach ($event_labels as $key => [$label]) {
      echo '<option value="' . esc_attr($key) . '" ' . selected($f_event, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></div>';

    // Filtro: actor
    echo '<div>';
    echo '<label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Actor</label>';
    echo '<select name="f_actor" class="ttb-input" style="font-size:13px">';
    echo '<option value="">Todos</option>';
    foreach ($actor_labels as $key => [$label]) {
      echo '<option value="' . esc_attr($key) . '" ' . selected($f_actor, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></div>';

    // Filtro: desde
    echo '<div>';
    echo '<label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Desde</label>';
    echo '<input type="date" name="f_from" class="ttb-input" value="' . esc_attr($f_from) . '" style="font-size:13px">';
    echo '</div>';

    // Filtro: hasta
    echo '<div>';
    echo '<label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Hasta</label>';
    echo '<input type="date" name="f_to" class="ttb-input" value="' . esc_attr($f_to) . '" style="font-size:13px">';
    echo '</div>';

    // Botones
    echo '<div style="display:flex;gap:8px;align-items:flex-end">';
    echo '<button class="ttb-btn" type="submit">Filtrar</button>';
    echo '<a href="' . esc_url($base_url) . '" class="ttb-btn ttb-btn--ghost">Limpiar</a>';
    echo '</div>';

    echo '</form>';

    // Contador
    echo '<p style="margin:14px 0 0;font-size:13px;color:var(--ttb-muted)">';
    echo '<strong>' . number_format($total) . '</strong> registros encontrados';
    if ($f_project || $f_event || $f_actor || $f_from || $f_to) echo ' con los filtros aplicados';
    echo '</p>';
    echo '</div>';

    // ── Tabla de registros ──
    if (!$rows) {
      echo '<div class="ttb-card"><p class="ttb-muted">No hay registros que coincidan con los filtros.</p></div>';
      return;
    }

    echo '<div class="ttb-card" style="padding:0;overflow:hidden">';
    echo '<div class="ttb-tablewrap">';
    echo '<table class="ttb-table" style="font-size:13px">';
    echo '<thead><tr>
      <th style="width:150px">Fecha y hora</th>
      <th>Evento</th>
      <th style="width:100px">Actor</th>
      <th>Cliente / Proyecto</th>
      <th>Detalle</th>
    </tr></thead><tbody>';

    foreach ($rows as $row) {
      [$ev_label, $ev_bg, $ev_bc, $ev_color] = $event_labels[$row->event] ?? [$row->event, '#f9fafb', '#e5e7eb', '#374151'];
      [$ac_label, $ac_bg, $ac_color]          = $actor_labels[$row->actor] ?? [$row->actor, '#f9fafb', '#374151'];

      // Detalle legible
      $detail_html = '';
      if ($row->detail) {
        $detail = json_decode($row->detail, true);
        if (is_array($detail)) {
          $parts = [];
          foreach ($detail as $k => $v) {
            $v_str = is_array($v) ? implode(', ', $v) : (string)$v;
            $parts[] = '<span style="color:var(--ttb-muted)">' . esc_html($k) . ':</span> <strong>' . esc_html($v_str) . '</strong>';
          }
          $detail_html = implode(' &nbsp;·&nbsp; ', $parts);
        }
      }

      echo '<tr>';
      echo '<td style="white-space:nowrap;color:var(--ttb-muted)">' . esc_html(date_i18n('d/m/Y H:i:s', strtotime($row->created_at))) . '</td>';
      echo '<td><span style="display:inline-block;font-size:12px;font-weight:800;padding:3px 10px;border-radius:999px;background:' . $ev_bg . ';border:1px solid ' . $ev_bc . ';color:' . $ev_color . '">' . esc_html($ev_label) . '</span></td>';
      echo '<td><span style="display:inline-block;font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;background:' . $ac_bg . ';color:' . $ac_color . '">' . esc_html($ac_label) . '</span></td>';
      echo '<td>' . ($row->project_name ? '<strong>' . esc_html($row->project_name) . '</strong>' : '<span style="color:var(--ttb-muted)">—</span>') . '</td>';
      echo '<td style="font-size:12px">' . ($detail_html ?: '<span style="color:var(--ttb-muted)">—</span>') . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table></div></div>';

    // ── Paginación ──
    if ($total_pages > 1) {
      echo '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:16px">';
      for ($p = 1; $p <= $total_pages; $p++) {
        $p_url = esc_url(add_query_arg([
          'section'   => 'revisiones-dis',
          'wrtab'     => 'audit',
          'f_project' => $f_project ?: '',
          'f_event'   => $f_event,
          'f_actor'   => $f_actor,
          'f_from'    => $f_from,
          'f_to'      => $f_to,
          'f_page'    => $p,
        ], home_url('/briefing')));
        $cls = ($p === $f_page) ? 'ttb-btn ttb-btn--sm' : 'ttb-btn ttb-btn--ghost ttb-btn--sm';
        echo '<a href="' . $p_url . '" class="' . $cls . '">' . $p . '</a>';
      }
      echo '</div>';
    }
  }
}