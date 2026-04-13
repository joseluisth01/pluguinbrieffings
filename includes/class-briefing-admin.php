<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Briefing_Admin')) return;

/**
 * TTB_Briefing_Admin
 * Panel de administración para el módulo Briefing (post-prebriefing).
 *
 * v1.1:
 * - Eliminado campo "Servicio asociado" (los servicios ya están en el cliente).
 * - Selector de clientes con buscador en tiempo real (JS nativo, sin dependencias).
 * - Soporte para múltiples documentos PDF/DOC por briefing.
 *   Se almacenan como JSON array en doc_url: [{"url":..,"name":..,"mime":..}, ...]
 */
class TTB_Briefing_Admin {

  private static $flash = null;

  private static function flash_and_redirect($type, $text, $url = null) {
    set_transient('ttb_briefing_admin_flash', ['type' => $type, 'text' => $text], 60);
    if (!$url) $url = home_url('/briefing?section=briefing-doc&brtab=list');
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) {
      header('Location: ' . esc_url_raw($url), true, 302);
      exit;
    }
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($url)) . ');</script>';
    exit;
  }

  private static function tab_url($tab) {
    return esc_url(home_url('/briefing?section=briefing-doc&brtab=' . $tab));
  }

  public static function render() {
    $tab = sanitize_text_field($_GET['brtab'] ?? 'list');

    self::handle_create($tab);
    self::handle_delete($tab);
    self::handle_resend($tab);
    self::handle_settings_save($tab);

    $flash = get_transient('ttb_briefing_admin_flash');
    if ($flash) {
      delete_transient('ttb_briefing_admin_flash');
      $cls = $flash['type'] === 'success' ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html($flash['text']) . '</div>';
    }

    echo '<div class="ttb-tabs">';
    self::tab_link('list',     'Briefings',     $tab);
    self::tab_link('audit',    'Auditoría',     $tab);
    self::tab_link('settings', 'Configuración', $tab);
    echo '</div>';

    switch ($tab) {
      case 'audit':    self::render_audit();    break;
      case 'settings': self::render_settings(); break;
      default:         self::render_list();     break;
    }
  }

  private static function tab_link($key, $label, $active) {
    $icon_map = ['list' => 'briefings', 'audit' => 'audit', 'settings' => 'settings'];
    $icon = ttb_icon($icon_map[$key] ?? '');
    $cls  = ($key === $active) ? 'ttb-tab ttb-tab--active' : 'ttb-tab';
    echo '<a class="' . $cls . '" href="' . self::tab_url($key) . '">' . $icon . esc_html($label) . '</a>';
  }

  // ══════════════════════════════════════════════
  // HANDLE: CREAR
  // ══════════════════════════════════════════════

  private static function handle_create(&$tab) {
    if (!isset($_POST['ttb_briefing_create'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_briefing_create')) return;

    $client_id = (int)($_POST['br_client_id'] ?? 0);
    $title     = sanitize_text_field($_POST['br_title'] ?? '');

    if (!$client_id) self::flash_and_redirect('error', 'Selecciona un cliente.');

    global $wpdb;
    $client = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", $client_id
    ));
    if (!$client) self::flash_and_redirect('error', 'Cliente no encontrado.');

    $emails = json_decode((string)($client->emails ?? ''), true) ?: [$client->email];

    // Subir múltiples archivos
    $docs = self::upload_docs($client->name, $title);
    if ($docs === false) return; // ya redirigió con error

    $doc_url_json = !empty($docs) ? wp_json_encode($docs, JSON_UNESCAPED_UNICODE) : null;
    $doc_name     = $docs[0]['name'] ?? null;
    $doc_mime     = $docs[0]['mime'] ?? null;

    $token = TTB_Briefing_DB::generate_token();
    $table = TTB_Briefing_DB::briefings_table();

    $wpdb->insert($table, [
      'ttb_client_id'    => $client_id,
      'service'          => 'general',
      'title'            => $title ?: ('Briefing — ' . $client->name),
      'doc_url'          => $doc_url_json,
      'doc_name'         => $doc_name,
      'doc_mime'         => $doc_mime,
      'drive_folder_url' => null, // ya no se usa: se usa carpeta raíz fija en TTB_Briefing_Drive
      'token'            => $token,
      'status'           => 'pending',
      'notified_at'      => TTB_Briefing_DB::now(),
      'notif_count'      => 1,
      'created_at'       => TTB_Briefing_DB::now(),
      'updated_at'       => TTB_Briefing_DB::now(),
    ]);

    $briefing_id = (int)$wpdb->insert_id;

    // Crear carpeta Drive automáticamente (carpeta raíz fija, sin necesidad de URL manual)
    if ($briefing_id) {
      $drive  = new TTB_Briefing_Drive();
      $result = $drive->setup_client_folder($client->name, $emails);
      if ($result) {
        $wpdb->update($table, [
          'shared_folder_id'  => $result['folder_id'],
          'shared_folder_url' => $result['folder_url'],
          'updated_at'        => TTB_Briefing_DB::now(),
        ], ['id' => $briefing_id]);
        TTB_Briefing_DB::log($briefing_id, $client_id, 'folder_created', 'admin', ['folder_id' => $result['folder_id']]);
        TTB_Briefing_DB::log($briefing_id, $client_id, 'folder_shared',  'admin', ['emails'    => $emails]);

        // Subir docs del briefing a la carpeta BRIEFINGS - [nombre cliente] (sin compartir)
        $briefings_folder_id = $result['briefings_folder_id'] ?? null;
        if ($briefings_folder_id) {

          // 1. Docs adjuntos del briefing (PDFs/DOCs que subió el admin)
          if (!empty($docs)) {
            $uploaded_to_drive = 0;
            foreach ($docs as $doc) {
              $doc_url_d  = $doc['url']  ?? '';
              $doc_name_d = $doc['name'] ?? 'briefing.pdf';
              $doc_mime_d = $doc['mime'] ?? 'application/pdf';
              if (!$doc_url_d) continue;
              $file_path = self::url_to_path($doc_url_d);
              if (!$file_path || !file_exists($file_path)) {
                error_log('TTB Briefing: archivo no localizado en disco: ' . $doc_url_d);
                continue;
              }
              $fid = $drive->upload_file_to_folder($briefings_folder_id, $file_path, $doc_name_d, $doc_mime_d);
              if ($fid) { $uploaded_to_drive++; error_log('TTB Briefing: doc subido → ' . $fid); }
            }
            if ($uploaded_to_drive > 0) {
              TTB_Briefing_DB::log($briefing_id, $client_id, 'docs_uploaded_to_drive', 'admin', [
                'count' => $uploaded_to_drive, 'folder_id' => $briefings_folder_id,
              ]);
            }
          }

          // 2. Resumen del prebriefing como archivo HTML
          $pb_html = self::generate_prebriefing_html($client);
          if ($pb_html) {
            $tmp_path = sys_get_temp_dir() . '/ttb_prebriefing_' . $client_id . '_' . time() . '.html';
            if (file_put_contents($tmp_path, $pb_html) !== false) {
              $pb_filename = 'Prebriefing - ' . $client->name . '.html';
              $pb_fid = $drive->upload_file_to_folder($briefings_folder_id, $tmp_path, $pb_filename, 'text/html');
              if ($pb_fid) {
                error_log('TTB Briefing: prebriefing HTML subido a Drive → ' . $pb_fid);
                TTB_Briefing_DB::log($briefing_id, $client_id, 'prebriefing_uploaded_to_drive', 'admin', [
                  'folder_id' => $briefings_folder_id, 'drive_file_id' => $pb_fid,
                ]);
              }
              @unlink($tmp_path);
            }
          }
        }

      } else {
        TTB_Logger::log('Briefing: fallo al crear carpeta Drive', ['briefing_id' => $briefing_id]);
      }
    }

    $briefing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $briefing_id));
    if ($briefing) {
      (new TTB_Briefing_Mailer())->send_briefing_ready($briefing, $client);
      TTB_Briefing_DB::log($briefing_id, $client_id, 'briefing_created', 'admin', [
        'title' => $briefing->title, 'docs' => count($docs), 'emails' => $emails,
      ]);
      TTB_Briefing_DB::log($briefing_id, $client_id, 'email_sent', 'admin', ['emails' => $emails]);
    }

    self::flash_and_redirect('success', 'Briefing creado y email enviado al cliente.');
  }

  /**
   * Genera un HTML con todas las respuestas del prebriefing del cliente.
   * Se sube a Drive como archivo de referencia interna en la carpeta BRIEFINGS.
   */
  private static function generate_prebriefing_html($client) {
    $client_id = (int)$client->id;
    $services  = json_decode((string)($client->services ?? ''), true);
    if (!is_array($services) || empty($services)) return null;

    $lang = in_array($client->lang ?? '', ['es', 'en'], true) ? $client->lang : 'es';

    $sections = [];
    foreach ($services as $svc) {
      if (!class_exists('TTB_Forms')) continue;
      $payload = TTB_Forms::get_client_answers($client_id, $svc);
      $answers = $payload['answers'] ?? null;
      $sent    = (int)($payload['sent'] ?? 0);
      if (!$answers || !$sent) continue;

      $schema = TTB_Forms::get_schema($svc, $lang);
      $rows   = '';
      foreach ($schema as $field) {
        $fid   = $field['id']    ?? ''; if (!$fid) continue;
        $label = $field['label'] ?? $fid;
        $val   = $answers[$fid]  ?? '';
        if (is_array($val)) $val = implode(', ', $val);
        $val_esc = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
        $rows .= '<tr>'
               . '<td style="width:40%;padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;font-weight:600;color:#374151;vertical-align:top">'
               . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
               . '</td>'
               . '<td style="padding:8px 12px;border:1px solid #e5e7eb;color:#1a1a2e;white-space:pre-wrap">'
               . ($val_esc ?: '<em style="color:#9ca3af">Sin respuesta</em>')
               . '</td></tr>';
      }
      if ($rows) {
        $svc_labels = ['design'=>'Diseño','social'=>'Redes Sociales','seo'=>'SEO','web'=>'Web','reservas'=>'Reservas'];
        $sections[] = '<h2 style="margin:28px 0 10px;font-size:16px;color:#D72173;border-bottom:2px solid #D72173;padding-bottom:6px">'
                    . htmlspecialchars($svc_labels[$svc] ?? strtoupper($svc), ENT_QUOTES, 'UTF-8')
                    . '</h2>'
                    . '<table style="width:100%;border-collapse:collapse;font-size:13px;font-family:Arial,sans-serif">'
                    . $rows . '</table>';
      }
    }

    if (empty($sections)) return null;

    $client_name_h = htmlspecialchars($client->name, ENT_QUOTES, 'UTF-8');
    $date          = date('d/m/Y H:i');

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
         . '<title>Prebriefing — ' . $client_name_h . '</title>'
         . '<style>body{margin:32px 40px;font-family:Arial,sans-serif;color:#1a1a2e}'
         . 'h1{font-size:22px;color:#D72173;margin:0 0 4px}'
         . '.meta{font-size:12px;color:#9ca3af;margin:0 0 24px}'
         . '</style></head><body>'
         . '<h1>Prebriefing — ' . $client_name_h . '</h1>'
         . '<p class="meta">Generado el ' . $date . ' · TicTac Comunicación</p>'
         . implode('', $sections)
         . '</body></html>';
  }

  /**
   * Convierte una URL de adjunto de WordPress a su ruta absoluta en el servidor.
   * Necesario para leer el archivo y subirlo a Drive.
   */
  private static function url_to_path($url) {
    $upload_dir  = wp_upload_dir();
    $base_url    = $upload_dir['baseurl'];
    $base_dir    = $upload_dir['basedir'];

    // Normalizar URL (quitar protocolo para comparación)
    $url_no_proto  = preg_replace('#^https?://#', '//', $url);
    $base_no_proto = preg_replace('#^https?://#', '//', $base_url);

    if (strpos($url_no_proto, $base_no_proto) === 0) {
      $relative = substr($url_no_proto, strlen($base_no_proto));
      return $base_dir . $relative;
    }

    return null;
  }

  /**
   * Sube todos los archivos de br_docs[].
   * @return array|false  Array de ['url','name','mime'] o false si hay error de tipo.
   */
  private static function upload_docs($client_name, $title) {
    $allowed_mime = [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $docs      = [];
    $files_raw = $_FILES['br_docs'] ?? [];
    if (empty($files_raw['name'])) return $docs;

    $count = count((array)$files_raw['name']);
    for ($i = 0; $i < $count; $i++) {
      $error    = $files_raw['error'][$i]    ?? UPLOAD_ERR_NO_FILE;
      $tmp_name = $files_raw['tmp_name'][$i] ?? '';
      $name     = $files_raw['name'][$i]     ?? '';
      $type     = $files_raw['type'][$i]     ?? '';
      $size     = $files_raw['size'][$i]     ?? 0;

      if ($error !== UPLOAD_ERR_OK || !$tmp_name) continue;

      if (!in_array($type, $allowed_mime, true)) {
        self::flash_and_redirect('error', 'El archivo "' . esc_html($name) . '" no está permitido. Solo PDF, DOC y DOCX.');
        return false;
      }

      $att_id = media_handle_sideload([
        'name'     => $name,
        'type'     => $type,
        'tmp_name' => $tmp_name,
        'error'    => $error,
        'size'     => $size,
      ], 0, null, [
        'post_title'  => 'Briefing - ' . $client_name . ($title ? ' - ' . $title : '') . ' #' . ($i + 1),
        // 'inherit' = accesible por URL directa (necesario para el <iframe> del portal TTB).
        // La privacidad la gestiona el sistema de autenticación propio de TTB (tokens).
        'post_status' => 'inherit',
      ]);

      if (is_wp_error($att_id)) {
        self::flash_and_redirect('error', 'Error al subir "' . esc_html($name) . '": ' . $att_id->get_error_message());
        return false;
      }

      $url = wp_get_attachment_url($att_id);
      if ($url) $docs[] = ['url' => $url, 'name' => $name, 'mime' => $type];
    }

    return $docs;
  }

  // ══════════════════════════════════════════════
  // HANDLE: ELIMINAR / REENVIAR / SETTINGS
  // ══════════════════════════════════════════════

  private static function handle_delete(&$tab) {
    if (!isset($_POST['ttb_briefing_delete'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_briefing_delete')) return;
    $id = (int)($_POST['br_id'] ?? 0); if (!$id) return;
    global $wpdb;
    $b = $wpdb->get_row($wpdb->prepare("SELECT ttb_client_id, title FROM " . TTB_Briefing_DB::briefings_table() . " WHERE id=%d", $id));
    $wpdb->delete(TTB_Briefing_DB::briefings_table(), ['id' => $id]);
    TTB_Briefing_DB::log($id, $b->ttb_client_id ?? null, 'briefing_deleted', 'admin', ['title' => $b->title ?? '']);
    self::flash_and_redirect('success', 'Briefing eliminado.');
  }

  private static function handle_resend(&$tab) {
    if (!isset($_POST['ttb_briefing_resend'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_briefing_resend')) return;
    $id = (int)($_POST['br_id'] ?? 0); if (!$id) return;
    global $wpdb;
    $table    = TTB_Briefing_DB::briefings_table();
    $briefing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
    if (!$briefing) return;
    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", (int)$briefing->ttb_client_id));
    if (!$client) return;
    (new TTB_Briefing_Mailer())->send_briefing_ready($briefing, $client);
    $wpdb->update($table, ['notified_at' => TTB_Briefing_DB::now(), 'notif_count' => (int)$briefing->notif_count + 1, 'updated_at' => TTB_Briefing_DB::now()], ['id' => $id]);
    TTB_Briefing_DB::log($id, $briefing->ttb_client_id, 'email_sent', 'admin', ['trigger' => 'manual_resend']);
    self::flash_and_redirect('success', 'Email de briefing reenviado.');
  }

  private static function handle_settings_save(&$tab) {
    if (!isset($_POST['ttb_briefing_settings'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_briefing_settings')) return;
    $fields = [
      'ttb_briefing_notify_a'                => sanitize_email($_POST['ttb_briefing_notify_a']                ?? ''),
      'ttb_briefing_notify_b'                => sanitize_email($_POST['ttb_briefing_notify_b']                ?? ''),
      'ttb_briefing_resend_days'             => max(1, (int)($_POST['ttb_briefing_resend_days']              ?? 3)),
      'ttb_briefing_max_resends'             => (int)($_POST['ttb_briefing_max_resends']                     ?? 5),
      'ttb_briefing_resources_reminder_days' => max(1, (int)($_POST['ttb_briefing_resources_reminder_days']  ?? 2)),
    ];
    foreach ($fields as $k => $v) update_option($k, $v);
    self::flash_and_redirect('success', 'Configuración guardada.', self::tab_url('settings'));
  }

  // ══════════════════════════════════════════════
  // RENDER: LISTADO + FORMULARIO
  // ══════════════════════════════════════════════

  private static function render_list() {
    global $wpdb;
    $table   = TTB_Briefing_DB::briefings_table();
    $clients = TTB_DB::clients_table();
    $action  = esc_url(home_url('/briefing?section=briefing-doc&brtab=list'));

    $briefings = $wpdb->get_results(
      "SELECT b.*, c.name AS client_name, c.services AS client_services
       FROM $table b LEFT JOIN $clients c ON c.id = b.ttb_client_id
       ORDER BY b.created_at DESC LIMIT 200"
    );

    $all_clients = $wpdb->get_results("SELECT id, name, email, emails, services FROM $clients ORDER BY name ASC LIMIT 500");

    // ── Estilos ──
    echo '<style>
    .ttbbr-client-wrap{position:relative}
    .ttbbr-client-input{width:100%;border-radius:14px;border:1px solid var(--ttb-border);padding:12px 40px 12px 12px;outline:none;background:#fff;color:var(--ttb-text);font-size:14px;font-family:inherit;transition:border-color .2s,box-shadow .2s;box-sizing:border-box}
    .ttbbr-client-input:focus{border-color:var(--ttb-pink);box-shadow:0 0 0 3px rgba(215,33,115,.12)}
    .ttbbr-client-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--ttb-muted);pointer-events:none;font-size:15px;font-style:normal}
    .ttbbr-client-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid var(--ttb-border);border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.12);max-height:250px;overflow-y:auto;z-index:9999;display:none}
    .ttbbr-client-dropdown.open{display:block}
    .ttbbr-client-opt{padding:10px 14px;cursor:pointer;font-size:14px;color:var(--ttb-text);border-bottom:1px solid #f3f4f6;display:flex;flex-direction:column;gap:2px;transition:background .12s}
    .ttbbr-client-opt:last-child{border-bottom:none}
    .ttbbr-client-opt:hover,.ttbbr-client-opt.hi{background:rgba(215,33,115,.07)}
    .ttbbr-client-opt-name{font-weight:700}
    .ttbbr-client-opt-email{font-size:12px;color:var(--ttb-muted)}
    .ttbbr-client-opt-svcs{font-size:11px;color:var(--ttb-pink);font-weight:700}
    .ttbbr-client-no-r{padding:14px;text-align:center;font-size:13px;color:var(--ttb-muted)}
    .ttbbr-selected-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(215,33,115,.10);border:1.5px solid rgba(215,33,115,.30);border-radius:10px;padding:7px 14px;font-size:13px;font-weight:700;color:var(--ttb-pink);margin-top:6px}

    .ttbbr-dropzone{border:2px dashed var(--ttb-border);border-radius:14px;padding:24px 20px;text-align:center;background:#fafafa;transition:border-color .2s,background .2s;cursor:pointer}
    .ttbbr-dropzone:hover,.ttbbr-dropzone.dv{border-color:var(--ttb-pink);background:rgba(215,33,115,.03)}
    .ttbbr-file-list{margin-top:10px;display:flex;flex-direction:column;gap:6px}
    .ttbbr-file-item{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--ttb-border);border-radius:10px;padding:8px 12px;font-size:13px}
    .ttbbr-file-icon{font-size:18px;flex-shrink:0}
    .ttbbr-file-name{flex:1;font-weight:700;color:var(--ttb-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ttbbr-file-size{font-size:11px;color:var(--ttb-muted);flex-shrink:0}
    .ttbbr-file-rm{background:none;border:none;color:#e11d48;cursor:pointer;font-size:15px;padding:0 4px;line-height:1;flex-shrink:0}
    </style>';

    echo '<div class="ttb-card"><h3 style="margin:0 0 4px">Nuevo Briefing</h3>';
    echo '<p class="ttb-muted" style="margin:0">Crea un briefing post-reunión. El cliente recibirá un email con el documento para revisarlo y confirmarlo.</p>';
    echo '</div>';

    echo '<form method="post" action="' . $action . '" enctype="multipart/form-data" class="ttb-card" id="ttbbr-form">';
    wp_nonce_field('ttb_briefing_create');

    echo '<div class="ttb-grid2">';

    // ── BUSCADOR DE CLIENTES ───────────────────────────────────
    echo '<div>';
    echo '<label>Cliente <span class="ttb-required">*</span></label>';
    if (empty($all_clients)) {
      echo '<p class="ttb-muted">No hay clientes. <a href="' . esc_url(home_url('/briefing?section=clientes')) . '">Crear cliente →</a></p>';
      echo '<input type="hidden" name="br_client_id" value="">';
    } else {
      $svc_names  = ['design'=>'🎨 Diseño','social'=>'📣 Redes','seo'=>'🚀 SEO','web'=>'🌐 Web','reservas'=>'🍽️ Reservas'];
      $clients_js = [];
      foreach ($all_clients as $c) {
        $em  = json_decode((string)($c->emails ?? ''), true) ?: [$c->email];
        $svc = json_decode((string)($c->services ?? ''), true) ?: [];
        $clients_js[] = [
          'id'    => (int)$c->id,
          'name'  => $c->name,
          'email' => $em[0] ?? '',
          'svcs'  => implode(' · ', array_map(fn($s) => $svc_names[$s] ?? $s, $svc)),
        ];
      }
      ?>
      <input type="hidden" name="br_client_id" id="ttbbr-cid" value="">
      <div class="ttbbr-client-wrap">
        <input type="text" class="ttbbr-client-input" id="ttbbr-csearch" placeholder="Escribe para buscar cliente..." autocomplete="off" spellcheck="false">
        <i class="ttbbr-client-icon">🔍</i>
        <div class="ttbbr-client-dropdown" id="ttbbr-cdrop"></div>
      </div>
      <div id="ttbbr-cbadge" style="display:none"></div>
      <script>
      (function(){
        var cls=<?php echo wp_json_encode($clients_js); ?>;
        var inp=document.getElementById('ttbbr-csearch'),
            hid=document.getElementById('ttbbr-cid'),
            drp=document.getElementById('ttbbr-cdrop'),
            bdg=document.getElementById('ttbbr-cbadge'),
            hi=-1;

        function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

        function render(list){
          drp.innerHTML='';
          if(!list.length){drp.innerHTML='<div class="ttbbr-client-no-r">Sin resultados</div>';return;}
          list.slice(0,50).forEach(function(c){
            var d=document.createElement('div');
            d.className='ttbbr-client-opt';
            d.innerHTML='<span class="ttbbr-client-opt-name">'+esc(c.name)+'</span>'+
              '<span class="ttbbr-client-opt-email">'+esc(c.email)+'</span>'+
              (c.svcs?'<span class="ttbbr-client-opt-svcs">'+esc(c.svcs)+'</span>':'');
            d.addEventListener('mousedown',function(e){e.preventDefault();pick(c);});
            drp.appendChild(d);
          });
        }

        function pick(c){
          hid.value=c.id;
          inp.style.display='none';
          document.querySelector('.ttbbr-client-icon').style.display='none';
          drp.classList.remove('open');
          bdg.style.display='block';
          bdg.innerHTML='<span class="ttbbr-selected-badge">👤 '+esc(c.name)+
            ' <span style="font-weight:400;font-size:12px;color:var(--ttb-muted)">('+esc(c.email)+')</span>'+
            '&nbsp;<button type="button" onclick="ttbbrClear()" style="background:none;border:none;color:var(--ttb-pink);cursor:pointer;font-size:14px;font-weight:900;padding:0 0 0 4px" title="Cambiar">✕</button></span>';
        }

        window.ttbbrClear=function(){
          hid.value='';
          inp.style.display='';
          document.querySelector('.ttbbr-client-icon').style.display='';
          bdg.style.display='none';bdg.innerHTML='';inp.focus();
        };

        function open(q){
          var f=q?cls.filter(function(c){return c.name.toLowerCase().includes(q)||c.email.toLowerCase().includes(q);}):cls;
          render(f);drp.classList.add('open');hi=-1;
        }
        function close(){drp.classList.remove('open');hi=-1;}

        inp.addEventListener('focus',function(){open(inp.value.toLowerCase().trim());});
        inp.addEventListener('input',function(){open(inp.value.toLowerCase().trim());});
        inp.addEventListener('blur', function(){setTimeout(close,150);});
        inp.addEventListener('keydown',function(e){
          var opts=drp.querySelectorAll('.ttbbr-client-opt');
          if(!opts.length)return;
          if(e.key==='ArrowDown'){e.preventDefault();hi=Math.min(hi+1,opts.length-1);opts.forEach(function(o,i){o.classList.toggle('hi',i===hi);});}
          else if(e.key==='ArrowUp'){e.preventDefault();hi=Math.max(hi-1,0);opts.forEach(function(o,i){o.classList.toggle('hi',i===hi);});}
          else if(e.key==='Enter'){e.preventDefault();if(hi>=0&&opts[hi]){opts[hi].dispatchEvent(new MouseEvent('mousedown'));}}
          else if(e.key==='Escape')close();
        });

        document.getElementById('ttbbr-form').addEventListener('submit',function(e){
          if(!hid.value){
            e.preventDefault();inp.style.display='';inp.focus();
            inp.style.borderColor='#f43f5e';inp.style.boxShadow='0 0 0 3px rgba(244,63,94,.15)';
            alert('Selecciona un cliente antes de continuar.');
          }
        });
      })();
      </script>
      <?php
    }
    echo '</div>'; // fin cliente

    // ── TÍTULO ─────────────────────────────────────────────────
    echo '<div><label>Título <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span></label>';
    echo '<input class="ttb-input" type="text" name="br_title" placeholder="Ej: Briefing estratégico web corporativa"></div>';

    echo '</div>'; // .ttb-grid2

    // ── MÚLTIPLES DOCUMENTOS ───────────────────────────────────
    // FIX CRÍTICO: el input debe ser VISIBLE y directo.
    // DataTransfer.items.add() para asignar files a un <input> solo funciona en Chrome,
    // en Firefox/Safari el input queda vacío al hacer submit → PHP recibe $_FILES vacío.
    echo '<div style="margin-top:18px">';
    echo '<label style="font-weight:700;font-size:14px;color:var(--ttb-text);display:block;margin-bottom:6px">Documentos (PDF o Word)</label>';
    echo '<p class="ttb-muted" style="font-size:13px;margin:0 0 10px">Puedes adjuntar uno o varios archivos. El cliente los verá directamente en el portal.</p>';
    // El input está DENTRO del label — es el elemento real que recibe los archivos
    echo '<label for="ttbbr-fi" class="ttbbr-dropzone" style="display:block;cursor:pointer">';
    echo '<p style="font-size:26px;margin:0 0 6px">📎</p>';
    echo '<p style="font-weight:700;color:var(--ttb-text);margin:0 0 4px;font-size:14px">Haz clic aquí para seleccionar archivos</p>';
    echo '<p style="font-size:12px;color:var(--ttb-muted);margin:0 0 12px">PDF, DOC, DOCX · Puedes seleccionar varios a la vez (Ctrl+clic)</p>';
    echo '<input type="file" id="ttbbr-fi" name="br_docs[]" multiple '
       . 'accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" '
       . 'style="font-size:13px;color:var(--ttb-text);max-width:100%">';
    echo '</label>';
    echo '<div class="ttbbr-file-list" id="ttbbr-fl" style="margin-top:8px"></div>';
    echo '</div>';

    echo '<div style="margin-top:18px;background:linear-gradient(135deg,#eff6ff,#fff);border:1.5px solid #bfdbfe;border-radius:14px;padding:16px 20px">';
    echo '<p style="margin:0;font-size:13px;color:#1e40af;line-height:1.6">';
    echo '<strong>📁 Carpeta Drive:</strong> Al crear el briefing se creará automáticamente la estructura en Drive. ';
    echo 'La carpeta <strong>"' . esc_html(TTB_Briefing_Drive::SHARED_FOLDER_NAME) . '"</strong> se compartirá con los emails del cliente. ';
    echo 'El documento se subirá a la carpeta <strong>"BRIEFINGS - [nombre cliente]"</strong>.';
    echo '</p></div>';

    echo '<div class="ttb-actions" style="margin-top:20px"><button class="ttb-btn" name="ttb_briefing_create" value="1">Crear Briefing y enviar email</button></div>';
    echo '</form>';

    // JS: solo para previsualizar los archivos seleccionados (decorativo, no afecta al submit)
    ?>
    <script>
    (function(){
      var fi = document.getElementById('ttbbr-fi');
      var fl = document.getElementById('ttbbr-fl');
      if (!fi || !fl) return;
      fi.addEventListener('change', function() {
        fl.innerHTML = '';
        Array.from(fi.files).forEach(function(f) {
          var icon = f.type === 'application/pdf' ? '📄' : '📝';
          var sz = f.size > 1048576 ? (f.size/1048576).toFixed(1)+' MB' : Math.round(f.size/1024)+' KB';
          var d = document.createElement('div');
          d.className = 'ttbbr-file-item';
          d.innerHTML = '<span class="ttbbr-file-icon">'+icon+'</span>'
            + '<span class="ttbbr-file-name">'+esc(f.name)+'</span>'
            + '<span class="ttbbr-file-size">'+sz+'</span>';
          fl.appendChild(d);
        });
      });
      function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    })();
    </script>
    <?php

    // ── LISTADO ────────────────────────────────────────────────
    echo '<div class="ttb-card"><h3>Briefings enviados</h3>';

    if (!$briefings) { echo '<p class="ttb-muted">No hay briefings creados aún.</p></div>'; return; }

    $status_map = [
      'pending'  => ['⏳ Pendiente',   'ttb-status--pending'],
      'accepted' => ['✅ Aceptado',    'ttb-status--sent'],
      'rejected' => ['✏️ Con cambios', 'ttb-status--progress'],
      'completed'=> ['✅ Completado',  'ttb-status--sent'],
    ];
    $svc_icons = ['design'=>'🎨','social'=>'📣','seo'=>'🚀','web'=>'🌐','reservas'=>'🍽️','general'=>'📄'];

    echo '<div class="ttb-tablewrap"><table class="ttb-table"><thead><tr>';
    echo '<th>Cliente</th><th>Título</th><th>Servicios</th><th>Documentos</th><th>Drive</th><th>Estado</th><th>Avisos</th><th>Acciones</th>';
    echo '</tr></thead><tbody>';

    foreach ($briefings as $b) {
      [$sl, $sc] = $status_map[$b->status] ?? [$b->status, ''];
      $client_url = esc_url(TTB_Briefing_DB::client_url($b->token));
      $title_disp = !empty($b->title) ? esc_html($b->title) : '<span style="color:var(--ttb-muted)">Sin título</span>';

      // Servicios del cliente
      $svc_badges = '';
      $raw_svcs   = json_decode((string)($b->client_services ?? ''), true) ?: [];
      foreach ($raw_svcs as $s) {
        $svc_badges .= '<span title="' . esc_attr($s) . '" style="font-size:15px">' . ($svc_icons[$s] ?? $s) . '</span>';
      }
      if (!$svc_badges) $svc_badges = '<span style="color:var(--ttb-muted)">—</span>';

      // Documentos: nuevo formato (JSON array) o legacy (URL directa)
      $docs_html = '<span style="color:var(--ttb-muted);font-size:13px">Sin docs</span>';
      $raw_doc   = (string)($b->doc_url ?? '');
      if ($raw_doc) {
        $docs_arr = json_decode($raw_doc, true);
        if (is_array($docs_arr)) {
          $links = array_map(function($doc) {
            $icon = (($doc['mime'] ?? '') === 'application/pdf') ? '📄' : '📝';
            return '<a href="' . esc_url($doc['url']) . '" target="_blank" style="color:var(--ttb-pink);font-size:12px;font-weight:700;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px">'
              . $icon . ' ' . esc_html(mb_substr($doc['name'] ?? 'Documento', 0, 22)) . '</a>';
          }, $docs_arr);
          $docs_html = implode('', $links) ?: $docs_html;
        } else {
          // Legacy
          $icon = ($b->doc_mime === 'application/pdf') ? '📄' : '📝';
          $docs_html = '<a href="' . esc_url($raw_doc) . '" target="_blank" style="color:var(--ttb-pink);font-size:12px;font-weight:700">'
            . $icon . ' ' . esc_html(mb_substr($b->doc_name ?? 'Documento', 0, 22)) . '</a>';
        }
      }

      // Drive
      $drive_html = $b->shared_folder_url
        ? '<a href="' . esc_url($b->shared_folder_url) . '" target="_blank" class="ttb-btn ttb-btn--ghost ttb-btn--sm">📁 Ver</a>'
        : ($b->drive_folder_url ? '<span style="color:var(--ttb-muted);font-size:12px">⚠️ Error</span>' : '<span style="color:var(--ttb-muted)">—</span>');

      echo '<tr>';
      echo '<td><strong>' . esc_html($b->client_name ?? '—') . '</strong></td>';
      echo '<td>' . $title_disp . '</td>';
      echo '<td>' . $svc_badges . '</td>';
      echo '<td>' . $docs_html . '</td>';
      echo '<td>' . $drive_html . '</td>';
      echo '<td><span class="ttb-status ' . $sc . '">' . $sl . '</span></td>';
      echo '<td style="text-align:center">' . (int)$b->notif_count . '</td>';
      echo '<td><div class="ttb-row-actions">';
      echo '<a href="' . $client_url . '" target="_blank" class="ttb-btn ttb-btn--ghost ttb-btn--sm">👁️ Ver</a>';

      if (!empty($b->client_note)) {
        echo '<span style="display:inline-block;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:4px 9px;font-size:12px;color:#92400e;max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="' . esc_attr($b->client_note) . '">💬 ' . esc_html(mb_substr($b->client_note, 0, 20)) . (mb_strlen($b->client_note) > 20 ? '…' : '') . '</span>';
      }

      echo '<form method="post" action="' . $action . '" style="margin:0">';
      wp_nonce_field('ttb_briefing_resend');
      echo '<input type="hidden" name="br_id" value="' . (int)$b->id . '">';
      echo '<button class="ttb-btn ttb-btn--ghost ttb-btn--sm" name="ttb_briefing_resend" value="1">📧 Reenviar</button>';
      echo '</form>';

      echo '<form method="post" action="' . $action . '" style="margin:0" onsubmit="return confirm(\'¿Eliminar este briefing?\');">';
      wp_nonce_field('ttb_briefing_delete');
      echo '<input type="hidden" name="br_id" value="' . (int)$b->id . '">';
      echo '<button class="ttb-btn ttb-btn--danger ttb-btn--sm" name="ttb_briefing_delete" value="1">🗑️</button>';
      echo '</form>';

      echo '</div></td></tr>';
    }

    echo '</tbody></table></div></div>';
  }

  // ══════════════════════════════════════════════
  // RENDER: AUDITORÍA
  // ══════════════════════════════════════════════

  private static function render_audit() {
    global $wpdb;
    $audit_table     = TTB_Briefing_DB::audit_table();
    $briefings_table = TTB_Briefing_DB::briefings_table();
    $clients_table   = TTB_DB::clients_table();
    $catalog         = TTB_Briefing_DB::event_catalog();
    $actor_labels    = [
      'admin'=>['Admin','#eff6ff','#1d4ed8'],'client'=>['Cliente','#fdf4ff','#7e22ce'],
      'cron' =>['Cron', '#f9fafb','#374151'],'system'=>['Sistema','#f9fafb','#374151'],
    ];

    $f_client = (int)($_GET['f_client'] ?? 0);
    $f_event  = sanitize_text_field($_GET['f_event'] ?? '');
    $f_page   = max(1, (int)($_GET['f_page'] ?? 1));
    $per_page = 50; $offset = ($f_page - 1) * $per_page;

    $where = ['1=1']; $params = [];
    if ($f_client) { $where[] = 'a.client_id = %d'; $params[] = $f_client; }
    if ($f_event)  { $where[] = 'a.event = %s';     $params[] = $f_event; }
    $ws = implode(' AND ', $where);

    $total = $params
      ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $audit_table a WHERE $ws", ...$params))
      : (int)$wpdb->get_var("SELECT COUNT(*) FROM $audit_table a WHERE $ws");
    $rows  = $wpdb->get_results($wpdb->prepare(
      "SELECT a.*, b.title AS briefing_title, c.name AS client_name
       FROM $audit_table a LEFT JOIN $briefings_table b ON b.id=a.briefing_id LEFT JOIN $clients_table c ON c.id=a.client_id
       WHERE $ws ORDER BY a.created_at DESC LIMIT %d OFFSET %d",
      ...array_merge($params, [$per_page, $offset])
    ));
    $total_pages = max(1, ceil($total / $per_page));
    $all_clients = $wpdb->get_results("SELECT id, name FROM $clients_table ORDER BY name ASC");
    $base_url    = home_url('/briefing?section=briefing-doc&brtab=audit');

    echo '<div class="ttb-card"><h3 style="margin:0 0 4px">Auditoría — Briefings</h3>';
    echo '<p class="ttb-muted" style="margin:0 0 18px">Registro completo de actividad del módulo Briefing.</p>';
    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">';
    echo '<input type="hidden" name="section" value="briefing-doc"><input type="hidden" name="brtab" value="audit">';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Cliente</label><select name="f_client" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    foreach ($all_clients as $c) echo '<option value="'.(int)$c->id.'" '.selected($f_client,$c->id,false).'>'.esc_html($c->name).'</option>';
    echo '</select></div>';
    echo '<div><label style="font-size:12px;font-weight:700;color:var(--ttb-muted);display:block;margin-bottom:4px">Evento</label><select name="f_event" class="ttb-input" style="font-size:13px"><option value="">Todos</option>';
    foreach ($catalog as $k => [$label]) echo '<option value="'.esc_attr($k).'" '.selected($f_event,$k,false).'>'.esc_html($label).'</option>';
    echo '</select></div>';
    echo '<div style="display:flex;gap:8px;align-items:flex-end"><button class="ttb-btn" type="submit">Filtrar</button><a href="'.esc_url($base_url).'" class="ttb-btn ttb-btn--ghost">Limpiar</a></div>';
    echo '</form><p style="margin:14px 0 0;font-size:13px;color:var(--ttb-muted)"><strong>'.number_format($total).'</strong> registros</p></div>';

    if (!$rows) {
      echo '<div class="ttb-card"><p class="ttb-muted" style="text-align:center;padding:24px">No hay registros.</p></div>';
    } else {
      echo '<div class="ttb-card" style="padding:0;overflow:hidden"><div class="ttb-tablewrap"><table class="ttb-table" style="font-size:13px"><thead><tr>';
      echo '<th>Fecha</th><th>Evento</th><th>Actor</th><th>Cliente</th><th>Briefing</th><th>Detalle</th></tr></thead><tbody>';
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
        echo '<td style="font-size:12px;color:var(--ttb-muted)">'.esc_html($row->briefing_title??'—').'</td>';
        echo '<td style="font-size:12px;max-width:280px">'.$det.'</td></tr>';
      }
      echo '</tbody></table></div></div>';
    }

    if ($total_pages > 1) {
      echo '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:16px">';
      for ($p = max(1,$f_page-3); $p <= min($total_pages,$f_page+3); $p++) {
        $u = esc_url(add_query_arg(['section'=>'briefing-doc','brtab'=>'audit','f_client'=>$f_client?:'','f_event'=>$f_event,'f_page'=>$p>1?$p:''], home_url('/briefing')));
        echo '<a href="'.$u.'" class="'.($p===$f_page?'ttb-btn ttb-btn--sm':'ttb-btn ttb-btn--ghost ttb-btn--sm').'">'.$p.'</a>';
      }
      echo '</div>';
    }
  }

  // ══════════════════════════════════════════════
  // RENDER: CONFIGURACIÓN
  // ══════════════════════════════════════════════

  private static function render_settings() {
    $action       = self::tab_url('settings');
    $notify_a     = (string)get_option('ttb_briefing_notify_a', 'hola@tictac-comunicacion.es');
    $notify_b     = (string)get_option('ttb_briefing_notify_b', '');
    $resend_days  = (int)get_option('ttb_briefing_resend_days', 3);
    $max_resends  = (int)get_option('ttb_briefing_max_resends', 5);
    $res_rem_days = (int)get_option('ttb_briefing_resources_reminder_days', 2);

    echo '<div class="ttb-card"><h3>Configuración — Módulo Briefing</h3></div>';
    echo '<form method="post" action="' . $action . '">';
    wp_nonce_field('ttb_briefing_settings');

    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">📬 Emails internos</h4>';
    echo '<p class="ttb-muted" style="margin:0 0 16px">Reciben la notificación cuando un cliente acepta o rechaza su briefing.</p>';
    echo '<div class="ttb-grid2">';
    echo '<div><label>Email principal</label><input class="ttb-input" type="email" name="ttb_briefing_notify_a" value="' . esc_attr($notify_a) . '"></div>';
    echo '<div><label>Email secundario <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span></label><input class="ttb-input" type="email" name="ttb_briefing_notify_b" value="' . esc_attr($notify_b) . '"></div>';
    echo '</div></div>';

    echo '<div class="ttb-card"><h4 style="margin:0 0 14px">⏰ Recordatorios automáticos</h4><div class="ttb-formgrid">';
    echo '<div class="ttb-grid2">';
    echo '<div><label>Días entre recordatorios de revisión</label>';
    echo '<input class="ttb-input" type="number" name="ttb_briefing_resend_days" value="' . $resend_days . '" min="1" max="30">';
    echo '<small class="ttb-muted" style="display:block;margin-top:4px">Si el cliente no revisa el briefing, se reenvía cada N días.</small></div>';
    echo '<div><label>Máximo de recordatorios</label>';
    echo '<input class="ttb-input" type="number" name="ttb_briefing_max_resends" value="' . $max_resends . '" min="0" max="20">';
    echo '<small class="ttb-muted" style="display:block;margin-top:4px">0 = sin límite.</small></div>';
    echo '</div>';
    echo '<div><label>Días tras aceptación para recordatorio de recursos</label>';
    echo '<input class="ttb-input" type="number" name="ttb_briefing_resources_reminder_days" value="' . $res_rem_days . '" min="1" max="14">';
    echo '<small class="ttb-muted" style="display:block;margin-top:4px">Si el cliente acepta el briefing pero no sube recursos a Drive, se le envía un recordatorio (una sola vez).</small>';
    echo '</div></div></div>';

    echo '<div class="ttb-actions"><button class="ttb-btn" name="ttb_briefing_settings" value="1">Guardar configuración</button></div>';
    echo '</form>';
  }
}