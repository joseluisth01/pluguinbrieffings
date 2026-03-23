<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebRev_Client')) return;

/**
 * TTB_WebRev_Client
 * Renderiza la zona pública del cliente (magic link) para revisiones web.
 * Se invoca desde TTB_Router cuando ?webrev=TOKEN está en la URL.
 */
class TTB_WebRev_Client {

  public static function render($token) {
    $project = TTB_WebRev_DB::get_project_by_token($token);

    if (!$project) {
      echo '<div class="ttb-card" style="text-align:center;padding:48px 24px">
        <p style="font-size:40px;margin:0 0 12px">🔗</p>
        <h2>Enlace no válido</h2>
        <p class="ttb-muted">Este enlace no existe o ha caducado. Contacta con TicTac Comunicación.</p>
      </div>';
      return;
    }

    // Manejar envío del formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_webrev_action'])) {
      self::handle_submit($project);
      return;
    }

    $accepted = ($project->status === 'accepted');

    // Obtener rondas de revisión
    global $wpdb;
    $revisions = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM " . TTB_WebRev_DB::revisions_table() . " WHERE project_id=%d ORDER BY created_at DESC",
      $project->id
    ));
    $round_count = count($revisions);
    $next_round  = $round_count + 1;

    ?>
    <div class="ttb-container">

      <!-- Cabecera del proyecto -->
      <div class="ttb-card ttb-card--header">
        <h2>Revisión de diseño web</h2>
        <p class="ttb-muted">Hola, <strong><?php echo esc_html($project->name); ?></strong>. Revisa el diseño y danos tu feedback.</p>
      </div>

      <?php if ($accepted): ?>
        <!-- Ya aceptado -->
        <div class="ttb-card" style="text-align:center;padding:40px 24px">
          <span style="font-size:54px;display:block;margin-bottom:12px">✅</span>
          <h3 style="margin:0 0 8px;color:#065f46">¡Diseño aceptado!</h3>
          <p class="ttb-muted">Ya nos has dado el visto bueno. Nuestro equipo está trabajando en ello. ¡Gracias!</p>
        </div>

      <?php else: ?>

        <!-- Preview del diseño — siempre tarjeta con botón (Figma bloquea iframes) -->
        <?php $is_figma = strpos($project->figma_url, 'figma.com') !== false; ?>
        <div class="ttb-card">
          <h3 style="margin:0 0 14px">🎨 Tu diseño</h3>
          <div style="
            border-radius:14px;
            border:2px dashed var(--ttb-border);
            background:linear-gradient(135deg,#fdf2f7 0%,#fff 100%);
            padding:48px 32px;
            text-align:center;
          ">
            <?php if ($is_figma): ?>
              <svg width="48" height="48" viewBox="0 0 38 57" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin:0 auto 16px;display:block">
                <path d="M19 28.5C19 25.9804 20.0009 23.5641 21.7825 21.7825C23.5641 20.0009 25.9804 19 28.5 19C31.0196 19 33.4359 20.0009 35.2175 21.7825C36.9991 23.5641 38 25.9804 38 28.5C38 31.0196 36.9991 33.4359 35.2175 35.2175C33.4359 36.9991 31.0196 38 28.5 38H19V28.5Z" fill="#1ABCFE"/>
                <path d="M0 47.5C0 44.9804 1.00089 42.5641 2.78249 40.7825C4.56408 39.0009 6.98044 38 9.5 38H19V47.5C19 50.0196 17.9991 52.4359 16.2175 54.2175C14.4359 55.9991 12.0196 57 9.5 57C6.98044 57 4.56408 55.9991 2.78249 54.2175C1.00089 52.4359 0 50.0196 0 47.5Z" fill="#0ACF83"/>
                <path d="M19 0V19H28.5C31.0196 19 33.4359 17.9991 35.2175 16.2175C36.9991 14.4359 38 12.0196 38 9.5C38 6.98044 36.9991 4.56408 35.2175 2.78249C33.4359 1.00089 31.0196 0 28.5 0H19Z" fill="#FF7262"/>
                <path d="M0 9.5C0 12.0196 1.00089 14.4359 2.78249 16.2175C4.56408 17.9991 6.98044 19 9.5 19H19V0H9.5C6.98044 0 4.56408 1.00089 2.78249 2.78249C1.00089 4.56408 0 6.98044 0 9.5Z" fill="#F24E1E"/>
                <path d="M0 28.5C0 31.0196 1.00089 33.4359 2.78249 35.2175C4.56408 36.9991 6.98044 38 9.5 38H19V19H9.5C6.98044 19 4.56408 20.0009 2.78249 21.7825C1.00089 23.5641 0 25.9804 0 28.5Z" fill="#A259FF"/>
              </svg>
              <p style="margin:0 0 8px;font-size:20px;font-weight:900;color:var(--ttb-text)">Tu diseño está en Figma</p>
              <p style="margin:0 0 28px;font-size:15px;color:var(--ttb-muted);line-height:1.6">
                Haz clic en el botón para ver el diseño completo.<br>
                Después vuelve aquí para aceptar o pedir cambios.
              </p>
            <?php else: ?>
              <p style="font-size:40px;margin:0 0 12px">🎨</p>
              <p style="margin:0 0 8px;font-size:20px;font-weight:900;color:var(--ttb-text)">Tu diseño está listo</p>
              <p style="margin:0 0 28px;font-size:15px;color:var(--ttb-muted)">
                Haz clic para abrirlo. Después vuelve aquí para aceptar o pedir cambios.
              </p>
            <?php endif; ?>

            <a href="<?php echo esc_url($project->figma_url); ?>" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:10px;
                      background:linear-gradient(135deg,#D72173 0%,#a8005a 100%);
                      color:#fff;text-decoration:none;font-weight:900;font-size:17px;
                      padding:18px 40px;border-radius:14px;
                      box-shadow:0 8px 24px rgba(215,33,115,.30)">
              🔗 <?php echo $is_figma ? 'Abrir diseño en Figma' : 'Ver diseño'; ?>
            </a>

            <p style="margin:18px 0 0;font-size:12px;color:var(--ttb-muted)">
              Se abrirá en una nueva pestaña
            </p>
          </div>
        </div>

        <!-- Acciones -->
        <div class="ttb-card">
          <h3 style="margin:0 0 6px">¿Qué quieres hacer?</h3>
          <p class="ttb-muted" style="margin:0 0 20px">Ronda actual: <strong>#<?php echo $next_round; ?></strong></p>

          <!-- Tabs de acción -->
          <div class="ttbwr-action-tabs" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
            <button class="ttb-btn ttb-btn--ghost ttbwr-tab-btn ttbwr-tab-btn--active" data-tab="accept" type="button">
              ✅ Aceptar diseño
            </button>
            <button class="ttb-btn ttb-btn--ghost ttbwr-tab-btn" data-tab="changes" type="button">
              ✏️ Necesito cambios
            </button>
          </div>

          <!-- Panel: Aceptar -->
          <div class="ttbwr-panel" id="ttbwr-panel-accept">
            <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:20px 24px;margin-bottom:16px">
              <p style="margin:0;font-size:15px;color:#065f46;line-height:1.6">
                Al aceptar, confirmas que el diseño es correcto y autorizas a TicTac a continuar con el desarrollo. Recibirás una confirmación por email.
              </p>
            </div>
            <form method="post" action="">
              <?php wp_nonce_field('ttb_webrev_accept_' . $token); ?>
              <input type="hidden" name="ttb_webrev_action" value="accept">
              <input type="hidden" name="ttb_webrev_token" value="<?php echo esc_attr($token); ?>">
              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#10b981,#059669)">
                  ✅ Confirmar aceptación del diseño
                </button>
              </div>
            </form>
          </div>

          <!-- Panel: Cambios -->
          <div class="ttbwr-panel" id="ttbwr-panel-changes" style="display:none">

            <div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:14px;color:#92400e;line-height:1.6">
              💡 <strong>Consejo:</strong> Añade tantos bloques como necesites. Puedes combinar texto libre con imágenes comentadas para explicar cada cambio con el máximo detalle.
            </div>

            <form method="post" action="" enctype="multipart/form-data" id="ttbwr-changes-form">
              <?php wp_nonce_field('ttb_webrev_changes_' . $token); ?>
              <input type="hidden" name="ttb_webrev_action" value="changes">
              <input type="hidden" name="ttb_webrev_token" value="<?php echo esc_attr($token); ?>">
              <!-- Los bloques se serializan aquí antes de enviar -->
              <input type="hidden" name="ttbwr_blocks_json" id="ttbwr_blocks_json" value="">

              <!-- Contenedor de bloques -->
              <div id="ttbwr-blocks-container"></div>

              <!-- Botones para añadir bloques -->
              <div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 24px">
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwr-add-text">
                  ✏️ Añadir bloque de texto
                </button>
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwr-add-image">
                  🖼️ Añadir imagen con comentario
                </button>
              </div>

              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" id="ttbwr-submit-btn">
                  📨 Enviar cambios
                </button>
              </div>
            </form>
          </div>
        </div>

      <?php endif; ?>

      <!-- Historial de revisiones -->
      <?php if ($revisions): ?>
        <div class="ttb-card">
          <h3 style="margin:0 0 16px">📋 Historial de revisiones</h3>
          <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($revisions as $rev): ?>
              <?php
                $is_accepted = $rev->type === 'accept';
                $images = json_decode((string)$rev->images, true);
                $bg  = $is_accepted ? '#ecfdf5' : '#fffbeb';
                $bc  = $is_accepted ? '#6ee7b7' : '#fcd34d';
                $ico = $is_accepted ? '✅' : '✏️';
                $lbl = $is_accepted ? 'Diseño aceptado' : 'Cambios solicitados — Ronda #' . $rev->round;
              ?>
              <div style="background:<?php echo $bg; ?>;border:1.5px solid <?php echo $bc; ?>;border-radius:14px;padding:16px 20px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                  <p style="margin:0;font-weight:900;color:var(--ttb-text)"><?php echo $ico . ' ' . esc_html($lbl); ?></p>
                  <span style="font-size:13px;color:var(--ttb-muted)"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($rev->created_at))); ?></span>
                </div>
                <?php if ($rev->message || $rev->images): ?>
                  <?php
                    // Intentar parsear como bloques nuevos
                    $blocks = json_decode((string)$rev->images, true);
                    $is_blocks = is_array($blocks) && !empty($blocks) && isset($blocks[0]['type']);
                  ?>
                  <?php if ($is_blocks): ?>
                    <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px">
                      <?php foreach ($blocks as $bl): ?>
                        <?php if (($bl['type'] ?? '') === 'text' && !empty($bl['html'])): ?>
                          <div style="font-size:14px;color:var(--ttb-text);line-height:1.7;background:#fff;border-radius:10px;padding:12px 14px;border:1px solid rgba(0,0,0,.07)">
                            <?php echo wp_kses_post($bl['html']); ?>
                          </div>
                        <?php elseif (($bl['type'] ?? '') === 'image'): ?>
                          <div style="background:#fff;border-radius:10px;border:1px solid rgba(0,0,0,.07);overflow:hidden">
                            <?php if (!empty($bl['image_url'])): ?>
                              <a href="<?php echo esc_url($bl['image_url']); ?>" target="_blank">
                                <img src="<?php echo esc_url($bl['image_url']); ?>"
                                     style="width:100%;max-height:320px;object-fit:contain;display:block;background:#f4f4f4"
                                     alt="Adjunto">
                              </a>
                            <?php endif; ?>
                            <?php if (!empty($bl['caption'])): ?>
                              <div style="padding:10px 14px;font-size:14px;color:var(--ttb-text);line-height:1.6;border-top:1px solid rgba(0,0,0,.06)">
                                <?php echo nl2br(esc_html($bl['caption'])); ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <?php /* Formato antiguo: texto plano + array de URLs */ ?>
                    <?php if ($rev->message): ?>
                      <p style="margin:10px 0 0;font-size:14px;color:var(--ttb-text);line-height:1.6;white-space:pre-line"><?php echo nl2br(esc_html($rev->message)); ?></p>
                    <?php endif; ?>
                    <?php
                      $old_images = json_decode((string)$rev->images, true);
                      if (is_array($old_images) && $old_images):
                    ?>
                      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                        <?php foreach ($old_images as $img_url): ?>
                          <a href="<?php echo esc_url($img_url); ?>" target="_blank">
                            <img src="<?php echo esc_url($img_url); ?>" style="height:72px;width:auto;border-radius:8px;border:1px solid rgba(0,0,0,.1);object-fit:cover" alt="Adjunto">
                          </a>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <style>
    /* ── Tabs de acción ── */
    .ttbwr-tab-btn--active {
      background: rgba(215,33,115,.10) !important;
      border-color: rgba(215,33,115,.35) !important;
      color: var(--ttb-pink) !important;
    }

    /* ── Bloques de cambio ── */
    .ttbwr-block {
      border: 1.5px solid var(--ttb-border);
      border-radius: 14px;
      background: #fff;
      margin-bottom: 14px;
      overflow: hidden;
      transition: box-shadow .2s;
    }
    .ttbwr-block:focus-within { box-shadow: 0 0 0 3px rgba(215,33,115,.12); border-color: rgba(215,33,115,.4); }

    .ttbwr-block-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 14px;
      background: #f9fafb;
      border-bottom: 1px solid var(--ttb-border);
      gap: 8px;
    }
    .ttbwr-block-label {
      font-size: 12px;
      font-weight: 900;
      color: var(--ttb-muted);
      text-transform: uppercase;
      letter-spacing: .06em;
    }
    .ttbwr-block-actions { display: flex; gap: 6px; }
    .ttbwr-block-btn {
      background: none;
      border: 1px solid var(--ttb-border);
      border-radius: 8px;
      padding: 4px 8px;
      font-size: 13px;
      cursor: pointer;
      color: var(--ttb-muted);
      transition: background .15s, color .15s;
    }
    .ttbwr-block-btn:hover { background: #f0f0f0; color: var(--ttb-text); }
    .ttbwr-block-btn--delete:hover { background: #fff1f2; color: #e11d48; border-color: #fecdd3; }

    /* ── WYSIWYG toolbar ── */
    .ttbwr-wysiwyg-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 2px;
      padding: 8px 10px;
      background: #f9fafb;
      border-bottom: 1px solid var(--ttb-border);
    }
    .ttbwr-wysiwyg-bar button {
      background: none;
      border: 1px solid transparent;
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      color: var(--ttb-text);
      line-height: 1.4;
      min-width: 28px;
      transition: background .12s, border-color .12s;
    }
    .ttbwr-wysiwyg-bar button:hover { background: #e5e7eb; border-color: #d1d5db; }
    .ttbwr-wysiwyg-bar button.active { background: rgba(215,33,115,.12); border-color: rgba(215,33,115,.3); color: var(--ttb-pink); }
    .ttbwr-wysiwyg-bar .ttbwr-sep { width: 1px; background: var(--ttb-border); margin: 2px 4px; align-self: stretch; }

    .ttbwr-editor {
      min-height: 120px;
      padding: 14px 16px;
      outline: none;
      font-size: 15px;
      line-height: 1.7;
      color: var(--ttb-text);
    }
    .ttbwr-editor:empty::before {
      content: attr(data-placeholder);
      color: #9ca3af;
      pointer-events: none;
    }
    .ttbwr-editor ul, .ttbwr-editor ol { padding-left: 22px; margin: 6px 0; }
    .ttbwr-editor blockquote {
      border-left: 3px solid var(--ttb-pink);
      margin: 8px 0;
      padding: 4px 12px;
      color: var(--ttb-muted);
      font-style: italic;
    }

    /* ── Bloque imagen ── */
    .ttbwr-img-block { padding: 14px 16px; }
    .ttbwr-img-dropzone {
      border: 2px dashed var(--ttb-border);
      border-radius: 12px;
      padding: 28px 20px;
      text-align: center;
      cursor: pointer;
      background: #fafafa;
      transition: border-color .2s, background .2s;
      margin-bottom: 12px;
    }
    .ttbwr-img-dropzone:hover, .ttbwr-img-dropzone.dragover {
      border-color: var(--ttb-pink);
      background: rgba(215,33,115,.03);
    }
    .ttbwr-img-preview {
      position: relative;
      display: inline-block;
      margin-bottom: 12px;
    }
    .ttbwr-img-preview img {
      max-width: 100%;
      max-height: 280px;
      border-radius: 10px;
      border: 1px solid var(--ttb-border);
      display: block;
    }
    .ttbwr-img-preview-remove {
      position: absolute;
      top: -8px; right: -8px;
      background: #e11d48; color: #fff;
      border: none; border-radius: 50%;
      width: 22px; height: 22px;
      font-size: 12px; font-weight: 900;
      cursor: pointer; line-height: 1;
    }
    .ttbwr-img-caption {
      width: 100%;
      border: 1px solid var(--ttb-border);
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 14px;
      line-height: 1.5;
      resize: vertical;
      min-height: 72px;
      font-family: inherit;
      color: var(--ttb-text);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .ttbwr-img-caption:focus { border-color: var(--ttb-pink); box-shadow: 0 0 0 3px rgba(215,33,115,.10); }
    </style>

    <script>
    (function(){
      var MAX_MB    = <?php echo (int)get_option('ttb_webrev_max_filesize', 5); ?>;
      var MAX_FILES = <?php echo (int)get_option('ttb_webrev_max_files', 10); ?>;
      var blockCount = 0;

      // ── Tabs de acción ──────────────────────────────
      document.querySelectorAll('.ttbwr-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var target = btn.getAttribute('data-tab');
          document.querySelectorAll('.ttbwr-tab-btn').forEach(function(b){ b.classList.remove('ttbwr-tab-btn--active'); });
          btn.classList.add('ttbwr-tab-btn--active');
          document.getElementById('ttbwr-panel-accept').style.display  = (target === 'accept')  ? 'block' : 'none';
          document.getElementById('ttbwr-panel-changes').style.display = (target === 'changes') ? 'block' : 'none';
        });
      });

      // ── Añadir bloque de texto ───────────────────────
      document.getElementById('ttbwr-add-text').addEventListener('click', function(){
        addTextBlock();
      });

      // ── Añadir bloque de imagen ──────────────────────
      document.getElementById('ttbwr-add-image').addEventListener('click', function(){
        addImageBlock();
      });

      // ── Bloque de texto (WYSIWYG) ────────────────────
      function addTextBlock(initialHtml) {
        blockCount++;
        var id = 'ttbwr-block-' + blockCount;
        var div = document.createElement('div');
        div.className = 'ttbwr-block';
        div.setAttribute('data-type', 'text');
        div.setAttribute('data-id', id);
        div.innerHTML =
          '<div class="ttbwr-block-header">'
          + '<span class="ttbwr-block-label">✏️ Bloque de texto</span>'
          + '<div class="ttbwr-block-actions">'
          + '<button type="button" class="ttbwr-block-btn" data-move="up" title="Subir">↑</button>'
          + '<button type="button" class="ttbwr-block-btn" data-move="down" title="Bajar">↓</button>'
          + '<button type="button" class="ttbwr-block-btn ttbwr-block-btn--delete" data-delete="1" title="Eliminar bloque">✕</button>'
          + '</div>'
          + '</div>'
          + '<div class="ttbwr-wysiwyg-bar">'
          + '<button type="button" data-cmd="bold" title="Negrita"><b>N</b></button>'
          + '<button type="button" data-cmd="italic" title="Cursiva"><i>C</i></button>'
          + '<button type="button" data-cmd="underline" title="Subrayado"><u>S</u></button>'
          + '<span class="ttbwr-sep"></span>'
          + '<button type="button" data-cmd="insertUnorderedList" title="Lista">• Lista</button>'
          + '<button type="button" data-cmd="insertOrderedList" title="Lista numerada">1. Lista</button>'
          + '<span class="ttbwr-sep"></span>'
          + '<button type="button" data-cmd="formatBlock|<blockquote>" title="Cita">❝ Cita</button>'
          + '<button type="button" data-cmd="formatBlock|<p>" title="Texto normal">¶ Normal</button>'
          + '<span class="ttbwr-sep"></span>'
          + '<button type="button" data-cmd="removeFormat" title="Quitar formato">✕ Formato</button>'
          + '</div>'
          + '<div class="ttbwr-editor" contenteditable="true" data-placeholder="Escribe aquí los cambios que necesitas con todo el detalle que quieras..."></div>';

        getContainer().appendChild(div);

        var editor = div.querySelector('.ttbwr-editor');
        if (initialHtml) editor.innerHTML = initialHtml;

        // Toolbar
        div.querySelectorAll('.ttbwr-wysiwyg-bar button').forEach(function(btn){
          btn.addEventListener('mousedown', function(e){
            e.preventDefault();
            var cmd = btn.getAttribute('data-cmd');
            if (cmd.indexOf('|') !== -1) {
              var parts = cmd.split('|');
              document.execCommand(parts[0], false, parts[1]);
            } else {
              document.execCommand(cmd, false, null);
            }
            editor.focus();
          });
        });

        // Actualizar estado activo en toolbar
        editor.addEventListener('keyup', updateToolbar);
        editor.addEventListener('mouseup', updateToolbar);
        function updateToolbar() {
          div.querySelectorAll('.ttbwr-wysiwyg-bar button[data-cmd]').forEach(function(b){
            var cmd = b.getAttribute('data-cmd').split('|')[0];
            try { b.classList.toggle('active', document.queryCommandState(cmd)); } catch(e){}
          });
        }

        // Acciones del bloque
        bindBlockActions(div);
        editor.focus();
      }

      // ── Bloque de imagen + comentario ───────────────
      function addImageBlock(initialSrc, initialCaption) {
        blockCount++;
        var id = 'ttbwr-block-' + blockCount;
        var div = document.createElement('div');
        div.className = 'ttbwr-block';
        div.setAttribute('data-type', 'image');
        div.setAttribute('data-id', id);
        div.innerHTML =
          '<div class="ttbwr-block-header">'
          + '<span class="ttbwr-block-label">🖼️ Imagen + comentario</span>'
          + '<div class="ttbwr-block-actions">'
          + '<button type="button" class="ttbwr-block-btn" data-move="up" title="Subir">↑</button>'
          + '<button type="button" class="ttbwr-block-btn" data-move="down" title="Bajar">↓</button>'
          + '<button type="button" class="ttbwr-block-btn ttbwr-block-btn--delete" data-delete="1" title="Eliminar bloque">✕</button>'
          + '</div>'
          + '</div>'
          + '<div class="ttbwr-img-block">'
          + '<div class="ttbwr-img-dropzone" tabindex="0">'
          + '<p style="margin:0 0 6px;font-size:28px">📎</p>'
          + '<p style="margin:0 0 4px;font-weight:700;color:var(--ttb-text);font-size:14px">Arrastra una imagen o haz clic para seleccionar</p>'
          + '<p style="margin:0;font-size:12px;color:var(--ttb-muted)">PNG, JPG, GIF, WEBP · Máx. ' + MAX_MB + ' MB</p>'
          + '<input type="file" accept="image/*" style="display:none">'
          + '</div>'
          + '<textarea class="ttbwr-img-caption" placeholder="Describe qué quieres cambiar en esta imagen (sección, elemento, color, texto...)"></textarea>'
          + '</div>';

        getContainer().appendChild(div);

        var dz      = div.querySelector('.ttbwr-img-dropzone');
        var input   = div.querySelector('input[type=file]');
        var caption = div.querySelector('.ttbwr-img-caption');

        if (initialSrc) {
          showPreview(div, dz, initialSrc);
        }
        if (initialCaption) {
          caption.value = initialCaption;
        }

        // Click / drag
        dz.addEventListener('click', function(){ input.click(); });
        dz.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' ') input.click(); });
        dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('dragover'); });
        dz.addEventListener('dragleave', function(){ dz.classList.remove('dragover'); });
        dz.addEventListener('drop', function(e){
          e.preventDefault(); dz.classList.remove('dragover');
          var file = e.dataTransfer.files[0];
          if (file) loadFile(div, dz, file);
        });
        input.addEventListener('change', function(){
          if (input.files[0]) loadFile(div, dz, input.files[0]);
        });

        bindBlockActions(div);
      }

      function loadFile(block, dz, file) {
        if (!file.type.startsWith('image/')) { alert('Solo se admiten imágenes.'); return; }
        if (file.size > MAX_MB * 1024 * 1024) { alert('La imagen supera el límite de ' + MAX_MB + ' MB.'); return; }
        var reader = new FileReader();
        reader.onload = function(e) {
          block.setAttribute('data-file-dataurl', e.target.result);
          block.setAttribute('data-file-name', file.name);
          block.setAttribute('data-file-type', file.type);
          block.setAttribute('data-file-size', file.size);
          showPreview(block, dz, e.target.result);
        };
        reader.readAsDataURL(file);
      }

      function showPreview(block, dz, src) {
        dz.style.display = 'none';
        var existing = block.querySelector('.ttbwr-img-preview');
        if (existing) existing.remove();

        var wrap = document.createElement('div');
        wrap.className = 'ttbwr-img-preview';
        var img = document.createElement('img');
        img.src = src;
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'ttbwr-img-preview-remove';
        rm.textContent = '✕';
        rm.title = 'Quitar imagen';
        rm.addEventListener('click', function(){
          wrap.remove();
          block.removeAttribute('data-file-dataurl');
          block.removeAttribute('data-file-name');
          dz.style.display = '';
        });
        wrap.appendChild(img);
        wrap.appendChild(rm);
        block.querySelector('.ttbwr-img-block').insertBefore(wrap, block.querySelector('.ttbwr-img-caption'));
      }

      // ── Acciones de bloque (mover / eliminar) ────────
      function bindBlockActions(block) {
        block.querySelector('[data-delete]').addEventListener('click', function(){
          if (getContainer().querySelectorAll('.ttbwr-block').length <= 1) {
            alert('Debe haber al menos un bloque de cambio.');
            return;
          }
          block.remove();
        });
        block.querySelector('[data-move="up"]').addEventListener('click', function(){
          var prev = block.previousElementSibling;
          if (prev) getContainer().insertBefore(block, prev);
        });
        block.querySelector('[data-move="down"]').addEventListener('click', function(){
          var next = block.nextElementSibling;
          if (next) getContainer().insertBefore(next, block);
        });
      }

      function getContainer() {
        return document.getElementById('ttbwr-blocks-container');
      }

      // ── Inicializar con un bloque de texto vacío ─────
      addTextBlock();

      // ── Serializar bloques antes de enviar ───────────
      document.getElementById('ttbwr-changes-form').addEventListener('submit', function(e){
        e.preventDefault();

        var blocks = [];
        var imageFiles = []; // { blockIndex, file }
        var container  = getContainer();
        var blockEls   = container.querySelectorAll('.ttbwr-block');

        var hasContent = false;
        blockEls.forEach(function(bl, idx) {
          var type = bl.getAttribute('data-type');
          if (type === 'text') {
            var html = bl.querySelector('.ttbwr-editor').innerHTML.trim();
            var plain = bl.querySelector('.ttbwr-editor').innerText.trim();
            if (plain) hasContent = true;
            blocks.push({ type: 'text', html: html, idx: idx });
          } else if (type === 'image') {
            var caption = bl.querySelector('.ttbwr-img-caption').value.trim();
            var dataUrl = bl.getAttribute('data-file-dataurl') || '';
            var fname   = bl.getAttribute('data-file-name')    || '';
            var ftype   = bl.getAttribute('data-file-type')    || '';
            if (dataUrl || caption) hasContent = true;
            blocks.push({ type: 'image', caption: caption, fileIndex: dataUrl ? imageFiles.length : -1, idx: idx });
            if (dataUrl) {
              imageFiles.push({ blockIndex: blocks.length - 1, dataUrl: dataUrl, name: fname, mimeType: ftype });
            }
          }
        });

        if (!hasContent) {
          alert('Añade al menos un comentario o imagen antes de enviar.');
          return;
        }

        document.getElementById('ttbwr_blocks_json').value = JSON.stringify(blocks);

        // Construir FormData manualmente con los archivos
        var fd = new FormData(document.getElementById('ttbwr-changes-form'));

        // Convertir dataURLs a Blob y adjuntar
        imageFiles.forEach(function(f, i) {
          var arr = f.dataUrl.split(','), mime = arr[0].match(/:(.*?);/)[1];
          var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
          for (var j=0; j<n; j++) u8[j] = bstr.charCodeAt(j);
          var blob = new Blob([u8], { type: mime });
          fd.append('ttbwr_img_file_' + i, blob, f.name || ('imagen-' + (i+1) + '.jpg'));
        });
        fd.set('ttbwr_img_count', imageFiles.length);

        var btn = document.getElementById('ttbwr-submit-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Enviando...';

        fetch(window.location.href, { method: 'POST', body: fd })
          .then(function(r){ return r.text(); })
          .then(function(html){
            // Buscar JS redirect en la respuesta
            var match = html.match(/window\.location\.replace\((.+?)\)/);
            if (match) {
              var url = JSON.parse(match[1]);
              window.location.replace(url);
            } else {
              window.location.reload();
            }
          })
          .catch(function(){
            btn.disabled = false;
            btn.textContent = '📨 Enviar cambios';
            alert('Error al enviar. Inténtalo de nuevo.');
          });
      });

    })();
    </script>
    <?php
  }

  /* ─────────────────────────────────────────────
     REDIRECT VÍA JS
     wp_safe_redirect no funciona aquí porque el
     shell ya ha enviado headers al hacer include.
  ───────────────────────────────────────────── */
  private static function js_redirect($url) {
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($url)) . ');</script>';
    exit;
  }

  /* ─────────────────────────────────────────────
     MANEJAR SUBMIT DEL CLIENTE
  ───────────────────────────────────────────── */
  private static function handle_submit($project) {
    $action = sanitize_text_field($_POST['ttb_webrev_action'] ?? '');
    $token  = sanitize_text_field($_POST['ttb_webrev_token']  ?? '');

    global $wpdb;
    $projects_table  = TTB_WebRev_DB::projects_table();
    $revisions_table = TTB_WebRev_DB::revisions_table();

    if ($action === 'accept') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_webrev_accept_' . $token)) {
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      // Marcar como aceptado
      $wpdb->update($projects_table, [
        'status'     => 'accepted',
        'updated_at' => TTB_WebRev_DB::now(),
      ], ['id' => $project->id]);

      // Guardar registro
      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'accept',
        'message'    => null,
        'images'     => null,
        'created_at' => TTB_WebRev_DB::now(),
      ]);

      // Notificar equipo
      (new TTB_WebRev_Mailer())->send_accepted_alert($project);
      TTB_WebRev_DB::log($project->id, 'design_accepted', 'client');
      TTB_WebRev_DB::log($project->id, 'email_accepted_sent', 'system', ['recipients' => 'hola+creativo']);

      // Redirigir para mostrar estado aceptado
      self::js_redirect(TTB_WebRev_DB::client_url($token));

    } elseif ($action === 'changes') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_webrev_changes_' . $token)) {
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      $blocks_raw = sanitize_text_field($_POST['ttbwr_blocks_json'] ?? '');
      $blocks     = json_decode(stripslashes($blocks_raw), true);

      if (!is_array($blocks) || empty($blocks)) {
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      // Validar que hay contenido real
      $has_content = false;
      foreach ($blocks as $b) {
        if (!empty($b['html']) || !empty($b['caption'])) { $has_content = true; break; }
        if (isset($b['fileIndex']) && $b['fileIndex'] >= 0)    { $has_content = true; break; }
      }
      if (!$has_content) {
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      // ── Subir imágenes indexadas ──────────────────────
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';

      $max_mb    = (int)get_option('ttb_webrev_max_filesize', 5);
      $max_files = (int)get_option('ttb_webrev_max_files', 10);
      $img_count = min((int)($_POST['ttbwr_img_count'] ?? 0), $max_files);
      $uploaded  = []; // índice → URL

      for ($i = 0; $i < $img_count; $i++) {
        $key = 'ttbwr_img_file_' . $i;
        if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) continue;
        if ($_FILES[$key]['size'] > $max_mb * 1024 * 1024) continue;
        $type = $_FILES[$key]['type'];
        if (!in_array($type, ['image/jpeg','image/png','image/gif','image/webp'], true)) continue;

        $file_array = [
          'name'     => $_FILES[$key]['name'],
          'type'     => $type,
          'tmp_name' => $_FILES[$key]['tmp_name'],
          'error'    => $_FILES[$key]['error'],
          'size'     => $_FILES[$key]['size'],
        ];
        $att_id = media_handle_sideload($file_array, 0, null, [
          'post_title'  => 'WebRev - ' . $project->name . ' #' . $round . ' img' . ($i+1),
          'post_status' => 'private',
        ]);
        if (!is_wp_error($att_id)) {
          $uploaded[$i] = wp_get_attachment_url($att_id);
        }
      }

      // ── Enriquecer bloques con las URLs subidas ───────
      $sanitized_blocks = [];
      foreach ($blocks as $b) {
        $type = $b['type'] ?? '';
        if ($type === 'text') {
          $html = wp_kses_post($b['html'] ?? '');
          $text = wp_strip_all_tags($html);
          if (trim($text) === '') continue; // omitir bloques vacíos
          $sanitized_blocks[] = ['type' => 'text', 'html' => $html];
        } elseif ($type === 'image') {
          $caption   = sanitize_textarea_field($b['caption'] ?? '');
          $file_idx  = (int)($b['fileIndex'] ?? -1);
          $image_url = ($file_idx >= 0 && isset($uploaded[$file_idx])) ? $uploaded[$file_idx] : '';
          if (!$image_url && !$caption) continue; // bloque vacío, omitir
          $sanitized_blocks[] = ['type' => 'image', 'caption' => $caption, 'image_url' => $image_url];
        }
      }

      if (empty($sanitized_blocks)) {
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      // Texto plano del resumen (para el email)
      $message_plain = '';
      foreach ($sanitized_blocks as $b) {
        if ($b['type'] === 'text') $message_plain .= wp_strip_all_tags($b['html']) . "\n\n";
        if ($b['type'] === 'image' && $b['caption']) $message_plain .= '[Imagen] ' . $b['caption'] . "\n\n";
      }
      $message_plain = trim($message_plain);

      // Actualizar estado proyecto
      $wpdb->update($projects_table, [
        'status'          => 'changes_requested',
        'last_notified'   => TTB_WebRev_DB::now(),
        'updated_at'      => TTB_WebRev_DB::now(),
      ], ['id' => $project->id]);

      // Guardar revisión con bloques en el campo `images` (JSON)
      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'change',
        'message'    => $message_plain,
        'images'     => wp_json_encode($sanitized_blocks, JSON_UNESCAPED_UNICODE),
        'created_at' => TTB_WebRev_DB::now(),
      ]);

      $revision = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $revisions_table WHERE id=%d", $wpdb->insert_id
      ));

      (new TTB_WebRev_Mailer())->send_changes_alert($project, $revision);
      TTB_WebRev_DB::log($project->id, 'changes_requested', 'client', ['round' => $round]);
      TTB_WebRev_DB::log($project->id, 'email_changes_sent', 'system', ['round' => $round, 'recipients' => 'hola+creativo']);

      self::js_redirect(TTB_WebRev_DB::client_url($token));
    }
  }
}