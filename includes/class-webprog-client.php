<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebProg_Client')) return;

/**
 * TTB_WebProg_Client
 * Renderiza la zona pública del cliente (magic link) para revisiones Prog. Web.
 * Se invoca desde TTB_Router cuando ?webprog=TOKEN está en la URL.
 */
class TTB_WebProg_Client {

  public static function render($token) {
    $project = TTB_WebProg_DB::get_project_by_token($token);

    if (!$project) {
      echo '<div class="ttb-card" style="text-align:center;padding:48px 24px">
        <p style="font-size:40px;margin:0 0 12px">🔗</p>
        <h2>Enlace no válido</h2>
        <p class="ttb-muted">Este enlace no existe o ha caducado. Contacta con TicTac Comunicación.</p>
      </div>';
      TTB_WebProg_DB::log(null, 'invalid_token_access', 'client', ['token_partial' => substr($token, 0, 8) . '…']);
      return;
    }

    // Manejar envío del formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_webprog_action'])) {
      self::handle_submit($project);
      return;
    }

    // Registrar visita
    TTB_WebProg_DB::log($project->id, 'client_view', 'client', [
      'status' => $project->status,
    ]);

    $accepted = ($project->status === 'accepted');

    global $wpdb;
    $revisions   = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM " . TTB_WebProg_DB::revisions_table() . " WHERE project_id=%d ORDER BY created_at DESC",
      $project->id
    ));
    $round_count = count($revisions);
    $next_round  = $round_count + 1;

    ?>
    <div class="ttb-container">

      <!-- Cabecera -->
      <div class="ttb-card ttb-card--header">
        <h2>Revisión de tu web</h2>
        <p class="ttb-muted">Hola, <strong><?php echo esc_html($project->name); ?></strong>. Navega por tu web y danos tu feedback.</p>
      </div>

      <?php if ($accepted): ?>
        <div class="ttb-card" style="text-align:center;padding:40px 24px">
          <span style="font-size:54px;display:block;margin-bottom:12px">✅</span>
          <h3 style="margin:0 0 8px;color:#065f46">¡Web aceptada!</h3>
          <p class="ttb-muted">Ya nos has dado el visto bueno. Nuestro equipo procederá con los siguientes pasos. ¡Gracias!</p>
        </div>

      <?php else: ?>

        <!-- Preview de la web -->
        <div class="ttb-card">
          <h3 style="margin:0 0 14px">🌐 Tu web</h3>
          <div style="
            border-radius:14px;
            border:2px dashed var(--ttb-border);
            background:linear-gradient(135deg,#f0f9ff 0%,#fff 100%);
            padding:48px 32px;
            text-align:center;
          ">
            <div style="font-size:52px;margin:0 auto 16px;display:block;line-height:1">🌐</div>
            <p style="margin:0 0 8px;font-size:20px;font-weight:900;color:var(--ttb-text)">Tu web está lista</p>
            <p style="margin:0 0 6px;font-size:14px;color:var(--ttb-muted);font-family:monospace;word-break:break-all">
              <?php echo esc_html($project->web_url); ?>
            </p>
            <p style="margin:0 0 28px;font-size:15px;color:var(--ttb-muted);line-height:1.6">
              Haz clic para abrirla y navegar por todas las páginas.<br>
              Después vuelve aquí para aceptar o pedir cambios.
            </p>

            <a href="<?php echo esc_url($project->web_url); ?>" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:10px;
                      background:linear-gradient(135deg,#D72173 0%,#a8005a 100%);
                      color:#fff;text-decoration:none;font-weight:900;font-size:17px;
                      padding:18px 40px;border-radius:14px;
                      box-shadow:0 8px 24px rgba(215,33,115,.30)">
              🔗 Abrir mi web
            </a>

            <p style="margin:18px 0 0;font-size:12px;color:var(--ttb-muted)">
              Se abrirá en una nueva pestaña · Revísala en móvil y ordenador si puedes
            </p>
          </div>
        </div>

        <!-- Consejos de revisión -->
        <div class="ttb-card" style="background:linear-gradient(135deg,#f0f9ff,#fff);border-color:#bae6fd">
          <h4 style="margin:0 0 12px;color:#0369a1">💡 ¿Qué revisar en tu web?</h4>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
            <?php
            $tips = [
              ['🔤', 'Textos', 'Comprueba que todos los textos son correctos y no hay erratas.'],
              ['🖼️', 'Imágenes', 'Verifica que las imágenes se ven bien y son las correctas.'],
              ['📱', 'Móvil', 'Ábrela desde tu teléfono y comprueba que se ve bien.'],
              ['🔗', 'Botones', 'Haz clic en los botones y enlaces para ver que funcionan.'],
              ['📝', 'Formularios', 'Si hay formularios, prueba a rellenarlos.'],
              ['📧', 'Contacto', 'Comprueba que los datos de contacto son correctos.'],
            ];
            foreach ($tips as [$icon, $title, $desc]):
            ?>
              <div style="background:#fff;border:1px solid #bae6fd;border-radius:12px;padding:14px 16px">
                <p style="margin:0 0 4px;font-size:16px"><?php echo $icon; ?> <strong style="color:var(--ttb-text);font-size:14px"><?php echo $title; ?></strong></p>
                <p style="margin:0;font-size:13px;color:var(--ttb-muted);line-height:1.5"><?php echo esc_html($desc); ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Acciones -->
        <div class="ttb-card">
          <h3 style="margin:0 0 6px">¿Qué quieres hacer?</h3>
          <p class="ttb-muted" style="margin:0 0 20px">Ronda actual: <strong>#<?php echo $next_round; ?></strong></p>

          <div class="ttbwp-action-tabs" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
            <button class="ttb-btn ttb-btn--ghost ttbwp-tab-btn ttbwp-tab-btn--active" data-tab="accept" type="button">
              ✅ Aceptar la web
            </button>
            <button class="ttb-btn ttb-btn--ghost ttbwp-tab-btn" data-tab="changes" type="button">
              ✏️ Tengo cambios que pedir
            </button>
          </div>

          <!-- Panel: Aceptar -->
          <div class="ttbwp-panel" id="ttbwp-panel-accept">
            <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:20px 24px;margin-bottom:16px">
              <p style="margin:0;font-size:15px;color:#065f46;line-height:1.6">
                Al aceptar, confirmas que la web está correcta tal y como está y autorizas a TicTac a continuar. Recibirás una confirmación por email.
              </p>
            </div>
            <form method="post" action="">
              <?php wp_nonce_field('ttb_webprog_accept_' . $token); ?>
              <input type="hidden" name="ttb_webprog_action" value="accept">
              <input type="hidden" name="ttb_webprog_token" value="<?php echo esc_attr($token); ?>">
              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#10b981,#059669)">
                  ✅ Confirmar aceptación de la web
                </button>
              </div>
            </form>
          </div>

          <!-- Panel: Cambios -->
          <div class="ttbwp-panel" id="ttbwp-panel-changes" style="display:none">

            <div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:14px;color:#92400e;line-height:1.6">
              💡 <strong>Consejo:</strong> Sé lo más específico posible. Indica en qué página está el error, qué elemento y qué quieres cambiar. Puedes añadir capturas de pantalla con el error marcado.
            </div>

            <form method="post" action="" enctype="multipart/form-data" id="ttbwp-changes-form">
              <?php wp_nonce_field('ttb_webprog_changes_' . $token); ?>
              <input type="hidden" name="ttb_webprog_action" value="changes">
              <input type="hidden" name="ttb_webprog_token" value="<?php echo esc_attr($token); ?>">
              <input type="hidden" name="ttbwp_blocks_json" id="ttbwp_blocks_json" value="">

              <div id="ttbwp-blocks-container"></div>

              <div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 24px">
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwp-add-text">
                  ✏️ Añadir bloque de texto
                </button>
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwp-add-image">
                  🖼️ Añadir captura con comentario
                </button>
              </div>

              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" id="ttbwp-submit-btn">
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
                $bg  = $is_accepted ? '#ecfdf5' : '#fffbeb';
                $bc  = $is_accepted ? '#6ee7b7' : '#fcd34d';
                $ico = $is_accepted ? '✅' : '✏️';
                $lbl = $is_accepted ? 'Web aceptada' : 'Cambios solicitados — Ronda #' . $rev->round;
              ?>
              <div style="background:<?php echo $bg; ?>;border:1.5px solid <?php echo $bc; ?>;border-radius:14px;padding:16px 20px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                  <p style="margin:0;font-weight:900;color:var(--ttb-text)"><?php echo $ico . ' ' . esc_html($lbl); ?></p>
                  <span style="font-size:13px;color:var(--ttb-muted)"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($rev->created_at))); ?></span>
                </div>
                <?php if ($rev->message || $rev->images): ?>
                  <?php
                    $blocks    = json_decode((string)$rev->images, true);
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
    .ttbwp-tab-btn--active {
      background: rgba(215,33,115,.10) !important;
      border-color: rgba(215,33,115,.35) !important;
      color: var(--ttb-pink) !important;
    }
    .ttbwp-block {
      border: 1.5px solid var(--ttb-border);
      border-radius: 14px;
      background: #fff;
      margin-bottom: 14px;
      overflow: hidden;
      transition: box-shadow .2s;
    }
    .ttbwp-block:focus-within { box-shadow: 0 0 0 3px rgba(215,33,115,.12); border-color: rgba(215,33,115,.4); }
    .ttbwp-block-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 14px;
      background: #f9fafb;
      border-bottom: 1px solid var(--ttb-border);
      gap: 8px;
    }
    .ttbwp-block-label { font-size: 12px; font-weight: 900; color: var(--ttb-muted); text-transform: uppercase; letter-spacing: .06em; }
    .ttbwp-block-actions { display: flex; gap: 6px; }
    .ttbwp-block-btn {
      background: none; border: 1px solid var(--ttb-border); border-radius: 8px;
      padding: 4px 8px; font-size: 13px; cursor: pointer; color: var(--ttb-muted);
      transition: background .15s, color .15s;
    }
    .ttbwp-block-btn:hover { background: #f0f0f0; color: var(--ttb-text); }
    .ttbwp-block-btn--delete:hover { background: #fff1f2; color: #e11d48; border-color: #fecdd3; }
    .ttbwp-wysiwyg-bar {
      display: flex; flex-wrap: wrap; gap: 2px;
      padding: 8px 10px; background: #f9fafb; border-bottom: 1px solid var(--ttb-border);
    }
    .ttbwp-wysiwyg-bar button {
      background: none; border: 1px solid transparent; border-radius: 6px;
      padding: 4px 8px; font-size: 13px; font-weight: 700; cursor: pointer;
      color: var(--ttb-text); line-height: 1.4; min-width: 28px;
      transition: background .12s, border-color .12s;
    }
    .ttbwp-wysiwyg-bar button:hover { background: #e5e7eb; border-color: #d1d5db; }
    .ttbwp-wysiwyg-bar button.active { background: rgba(215,33,115,.12); border-color: rgba(215,33,115,.3); color: var(--ttb-pink); }
    .ttbwp-wysiwyg-bar .ttbwp-sep { width: 1px; background: var(--ttb-border); margin: 2px 4px; align-self: stretch; }
    .ttbwp-editor {
      min-height: 120px; padding: 14px 16px; outline: none;
      font-size: 15px; line-height: 1.7; color: var(--ttb-text);
    }
    .ttbwp-editor:empty::before { content: attr(data-placeholder); color: #9ca3af; pointer-events: none; }
    .ttbwp-editor ul, .ttbwp-editor ol { padding-left: 22px; margin: 6px 0; }
    .ttbwp-editor blockquote { border-left: 3px solid var(--ttb-pink); margin: 8px 0; padding: 4px 12px; color: var(--ttb-muted); font-style: italic; }
    .ttbwp-img-block { padding: 14px 16px; }
    .ttbwp-img-dropzone {
      border: 2px dashed var(--ttb-border); border-radius: 12px; padding: 28px 20px;
      text-align: center; cursor: pointer; background: #fafafa; margin-bottom: 12px;
      transition: border-color .2s, background .2s;
    }
    .ttbwp-img-dropzone:hover, .ttbwp-img-dropzone.dragover { border-color: var(--ttb-pink); background: rgba(215,33,115,.03); }
    .ttbwp-img-preview { position: relative; display: inline-block; margin-bottom: 12px; }
    .ttbwp-img-preview img { max-width: 100%; max-height: 280px; border-radius: 10px; border: 1px solid var(--ttb-border); display: block; }
    .ttbwp-img-preview-remove {
      position: absolute; top: -8px; right: -8px;
      background: #e11d48; color: #fff; border: none; border-radius: 50%;
      width: 22px; height: 22px; font-size: 12px; font-weight: 900; cursor: pointer; line-height: 1;
    }
    .ttbwp-img-caption {
      width: 100%; border: 1px solid var(--ttb-border); border-radius: 10px;
      padding: 10px 12px; font-size: 14px; line-height: 1.5; resize: vertical;
      min-height: 72px; font-family: inherit; color: var(--ttb-text); outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .ttbwp-img-caption:focus { border-color: var(--ttb-pink); box-shadow: 0 0 0 3px rgba(215,33,115,.10); }
    </style>

    <script>
    (function(){
      var MAX_MB    = <?php echo (int)get_option('ttb_webprog_max_filesize', 5); ?>;
      var MAX_FILES = <?php echo (int)get_option('ttb_webprog_max_files', 10); ?>;
      var blockCount = 0;

      // ── Tabs ──────────────────────────────────────────────
      document.querySelectorAll('.ttbwp-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var target = btn.getAttribute('data-tab');
          document.querySelectorAll('.ttbwp-tab-btn').forEach(function(b){ b.classList.remove('ttbwp-tab-btn--active'); });
          btn.classList.add('ttbwp-tab-btn--active');
          document.getElementById('ttbwp-panel-accept').style.display  = (target === 'accept')  ? 'block' : 'none';
          document.getElementById('ttbwp-panel-changes').style.display = (target === 'changes') ? 'block' : 'none';
        });
      });

      document.getElementById('ttbwp-add-text').addEventListener('click', function(){ addTextBlock(); });
      document.getElementById('ttbwp-add-image').addEventListener('click', function(){ addImageBlock(); });

      function addTextBlock(initialHtml) {
        blockCount++;
        var id  = 'ttbwp-block-' + blockCount;
        var div = document.createElement('div');
        div.className = 'ttbwp-block';
        div.setAttribute('data-type', 'text');
        div.setAttribute('data-id', id);
        div.innerHTML =
          '<div class="ttbwp-block-header">'
          + '<span class="ttbwp-block-label">✏️ Bloque de texto</span>'
          + '<div class="ttbwp-block-actions">'
          + '<button type="button" class="ttbwp-block-btn" data-move="up" title="Subir">↑</button>'
          + '<button type="button" class="ttbwp-block-btn" data-move="down" title="Bajar">↓</button>'
          + '<button type="button" class="ttbwp-block-btn ttbwp-block-btn--delete" data-delete="1" title="Eliminar bloque">✕</button>'
          + '</div>'
          + '</div>'
          + '<div class="ttbwp-wysiwyg-bar">'
          + '<button type="button" data-cmd="bold"><b>N</b></button>'
          + '<button type="button" data-cmd="italic"><i>C</i></button>'
          + '<button type="button" data-cmd="underline"><u>S</u></button>'
          + '<span class="ttbwp-sep"></span>'
          + '<button type="button" data-cmd="insertUnorderedList">• Lista</button>'
          + '<button type="button" data-cmd="insertOrderedList">1. Lista</button>'
          + '<span class="ttbwp-sep"></span>'
          + '<button type="button" data-cmd="formatBlock|<blockquote>">❝ Cita</button>'
          + '<button type="button" data-cmd="formatBlock|<p>">¶ Normal</button>'
          + '<span class="ttbwp-sep"></span>'
          + '<button type="button" data-cmd="removeFormat">✕ Formato</button>'
          + '</div>'
          + '<div class="ttbwp-editor" contenteditable="true" data-placeholder="Describe con detalle qué quieres cambiar: página, sección, elemento, texto…"></div>';

        getContainer().appendChild(div);
        var editor = div.querySelector('.ttbwp-editor');
        if (initialHtml) editor.innerHTML = initialHtml;

        div.querySelectorAll('.ttbwp-wysiwyg-bar button').forEach(function(btn){
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

        editor.addEventListener('keyup', updateToolbar);
        editor.addEventListener('mouseup', updateToolbar);
        function updateToolbar() {
          div.querySelectorAll('.ttbwp-wysiwyg-bar button[data-cmd]').forEach(function(b){
            var cmd = b.getAttribute('data-cmd').split('|')[0];
            try { b.classList.toggle('active', document.queryCommandState(cmd)); } catch(e){}
          });
        }

        bindBlockActions(div);
        editor.focus();
      }

      function addImageBlock(initialSrc, initialCaption) {
        blockCount++;
        var id  = 'ttbwp-block-' + blockCount;
        var div = document.createElement('div');
        div.className = 'ttbwp-block';
        div.setAttribute('data-type', 'image');
        div.setAttribute('data-id', id);
        div.innerHTML =
          '<div class="ttbwp-block-header">'
          + '<span class="ttbwp-block-label">🖼️ Captura + comentario</span>'
          + '<div class="ttbwp-block-actions">'
          + '<button type="button" class="ttbwp-block-btn" data-move="up" title="Subir">↑</button>'
          + '<button type="button" class="ttbwp-block-btn" data-move="down" title="Bajar">↓</button>'
          + '<button type="button" class="ttbwp-block-btn ttbwp-block-btn--delete" data-delete="1" title="Eliminar bloque">✕</button>'
          + '</div>'
          + '</div>'
          + '<div class="ttbwp-img-block">'
          + '<div class="ttbwp-img-dropzone" tabindex="0">'
          + '<p style="margin:0 0 6px;font-size:28px">📎</p>'
          + '<p style="margin:0 0 4px;font-weight:700;color:var(--ttb-text);font-size:14px">Arrastra una captura o haz clic para seleccionar</p>'
          + '<p style="margin:0;font-size:12px;color:var(--ttb-muted)">PNG, JPG, GIF, WEBP · Máx. ' + MAX_MB + ' MB</p>'
          + '<input type="file" accept="image/*" style="display:none">'
          + '</div>'
          + '<textarea class="ttbwp-img-caption" placeholder="Explica qué hay que cambiar en esta captura (elemento, texto, color, posición...)"></textarea>'
          + '</div>';

        getContainer().appendChild(div);

        var dz      = div.querySelector('.ttbwp-img-dropzone');
        var input   = div.querySelector('input[type=file]');
        var caption = div.querySelector('.ttbwp-img-caption');

        if (initialSrc)     showPreview(div, dz, initialSrc);
        if (initialCaption) caption.value = initialCaption;

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
        var existing = block.querySelector('.ttbwp-img-preview');
        if (existing) existing.remove();
        var wrap = document.createElement('div');
        wrap.className = 'ttbwp-img-preview';
        var img = document.createElement('img');
        img.src = src;
        var rm = document.createElement('button');
        rm.type = 'button'; rm.className = 'ttbwp-img-preview-remove'; rm.textContent = '✕'; rm.title = 'Quitar imagen';
        rm.addEventListener('click', function(){
          wrap.remove();
          block.removeAttribute('data-file-dataurl');
          block.removeAttribute('data-file-name');
          dz.style.display = '';
        });
        wrap.appendChild(img); wrap.appendChild(rm);
        block.querySelector('.ttbwp-img-block').insertBefore(wrap, block.querySelector('.ttbwp-img-caption'));
      }

      function bindBlockActions(block) {
        block.querySelector('[data-delete]').addEventListener('click', function(){
          if (getContainer().querySelectorAll('.ttbwp-block').length <= 1) {
            alert('Debe haber al menos un bloque de cambio.'); return;
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

      function getContainer() { return document.getElementById('ttbwp-blocks-container'); }

      // Bloque inicial
      addTextBlock();

      // ── Envío del formulario ──────────────────────────────
      document.getElementById('ttbwp-changes-form').addEventListener('submit', function(e){
        e.preventDefault();
        var blocks     = [];
        var imageFiles = [];
        var container  = getContainer();
        var blockEls   = container.querySelectorAll('.ttbwp-block');
        var hasContent = false;

        blockEls.forEach(function(bl, idx) {
          var type = bl.getAttribute('data-type');
          if (type === 'text') {
            var html  = bl.querySelector('.ttbwp-editor').innerHTML.trim();
            var plain = bl.querySelector('.ttbwp-editor').innerText.trim();
            if (plain) hasContent = true;
            blocks.push({ type: 'text', html: html, idx: idx });
          } else if (type === 'image') {
            var caption = bl.querySelector('.ttbwp-img-caption').value.trim();
            var dataUrl = bl.getAttribute('data-file-dataurl') || '';
            var fname   = bl.getAttribute('data-file-name')    || '';
            var ftype   = bl.getAttribute('data-file-type')    || '';
            if (dataUrl || caption) hasContent = true;
            blocks.push({ type: 'image', caption: caption, fileIndex: dataUrl ? imageFiles.length : -1, idx: idx });
            if (dataUrl) imageFiles.push({ blockIndex: blocks.length - 1, dataUrl: dataUrl, name: fname, mimeType: ftype });
          }
        });

        if (!hasContent) { alert('Añade al menos un comentario o captura antes de enviar.'); return; }

        document.getElementById('ttbwp_blocks_json').value = JSON.stringify(blocks);

        var fd = new FormData(document.getElementById('ttbwp-changes-form'));
        imageFiles.forEach(function(f, i) {
          var arr = f.dataUrl.split(','), mime = arr[0].match(/:(.*?);/)[1];
          var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
          for (var j=0; j<n; j++) u8[j] = bstr.charCodeAt(j);
          var blob = new Blob([u8], { type: mime });
          fd.append('ttbwp_img_file_' + i, blob, f.name || ('captura-' + (i+1) + '.jpg'));
        });
        fd.set('ttbwp_img_count', imageFiles.length);

        var btn = document.getElementById('ttbwp-submit-btn');
        btn.disabled = true; btn.textContent = '⏳ Enviando...';

        fetch(window.location.href, { method: 'POST', body: fd })
          .then(function(r){ return r.text(); })
          .then(function(html){
            var match = html.match(/window\.location\.replace\((.+?)\)/);
            if (match) { window.location.replace(JSON.parse(match[1])); }
            else { window.location.reload(); }
          })
          .catch(function(){
            btn.disabled = false; btn.textContent = '📨 Enviar cambios';
            alert('Error al enviar. Inténtalo de nuevo.');
          });
      });
    })();
    </script>
    <?php
  }

  private static function js_redirect($url) {
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($url)) . ');</script>';
    exit;
  }

  private static function handle_submit($project) {
    $action = sanitize_text_field($_POST['ttb_webprog_action'] ?? '');
    $token  = sanitize_text_field($_POST['ttb_webprog_token']  ?? '');

    global $wpdb;
    $projects_table  = TTB_WebProg_DB::projects_table();
    $revisions_table = TTB_WebProg_DB::revisions_table();

    if ($action === 'accept') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_webprog_accept_' . $token)) {
        TTB_WebProg_DB::log($project->id, 'nonce_failed', 'client', ['action' => 'accept']);
        self::js_redirect(TTB_WebProg_DB::client_url($token));
      }

      $wpdb->update($projects_table, [
        'status'     => 'accepted',
        'updated_at' => TTB_WebProg_DB::now(),
      ], ['id' => $project->id]);

      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'accept',
        'message'    => null,
        'images'     => null,
        'created_at' => TTB_WebProg_DB::now(),
      ]);

      (new TTB_WebProg_Mailer())->send_accepted_alert($project);

      TTB_WebProg_DB::log($project->id, 'web_accepted', 'client', ['round' => $round]);
      TTB_WebProg_DB::log($project->id, 'email_accepted_sent', 'system', ['recipients' => 'hola + produccion']);

      self::js_redirect(TTB_WebProg_DB::client_url($token));

    } elseif ($action === 'changes') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_webprog_changes_' . $token)) {
        TTB_WebProg_DB::log($project->id, 'nonce_failed', 'client', ['action' => 'changes']);
        self::js_redirect(TTB_WebProg_DB::client_url($token));
      }

      $blocks_raw = sanitize_text_field($_POST['ttbwp_blocks_json'] ?? '');
      $blocks     = json_decode(stripslashes($blocks_raw), true);

      if (!is_array($blocks) || empty($blocks)) {
        self::js_redirect(TTB_WebProg_DB::client_url($token));
      }

      $has_content = false;
      foreach ($blocks as $b) {
        if (!empty($b['html']) || !empty($b['caption'])) { $has_content = true; break; }
        if (isset($b['fileIndex']) && $b['fileIndex'] >= 0) { $has_content = true; break; }
      }
      if (!$has_content) {
        self::js_redirect(TTB_WebProg_DB::client_url($token));
      }

      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';

      $max_mb         = (int)get_option('ttb_webprog_max_filesize', 5);
      $max_files      = (int)get_option('ttb_webprog_max_files', 10);
      $img_count      = min((int)($_POST['ttbwp_img_count'] ?? 0), $max_files);
      $uploaded       = [];
      $uploaded_count = 0;

      for ($i = 0; $i < $img_count; $i++) {
        $key = 'ttbwp_img_file_' . $i;
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
          'post_title'  => 'WebProg - ' . $project->name . ' #' . $round . ' img' . ($i+1),
          'post_status' => 'private',
        ]);
        if (!is_wp_error($att_id)) {
          $uploaded[$i] = wp_get_attachment_url($att_id);
          $uploaded_count++;
        }
      }

      $sanitized_blocks = [];
      foreach ($blocks as $b) {
        $type = $b['type'] ?? '';
        if ($type === 'text') {
          $html = wp_kses_post($b['html'] ?? '');
          $text = wp_strip_all_tags($html);
          if (trim($text) === '') continue;
          $sanitized_blocks[] = ['type' => 'text', 'html' => $html];
        } elseif ($type === 'image') {
          $caption   = sanitize_textarea_field($b['caption'] ?? '');
          $file_idx  = (int)($b['fileIndex'] ?? -1);
          $image_url = ($file_idx >= 0 && isset($uploaded[$file_idx])) ? $uploaded[$file_idx] : '';
          if (!$image_url && !$caption) continue;
          $sanitized_blocks[] = ['type' => 'image', 'caption' => $caption, 'image_url' => $image_url];
        }
      }

      if (empty($sanitized_blocks)) {
        self::js_redirect(TTB_WebProg_DB::client_url($token));
      }

      $message_plain = '';
      foreach ($sanitized_blocks as $b) {
        if ($b['type'] === 'text') $message_plain .= wp_strip_all_tags($b['html']) . "\n\n";
        if ($b['type'] === 'image' && $b['caption']) $message_plain .= '[Imagen] ' . $b['caption'] . "\n\n";
      }
      $message_plain = trim($message_plain);

      $wpdb->update($projects_table, [
        'status'        => 'changes_requested',
        'last_notified' => TTB_WebProg_DB::now(),
        'updated_at'    => TTB_WebProg_DB::now(),
      ], ['id' => $project->id]);

      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'change',
        'message'    => $message_plain,
        'images'     => wp_json_encode($sanitized_blocks, JSON_UNESCAPED_UNICODE),
        'created_at' => TTB_WebProg_DB::now(),
      ]);

      $revision = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $revisions_table WHERE id=%d", $wpdb->insert_id
      ));

      (new TTB_WebProg_Mailer())->send_changes_alert($project, $revision);

      TTB_WebProg_DB::log($project->id, 'changes_requested', 'client', [
        'round'        => $round,
        'text_blocks'  => count(array_filter($sanitized_blocks, fn($b) => $b['type'] === 'text')),
        'image_blocks' => $uploaded_count,
      ]);
      TTB_WebProg_DB::log($project->id, 'email_changes_sent', 'system', [
        'round'      => $round,
        'recipients' => 'hola + produccion',
      ]);

      self::js_redirect(TTB_WebProg_DB::client_url($token));
    }
  }
}