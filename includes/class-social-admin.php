<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Admin')) return;

class TTB_Social_Admin {

  private static $flash = null;

  private static function active_client_id() {
    return (int)($_GET['sc_client'] ?? 0);
  }

  private static function base_url($tab = '', $extra = []) {
    $params = ['section' => 'redes-sociales'];
    if ($tab) $params['sstab'] = $tab;
    $sc = self::active_client_id();
    if ($sc) $params['sc_client'] = $sc;
    foreach ($extra as $k => $v) {
      if ($v !== '' && $v !== null && $v !== 0) $params[$k] = $v;
    }
    return home_url('/briefing?' . http_build_query($params));
  }

  public static function event_catalog() {
    return [
      'client_created'       => ['Cliente creado',              '#ecfdf5','#6ee7b7','#065f46'],
      'client_updated'       => ['Cliente editado',             '#eff6ff','#bfdbfe','#1d4ed8'],
      'client_deleted'       => ['Cliente eliminado',           '#fff1f2','#fecdd3','#be123c'],
      'email_welcome_sent'   => ['Email bienvenida enviado',    '#fdf4ff','#e9d5ff','#7e22ce'],
      'email_approval_sent'  => ['Email aprobación enviado',    '#fdf4ff','#e9d5ff','#7e22ce'],
      'content_uploaded'     => ['Contenido subido',            '#eff6ff','#bfdbfe','#1d4ed8'],
      'post_created'         => ['Post creado',                 '#ecfdf5','#6ee7b7','#065f46'],
      'post_updated'         => ['Post editado',                '#eff6ff','#bfdbfe','#1d4ed8'],
      'post_deleted'         => ['Post eliminado',              '#fff1f2','#fecdd3','#be123c'],
      'post_notified'        => ['Notificación enviada',        '#fdf4ff','#e9d5ff','#7e22ce'],
      'week_notified'        => ['Semana notificada',           '#fdf4ff','#e9d5ff','#7e22ce'],
      'post_approved'        => ['Post aprobado',               '#ecfdf5','#6ee7b7','#065f46'],
      'post_rejected'        => ['Post rechazado',              '#fff1f2','#fecdd3','#be123c'],
      'post_published'       => ['Post marcado publicado',      '#eff6ff','#bfdbfe','#1d4ed8'],
      'post_auto_published'  => ['Auto-publicado por fecha',    '#eff6ff','#bfdbfe','#1d4ed8'],
      'post_auto_accepted'   => ['⏰ Auto-aceptado por plazo',  '#fffbeb','#fde68a','#92400e'],
      'post_draft_restored'  => ['Post vuelto a borrador',      '#f9fafb','#e5e7eb','#374151'],
      'nonce_failed'         => ['Nonce inválido',              '#fff1f2','#fecdd3','#be123c'],
      'invalid_token_access' => ['Token inválido',              '#fff1f2','#fecdd3','#be123c'],
      'cron_reminder_sent'   => ['Recordatorio cron',           '#f5f3ff','#ddd6fe','#5b21b6'],
      'cron_eve_reminder'    => ['Recordatorio víspera',        '#fff7ed','#fed7aa','#9a3412'],
      'audit_cleared'        => ['Auditoría limpiada',          '#fff1f2','#fecdd3','#be123c'],
      'client_view'          => ['Cliente accedió',             '#f0f9ff','#bae6fd','#0369a1'],
    ];
  }

  /* ══════════════════════════════════════════════════════════
     WYSIWYG — Estilos + helpers
  ══════════════════════════════════════════════════════════ */

