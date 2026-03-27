<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Briefing_Client')) return;

/**
 * TTB_Briefing_Client
 * Vista del cliente para el módulo Briefing.
 * Muestra el documento, permite aceptar o rechazar, y enlaza a la carpeta Drive.
 *
 * Se puede renderizar de dos formas:
 * 1. Como panel dentro del portal principal (ctab=briefings).
 * 2. Como magic link independiente (?ttb_briefing=TOKEN).
 */
class TTB_Briefing_Client {

  private static $submitted = false;

  /**
   * Renderiza la vista para UN briefing concreto (por token).
   * Usado en el portal de magic link independiente.
   */
  public static function render_by_token($token) {
    $briefing = TTB_Briefing_DB::get_briefing_by_token($token);

    if (!$briefing) {
      echo '<div class="ttb-card" style="text-align:center;padding:48px 24px">
        <p style="font-size:40px;margin:0 0 12px">🔗</p>
        <h2>Enlace no válido</h2>
        <p class="ttb-muted">Este enlace no existe o ha caducado. Contacta con TicTac Comunicación.</p>
      </div>';
      TTB_Briefing_DB::log(null, null, 'invalid_token', 'client', ['token_partial' => substr($token, 0, 8) . '…']);
      return;
    }

    if (!self::$submitted && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_briefing_action'])) {
      self::$submitted = true;
      self::handle_post($briefing, $token);
      return;
    }

    TTB_Briefing_DB::log($briefing->id, $briefing->ttb_client_id, 'client_view', 'client', []);

    global $wpdb;
    $client = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", (int)$briefing->ttb_client_id
    ));

    $lang = in_array($client->lang ?? '', ['es', 'en'], true) ? $client->lang : 'es';
    self::render_briefing_view($briefing, $client, $lang, false);
  }

  /**
   * Renderiza el panel de briefings dentro del portal principal del cliente.
   * Muestra todos los briefings de este cliente.
   */
  public static function render_for_client($client_id, $lang = 'es') {
    global $wpdb;
    $briefings = TTB_Briefing_DB::get_by_client($client_id);

    if (empty($briefings)) {
      self::render_empty_state($lang);
      return;
    }

    // Si hay múltiples, mostrar el primero activo (pending/rejected) o el más reciente
    $active = null;
    foreach ($briefings as $b) {
      if (in_array($b->status, ['pending', 'rejected'], true)) {
        $active = $b;
        break;
      }
    }
    if (!$active) $active = $briefings[0];

    // Manejar POST dentro del portal
    if (!self::$submitted && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_briefing_action'])) {
      $posted_token = sanitize_text_field($_POST['ttb_briefing_token'] ?? '');
      // Verificar que el token pertenece a este cliente
      $check = TTB_Briefing_DB::get_briefing_by_token($posted_token);
      if ($check && (int)$check->ttb_client_id === (int)$client_id) {
        self::$submitted = true;
        self::handle_post($check, $posted_token);
        return;
      }
    }

    $client = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", (int)$client_id
    ));

    self::render_briefing_view($active, $client, $lang, true);

    // Si hay más briefings, mostrar historial
    if (count($briefings) > 1) {
      self::render_briefing_history($briefings, $lang);
    }
  }

  // ══════════════════════════════════════════════
  // RENDER PRINCIPAL DE UN BRIEFING
  // ══════════════════════════════════════════════

  private static function render_briefing_view($briefing, $client, $lang, $is_portal) {
    $i18n = [
      'es' => [
        'title'          => 'Tu Briefing',
        'subtitle'       => 'Este es el documento de briefing que hemos preparado tras nuestra reunión. Léelo con atención y confirma que estás de acuerdo, o indícanos los cambios que necesites.',
        'doc_title'      => '📄 Documento de Briefing',
        'doc_view'       => 'Ver documento',
        'doc_download'   => 'Descargar',
        'drive_title'    => '📁 Tu carpeta de recursos en Drive',
        'drive_msg'      => 'Sube aquí todos los recursos visuales de tu empresa: logos, fotografías, vídeos, colores corporativos, guías de estilo, etc.',
        'drive_warning'  => '⚠️ Importante: nuestro equipo no puede comenzar a trabajar hasta que subas los recursos a esta carpeta.',
        'drive_btn'      => 'Abrir mi carpeta de Drive →',
        'accept_title'   => '¿Está todo correcto?',
        'accept_btn'     => '✅ Sí, acepto el Briefing',
        'reject_btn'     => '✏️ No, tengo comentarios',
        'reject_label'   => '¿Qué necesitas cambiar o aclarar?',
        'reject_ph'      => 'Descríbenos con detalle qué quieres modificar o aclarar...',
        'reject_send'    => '📨 Enviar comentarios',
        'accepted_title' => '✅ ¡Briefing aceptado!',
        'accepted_msg'   => 'Has confirmado el briefing. Recuerda subir todos los recursos visuales a tu carpeta de Drive para que podamos empezar a trabajar.',
        'rejected_title' => '📨 Comentarios enviados',
        'rejected_msg'   => 'Hemos recibido tus comentarios. Nuestro equipo los revisará y actualizará el documento si es necesario.',
        'your_note'      => 'Tu comentario:',
      ],
      'en' => [
        'title'          => 'Your Briefing',
        'subtitle'       => 'This is the briefing document we prepared after our meeting. Please read it carefully and confirm you agree, or let us know any changes you need.',
        'doc_title'      => '📄 Briefing Document',
        'doc_view'       => 'View document',
        'doc_download'   => 'Download',
        'drive_title'    => '📁 Your resources folder on Drive',
        'drive_msg'      => 'Upload all your company\'s visual assets here: logos, photos, videos, brand colours, style guides, etc.',
        'drive_warning'  => '⚠️ Important: our team cannot start working until you upload the resources to this folder.',
        'drive_btn'      => 'Open my Drive folder →',
        'accept_title'   => 'Is everything correct?',
        'accept_btn'     => '✅ Yes, I accept the Briefing',
        'reject_btn'     => '✏️ No, I have comments',
        'reject_label'   => 'What do you need to change or clarify?',
        'reject_ph'      => 'Describe in detail what you want to modify or clarify...',
        'reject_send'    => '📨 Send comments',
        'accepted_title' => '✅ Briefing accepted!',
        'accepted_msg'   => 'You have confirmed the briefing. Remember to upload all your visual assets to your Drive folder so we can start working.',
        'rejected_title' => '📨 Comments sent',
        'rejected_msg'   => 'We have received your comments. Our team will review them and update the document if necessary.',
        'your_note'      => 'Your comment:',
      ],
    ];
    $t = $i18n[$lang] ?? $i18n['es'];

    $form_action = $is_portal
      ? esc_url(home_url('/briefing'))
      : esc_url(TTB_Briefing_DB::client_url($briefing->token));

    // Detectar si doc_url es JSON array (nuevo formato) o URL directa (legacy)
    $raw_doc_url = (string)($briefing->doc_url ?? '');
    $docs_list   = null;
    if ($raw_doc_url) {
      $decoded = json_decode($raw_doc_url, true);
      if (is_array($decoded) && !empty($decoded)) {
        $docs_list = $decoded; // nuevo formato: array de [{url,name,mime}]
      }
    }

    // Para compatibilidad con el código de abajo (legacy de un solo doc)
    $is_pdf  = ($briefing->doc_mime === 'application/pdf');
    $is_word = in_array($briefing->doc_mime ?? '', [
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ], true);

    ?>
    <div class="ttb-container">

      <?php if (!$is_portal): ?>
        <div class="ttb-card ttb-card--header">
          <h2><?php echo esc_html($t['title']); ?></h2>
          <?php if ($client): ?><p class="ttb-muted">Hola, <strong><?php echo esc_html($client->name); ?></strong>. <?php echo esc_html($t['subtitle']); ?></p><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="ttb-card" style="background:linear-gradient(135deg,#fdf2f7,#fff);border-color:rgba(215,33,115,.2)">
          <p style="margin:0;font-size:14px;color:var(--ttb-muted);line-height:1.6"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
      <?php endif; ?>

      <?php /* ── DOCUMENTOS ─────────────────────────────────── */ ?>
      <?php
      // Construir lista normalizada de documentos
      $render_docs = [];
      if ($docs_list) {
        // Nuevo formato: array de objetos
        foreach ($docs_list as $doc) {
          $render_docs[] = [
            'url'  => $doc['url']  ?? '',
            'name' => $doc['name'] ?? 'Documento',
            'mime' => $doc['mime'] ?? '',
          ];
        }
      } elseif ($raw_doc_url) {
        // Formato legacy: URL única
        $render_docs[] = [
          'url'  => $raw_doc_url,
          'name' => $briefing->doc_name ?? 'Documento',
          'mime' => $briefing->doc_mime ?? '',
        ];
      }
      ?>

      <?php if (!empty($render_docs)): ?>
        <div class="ttb-card">
          <h3 style="margin:0 0 16px"><?php echo esc_html($t['doc_title']); ?></h3>

          <?php foreach ($render_docs as $di => $doc):
            $d_is_pdf  = ($doc['mime'] === 'application/pdf');
            $d_is_word = in_array($doc['mime'], [
              'application/msword',
              'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ], true);
            $d_icon    = $d_is_pdf ? '📄' : ($d_is_word ? '📝' : '📎');
          ?>

            <?php if (count($render_docs) > 1): ?>
              <div style="display:flex;align-items:center;gap:8px;margin:<?php echo $di > 0 ? '20px' : '0'; ?> 0 10px">
                <span style="font-size:16px"><?php echo $d_icon; ?></span>
                <strong style="font-size:14px;color:var(--ttb-text)"><?php echo esc_html($doc['name']); ?></strong>
              </div>
            <?php endif; ?>

            <?php if ($d_is_pdf): ?>
              <div style="border:2px solid var(--ttb-border);border-radius:14px;overflow:hidden;margin-bottom:10px;background:#f4f4f4">
                <iframe
                  src="<?php echo esc_url($doc['url']); ?>#toolbar=1&navpanes=0&scrollbar=1"
                  style="width:100%;height:580px;border:none;display:block"
                  title="<?php echo esc_attr($doc['name']); ?>"
                  loading="lazy"
                ></iframe>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:<?php echo ($di < count($render_docs) - 1) ? '20px' : '0'; ?>">
                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener" class="ttb-btn ttb-btn--ghost ttb-btn--sm">
                  🔗 <?php echo esc_html($t['doc_view']); ?>
                </a>
                <a href="<?php echo esc_url($doc['url']); ?>" download class="ttb-btn ttb-btn--ghost ttb-btn--sm">
                  ⬇️ <?php echo esc_html($t['doc_download']); ?>
                </a>
              </div>

            <?php elseif ($d_is_word): ?>
              <?php $gdocs_url = 'https://docs.google.com/viewer?url=' . urlencode($doc['url']) . '&embedded=true'; ?>
              <div style="border:2px solid var(--ttb-border);border-radius:14px;overflow:hidden;margin-bottom:10px;background:#f4f4f4">
                <iframe
                  src="<?php echo esc_url($gdocs_url); ?>"
                  style="width:100%;height:580px;border:none;display:block"
                  title="<?php echo esc_attr($doc['name']); ?>"
                  loading="lazy"
                ></iframe>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:<?php echo ($di < count($render_docs) - 1) ? '20px' : '0'; ?>">
                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener" class="ttb-btn ttb-btn--ghost ttb-btn--sm">
                  🔗 <?php echo esc_html($t['doc_view']); ?>
                </a>
                <a href="<?php echo esc_url($doc['url']); ?>" download class="ttb-btn ttb-btn--ghost ttb-btn--sm">
                  ⬇️ <?php echo esc_html($t['doc_download']); ?>
                </a>
              </div>

            <?php else: ?>
              <div style="margin-bottom:<?php echo ($di < count($render_docs) - 1) ? '12px' : '0'; ?>">
                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener" class="ttb-btn ttb-btn--ghost ttb-btn--sm">
                  <?php echo $d_icon; ?> <?php echo esc_html($doc['name']); ?>
                </a>
              </div>
            <?php endif; ?>

          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php /* ── CARPETA DRIVE ────────────────────────────── */ ?>
      <?php if ($briefing->shared_folder_url): ?>
        <div class="ttb-card" style="border-color:#bfdbfe;background:linear-gradient(135deg,#eff6ff,#fff)">
          <h3 style="margin:0 0 10px;color:#1d4ed8"><?php echo esc_html($t['drive_title']); ?></h3>
          <p style="margin:0 0 12px;font-size:14px;color:#1e40af;line-height:1.6"><?php echo esc_html($t['drive_msg']); ?></p>
          <div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;padding:12px 16px;margin-bottom:18px">
            <p style="margin:0;font-size:13px;font-weight:700;color:#9a3412"><?php echo esc_html($t['drive_warning']); ?></p>
          </div>
          <a href="<?php echo esc_url($briefing->shared_folder_url); ?>" target="_blank" rel="noopener" style="display:inline-block;background-color:blue;color:#ffffff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 28px;border-radius:12px;"><?php echo esc_html($t['drive_btn']); ?></a>
        </div>
      <?php endif; ?>

      <?php /* ── ACCIONES: ACEPTAR / RECHAZAR ───────────────── */ ?>
      <?php if ($briefing->status === 'pending' || $briefing->status === 'rejected'): ?>

        <?php if ($briefing->status === 'rejected' && $briefing->client_note): ?>
          <div class="ttb-card" style="border-color:#fecdd3;background:#fff1f2">
            <p style="margin:0 0 6px;font-size:14px;font-weight:800;color:#be123c"><?php echo esc_html($t['your_note']); ?></p>
            <p style="margin:0;font-size:14px;color:#be123c;line-height:1.6;white-space:pre-line"><?php echo nl2br(esc_html($briefing->client_note)); ?></p>
            <p style="margin:10px 0 0;font-size:12px;color:#e11d48"><?php echo $lang === 'en' ? 'Our team will review your comments and contact you.' : 'Nuestro equipo revisará tus comentarios y se pondrá en contacto contigo.'; ?></p>
          </div>
        <?php endif; ?>

        <div class="ttb-card">
          <h3 style="margin:0 0 16px"><?php echo esc_html($t['accept_title']); ?></h3>

          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
            <button type="button" class="ttb-btn" id="ttbbr-accept-btn" style="background:linear-gradient(135deg,#10b981,#059669)" onclick="ttbbrShowAccept()">
              <?php echo esc_html($t['accept_btn']); ?>
            </button>
            <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbbr-reject-btn" style="color:#e11d48;border-color:#fecdd3" onclick="ttbbrShowReject()">
              <?php echo esc_html($t['reject_btn']); ?>
            </button>
          </div>

          <?php /* Formulario aceptar */ ?>
          <div id="ttbbr-accept-form" style="display:none">
            <form method="post" action="<?php echo $form_action; ?>">
              <?php wp_nonce_field('ttb_briefing_accept_' . $briefing->token); ?>
              <input type="hidden" name="ttb_briefing_action" value="accept">
              <input type="hidden" name="ttb_briefing_token" value="<?php echo esc_attr($briefing->token); ?>">
              <?php if ($is_portal): ?>
                <input type="hidden" name="ttb_return" value="portal">
              <?php endif; ?>
              <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:16px 20px;margin-bottom:16px">
                <p style="margin:0;font-size:14px;color:#065f46;line-height:1.6">
                  <?php echo $lang === 'en'
                    ? 'By confirming, you indicate that the briefing document is correct and authorise TicTac to start working on it.'
                    : 'Al confirmar, indicas que el documento de briefing es correcto y autorizas a TicTac a comenzar a trabajar en él.'; ?>
                </p>
              </div>
              <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#10b981,#059669)">
                <?php echo esc_html($t['accept_btn']); ?>
              </button>
            </form>
          </div>

          <?php /* Formulario rechazar */ ?>
          <div id="ttbbr-reject-form" style="display:none">
            <form method="post" action="<?php echo $form_action; ?>">
              <?php wp_nonce_field('ttb_briefing_reject_' . $briefing->token); ?>
              <input type="hidden" name="ttb_briefing_action" value="reject">
              <input type="hidden" name="ttb_briefing_token" value="<?php echo esc_attr($briefing->token); ?>">
              <?php if ($is_portal): ?>
                <input type="hidden" name="ttb_return" value="portal">
              <?php endif; ?>
              <label style="display:block;font-weight:700;font-size:14px;margin-bottom:8px">
                <?php echo esc_html($t['reject_label']); ?>
              </label>
              <textarea name="ttb_briefing_note" class="ttb-textarea" style="min-height:120px;margin-bottom:12px"
                placeholder="<?php echo esc_attr($t['reject_ph']); ?>" required></textarea>
              <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#e11d48,#be123c)">
                <?php echo esc_html($t['reject_send']); ?>
              </button>
            </form>
          </div>
        </div>

      <?php elseif ($briefing->status === 'accepted' || $briefing->status === 'completed'): ?>

        <div class="ttb-card" style="text-align:center;padding:40px 24px">
          <span style="font-size:54px;display:block;margin-bottom:12px">✅</span>
          <h3 style="margin:0 0 8px;color:#065f46"><?php echo esc_html($t['accepted_title']); ?></h3>
          <p class="ttb-muted" style="margin:0 auto;max-width:480px;line-height:1.6"><?php echo esc_html($t['accepted_msg']); ?></p>
          <?php if ($briefing->shared_folder_url): ?>
            <div style="margin-top:24px">
              <a href="<?php echo esc_url($briefing->shared_folder_url); ?>" target="_blank" rel="noopener" style="display:inline-block;background-color:#1d4ed8;color:#ffffff;text-decoration:none;font-weight:900;font-size:14px;padding:12px 24px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;">
                📁 <?php echo $lang === 'en' ? 'Open my Drive folder' : 'Abrir mi carpeta de Drive'; ?> →
              </a>
            </div>
          <?php endif; ?>
        </div>

      <?php endif; ?>

    </div>

    <script>
    function ttbbrShowAccept() {
      document.getElementById('ttbbr-accept-form').style.display = 'block';
      document.getElementById('ttbbr-reject-form').style.display = 'none';
      document.getElementById('ttbbr-accept-btn').style.opacity = '1';
      document.getElementById('ttbbr-reject-btn').style.opacity = '.5';
    }
    function ttbbrShowReject() {
      document.getElementById('ttbbr-reject-form').style.display = 'block';
      document.getElementById('ttbbr-accept-form').style.display = 'none';
      document.getElementById('ttbbr-reject-btn').style.opacity = '1';
      document.getElementById('ttbbr-accept-btn').style.opacity = '.5';
    }
    </script>
    <?php
  }

  // ── Historial de briefings ──────────────────────────────────
  private static function render_briefing_history($briefings, $lang) {
    $label = $lang === 'en' ? 'Briefing history' : 'Historial de briefings';
    echo '<div class="ttb-card" style="margin-top:16px">';
    echo '<h4 style="margin:0 0 12px">' . esc_html($label) . '</h4>';
    echo '<div style="display:flex;flex-direction:column;gap:8px">';
    $status_map = [
      'pending'  => ['⏳ Pendiente',   '#fffbeb','#fde68a','#92400e'],
      'accepted' => ['✅ Aceptado',    '#ecfdf5','#6ee7b7','#065f46'],
      'rejected' => ['✏️ Con cambios', '#fff1f2','#fecdd3','#be123c'],
      'completed'=> ['✅ Completado',  '#ecfdf5','#6ee7b7','#065f46'],
    ];
    foreach ($briefings as $i => $b) {
      [$sl,$sbg,$sbc,$sco] = $status_map[$b->status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];
      echo '<div style="display:flex;align-items:center;gap:10px;background:#f9fafb;border-radius:10px;padding:10px 14px">';
      echo '<span style="font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px;background:' . $sbg . ';border:1px solid ' . $sbc . ';color:' . $sco . '">' . esc_html($sl) . '</span>';
      echo '<span style="font-size:14px;font-weight:700;color:var(--ttb-text)">' . esc_html($b->title ?: 'Briefing') . '</span>';
      echo '<span style="font-size:12px;color:var(--ttb-muted);margin-left:auto">' . esc_html(date_i18n('d/m/Y', strtotime($b->created_at))) . '</span>';
      echo '</div>';
    }
    echo '</div></div>';
  }

  // ── Estado vacío (sin briefings) ───────────────────────────
  private static function render_empty_state($lang) {
    if ($lang === 'en') {
      echo '<div class="ttb-card" style="text-align:center;padding:60px 24px">
        <span style="font-size:56px;display:block;margin-bottom:16px">📋</span>
        <h3 style="margin:0 0 10px;color:var(--ttb-text)">No briefing yet</h3>
        <p class="ttb-muted" style="margin:0 auto;max-width:480px;line-height:1.6">
          After your initial meeting with the TicTac team, your briefing document will appear here for you to review and confirm.
        </p>
      </div>';
    } else {
      echo '<div class="ttb-card" style="text-align:center;padding:60px 24px">
        <span style="font-size:56px;display:block;margin-bottom:16px">📋</span>
        <h3 style="margin:0 0 10px;color:var(--ttb-text)">Todavía no hay briefing</h3>
        <p class="ttb-muted" style="margin:0 auto;max-width:480px;line-height:1.6">
          Tras tu reunión inicial con el equipo de TicTac, aquí aparecerá el documento de briefing para que lo revises y confirmes.
        </p>
      </div>';
    }
  }

  // ── Manejo del POST ─────────────────────────────────────────
  private static function handle_post($briefing, $token) {
    $action = sanitize_text_field($_POST['ttb_briefing_action'] ?? '');
    $is_portal = (sanitize_text_field($_POST['ttb_return'] ?? '') === 'portal');

    global $wpdb;
    $table  = TTB_Briefing_DB::briefings_table();
    $client = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d",
      (int)$briefing->ttb_client_id
    ));

    if ($action === 'accept') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_briefing_accept_' . $token)) {
        self::js_redirect(TTB_Briefing_DB::client_url($token));
      }

      $wpdb->update($table, [
        'status'       => 'accepted',
        'responded_at' => TTB_Briefing_DB::now(),
        'updated_at'   => TTB_Briefing_DB::now(),
      ], ['id' => $briefing->id]);

      // Leer el briefing actualizado para el mailer
      $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $briefing->id));

      if ($client) {
        (new TTB_Briefing_Mailer())->send_accepted_internal($updated, $client);
      }

      TTB_Briefing_DB::log($briefing->id, $briefing->ttb_client_id, 'briefing_accepted', 'client', []);

      if ($is_portal) {
        self::js_redirect(home_url('/briefing?ctab=briefings'));
      } else {
        self::js_redirect(TTB_Briefing_DB::client_url($token));
      }

    } elseif ($action === 'reject') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_briefing_reject_' . $token)) {
        self::js_redirect(TTB_Briefing_DB::client_url($token));
      }

      $note = sanitize_textarea_field($_POST['ttb_briefing_note'] ?? '');

      $wpdb->update($table, [
        'status'       => 'rejected',
        'client_note'  => $note,
        'responded_at' => TTB_Briefing_DB::now(),
        'updated_at'   => TTB_Briefing_DB::now(),
      ], ['id' => $briefing->id]);

      $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $briefing->id));

      if ($client) {
        (new TTB_Briefing_Mailer())->send_rejected_internal($updated, $client);
      }

      TTB_Briefing_DB::log($briefing->id, $briefing->ttb_client_id, 'briefing_rejected', 'client', [
        'note' => mb_substr($note, 0, 100),
      ]);

      if ($is_portal) {
        self::js_redirect(home_url('/briefing?ctab=briefings'));
      } else {
        self::js_redirect(TTB_Briefing_DB::client_url($token));
      }
    }
  }

  private static function js_redirect($url) {
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($url)) . ');</script>';
    exit;
  }
}