  private static function inject_wysiwyg_styles() {
    static $injected = false;
    if ($injected) return;
    $injected = true;
    echo '<style>
/* ── WYSIWYG Social Admin ── */
.ttb-wy-wrap{border:1px solid var(--ttb-border);border-radius:12px;overflow:hidden;background:#fff;transition:border-color .2s,box-shadow .2s}
.ttb-wy-wrap:focus-within{border-color:var(--ttb-pink);box-shadow:0 0 0 3px rgba(215,33,115,.10)}
.ttb-wy-bar{display:flex;flex-wrap:wrap;gap:2px;padding:6px 8px;background:#f9fafb;border-bottom:1px solid var(--ttb-border)}
.ttb-wy-bar button{background:none;border:1px solid transparent;border-radius:6px;padding:3px 8px;font-size:13px;font-weight:700;cursor:pointer;color:var(--ttb-text);line-height:1.4;min-width:26px;transition:background .12s,border-color .12s}
.ttb-wy-bar button:hover{background:#e5e7eb;border-color:#d1d5db}
.ttb-wy-bar button.active{background:rgba(215,33,115,.12);border-color:rgba(215,33,115,.3);color:var(--ttb-pink)}
.ttb-wy-sep{width:1px;background:var(--ttb-border);margin:2px 3px;align-self:stretch;display:inline-block}
.ttb-wy-editor{min-height:80px;padding:10px 12px;outline:none;font-size:14px;line-height:1.7;color:var(--ttb-text);font-family:inherit}
.ttb-wy-editor--tall{min-height:120px}
.ttb-wy-editor:empty::before{content:attr(data-placeholder);color:#9ca3af;pointer-events:none}
.ttb-wy-editor ul,.ttb-wy-editor ol{padding-left:20px;margin:4px 0}
.ttb-wy-editor blockquote{border-left:3px solid var(--ttb-pink);margin:6px 0;padding:3px 10px;color:var(--ttb-muted);font-style:italic}
</style>';
  }

  private static function wysiwyg_field($hidden_name, $initial_html = '', $placeholder = '', $full = true, $tall = false) {
    static $wy_counter = 0;
    $wy_counter++;
    $editor_id = 'ttb-wy-ed-' . $wy_counter;
    $hidden_id = 'ttb-wy-hid-' . $wy_counter;

    $tall_cls = $tall ? ' ttb-wy-editor--tall' : '';

    echo '<div class="ttb-wy-wrap">';

    echo '<div class="ttb-wy-bar" data-editor="' . esc_attr($editor_id) . '">';
    echo '<button type="button" data-cmd="bold" title="Negrita"><b>N</b></button>';
    echo '<button type="button" data-cmd="italic" title="Cursiva"><i>C</i></button>';
    echo '<button type="button" data-cmd="underline" title="Subrayado"><u>S</u></button>';
    echo '<span class="ttb-wy-sep"></span>';
    echo '<button type="button" data-cmd="insertUnorderedList" title="Lista">• Lista</button>';
    echo '<button type="button" data-cmd="insertOrderedList" title="Lista numerada">1. Lista</button>';
    if ($full) {
      echo '<span class="ttb-wy-sep"></span>';
      echo '<button type="button" data-cmd="formatBlock|<blockquote>" title="Cita">❝ Cita</button>';
      echo '<button type="button" data-cmd="formatBlock|<p>" title="Párrafo normal">¶ Normal</button>';
      echo '<span class="ttb-wy-sep"></span>';
      echo '<button type="button" data-cmd="removeFormat" title="Limpiar formato">✕ Limpiar</button>';
    }
    echo '</div>';

    echo '<input type="hidden" id="' . esc_attr($hidden_id) . '" name="' . esc_attr($hidden_name) . '" value="' . esc_attr($initial_html) . '">';

    echo '<div class="ttb-wy-editor' . $tall_cls . '" '
       . 'id="' . esc_attr($editor_id) . '" '
       . 'contenteditable="true" '
       . 'data-hidden="' . esc_attr($hidden_id) . '" '
       . 'data-placeholder="' . esc_attr($placeholder) . '">';
    if ($initial_html) {
      echo wp_kses_post($initial_html);
    }
    echo '</div>';

    echo '</div>';

    return $editor_id;
  }

  private static function inject_wysiwyg_js() {
    static $js_injected = false;
    if ($js_injected) return;
    $js_injected = true;
    ?>
<script>
(function(){
  'use strict';

  function initWysiwyg() {
    document.querySelectorAll('.ttb-wy-bar').forEach(function(bar) {
      var edId  = bar.getAttribute('data-editor');
      var editor = document.getElementById(edId);
      if (!editor || bar._ttbWyInit) return;
      bar._ttbWyInit = true;

      bar.querySelectorAll('[data-cmd]').forEach(function(btn) {
        btn.addEventListener('mousedown', function(e) {
          e.preventDefault();
          var cmd = btn.getAttribute('data-cmd');
          if (cmd.indexOf('|') !== -1) {
            var parts = cmd.split('|');
            document.execCommand(parts[0], false, parts[1]);
          } else {
            document.execCommand(cmd, false, null);
          }
          editor.focus();
          syncHidden(editor);
          updateActiveStates(bar, editor);
        });
      });

      editor.addEventListener('input',  function() { syncHidden(editor); });
      editor.addEventListener('keyup',  function() { updateActiveStates(bar, editor); });
      editor.addEventListener('mouseup',function() { updateActiveStates(bar, editor); });
    });
  }

  function syncHidden(editor) {
    var hidId  = editor.getAttribute('data-hidden');
    var hidden = document.getElementById(hidId);
    if (hidden) hidden.value = editor.innerHTML;
  }

  function syncAllHidden() {
    document.querySelectorAll('.ttb-wy-editor[data-hidden]').forEach(function(editor) {
      syncHidden(editor);
    });
  }

  function updateActiveStates(bar, editor) {
    bar.querySelectorAll('[data-cmd]').forEach(function(btn) {
      var cmd = btn.getAttribute('data-cmd').split('|')[0];
      try { btn.classList.toggle('active', document.queryCommandState(cmd)); } catch(e) {}
    });
  }

  document.addEventListener('submit', function() {
    syncAllHidden();
  }, true);

  var observer = new MutationObserver(function() {
    initWysiwyg();
  });
  observer.observe(document.body, { childList: true, subtree: true });

  document.addEventListener('DOMContentLoaded', initWysiwyg);
  if (document.readyState !== 'loading') initWysiwyg();
})();
</script>
<?php
  }


  public static function render() {
    $tab = sanitize_text_field($_GET['sstab'] ?? 'clients');
    $sc  = self::active_client_id();

    self::handle_resend_welcome($tab);
    self::handle_week_create($tab);
    self::handle_post_edit($tab);
    self::handle_post_resend_edited($tab); // ── NUEVO: reenvío manual post editado
    self::handle_post_delete($tab);
    self::handle_post_status($tab);
    self::handle_settings_save($tab);
    self::handle_content_delete($tab);
    self::handle_audit_clear($tab);

    if (self::$flash) {
      $cls = self::$flash['type'] === 'success' ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html(self::$flash['text']) . '</div>';
    }

    self::render_client_selector($tab, $sc);

    echo '<div class="ttb-tabs">';
    self::tab_link('clients',   'Clientes activos',     $tab, $sc);
    self::tab_link('content',   'Contenido',            $tab, $sc);
    self::tab_link('calendar',  'Calendario',           $tab, $sc);
    self::tab_link('editorial', 'Calendario Editorial', $tab, $sc);
    self::tab_link('audit',     'Auditoría',            $tab, $sc);
    self::tab_link('settings',  'Configuración',        $tab, $sc);
    echo '</div>';

    switch ($tab) {
      case 'content':   self::render_content($sc);               break;
      case 'calendar':  self::render_calendar($sc);              break;
      case 'editorial': TTB_Social_Editorial_Admin::render($sc); break;
      case 'audit':     self::render_audit($sc);                 break;
      case 'settings':  self::render_settings();                 break;
      default:          self::render_clients($sc);               break;
    }
  }


  /* ════════════════════════════════
     SELECTOR GLOBAL DE CLIENTE
  ════════════════════════════════ */
  private static function render_client_selector($tab, $sc) {
    global $wpdb;
    $sc_table = TTB_Social_DB::clients_table();
    $clients  = $wpdb->get_results("SELECT id, name, status FROM $sc_table ORDER BY name ASC LIMIT 200");
    if (empty($clients)) return;

    $active_client = null;
    if ($sc) {
      foreach ($clients as $c) {
        if ((int)$c->id === $sc) { $active_client = $c; break; }
      }
    }

    echo '<div style="background:linear-gradient(135deg,#fdf2f7,#fff);border:2px solid rgba(215,33,115,.18);border-radius:18px;padding:16px 20px;margin-bottom:0;display:flex;align-items:center;gap:14px;flex-wrap:wrap">';
    echo '<div style="display:flex;align-items:center;gap:8px;flex-shrink:0">';
    echo '<span style="font-size:22px">👤</span>';
    echo '<span style="font-size:13px;font-weight:900;color:var(--ttb-pink);text-transform:uppercase;letter-spacing:.06em">Cliente activo</span>';
    echo '</div>';

    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:8px;align-items:center;flex:1;min-width:200px">';
    echo '<input type="hidden" name="section" value="redes-sociales">';
    echo '<input type="hidden" name="sstab" value="' . esc_attr($tab) . '">';
    echo '<select name="sc_client" class="ttb-input" style="flex:1;max-width:380px;font-weight:700" onchange="this.form.submit()">';
    echo '<option value="">— Ver todos los clientes —</option>';
    foreach ($clients as $c) {
      $inactive = $c->status !== 'active' ? ' (inactivo)' : '';
      echo '<option value="' . (int)$c->id . '"' . selected($sc, (int)$c->id, false) . '>'
        . esc_html($c->name . $inactive) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Filtrar</button>';
    if ($sc) {
      echo '<a href="' . esc_url(self::base_url($tab, ['sc_client' => ''])) . '" class="ttb-btn ttb-btn--danger ttb-btn--sm" title="Quitar filtro">✕</a>';
    }
    echo '</form>';

    if ($active_client) {
      global $wpdb;
      $token = $wpdb->get_var($wpdb->prepare(
        "SELECT token FROM " . TTB_Social_DB::clients_table() . " WHERE id=%d LIMIT 1",
        (int)$active_client->id
      ));
      echo '<div style="display:flex;align-items:center;gap:8px;flex-shrink:0">';
      echo '<span style="background:rgba(215,33,115,.12);border:1.5px solid rgba(215,33,115,.3);border-radius:10px;padding:6px 12px;font-size:13px;font-weight:800;color:var(--ttb-pink)">'
        . esc_html($active_client->name) . '</span>';
      if ($token) {
        echo '<a href="' . esc_url(TTB_Social_DB::client_url($token)) . '" target="_blank" class="ttb-btn ttb-btn--ghost ttb-btn--sm" title="Ver portal del cliente">👁️ Portal</a>';
      }
      echo '</div>';
    }

    echo '</div>';
    echo '<div style="height:1px;background:rgba(215,33,115,.10);margin:0 0 0"></div>';
  }

  private static function tab_link($key, $label, $active, $sc = 0) {
    $icon_map = [
      'clients'   => 'clients',
      'content'   => 'content',
      'calendar'  => 'calendar',
      'editorial' => 'calendar',
      'audit'     => 'audit',
      'settings'  => 'settings',
    ];
    $icon = ttb_icon($icon_map[$key] ?? '');
    $params = ['section' => 'redes-sociales', 'sstab' => $key];
    if ($sc) $params['sc_client'] = $sc;
    $url = esc_url(home_url('/briefing?' . http_build_query($params)));
    $cls = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . $url . '">' . $icon . esc_html($label) . '</a>';
  }

  private static function set_flash($type, $text) {
    self::$flash = ['type' => $type, 'text' => $text];
  }

  private static function action_url($tab = '') {
    $t  = $tab ?: (sanitize_text_field($_GET['sstab'] ?? 'clients'));
    $sc = self::active_client_id();
    $params = ['section' => 'redes-sociales', 'sstab' => $t];
    if ($sc) $params['sc_client'] = $sc;
    return esc_url(home_url('/briefing?' . http_build_query($params)));
  }

  public static function is_video_url($url) {
    if (!$url) return false;
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'mov', 'webm', 'avi', 'm4v'], true);
  }

  public static function is_video_mime($mime) {
    return strpos((string)$mime, 'video/') === 0;
  }

  private static function upload_single_creative($file_data, $date_label) {
    $max_mb = (int)get_option('ttb_social_max_filesize', 500);
    $mime   = $file_data['type'];
    $size   = $file_data['size'];

    if ($size > $max_mb * 1024 * 1024) {
      self::set_flash('error', 'El archivo "' . $file_data['name'] . '" supera el límite de ' . $max_mb . ' MB.');
      return false;
    }

    $allowed_mime = [
      'image/jpeg', 'image/png', 'image/gif', 'image/webp',
      'video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo',
    ];
    if (!in_array($mime, $allowed_mime, true)) {
      self::set_flash('error', 'Tipo de archivo no permitido.');
      return false;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $att_id = media_handle_sideload([
      'name'     => $file_data['name'],
      'type'     => $mime,
      'tmp_name' => $file_data['tmp_name'],
      'error'    => $file_data['error'],
      'size'     => $size,
    ], 0, null, [
      'post_title'  => 'Social Creative - ' . $date_label,
      'post_status' => 'private',
    ]);

    if (is_wp_error($att_id)) {
      self::set_flash('error', 'Error al subir el archivo: ' . $att_id->get_error_message());
      return false;
    }

    return wp_get_attachment_url($att_id) ?: null;
  }

  private static function process_post_files($slot_index, $date_label) {
    $max_per_post = 10;
    $urls         = [];
    $has_video    = false;

    $file_key = 'sp_creative_' . $slot_index;

    if (empty($_FILES[$file_key])) {
      return ['urls' => [], 'type' => 'post'];
    }

    $files_raw = $_FILES[$file_key];

    if (!is_array($files_raw['name'])) {
      $files_raw = [
        'name'     => [$files_raw['name']],
        'type'     => [$files_raw['type']],
        'tmp_name' => [$files_raw['tmp_name']],
        'error'    => [$files_raw['error']],
        'size'     => [$files_raw['size']],
      ];
    }

    $count = min(count($files_raw['name']), $max_per_post);

    for ($i = 0; $i < $count; $i++) {
      if ($files_raw['error'][$i] !== UPLOAD_ERR_OK || !$files_raw['tmp_name'][$i]) continue;

      $file_data = [
        'name'     => $files_raw['name'][$i],
        'type'     => $files_raw['type'][$i],
        'tmp_name' => $files_raw['tmp_name'][$i],
        'error'    => $files_raw['error'][$i],
        'size'     => $files_raw['size'][$i],
      ];

      $url = self::upload_single_creative($file_data, $date_label);
      if ($url === false) return false;
      if ($url) {
        $urls[] = $url;
        if (self::is_video_url($url) || self::is_video_mime($file_data['type'])) {
          $has_video = true;
        }
      }
    }

    if ($has_video && count($urls) === 1) {
      $type = 'video';
    } elseif (count($urls) > 1) {
      $type = 'carousel';
    } else {
      $type = 'post';
    }

    return ['urls' => $urls, 'type' => $type];
  }


  /* ════════════════════════════════
     ACCIONES POST
  ════════════════════════════════ */

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

  private static function handle_week_create(&$tab) {
    if (!isset($_POST['ttb_social_week_create'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_week_create')) return;

    $sc_id      = (int)($_POST['sp_client_id'] ?? 0);
    $posts_data = $_POST['sp_posts'] ?? [];

    if (!$sc_id || !is_array($posts_data) || empty($posts_data)) {
      self::set_flash('error', 'Selecciona un cliente y añade al menos un post.');
      $tab = 'calendar'; return;
    }

    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $sc_table    = TTB_Social_DB::clients_table();

    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sc_table WHERE id=%d", $sc_id));
    if (!$client) {
      self::set_flash('error', 'Cliente no encontrado.');
      $tab = 'calendar'; return;
    }

    $created_ids = [];

    foreach ($posts_data as $idx => $p) {
      $date      = sanitize_text_field($p['date'] ?? '');
      $copy      = wp_kses_post(wp_unslash($p['copy'] ?? ''));
      $note      = wp_kses_post(wp_unslash($p['note'] ?? ''));

      if (!$date) continue;

      $week_group = TTB_Social_DB::week_group_for_date($date);

      $file_result = self::process_post_files($idx, $date);
      if ($file_result === false) { $tab = 'calendar'; return; }

      $urls      = $file_result['urls'];
      $post_type = $file_result['type'];

      $creative_url  = $urls[0] ?? '';
      $creative_urls = !empty($urls) ? wp_json_encode($urls, JSON_UNESCAPED_UNICODE) : null;

      $wpdb->insert($posts_table, [
        'client_id'      => $sc_id,
        'scheduled_date' => $date,
        'network'        => 'all',
        'post_type'      => $post_type,
        'copy_text'      => $copy,
        'creative_url'   => $creative_url,
        'creative_urls'  => $creative_urls,
        'creative_note'  => $note,
        'week_group'     => $week_group,
        'status'         => 'pending_approval',
        'notified_at'    => TTB_Social_DB::now(),
        'created_at'     => TTB_Social_DB::now(),
        'updated_at'     => TTB_Social_DB::now(),
      ]);

      $post_id       = (int)$wpdb->insert_id;
      $created_ids[] = $post_id;
      TTB_Social_DB::log($sc_id, $post_id, 'post_created', 'admin', [
        'date'       => $date,
        'type'       => $post_type,
        'week_group' => $week_group,
        'files'      => count($urls),
      ]);
    }

    if (empty($created_ids)) {
      self::set_flash('error', 'No se ha creado ningún post. Comprueba las fechas.');
      $tab = 'calendar'; return;
    }

    $all_posts = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $posts_table WHERE id IN (" . implode(',', array_map('intval', $created_ids)) . ") ORDER BY scheduled_date ASC"
    ));

    if ($client && $all_posts) {
      (new TTB_Social_Mailer())->send_week_approval($client, $all_posts);
      foreach ($created_ids as $pid) {
        TTB_Social_DB::log($sc_id, $pid, 'post_notified', 'admin', ['trigger' => 'week_create']);
      }
      TTB_Social_DB::log($sc_id, null, 'email_approval_sent', 'admin', ['posts_count' => count($created_ids)]);
    }

    self::set_flash('success', count($created_ids) . ' publicación(es) creada(s) y notificación semanal enviada al cliente.');
    $tab = 'calendar';
  }

  private static function handle_post_edit(&$tab) {
    if (!isset($_POST['ttb_social_post_edit'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_post_edit')) return;

    $post_id  = (int)($_POST['sp_post_id'] ?? 0);
    $date     = sanitize_text_field($_POST['sp_date'] ?? '');
    $copy     = wp_kses_post(wp_unslash($_POST['sp_copy'] ?? ''));
    $note     = wp_kses_post(wp_unslash($_POST['sp_note'] ?? ''));
    $keep_url = esc_url_raw($_POST['sp_keep_creative_url'] ?? '');
    $keep_urls_raw = sanitize_text_field($_POST['sp_keep_creative_urls'] ?? '');
    $keep_urls = json_decode($keep_urls_raw, true);
    if (!is_array($keep_urls)) $keep_urls = $keep_url ? [$keep_url] : [];

    if (!$post_id || !$date) {
      self::set_flash('error', 'Datos incompletos.'); $tab = 'calendar'; return;
    }

    $new_urls = [];
    $has_new_video = false;
    $max_per_post  = 5;

    if (!empty($_FILES['sp_creative']['tmp_name'])) {
      $files_raw = $_FILES['sp_creative'];
      if (!is_array($files_raw['name'])) {
        $files_raw = [
          'name'     => [$files_raw['name']],
          'type'     => [$files_raw['type']],
          'tmp_name' => [$files_raw['tmp_name']],
          'error'    => [$files_raw['error']],
          'size'     => [$files_raw['size']],
        ];
      }
      $available_slots = $max_per_post - count($keep_urls);
      $count = min(count($files_raw['name']), max(0, $available_slots));

      for ($i = 0; $i < $count; $i++) {
        if ($files_raw['error'][$i] !== UPLOAD_ERR_OK || !$files_raw['tmp_name'][$i]) continue;
        $file_data = [
          'name'     => $files_raw['name'][$i],
          'type'     => $files_raw['type'][$i],
          'tmp_name' => $files_raw['tmp_name'][$i],
          'error'    => $files_raw['error'][$i],
          'size'     => $files_raw['size'][$i],
        ];
        $url = self::upload_single_creative($file_data, $date);
        if ($url === false) { $tab = 'calendar'; return; }
        if ($url) {
          $new_urls[] = $url;
          if (self::is_video_url($url) || self::is_video_mime($file_data['type'])) $has_new_video = true;
        }
      }
    }

    $all_urls = array_merge($keep_urls, $new_urls);

    if (count($all_urls) > 1) {
      $post_type = 'carousel';
    } elseif (!empty($all_urls) && (self::is_video_url($all_urls[0]) || $has_new_video)) {
      $post_type = 'video';
    } else {
      $post_type = 'post';
    }

    $creative_url  = $all_urls[0] ?? '';
    $creative_urls = !empty($all_urls) ? wp_json_encode($all_urls, JSON_UNESCAPED_UNICODE) : null;

    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $post        = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d", $post_id));
    if (!$post) { self::set_flash('error', 'Post no encontrado.'); $tab = 'calendar'; return; }

    // ── FIX: ya NO se cambia el estado ni se reenvía email automáticamente ──
    // Si el post estaba rechazado, lo dejamos en pending_approval silenciosamente,
    // pero el admin decide cuándo reenviar con el botón manual.
    $new_status = $post->status;
    if ($post->status === 'rejected') {
      $new_status = 'pending_approval';
    }

    $week_group = TTB_Social_DB::week_group_for_date($date);

    $wpdb->update($posts_table, [
      'scheduled_date' => $date,
      'copy_text'      => $copy,
      'creative_url'   => $creative_url,
      'creative_urls'  => $creative_urls,
      'creative_note'  => $note,
      'post_type'      => $post_type,
      'week_group'     => $week_group,
      'status'         => $new_status,
      'updated_at'     => TTB_Social_DB::now(),
    ], ['id' => $post_id]);

    TTB_Social_DB::log((int)$post->client_id, $post_id, 'post_updated', 'admin', [
      'date'  => $date,
      'type'  => $post_type,
      'files' => count($all_urls),
    ]);

    self::set_flash('success', 'Post actualizado. Usa el botón "Reenviar post editado" cuando quieras notificar al cliente.');
    $tab = 'calendar';
  }

  /**
   * NUEVO: Reenvío manual del post editado al cliente.
   * El admin lo activa desde el botón "📧 Reenviar post editado al cliente".
   */
  private static function handle_post_resend_edited(&$tab) {
    if (!isset($_POST['ttb_social_post_resend_edited'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_post_resend_edited')) return;

    $post_id = (int)($_POST['sp_post_id'] ?? 0);
    if (!$post_id) return;

    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $post        = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d", $post_id));
    if (!$post) { self::set_flash('error', 'Post no encontrado.'); $tab = 'calendar'; return; }

    $client = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_Social_DB::clients_table() . " WHERE id=%d", (int)$post->client_id
    ));
    if (!$client) { self::set_flash('error', 'Cliente no encontrado.'); $tab = 'calendar'; return; }

    // Asegurarse de que está en pending_approval antes de notificar
    if ($post->status !== 'pending_approval') {
      $wpdb->update($posts_table, [
        'status'     => 'pending_approval',
        'updated_at' => TTB_Social_DB::now(),
      ], ['id' => $post_id]);
    }

    $updated_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d", $post_id));

    (new TTB_Social_Mailer())->send_week_approval($client, [$updated_post]);
    $wpdb->update($posts_table, ['notified_at' => TTB_Social_DB::now()], ['id' => $post_id]);

    TTB_Social_DB::log((int)$post->client_id, $post_id, 'post_notified', 'admin', [
      'trigger' => 'manual_resend_after_edit',
    ]);
    TTB_Social_DB::log((int)$post->client_id, null, 'email_approval_sent', 'admin', [
      'posts_count' => 1,
      'trigger'     => 'resend_edited',
    ]);

    self::set_flash('success', 'Post editado reenviado al cliente para su aprobación.');
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

  private static function handle_audit_clear(&$tab) {
    if (!isset($_POST['ttb_social_audit_clear'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_audit_clear')) return;
    TTB_Social_DB::clear_audit();
    TTB_Social_DB::log(null, null, 'audit_cleared', 'admin', ['trigger' => 'manual']);
    self::set_flash('success', 'Registros de auditoría eliminados.');
    $tab = 'audit';
  }

  private static function handle_settings_save(&$tab) {
    if (!isset($_POST['ttb_social_settings'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_settings')) return;
    $fields = [
      'ttb_social_notify_social'    => sanitize_email($_POST['ttb_social_notify_social']    ?? ''),
      'ttb_social_notify_hola'      => sanitize_email($_POST['ttb_social_notify_hola']      ?? ''),
      'ttb_social_resend_days'      => max(1, (int)($_POST['ttb_social_resend_days']        ?? 2)),
      'ttb_social_max_resends'      => (int)($_POST['ttb_social_max_resends']               ?? 3),
      'ttb_social_max_filesize'     => max(1, min(500, (int)($_POST['ttb_social_max_filesize'] ?? 500))),
      'ttb_social_approval_subject' => sanitize_text_field($_POST['ttb_social_approval_subject'] ?? ''),
      'ttb_social_approval_note'    => sanitize_textarea_field($_POST['ttb_social_approval_note'] ?? ''),
      'ttb_social_eve_reminder'     => isset($_POST['ttb_social_eve_reminder']) ? '1' : '0',
    ];
    foreach ($fields as $key => $val) update_option($key, $val);
    self::set_flash('success', 'Configuración guardada.');
    $tab = 'settings';
  }


  /* ════════════════════════════════
     RENDER: CLIENTES (sin cambios)
  ════════════════════════════════ */
  private static function render_clients($sc = 0) {
    global $wpdb;
    $sc_table      = TTB_Social_DB::clients_table();
    $clients_table = TTB_DB::clients_table();
    $networks_all  = TTB_Social_DB::networks();
    $action_url    = self::action_url('clients');

    if ($sc) {
      $clients = $wpdb->get_results($wpdb->prepare(
        "SELECT sc.*, c.services AS central_services FROM $sc_table sc LEFT JOIN $clients_table c ON c.id = sc.ttb_client_id WHERE sc.id = %d LIMIT 1", $sc
      ));
    } else {
      $clients = $wpdb->get_results(
        "SELECT sc.*, c.services AS central_services FROM $sc_table sc LEFT JOIN $clients_table c ON c.id = sc.ttb_client_id ORDER BY sc.created_at DESC LIMIT 200"
      );
    }

    echo '<div class="ttb-card">';
    echo '<h3 style="margin:0 0 4px">Clientes activos en Redes Sociales</h3>';
    if ($sc) {
      echo '<p class="ttb-muted" style="margin:0">Mostrando el cliente seleccionado. <a href="' . esc_url(self::base_url('clients', ['sc_client' => ''])) . '" style="color:var(--ttb-pink)">Ver todos →</a></p>';
    } else {
      echo '<p class="ttb-muted" style="margin:0">Los clientes se gestionan desde la pestaña <strong>Clientes</strong>. Aquí puedes configurar redes y reenviar accesos.</p>';
    }
    echo '</div>';

    if (!$clients) {
      echo '<div class="ttb-card"><p class="ttb-muted">No hay clientes' . ($sc ? ' con ese filtro' : ' con servicio de redes sociales') . ' aún.</p></div>';
      return;
    }

    $edit_id = (int)($_GET['edit_sc'] ?? 0);
    $edit_c  = null;
    if ($edit_id) $edit_c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sc_table WHERE id=%d", $edit_id));

    if ($edit_c) {
      $edit_networks = json_decode((string)$edit_c->networks, true) ?: [];
      $edit_kit      = (int)($edit_c->kit_digital ?? 0);
      $cancel_url    = esc_url(self::base_url('clients'));

      echo '<div class="ttb-modal-overlay" id="ttbScEditModal" role="dialog" aria-modal="true" style="display:flex">';
      echo '<div class="ttb-modal ttb-edit-modal"><h3 class="ttb-edit-modal__title">Configurar redes: ' . esc_html($edit_c->name) . '</h3>';
      echo '<form method="post" action="' . $action_url . '" class="ttb-formgrid">';
      wp_nonce_field('ttb_social_client_edit');
      echo '<input type="hidden" name="sc_id" value="' . (int)$edit_c->id . '">';

      echo '<div style="margin-top:10px"><label>Redes que gestiona TicTac</label><div class="ttb-checks" style="margin-top:8px">';
      foreach ($networks_all as $k => [$label]) {
        $checked = in_array($k, $edit_networks, true) ? 'checked' : '';
        echo '<label class="ttb-check"><input type="checkbox" name="sc_networks[]" value="' . esc_attr($k) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
      }
      echo '</div></div>';

      echo '<div style="margin-top:10px"><label>Notas internas</label><input class="ttb-input" type="text" name="sc_notes" value="' . esc_attr($edit_c->notes ?? '') . '"></div>';

      echo '<div style="margin-top:10px"><label>Estado</label><div class="ttb-checks" style="margin-top:8px">';
      echo '<label class="ttb-check"><input type="radio" name="sc_status" value="active"' . ($edit_c->status === 'active' ? ' checked' : '') . '> Activo</label>';
      echo '<label class="ttb-check"><input type="radio" name="sc_status" value="inactive"' . ($edit_c->status === 'inactive' ? ' checked' : '') . '> Inactivo</label>';
      echo '</div></div>';

      echo '<div style="margin-top:14px;background:linear-gradient(135deg,#fefce8,#fff);border:1.5px solid #fde68a;border-radius:14px;padding:14px 18px">';
      echo '<label style="display:flex;align-items:center;gap:10px;cursor:pointer">';
      echo '<input type="checkbox" name="sc_kit_digital" value="1"' . ($edit_kit ? ' checked' : '') . ' style="width:18px;height:18px;accent-color:#d97706;flex-shrink:0">';
      echo '<span>';
      echo '<strong style="font-size:14px;color:#92400e;display:block;margin-bottom:2px">🏆 Kit Digital</strong>';
      echo '<span style="font-size:12px;color:#b45309">Marca si este cliente gestiona sus redes a través del programa Kit Digital.</span>';
      echo '</span></label>';
      echo '</div>';

      echo '<div class="ttb-actions" style="margin-top:16px">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_social_client_edit_networks" value="1">Guardar</button>';
      echo '</div></form></div></div>';
    }

    echo '<div class="ttb-card"><div class="ttb-tablewrap"><table class="ttb-table"><thead><tr><th>Cliente</th><th>Emails</th><th>Redes</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';

    foreach ($clients as $c) {
      $nets       = json_decode((string)$c->networks, true) ?: [];
      $nets_label = implode(', ', array_map(fn($n) => $networks_all[$n][0] ?? $n, $nets)) ?: '—';
      $emails_arr = json_decode((string)$c->emails, true) ?: [];
      $is_kit     = !empty($c->kit_digital);

      $edit_params = ['edit_sc' => (int)$c->id];
      if ($sc) $edit_params['sc_client'] = $sc;
      $edit_url   = esc_url(home_url('/briefing?section=redes-sociales&sstab=clients&' . http_build_query($edit_params)));
      $cal_url    = esc_url(self::base_url('calendar', ['sc_client' => (int)$c->id]));
      $portal_url = esc_url(TTB_Social_DB::client_url($c->token));

      $status_lbl = $c->status === 'active'
        ? '<span class="ttb-status ttb-status--sent">Activo</span>'
        : '<span class="ttb-status ttb-status--pending">Inactivo</span>';

      $kit_badge = $is_kit
        ? ' <span style="display:inline-block;font-size:10px;font-weight:900;padding:2px 8px;border-radius:999px;background:#fef9c3;border:1px solid #fde68a;color:#854d0e;vertical-align:middle;margin-left:4px">🏆 Kit Digital</span>'
        : '';

      $notes_html = $c->notes
        ? '<br><small style="color:var(--ttb-muted)">' . esc_html(mb_substr($c->notes, 0, 50)) . '</small>'
        : '';

      echo '<tr>';
      echo '<td><strong>' . esc_html($c->name) . '</strong>' . $kit_badge . $notes_html . '</td>';
      echo '<td style="font-size:13px">' . implode('<br>', array_map('esc_html', $emails_arr)) . '</td>';
      echo '<td style="font-size:13px">' . esc_html($nets_label) . '</td>';
      echo '<td>' . $status_lbl . '</td>';
      echo '<td><div class="ttb-row-actions">';
      echo '<a href="' . $portal_url . '" target="_blank" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Ver portal</a>';
      echo '<a href="' . $cal_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Calendario</a>';
      echo '<a href="' . $edit_url . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">Configurar redes</a>';
      echo '<form method="post" action="' . $action_url . '" style="margin:0">';
      wp_nonce_field('ttb_social_resend_welcome');
      echo '<input type="hidden" name="sc_id" value="' . (int)$c->id . '">';
      echo '<button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_social_resend_welcome" value="1">Reenviar acceso</button></form>';
      echo '</div></td></tr>';
    }

    echo '</tbody></table></div></div>';
  }


  /* ════════════════════════════════
     RENDER: CONTENIDO (sin cambios)
  ════════════════════════════════ */
  private static function render_content($sc = 0) {
    global $wpdb;
    $sc_table      = TTB_Social_DB::clients_table();
    $content_table = TTB_Social_DB::content_table();
    $action_url    = self::action_url('content');

    $filter_cid = $sc ?: (int)($_GET['filter_client'] ?? 0);

    echo '<div class="ttb-card"><h3>Contenido enviado por los clientes</h3>';
    echo '<p class="ttb-muted" style="margin:0 0 14px">Archivos y notas subidos para usar en las publicaciones.</p>';

    if (!$sc) {
      $clients = $wpdb->get_results("SELECT id, name FROM $sc_table WHERE status='active' ORDER BY name ASC");
      echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
      echo '<input type="hidden" name="section" value="redes-sociales"><input type="hidden" name="sstab" value="content">';
      echo '<select name="filter_client" class="ttb-input" style="max-width:260px"><option value="">— Todos —</option>';
      foreach ($clients as $c) echo '<option value="' . (int)$c->id . '" ' . selected($filter_cid, $c->id, false) . '>' . esc_html($c->name) . '</option>';
      echo '</select><button class="ttb-btn ttb-btn--ghost" type="submit">Filtrar</button></form>';
    }
    echo '</div>';

    $where = $filter_cid ? $wpdb->prepare('WHERE ct.client_id = %d', $filter_cid) : '';
    $items = $wpdb->get_results("SELECT ct.*, cl.name AS client_name FROM $content_table ct INNER JOIN $sc_table cl ON cl.id=ct.client_id $where ORDER BY ct.created_at DESC LIMIT 200");
    if (!$items) { echo '<div class="ttb-card"><p class="ttb-muted">No hay contenido aún.</p></div>'; return; }

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;padding:4px 0">';
    foreach ($items as $item) {
      $is_video = ($item->type === 'video') || self::is_video_url($item->file_url ?? '');
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
     RENDER: CALENDARIO — con WYSIWYG
  ════════════════════════════════ */
  private static function render_calendar($sc = 0) {
    global $wpdb;
    $sc_table    = TTB_Social_DB::clients_table();
    $posts_table = TTB_Social_DB::posts_table();
    $action_url  = self::action_url('calendar');
    $statuses    = TTB_Social_DB::post_statuses();

    $clients = $wpdb->get_results("SELECT id, name FROM $sc_table WHERE status='active' ORDER BY name ASC");

    $filter_client = $sc ?: (int)($_GET['filter_client'] ?? 0);
    $filter_month  = sanitize_text_field($_GET['filter_month']  ?? date('Y-m'));
    $max_mb        = (int)get_option('ttb_social_max_filesize', 50);
    $max_per_post  = 5;

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
      "SELECT p.*, c.name AS client_name FROM $posts_table p INNER JOIN $sc_table c ON c.id=p.client_id WHERE " . implode(' AND ', $where) . " ORDER BY p.scheduled_date ASC",
      ...$params
    ));

    $posts_by_day = [];
    foreach ($posts_raw as $p) {
      $posts_by_day[(int)date('j', strtotime($p->scheduled_date))][] = $p;
    }

    $active_client_name = '';
    if ($filter_client) {
      foreach ($clients as $c) {
        if ((int)$c->id === $filter_client) { $active_client_name = $c->name; break; }
      }
    }

    self::inject_wysiwyg_styles();
    self::inject_wysiwyg_js();

    echo '
    <style>
    .ttb-newpost-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);z-index:99998;align-items:flex-start;justify-content:center;padding:24px 16px 40px;overflow-y:auto}
    .ttb-newpost-overlay.active{display:flex}
    .ttb-newpost-modal{background:#fff;border-radius:22px;width:100%;max-width:680px;margin:auto;box-shadow:0 32px 80px rgba(0,0,0,.28);overflow:hidden;animation:ttbModalUp .35s cubic-bezier(.34,1.56,.64,1) both}
    .ttb-newpost-modal-header{background:linear-gradient(135deg,var(--ttb-pink) 0%,#a8005a 100%);padding:22px 28px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .ttb-newpost-modal-header h3{margin:0;color:#fff;font-size:18px;font-weight:900}
    .ttb-newpost-modal-header p{margin:4px 0 0;color:rgba(255,255,255,.80);font-size:13px}
    .ttb-newpost-modal-close{background:rgba(255,255,255,.18);border:none;border-radius:50%;width:34px;height:34px;font-size:18px;cursor:pointer;color:#fff;line-height:34px;text-align:center;flex-shrink:0;transition:background .15s}
    .ttb-newpost-modal-close:hover{background:rgba(255,255,255,.30)}
    .ttb-newpost-modal-body{padding:24px 28px 28px;max-height:72vh;overflow-y:auto}
    .ttb-admin-gallery{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
    .ttb-admin-gallery-item{position:relative;width:80px;height:80px;border-radius:8px;overflow:hidden;border:1px solid var(--ttb-border);background:#f0f0f0;flex-shrink:0}
    .ttb-admin-gallery-item img,.ttb-admin-gallery-item video{width:100%;height:100%;object-fit:cover;display:block}
    .ttb-admin-gallery-badge{position:absolute;top:3px;left:3px;background:rgba(0,0,0,.6);color:#fff;font-size:9px;font-weight:900;padding:2px 5px;border-radius:4px}
    .ttb-admin-gallery-remove{position:absolute;top:3px;right:3px;background:#e11d48;color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:9px;font-weight:900;cursor:pointer;line-height:18px;text-align:center;padding:0;z-index:10}
    .ttb-slot-preview{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
    .ttb-slot-preview-item{width:64px;height:64px;border-radius:8px;overflow:hidden;border:1px solid var(--ttb-border);background:#f0f0f0;position:relative}
    .ttb-slot-preview-item img{width:100%;height:100%;object-fit:cover;display:block}
    .ttb-slot-preview-rm{position:absolute;top:2px;right:2px;background:#e11d48;color:#fff;border:none;border-radius:50%;width:16px;height:16px;font-size:9px;cursor:pointer;line-height:16px;text-align:center;padding:0}

    /* ── Modal editar post: más ancho y con scroll ── */
    .ttb-sp-edit-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.52);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);z-index:99998;align-items:flex-start;justify-content:center;padding:24px 16px 40px;overflow-y:auto}
    .ttb-sp-edit-overlay.active{display:flex}
    .ttb-sp-edit-modal{background:#fff;border-radius:22px;width:100%;max-width:780px;margin:auto;box-shadow:0 32px 80px rgba(0,0,0,.28);overflow:hidden;animation:ttbModalUp .35s cubic-bezier(.34,1.56,.64,1) both}
    .ttb-sp-edit-modal-header{background:linear-gradient(135deg,var(--ttb-pink) 0%,#a8005a 100%);padding:20px 28px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0}
    .ttb-sp-edit-modal-header h3{margin:0;color:#fff;font-size:18px;font-weight:900}
    .ttb-sp-edit-modal-close{background:rgba(255,255,255,.18);border:none;border-radius:50%;width:34px;height:34px;font-size:18px;cursor:pointer;color:#fff;line-height:34px;text-align:center;flex-shrink:0;transition:background .15s}
    .ttb-sp-edit-modal-close:hover{background:rgba(255,255,255,.30)}
    .ttb-sp-edit-modal-body{padding:24px 28px 28px;max-height:calc(90vh - 80px);overflow-y:auto}
    </style>';

    // ── Modal nuevo post ──
    echo '<div class="ttb-newpost-overlay" id="ttb-newpost-overlay" role="dialog" aria-modal="true">';
    echo '<div class="ttb-newpost-modal">';
    echo '<div class="ttb-newpost-modal-header">';
    echo '<div><h3>📌 Nueva publicación semanal</h3>';
    if ($active_client_name) {
      echo '<p>👤 ' . esc_html($active_client_name) . '</p>';
    } else {
      echo '<p>Se notificará al cliente con un email agrupado por semana</p>';
    }
    echo '</div>';
    echo '<button type="button" class="ttb-newpost-modal-close" onclick="ttbCloseNewPost()">✕</button>';
    echo '</div>';
    echo '<div class="ttb-newpost-modal-body">';

    echo '<form method="post" action="' . $action_url . '" enctype="multipart/form-data" id="ttb-week-form">';
    wp_nonce_field('ttb_social_week_create');

    if (!$filter_client) {
      echo '<div style="margin-bottom:16px">';
      echo '<label>Cliente <span class="ttb-required">*</span></label>';
      echo '<select name="sp_client_id" class="ttb-input" required>';
      echo '<option value="">— Selecciona —</option>';
      foreach ($clients as $c) {
        echo '<option value="' . (int)$c->id . '">' . esc_html($c->name) . '</option>';
      }
      echo '</select></div>';
    } else {
      echo '<input type="hidden" name="sp_client_id" value="' . $filter_client . '">';
    }

    echo '<div id="ttb-posts-slots">';
    self::render_post_slot(0, $max_mb, $max_per_post);
    echo '</div>';

    echo '<div style="margin:14px 0">';
    echo '<button type="button" class="ttb-btn ttb-btn--ghost ttb-btn--sm" id="ttb-add-post-slot">+ Añadir otro post</button>';
    echo '</div>';

    echo '<div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--ttb-border)">';
    echo '<button type="button" class="ttb-btn ttb-btn--ghost" onclick="ttbCloseNewPost()">Cancelar</button>';
    echo '<button class="ttb-btn" name="ttb_social_week_create" value="1">📨 Crear y notificar al cliente</button>';
    echo '</div>';
    echo '</form>';
    echo '</div></div></div>';

    // ── JS modal nuevo post + slots dinámicos ──
    echo '<script>
    (function(){
      window.ttbOpenNewPost = function() {
        document.getElementById("ttb-newpost-overlay").classList.add("active");
        document.body.style.overflow = "hidden";
      };
      window.ttbCloseNewPost = function() {
        document.getElementById("ttb-newpost-overlay").classList.remove("active");
        document.body.style.overflow = "";
      };
      document.getElementById("ttb-newpost-overlay").addEventListener("click", function(e) {
        if (e.target === this) ttbCloseNewPost();
      });
      document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && document.getElementById("ttb-newpost-overlay").classList.contains("active"))
          ttbCloseNewPost();
      });

      var slotIdx = 1;
      var MAX_PER_POST = ' . $max_per_post . ';
      var MAX_MB = ' . $max_mb . ';

      function renderPreview(input, previewEl) {
        previewEl.innerHTML = "";
        var files = Array.from(input.files);
        if (!files.length) return;
        var limited = files.slice(0, MAX_PER_POST);
        limited.forEach(function(f) {
          var item = document.createElement("div");
          item.className = "ttb-slot-preview-item";
          item.title = f.name;
          if (f.type.startsWith("image/")) {
            var img = document.createElement("img");
            img.src = URL.createObjectURL(f);
            item.appendChild(img);
          } else {
            item.style.cssText = "background:#1a1a2e;display:flex;align-items:center;justify-content:center;font-size:20px";
            item.textContent = "🎬";
          }
          previewEl.appendChild(item);
        });
      }

      function bindSlot(idx) {
        var fi = document.getElementById("sp-fi-" + idx);
        var pr = document.getElementById("sp-prev-" + idx);
        if (!fi || !pr || fi._ttbBound) return;
        fi._ttbBound = true;
        fi.addEventListener("change", function() { renderPreview(fi, pr); });
      }
      bindSlot(0);

      document.getElementById("ttb-add-post-slot").addEventListener("click", function(){
        var container = document.getElementById("ttb-posts-slots");
        var slot = document.createElement("div");
        slot.className = "ttb-week-slot";
        slot.style.cssText = "border:1.5px solid var(--ttb-border);border-radius:14px;padding:16px;margin-bottom:12px;position:relative;background:#fff";

        var edId  = "ttb-wy-dyn-ed-" + slotIdx;
        var hidId = "ttb-wy-dyn-hid-" + slotIdx;
        var noteEdId  = "ttb-wy-dyn-note-ed-" + slotIdx;
        var noteHidId = "ttb-wy-dyn-note-hid-" + slotIdx;

        slot.innerHTML =
          \'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">\'
          + \'<strong style="font-size:14px;color:var(--ttb-text)">📌 Post #\' + (slotIdx + 1) + \'</strong>\'
          + \'<button type="button" class="ttb-btn ttb-btn--danger ttb-btn--sm" onclick="this.closest(\\\'.ttb-week-slot\\\').remove()">✕ Quitar</button>\'
          + \'</div>\'
          + \'<div class="ttb-grid2">\'
          + \'<div><label>Fecha <span class="ttb-required">*</span></label>\'
          + \'<input class="ttb-input" type="date" name="sp_posts[\' + slotIdx + \'][date]" required value="\' + new Date().toISOString().split("T")[0] + \'"></div>\'
          + \'<div><label>Archivos (máx. \' + MAX_PER_POST + \')</label>\'
          + \'<input class="ttb-input" type="file" name="sp_creative_\' + slotIdx + \'[]" id="sp-fi-\' + slotIdx + \'" data-slot="\' + slotIdx + \'" multiple style="width:100%;cursor:pointer" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm">\'
          + \'<div class="ttb-slot-preview" id="sp-prev-\' + slotIdx + \'"></div>\'
          + \'</div></div>\'
          + \'<div style="margin-top:10px">\'
          + \'<label>Copy</label>\'
          + \'<input type="hidden" id="\' + hidId + \'" name="sp_posts[\' + slotIdx + \'][copy]" value="">\'
          + \'<div class="ttb-wy-wrap">\'
          + \'<div class="ttb-wy-bar" data-editor="\' + edId + \'">\'
          + \'<button type="button" data-cmd="bold"><b>N</b></button>\'
          + \'<button type="button" data-cmd="italic"><i>C</i></button>\'
          + \'<button type="button" data-cmd="underline"><u>S</u></button>\'
          + \'<span class="ttb-wy-sep"></span>\'
          + \'<button type="button" data-cmd="insertUnorderedList">• Lista</button>\'
          + \'<button type="button" data-cmd="insertOrderedList">1. Lista</button>\'
          + \'<span class="ttb-wy-sep"></span>\'
          + \'<button type="button" data-cmd="formatBlock|<blockquote>">❝ Cita</button>\'
          + \'<button type="button" data-cmd="formatBlock|<p>">¶ Normal</button>\'
          + \'<span class="ttb-wy-sep"></span>\'
          + \'<button type="button" data-cmd="removeFormat">✕ Limpiar</button>\'
          + \'</div>\'
          + \'<div class="ttb-wy-editor ttb-wy-editor--tall" id="\' + edId + \'" contenteditable="true" data-hidden="\' + hidId + \'" data-placeholder="Texto de la publicación..."></div>\'
          + \'</div></div>\'
          + \'<div style="margin-top:10px">\'
          + \'<label>Nota para el cliente <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span></label>\'
          + \'<input type="hidden" id="\' + noteHidId + \'" name="sp_posts[\' + slotIdx + \'][note]" value="">\'
          + \'<div class="ttb-wy-wrap">\'
          + \'<div class="ttb-wy-bar" data-editor="\' + noteEdId + \'">\'
          + \'<button type="button" data-cmd="bold"><b>N</b></button>\'
          + \'<button type="button" data-cmd="italic"><i>C</i></button>\'
          + \'<button type="button" data-cmd="underline"><u>S</u></button>\'
          + \'<span class="ttb-wy-sep"></span>\'
          + \'<button type="button" data-cmd="insertUnorderedList">• Lista</button>\'
          + \'</div>\'
          + \'<div class="ttb-wy-editor" id="\' + noteEdId + \'" contenteditable="true" data-hidden="\' + noteHidId + \'" data-placeholder="Ej: He usado las fotos que nos mandaste."></div>\'
          + \'</div></div>\';

        container.appendChild(slot);
        bindSlot(slotIdx);
        slotIdx++;
        if (typeof initWysiwyg === "function") initWysiwyg();
      });
    })();
    </script>';

    // ── Modal editar post ── (REDISEÑADO: más ancho + scroll + fix X de imágenes)
    if ($edit_post) {
      $cancel_url      = esc_url(self::base_url('calendar', ['filter_month' => $filter_month]));
      $existing_urls   = TTB_Social_DB::get_post_creative_urls($edit_post);
      // Pasar las URLs como JSON seguro para JS
      $existing_urls_json_php = wp_json_encode($existing_urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $edit_copy_html  = $edit_post->copy_text ?? '';
      $edit_note_html  = $edit_post->creative_note ?? '';
      $post_date_fmt   = date_i18n('j/n/Y', strtotime($edit_post->scheduled_date));

      echo '<div class="ttb-sp-edit-overlay active" id="ttbSpEditOverlay" role="dialog" aria-modal="true">';
      echo '<div class="ttb-sp-edit-modal">';

      // Header
      echo '<div class="ttb-sp-edit-modal-header">';
      echo '<h3>✏️ Editar publicación — ' . esc_html($post_date_fmt) . '</h3>';
      echo '<a href="' . $cancel_url . '" class="ttb-sp-edit-modal-close" title="Cerrar">✕</a>';
      echo '</div>';

      // Body scrollable
      echo '<div class="ttb-sp-edit-modal-body">';
      echo '<form method="post" action="' . $action_url . '" enctype="multipart/form-data" id="ttbSpEditForm">';
      wp_nonce_field('ttb_social_post_edit');
      echo '<input type="hidden" name="sp_post_id" value="' . (int)$edit_post->id . '">';
      echo '<input type="hidden" name="sp_keep_creative_url" value="' . esc_attr($edit_post->creative_url ?? '') . '">';
      echo '<input type="hidden" name="sp_keep_creative_urls" id="sp-keep-urls" value="' . esc_attr($existing_urls_json_php) . '">';

      // Fecha
      echo '<div style="margin-bottom:16px"><label>Fecha</label>';
      echo '<input class="ttb-input" type="date" name="sp_date" value="' . esc_attr($edit_post->scheduled_date) . '" required></div>';

      // Galería de archivos existentes
      echo '<div style="margin-bottom:16px"><label>Archivos actuales</label>';
      if (!empty($existing_urls)) {
        echo '<p style="font-size:12px;color:var(--ttb-muted);margin:4px 0 8px">Haz clic en ✕ para eliminar un archivo. Los cambios se guardarán al guardar el post.</p>';
        echo '<div class="ttb-admin-gallery" id="ttb-edit-gallery">';
        foreach ($existing_urls as $idx => $eu_url) {
          $is_v = self::is_video_url($eu_url);
          echo '<div class="ttb-admin-gallery-item" id="ttb-eu-' . $idx . '">';
          if ($is_v) {
            echo '<video src="' . esc_url($eu_url) . '" muted style="width:100%;height:100%;object-fit:cover"></video>';
            echo '<span class="ttb-admin-gallery-badge">🎬</span>';
          } else {
            echo '<img src="' . esc_url($eu_url) . '" alt="">';
          }
          // FIX: usar data-url en lugar de pasar la URL como parámetro JS
          echo '<button type="button" class="ttb-admin-gallery-remove" data-idx="' . $idx . '" data-url="' . esc_attr($eu_url) . '" title="Eliminar este archivo">✕</button>';
          echo '</div>';
        }
        echo '</div>';
        echo '<p style="font-size:12px;color:var(--ttb-muted);margin-top:4px" id="ttb-gallery-count">' . count($existing_urls) . ' archivo(s) actual' . (count($existing_urls) === 1 ? '' : 'es') . '</p>';
      } else {
        echo '<p style="font-size:13px;color:var(--ttb-muted);margin-top:6px">Sin archivos.</p>';
      }
      echo '</div>';

      // Añadir más archivos
      $slots_remaining = $max_per_post - count($existing_urls);
      if ($slots_remaining > 0) {
        echo '<div style="margin-bottom:16px"><label>Añadir más archivos <span style="font-weight:400;color:var(--ttb-muted)">(máx. ' . $slots_remaining . ' adicional' . ($slots_remaining === 1 ? '' : 'es') . ')</span></label>';
        echo '<input class="ttb-input" type="file" name="sp_creative[]" multiple id="sp-edit-fi"
              accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm">';
        echo '<div class="ttb-slot-preview" id="sp-edit-preview"></div>';
        echo '</div>';
      }

      // Copy WYSIWYG
      echo '<div style="margin-bottom:16px"><label>Copy</label>';
      self::wysiwyg_field('sp_copy', $edit_copy_html, 'Texto de la publicación...', true, true);
      echo '</div>';

      // Nota WYSIWYG
      echo '<div style="margin-bottom:20px"><label>Nota para el cliente</label>';
      self::wysiwyg_field('sp_note', $edit_note_html, 'Ej: He usado las fotos que nos mandaste.', false, false);
      echo '</div>';

      // Acciones
      echo '<div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--ttb-border)">';
      echo '<a href="' . $cancel_url . '" class="ttb-btn ttb-btn--ghost">Cancelar</a>';
      echo '<button class="ttb-btn" name="ttb_social_post_edit" value="1">💾 Guardar cambios</button>';
      echo '</div>';

      echo '</form>';
      echo '</div>'; // .ttb-sp-edit-modal-body
      echo '</div>'; // .ttb-sp-edit-modal
      echo '</div>'; // .ttb-sp-edit-overlay

      // ── JS del modal de edición ── (FIX PRINCIPAL: eliminar imágenes existentes)
      $slots_remaining_js = $max_per_post - count($existing_urls);
      echo '<script>
      (function(){
        // ── FIX: gestionar eliminación de imágenes existentes con data-url ──
        var keepUrls = ' . $existing_urls_json_php . ';

        // Actualizar el campo hidden con las URLs actuales
        function updateKeepUrls() {
          document.getElementById("sp-keep-urls").value = JSON.stringify(keepUrls);
          var countEl = document.getElementById("ttb-gallery-count");
          if (countEl) countEl.textContent = keepUrls.length + " archivo(s) actual" + (keepUrls.length === 1 ? "" : "es");
        }

        // Bind botones de eliminar existentes
        document.querySelectorAll(".ttb-admin-gallery-remove").forEach(function(btn) {
          btn.addEventListener("click", function() {
            var urlToRemove = btn.getAttribute("data-url");
            var item = btn.closest(".ttb-admin-gallery-item");

            // Marcar visualmente como eliminado
            item.style.opacity = "0.3";
            item.style.pointerEvents = "none";
            btn.style.display = "none";

            // Quitar de la lista
            keepUrls = keepUrls.filter(function(u) { return u !== urlToRemove; });
            updateKeepUrls();
          });
        });

        // Preview de nuevos archivos
        var editFi   = document.getElementById("sp-edit-fi");
        var editPrev = document.getElementById("sp-edit-preview");
        var maxSlots = ' . $slots_remaining_js . ';
        if (editFi && editPrev) {
          editFi.addEventListener("change", function() {
            editPrev.innerHTML = "";
            Array.from(editFi.files).slice(0, maxSlots).forEach(function(f) {
              var item = document.createElement("div");
              item.className = "ttb-slot-preview-item";
              if (f.type.startsWith("image/")) {
                var img = document.createElement("img"); img.src = URL.createObjectURL(f); item.appendChild(img);
              } else {
                item.style.cssText = "background:#1a1a2e;display:flex;align-items:center;justify-content:center;font-size:20px";
                item.textContent = "🎬";
              }
              editPrev.appendChild(item);
            });
          });
        }

        // Cerrar con Escape
        document.addEventListener("keydown", function(e) {
          if (e.key === "Escape") {
            window.location.href = ' . wp_json_encode($cancel_url) . ';
          }
        });

        // Cerrar al clicar fuera del modal
        document.getElementById("ttbSpEditOverlay").addEventListener("click", function(e) {
          if (e.target === this) window.location.href = ' . wp_json_encode($cancel_url) . ';
        });
      })();
      </script>';
    }

    // ── Cabecera del calendario ──
    echo '<div class="ttb-card">';
    echo '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">';
    echo '<div style="display:flex;align-items:center;gap:10px">';
    echo '<a href="' . esc_url(self::base_url('calendar', ['filter_month' => $prev_month])) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8592;</a>';
    echo '<h3 style="margin:0;font-size:18px;text-transform:capitalize">' . esc_html($month_name) . '</h3>';
    echo '<a href="' . esc_url(self::base_url('calendar', ['filter_month' => $next_month])) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8594;</a>';
    echo '</div>';
    echo '<button type="button" class="ttb-btn" onclick="ttbOpenNewPost()" style="display:inline-flex;align-items:center;gap:8px;white-space:nowrap">✏️ Nueva publicación</button>';

    if (!$sc) {
      echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:8px;align-items:center">';
      echo '<input type="hidden" name="section" value="redes-sociales"><input type="hidden" name="sstab" value="calendar"><input type="hidden" name="filter_month" value="' . esc_attr($filter_month) . '">';
      echo '<select name="filter_client" class="ttb-input" style="min-width:160px"><option value="">Todos los clientes</option>';
      foreach ($clients as $c) echo '<option value="' . (int)$c->id . '" ' . selected($filter_client, $c->id, false) . '>' . esc_html($c->name) . '</option>';
      echo '</select><button class="ttb-btn ttb-btn--ghost ttb-btn--sm" type="submit">Filtrar</button></form>';
    } else {
      if ($active_client_name) {
        echo '<span style="font-size:13px;font-weight:700;color:var(--ttb-muted)">Mostrando: <span style="color:var(--ttb-pink)">' . esc_html($active_client_name) . '</span></span>';
      }
    }
    echo '</div>';

    self::render_posts_data_store($posts_raw, $action_url, $statuses, $filter_month, $filter_client);
    self::render_calendar_grid($posts_by_day, $days_in, $start_dow, $year, $month, $action_url, $statuses, $filter_month, $filter_client, true);

    echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px;font-size:12px">';
    foreach ([['#fffbeb','#fde68a','#92400e','Pendiente aprobación'],['#ecfdf5','#6ee7b7','#065f46','Aprobado'],['#fff1f2','#fecdd3','#be123c','Rechazado'],['#eff6ff','#bfdbfe','#1d4ed8','Publicado']] as [$bg,$bc,$co,$lbl]) {
      echo '<span style="display:inline-flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:' . $bg . ';border:1px solid ' . $bc . ';display:inline-block"></span><span style="color:var(--ttb-muted)">' . esc_html($lbl) . '</span></span>';
    }
    echo '</div></div>';
  }


  private static function render_post_slot($idx, $max_mb, $max_per_post = 5) {
    echo '<div class="ttb-week-slot" style="border:1.5px solid var(--ttb-border);border-radius:14px;padding:16px;margin-bottom:12px;background:#fff">';
    echo '<strong style="font-size:14px;color:var(--ttb-text);display:block;margin-bottom:12px">📌 Post #' . ($idx + 1) . '</strong>';
    echo '<div class="ttb-grid2">';

    echo '<div><label>Fecha <span class="ttb-required">*</span></label>';
    echo '<input class="ttb-input" type="date" name="sp_posts[' . $idx . '][date]" required value="' . esc_attr(date('Y-m-d')) . '"></div>';

    echo '<div>';
    echo '<label>Archivos <span style="font-weight:400;color:var(--ttb-muted)">(máx. ' . $max_per_post . ')</span></label>';
    echo '<input class="ttb-input" type="file"'
      . ' name="sp_creative_' . $idx . '[]"'
      . ' id="sp-fi-' . $idx . '"'
      . ' data-slot="' . $idx . '"'
      . ' multiple'
      . ' accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm"'
      . ' style="width:100%;cursor:pointer">';
    echo '<small class="ttb-muted" style="display:block;margin-top:4px">JPG, PNG, GIF, WEBP, MP4, MOV · Hasta ' . $max_per_post . ' archivos · Máx. ' . $max_mb . ' MB c/u</small>';
    echo '<div class="ttb-slot-preview" id="sp-prev-' . $idx . '"></div>';
    echo '</div>';

    echo '</div>';

    echo '<div style="margin-top:10px">';
    echo '<label>Copy (texto de la publicación)</label>';
    self::wysiwyg_field(
      'sp_posts[' . $idx . '][copy]',
      '',
      'Texto que acompañará a la publicación...',
      true,
      true
    );
    echo '</div>';

    echo '<div style="margin-top:10px">';
    echo '<label>Nota para el cliente <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span></label>';
    self::wysiwyg_field(
      'sp_posts[' . $idx . '][note]',
      '',
      'Ej: He usado las fotos que nos mandaste.',
      false,
      false
    );
    echo '</div>';

    echo '</div>';
  }


  private static function render_posts_data_store($posts, $action_url, $statuses, $filter_month, $filter_client) {
    echo '<div id="ttb-posts-store" style="display:none">';
    foreach ($posts as $post) {
      [$sl,$sbg,$sbc,$sco] = $statuses[$post->status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];
      $date_fmt    = date_i18n('l, j \d\e F \d\e Y', strtotime($post->scheduled_date));
      $sc          = self::active_client_id();
      $back_params = ['filter_month' => $filter_month];
      if ($filter_client && !$sc) $back_params['filter_client'] = $filter_client;
      $edit_url    = esc_url(self::base_url('calendar', array_merge(['edit_sp' => (int)$post->id], $back_params)));
      $copy_html   = $post->copy_text ?? '';
      $week_label  = '';
      if ($post->week_group) $week_label = 'Semana ' . TTB_Social_DB::week_range_label($post->week_group);

      $all_urls = TTB_Social_DB::get_post_creative_urls($post);
      $url_count = count($all_urls);

      ob_start();
      ?>
      <div id="ttb-post-data-<?php echo (int)$post->id; ?>">
        <div style="margin-bottom:14px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
            <strong style="font-size:16px"><?php echo esc_html($post->client_name ?? ''); ?></strong>
            <span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;background:<?php echo $sbg; ?>;border:1px solid <?php echo $sbc; ?>;color:<?php echo $sco; ?>"><?php echo esc_html($sl); ?></span>
            <?php if ($post->post_type === 'carousel' || $url_count > 1): ?>
              <span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8">🖼️ <?php echo $url_count; ?> archivos</span>
            <?php elseif ($post->post_type === 'video'): ?>
              <span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;background:#1a1a2e;color:#fff">🎬 Vídeo</span>
            <?php endif; ?>
          </div>
          <p style="margin:0;font-size:13px;color:var(--ttb-muted)"><?php echo esc_html($date_fmt); ?></p>
          <?php if ($week_label): ?><p style="margin:4px 0 0;font-size:12px;color:var(--ttb-pink);font-weight:700"><?php echo esc_html($week_label); ?></p><?php endif; ?>
        </div>

        <?php if (!empty($all_urls)): ?>
          <?php if ($url_count === 1): ?>
            <?php $is_vid = self::is_video_url($all_urls[0]); ?>
            <div style="border-radius:12px;overflow:hidden;margin-bottom:14px;border:1px solid var(--ttb-border)">
              <?php if ($is_vid): ?>
                <video src="<?php echo esc_url($all_urls[0]); ?>" controls style="width:100%;max-height:320px;display:block;background:#111"></video>
              <?php else: ?>
                <a href="<?php echo esc_url($all_urls[0]); ?>" target="_blank"><img src="<?php echo esc_url($all_urls[0]); ?>" style="width:100%;max-height:300px;object-fit:cover;display:block" alt="Creatividad"></a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="ttb-admin-gallery" style="margin-bottom:14px">
              <?php foreach ($all_urls as $i => $u): ?>
                <?php $is_v = self::is_video_url($u); ?>
                <div class="ttb-admin-gallery-item">
                  <?php if ($is_v): ?>
                    <video src="<?php echo esc_url($u); ?>" muted style="width:100%;height:100%;object-fit:cover"></video>
                    <span class="ttb-admin-gallery-badge">🎬</span>
                  <?php else: ?>
                    <a href="<?php echo esc_url($u); ?>" target="_blank"><img src="<?php echo esc_url($u); ?>" alt="Archivo <?php echo $i+1; ?>"></a>
                    <span class="ttb-admin-gallery-badge"><?php echo $i+1; ?></span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($copy_html): ?>
          <div style="background:#f9fafb;border-radius:10px;padding:12px;margin-bottom:12px;font-size:14px;color:var(--ttb-text);line-height:1.7;border-left:3px solid var(--ttb-pink)">
            <?php echo wp_kses_post($copy_html); ?>
          </div>
        <?php endif; ?>

        <?php if ($post->creative_note): ?>
          <div style="background:#fdf4ff;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#7e22ce;border:1px solid #e9d5ff">
            <?php echo wp_kses_post($post->creative_note); ?>
          </div>
        <?php endif; ?>

        <?php if ($post->status === 'rejected' && $post->client_note): ?>
          <div style="background:#fff1f2;border-radius:10px;padding:12px;margin-bottom:12px;font-size:13px;color:#be123c;border:1px solid #fecdd3">
            <strong style="display:block;margin-bottom:4px">Comentario del cliente:</strong><?php echo nl2br(esc_html($post->client_note)); ?>
          </div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;border-top:1px solid var(--ttb-border);padding-top:14px">
          <a href="<?php echo $edit_url; ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">✏️ Editar</a>
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
            <button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_social_post_delete" value="1">🗑️ Eliminar</button>
          </form>
        </div>
      </div>
      <?php
      echo ob_get_clean();
    }
    echo '</div>';
  }


  public static function render_calendar_grid($posts_by_day, $days_in, $start_dow, $year, $month, $action_url, $statuses, $filter_month, $filter_client, $is_admin) {
    $today_day    = (int)date('j');
    $today_month  = (int)date('m');
    $today_year   = (int)date('Y');
    $is_cur_month = ($today_year === $year && $today_month === $month);
    $day_names    = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    ?>
    <style>
    .ttb-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
    .ttb-cal-dayname{text-align:center;font-size:11px;font-weight:900;color:var(--ttb-muted);text-transform:uppercase;letter-spacing:.06em;padding:6px 0}
    .ttb-cal-cell{min-height:90px;border-radius:10px;border:1px solid var(--ttb-border);background:#fff;padding:6px;overflow:hidden}
    .ttb-cal-cell.is-today{border-color:var(--ttb-pink);background:rgba(215,33,115,.03)}
    .ttb-cal-cell.is-empty{background:#f9fafb;border-color:transparent}
    .ttb-cal-daynumber{font-size:12px;font-weight:900;color:var(--ttb-muted);margin-bottom:4px;line-height:1}
    .ttb-cal-cell.is-today .ttb-cal-daynumber{color:var(--ttb-pink)}
    .ttb-cal-post-chip{border-radius:6px;padding:3px 6px;margin-bottom:3px;font-size:11px;font-weight:700;line-height:1.3;cursor:pointer;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:opacity .15s}
    .ttb-cal-post-chip:hover{opacity:.8}
    .ttb-cal-chip-more{font-size:10px;font-weight:700;color:var(--ttb-muted);padding:2px 4px;display:block}
    .ttb-post-detail-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:99999;padding:16px}
    .ttb-post-detail{background:#fff;border-radius:20px;padding:28px;max-width:540px;width:100%;max-height:85vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.2);position:relative;animation:ttbModalUp .3s cubic-bezier(.34,1.56,.64,1) both}
    .ttb-post-detail::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--ttb-pink),#e63a86);border-radius:20px 20px 0 0}
    .ttb-post-detail-close{position:absolute;top:14px;right:16px;background:#f3f4f6;border:none;border-radius:50%;width:28px;height:28px;font-size:14px;cursor:pointer;line-height:28px;text-align:center;color:var(--ttb-muted)}
    .ttb-post-detail-close:hover{background:#e5e7eb}
    </style>
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
          $all_urls   = TTB_Social_DB::get_post_creative_urls($post);
          $url_count  = count($all_urls);
          $chip_label = ($post->post_type === 'video') ? '🎬' : ($url_count > 1 ? '🖼️×' . $url_count : '📌');
          echo '<span class="ttb-cal-post-chip" style="background:' . $sbg . ';border:1px solid ' . $sbc . ';color:' . $sco . '" data-post-id="' . (int)$post->id . '" title="' . esc_attr(($post->client_name ?? 'Post') . ' — ' . $sl) . '">' . $chip_label . ' ' . esc_html(mb_substr($post->client_name ?? '', 0, 8)) . '</span>';
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
      document.querySelectorAll('.ttb-cal-post-chip[data-post-id]').forEach(function(chip){
        chip.addEventListener('click',function(){ ttbOpenPostDetail(chip.getAttribute('data-post-id')); });
      });
      window.ttbOpenPostDetail=function(postId){
        var store=document.getElementById('ttb-post-data-'+postId)||document.getElementById('ttb-client-post-data-'+postId);
        if(!store)return;
        var overlay=document.getElementById('ttb-post-detail-overlay'),body=document.getElementById('ttb-post-detail-body');
        body.innerHTML='';
        var clone=store.cloneNode(true);clone.style.display='block';clone.removeAttribute('id');body.appendChild(clone);
        body.querySelectorAll('video').forEach(function(v){v.load();});
        overlay.style.display='flex';
        document.addEventListener('keydown',ttbEscPostDetail);
      };
      window.ttbClosePostDetail=function(){
        var overlay=document.getElementById('ttb-post-detail-overlay');
        overlay.querySelectorAll('video').forEach(function(v){v.pause();});
        overlay.style.display='none';
        document.removeEventListener('keydown',ttbEscPostDetail);
      };
      function ttbEscPostDetail(e){if(e.key==='Escape')ttbClosePostDetail();}
      document.getElementById('ttb-post-detail-overlay').addEventListener('click',function(e){if(e.target===this)ttbClosePostDetail();});
    })();
    </script>
    <?php
  }


  private static function render_audit($sc = 0) {
    global $wpdb;
    $audit_table = TTB_Social_DB::audit_table();
    $sc_table    = TTB_Social_DB::clients_table();
    $catalog     = self::event_catalog();
    $actor_labels = [
      'admin'  => ['Admin',   '#eff6ff','#1d4ed8'],
      'client' => ['Cliente', '#fdf4ff','#7e22ce'],
      'cron'   => ['Cron',    '#f9fafb','#374151'],
      'system' => ['Sistema', '#f9fafb','#374151'],
    ];

    $f_client = $sc ?: (int)($_GET['f_client'] ?? 0);
    $f_event  = sanitize_text_field($_GET['f_event']  ?? '');
    $f_actor  = sanitize_text_field($_GET['f_actor']  ?? '');
    $f_from   = sanitize_text_field($_GET['f_from']   ?? '');
    $f_to     = sanitize_text_field($_GET['f_to']     ?? '');
    $f_search = sanitize_text_field($_GET['f_search'] ?? '');
    $f_page   = max(1, (int)($_GET['f_page'] ?? 1));
    $per_page = 50; $offset = ($f_page - 1) * $per_page;

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
    $ws = implode(' AND ', $where);

    $total = $params
      ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $audit_table a LEFT JOIN $sc_table c ON c.id=a.client_id WHERE $ws", ...$params))
      : (int)$wpdb->get_var("SELECT COUNT(*) FROM $audit_table a LEFT JOIN $sc_table c ON c.id=a.client_id WHERE $ws");
    $rows  = $wpdb->get_results($wpdb->prepare(
      "SELECT a.*, c.name AS client_name FROM $audit_table a LEFT JOIN $sc_table c ON c.id=a.client_id WHERE $ws ORDER BY a.created_at DESC LIMIT %d OFFSET %d",
      ...array_merge($params, [$per_page, $offset])
    ));
    $total_pages = max(1, ceil($total / $per_page));
    $all_clients = $wpdb->get_results("SELECT id, name FROM $sc_table ORDER BY name ASC");
    $base_url    = home_url('/briefing?section=redes-sociales&sstab=audit');

    echo '<div class="ttb-card"><h3 style="margin:0 0 4px">Auditoría — Redes Sociales</h3>';
    echo '<p class="ttb-muted" style="margin:0 0 18px">Registro completo de actividad del módulo Redes Sociales.</p>';

    echo '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:16px">';
    $stats = $wpdb->get_results("SELECT event, COUNT(*) as cnt FROM $audit_table GROUP BY event ORDER BY cnt DESC LIMIT 6");
    if ($stats) {
      echo '<div style="display:flex;flex-wrap:wrap;gap:8px">';
      foreach ($stats as $s) {
        [$label] = $catalog[$s->event] ?? [$s->event];
        echo '<div style="background:#f9fafb;border:1px solid var(--ttb-border);border-radius:10px;padding:8px 14px;font-size:13px"><span style="font-weight:900;color:var(--ttb-text)">' . (int)$s->cnt . '</span> <span style="color:var(--ttb-muted)">' . esc_html($label) . '</span></div>';
      }
      echo '</div>';
    }
    $clear_url = esc_url(self::base_url('audit'));
    echo '<form method="post" action="' . $clear_url . '" style="margin:0" onsubmit="return confirm(\'⚠️ ¿Seguro que quieres eliminar TODOS los registros de auditoría?\')">';
    wp_nonce_field('ttb_social_audit_clear');
    echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_social_audit_clear" value="1">🗑️ Limpiar registros</button>';
    echo '</form></div>';

    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;align-items:end">';
    echo '<input type="hidden" name="section" value="redes-sociales"><input type="hidden" name="sstab" value="audit">';
    if ($sc) echo '<input type="hidden" name="sc_client" value="' . $sc . '">';

    if (!$sc) {
      echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Cliente</label><select name="f_client" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
      foreach ($all_clients as $c) echo '<option value="' . (int)$c->id . '" ' . selected($f_client, $c->id, false) . '>' . esc_html($c->name) . '</option>';
      echo '</select></div>';
    }
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
      echo '<div class="ttb-card"><p class="ttb-muted" style="text-align:center;padding:24px">No hay registros.</p></div>';
    } else {
      echo '<div class="ttb-card" style="padding:0;overflow:hidden"><div class="ttb-tablewrap"><table class="ttb-table" style="font-size:13px"><thead><tr>';
      echo '<th>Fecha</th><th>Evento</th><th>Actor</th><th>Cliente</th><th>Detalle</th></tr></thead><tbody>';
      foreach ($rows as $row) {
        [$ev_lbl,$ev_bg,$ev_bc,$ev_co] = $catalog[$row->event] ?? [$row->event,'#f9fafb','#e5e7eb','#374151'];
        [$ac_lbl,$ac_bg,$ac_co]        = $actor_labels[$row->actor] ?? [$row->actor,'#f9fafb','#374151'];
        $det='—';
        if ($row->detail) {
          $d=json_decode($row->detail,true);
          if(is_array($d)){$p=[];foreach($d as $k=>$v){if(is_array($v))$v=implode(',',$v);$p[]='<span style="color:var(--ttb-muted)">'.esc_html($k).':</span> <strong>'.esc_html((string)$v).'</strong>';}$det=implode(' · ',$p);}
          else $det=esc_html($row->detail);
        }
        echo '<tr>';
        echo '<td style="white-space:nowrap;color:var(--ttb-muted)">'.esc_html(date_i18n('d/m/Y H:i',strtotime($row->created_at))).'</td>';
        echo '<td><span style="display:inline-block;font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;background:'.$ev_bg.';border:1px solid '.$ev_bc.';color:'.$ev_co.'">'.esc_html($ev_lbl).'</span></td>';
        echo '<td><span style="display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:'.$ac_bg.';color:'.$ac_co.'">'.esc_html($ac_lbl).'</span></td>';
        echo '<td>'.esc_html($row->client_name??'—').'</td>';
        echo '<td style="font-size:12px;max-width:280px">'.$det.'</td></tr>';
      }
      echo '</tbody></table></div></div>';
    }

    if ($total_pages > 1) {
      echo '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:16px">';
      for ($p = max(1,$f_page-3); $p <= min($total_pages,$f_page+3); $p++) {
        $u = esc_url(add_query_arg(['section'=>'redes-sociales','sstab'=>'audit','f_client'=>$f_client?:'','f_event'=>$f_event,'f_page'=>$p>1?$p:''], home_url('/briefing')));
        echo '<a href="'.$u.'" class="'.($p===$f_page?'ttb-btn ttb-btn--sm':'ttb-btn ttb-btn--ghost ttb-btn--sm').'">'.$p.'</a>';
      }
      echo '</div>';
    }
  }

  private static function render_settings() {
    $action_url    = self::action_url('settings');
    $notify_social = (string)get_option('ttb_social_notify_social', 'comunicacion@tictac-comunicacion.es');
    $notify_hola   = (string)get_option('ttb_social_notify_hola',   'hola@tictac-comunicacion.es');
    $resend_days   = (int)get_option('ttb_social_resend_days',      2);
    $max_resends   = (int)get_option('ttb_social_max_resends',      3);
    $max_mb        = (int)get_option('ttb_social_max_filesize',     50);
    $approval_subj = (string)get_option('ttb_social_approval_subject', 'Creatividad lista para revisar — TicTac Comunicación');
    $approval_note = (string)get_option('ttb_social_approval_note', '');
    $eve_reminder  = (string)get_option('ttb_social_eve_reminder',  '0');

    echo '<div class="ttb-card"><h3>Configuración — Redes Sociales</h3></div>';
    echo '<form method="post" action="' . $action_url . '">';
    wp_nonce_field('ttb_social_settings');
    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Emails internos</h4><div class="ttb-grid2">';
    echo '<div><label>Email departamento redes</label><input class="ttb-input" type="email" name="ttb_social_notify_social" value="' . esc_attr($notify_social) . '"></div>';
    echo '<div><label>Email general (hola@)</label><input class="ttb-input" type="email" name="ttb_social_notify_hola" value="' . esc_attr($notify_hola) . '"></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Recordatorios automáticos</h4><div class="ttb-grid2">';
    echo '<div><label>Días entre recordatorios semanales</label><input class="ttb-input" type="number" name="ttb_social_resend_days" value="' . $resend_days . '" min="1" max="30"><small class="ttb-muted">Si el cliente no aprueba los posts de la semana, se reenvía cada N días.</small></div>';
    echo '<div><label>Máximo de recordatorios</label><input class="ttb-input" type="number" name="ttb_social_max_resends" value="' . $max_resends . '" min="0" max="20"><small class="ttb-muted">0 = sin límite.</small></div>';
    echo '</div>';
    echo '<div style="margin-top:16px;background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;padding:16px 20px">';
    echo '<label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:700;color:#9a3412">';
    echo '<input type="checkbox" name="ttb_social_eve_reminder" value="1"' . ($eve_reminder === '1' ? ' checked' : '') . ' style="width:18px;height:18px">';
    echo '📅 Recordatorio un día antes de la publicación';
    echo '</label>';
    echo '<p class="ttb-muted" style="margin:6px 0 0;font-size:13px">Si está activado, se envía un email al cliente recordándole las publicaciones del día siguiente (si aún no las ha aprobado).</p>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Archivos</h4>';
    echo '<div><label>Tamaño máximo por archivo (MB)</label><input class="ttb-input" type="number" name="ttb_social_max_filesize" value="' . $max_mb . '" min="1" max="500"></div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">Email de aprobación al cliente</h4><div class="ttb-formgrid">';
    echo '<div><label>Asunto</label><input class="ttb-input" type="text" name="ttb_social_approval_subject" value="' . esc_attr($approval_subj) . '"></div>';
    echo '<div><label>Nota extra (opcional)</label><textarea class="ttb-textarea" name="ttb_social_approval_note" style="min-height:70px">' . esc_textarea($approval_note) . '</textarea></div>';
    echo '</div></div>';
    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_social_settings" value="1">Guardar configuración</button></div>';
    echo '</form>';
  }

  public static function handle_client_edit_networks_action() {
    if (!isset($_POST['ttb_social_client_edit_networks'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_client_edit')) return;

    $id          = (int)($_POST['sc_id']     ?? 0);
    $networks    = array_map('sanitize_text_field', (array)($_POST['sc_networks'] ?? []));
    $notes       = sanitize_textarea_field($_POST['sc_notes']   ?? '');
    $status      = in_array($_POST['sc_status'] ?? '', ['active','inactive'], true) ? $_POST['sc_status'] : 'active';
    $kit_digital = isset($_POST['sc_kit_digital']) ? 1 : 0;

    if (!$id) return;

    global $wpdb;
    $wpdb->update(TTB_Social_DB::clients_table(), [
      'networks'    => wp_json_encode(array_values($networks)),
      'notes'       => $notes,
      'status'      => $status,
      'kit_digital' => $kit_digital,
      'updated_at'  => TTB_Social_DB::now(),
    ], ['id' => $id]);

    TTB_Social_DB::log($id, null, 'client_updated', 'admin', ['networks' => $networks, 'kit_digital' => $kit_digital]);

    $sc = self::active_client_id();
    $redirect_params = ['section' => 'redes-sociales', 'sstab' => 'clients'];
    if ($sc) $redirect_params['sc_client'] = $sc;
    set_transient('ttb_admin_flash', ['type' => 'success', 'text' => 'Configuración de redes actualizada.'], 60);

    $redirect_url = home_url('/briefing?' . http_build_query($redirect_params));
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($redirect_url)) . ');</script>';
    exit;
  }
}