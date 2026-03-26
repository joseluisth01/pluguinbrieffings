<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebProg_Client')) return;

/**
 * TTB_WebProg_Client
 * v2 — FIXES:
 *   1. Eliminada duplicación de revisiones: handle_submit() usa flag estático.
 *   2. Editor de anotaciones igual que WebRev (mismo sistema de canvas).
 */
class TTB_WebProg_Client {

  /** Flag para evitar doble procesamiento del POST en el mismo ciclo de vida */
  private static $submitted = false;

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

    // ── FIX DUPLICACIÓN: solo procesar POST una vez por ciclo ──
    if (!self::$submitted && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_webprog_action'])) {
      self::$submitted = true;
      self::handle_submit($project);
      return;
    }

    TTB_WebProg_DB::log($project->id, 'client_view', 'client', ['status' => $project->status]);

    $accepted = ($project->status === 'accepted');

    global $wpdb;
    $revisions   = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM " . TTB_WebProg_DB::revisions_table() . " WHERE project_id=%d ORDER BY created_at DESC",
      $project->id
    ));
    $round_count = count($revisions);
    $next_round  = $round_count + 1;

    $min_date = self::next_workday(strtotime('+3 days'));
    $max_date = date('Y-m-d', strtotime('+90 days'));

    ?>
    <div class="ttb-container">

      <div class="ttb-card ttb-card--header">
        <h2>Revisión de tu web</h2>
        <p class="ttb-muted">Hola, <strong><?php echo esc_html($project->name); ?></strong>. Navega por tu web y danos tu feedback.</p>
      </div>

      <?php if ($accepted): ?>
        <div class="ttb-card" style="text-align:center;padding:40px 24px">
          <span style="font-size:54px;display:block;margin-bottom:12px">✅</span>
          <h3 style="margin:0 0 8px;color:#065f46">¡Web aceptada!</h3>
          <p class="ttb-muted">Ya nos has dado el visto bueno. Nuestro equipo procederá con los siguientes pasos. ¡Gracias!</p>
          <?php if (!empty($project->go_live_date)): ?>
            <div style="margin-top:20px;display:inline-block;background:#fffbeb;border:1.5px solid #fcd34d;border-radius:14px;padding:14px 24px">
              <p style="margin:0 0 4px;font-size:12px;font-weight:900;color:#92400e;text-transform:uppercase;letter-spacing:.06em">📅 Fecha de subida elegida</p>
              <p style="margin:0;font-size:18px;font-weight:900;color:#92400e"><?php echo esc_html(TTB_WebProg_DB::format_go_live($project->go_live_date)); ?></p>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>

        <!-- Preview de la web -->
        <div class="ttb-card">
          <h3 style="margin:0 0 14px">🌐 Tu web</h3>
          <div style="border-radius:14px;border:2px dashed var(--ttb-border);background:linear-gradient(135deg,#f0f9ff 0%,#fff 100%);padding:48px 32px;text-align:center">
            <div style="font-size:52px;margin:0 auto 16px;display:block;line-height:1">🌐</div>
            <p style="margin:0 0 8px;font-size:20px;font-weight:900;color:var(--ttb-text)">Tu web está lista</p>
            <p style="margin:0 0 6px;font-size:14px;color:var(--ttb-muted);font-family:monospace;word-break:break-all"><?php echo esc_html($project->web_url); ?></p>
            <p style="margin:0 0 28px;font-size:15px;color:var(--ttb-muted);line-height:1.6">Haz clic para abrirla y navegar por todas las páginas.<br>Después vuelve aquí para aceptar o pedir cambios.</p>
            <a href="<?php echo esc_url($project->web_url); ?>" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,#D72173 0%,#a8005a 100%);color:#fff;text-decoration:none;font-weight:900;font-size:17px;padding:18px 40px;border-radius:14px;box-shadow:0 8px 24px rgba(215,33,115,.30)">
              🔗 Abrir mi web
            </a>
            <p style="margin:18px 0 0;font-size:12px;color:var(--ttb-muted)">Se abrirá en una nueva pestaña</p>
          </div>
        </div>

        <!-- Consejos -->
        <div class="ttb-card" style="background:linear-gradient(135deg,#f0f9ff,#fff);border-color:#bae6fd">
          <h4 style="margin:0 0 12px;color:#0369a1">💡 ¿Qué revisar en tu web?</h4>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
            <?php foreach ([
              ['🔤','Textos','Comprueba que todos los textos son correctos y no hay erratas.'],
              ['🖼️','Imágenes','Verifica que las imágenes se ven bien y son las correctas.'],
              ['📱','Móvil','Ábrela desde tu teléfono y comprueba que se ve bien.'],
              ['🔗','Botones','Haz clic en los botones y enlaces para ver que funcionan.'],
              ['📝','Formularios','Si hay formularios, prueba a rellenarlos.'],
              ['📧','Contacto','Comprueba que los datos de contacto son correctos.'],
            ] as [$icon, $title, $desc]): ?>
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
            <button class="ttb-btn ttb-btn--ghost ttbwp-tab-btn ttbwp-tab-btn--active" data-tab="accept" type="button">✅ Aceptar la web</button>
            <button class="ttb-btn ttb-btn--ghost ttbwp-tab-btn" data-tab="changes" type="button">✏️ Tengo cambios que pedir</button>
          </div>

          <!-- Panel: Aceptar -->
          <div class="ttbwp-panel" id="ttbwp-panel-accept">
            <div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:14px;padding:18px 22px;margin-bottom:20px">
              <p style="margin:0 0 6px;font-size:15px;font-weight:900;color:#9a3412">⚠️ Importante antes de aceptar</p>
              <p style="margin:0;font-size:14px;color:#c2410c;line-height:1.6">El día que elijamos para subir la web a producción, <strong>la web estará parcialmente caída</strong> durante unas horas.</p>
            </div>
            <form method="post" action="">
              <?php wp_nonce_field('ttb_webprog_accept_' . $token); ?>
              <input type="hidden" name="ttb_webprog_action" value="accept">
              <input type="hidden" name="ttb_webprog_token" value="<?php echo esc_attr($token); ?>">
              <div style="margin-bottom:20px">
                <label style="display:block;font-weight:700;font-size:15px;color:var(--ttb-text);margin-bottom:8px">📅 Fecha preferida de subida <span style="color:#e11d48">*</span></label>
                <p style="margin:0 0 10px;font-size:13px;color:var(--ttb-muted)">Selecciona un día de lunes a viernes (mínimo 3 días laborables desde hoy).</p>
                <input type="date" id="ttbwp-go-live-date" name="go_live_date" class="ttb-input" min="<?php echo esc_attr($min_date); ?>" max="<?php echo esc_attr($max_date); ?>" required style="max-width:260px">
                <p id="ttbwp-date-error" style="display:none;margin:6px 0 0;font-size:13px;font-weight:700;color:#e11d48">Por favor selecciona un día de lunes a viernes.</p>
              </div>
              <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:16px 20px;margin-bottom:20px">
                <p style="margin:0;font-size:14px;color:#065f46;line-height:1.6">Al aceptar confirmas que la web está correcta y autorizas a TicTac a proceder con la subida a producción en la fecha indicada.</p>
              </div>
              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" id="ttbwp-accept-btn" style="background:linear-gradient(135deg,#10b981,#059669)">✅ Confirmar aceptación</button>
              </div>
            </form>
          </div>

          <!-- Panel: Cambios -->
          <div class="ttbwp-panel" id="ttbwp-panel-changes" style="display:none">

            <!-- Guía -->
            <div style="background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:14px;padding:16px 20px;margin-bottom:20px">
              <p style="margin:0 0 10px;font-size:14px;font-weight:900;color:#0369a1">💡 Cómo explicar bien los cambios</p>
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
                <div style="font-size:13px;color:#0369a1;line-height:1.5"><strong>✏️ Bloque de texto</strong><br>Indica la página, sección y qué quieres cambiar.</div>
                <div style="font-size:13px;color:#0369a1;line-height:1.5"><strong>🖼️ Captura anotada</strong><br>Haz una captura de pantalla, súbela y dibuja encima para señalar el problema.</div>
                <div style="font-size:13px;color:#0369a1;line-height:1.5"><strong>📋 Combínalos</strong><br>Añade todos los bloques que necesites en el orden que quieras.</div>
              </div>
            </div>

            <form method="post" action="" enctype="multipart/form-data" id="ttbwp-changes-form">
              <?php wp_nonce_field('ttb_webprog_changes_' . $token); ?>
              <input type="hidden" name="ttb_webprog_action" value="changes">
              <input type="hidden" name="ttb_webprog_token" value="<?php echo esc_attr($token); ?>">
              <input type="hidden" name="ttbwp_blocks_json" id="ttbwp_blocks_json" value="">

              <div id="ttbwp-blocks-container"></div>

              <div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 24px">
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwp-add-text">✏️ Añadir bloque de texto</button>
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwp-add-image">🖼️ Añadir captura con anotaciones</button>
              </div>

              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" id="ttbwp-submit-btn">📨 Enviar cambios</button>
              </div>
            </form>
          </div>
        </div>

      <?php endif; ?>

      <!-- Historial -->
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
                          <div style="font-size:14px;color:var(--ttb-text);line-height:1.7;background:#fff;border-radius:10px;padding:12px 14px;border:1px solid rgba(0,0,0,.07)"><?php echo wp_kses_post($bl['html']); ?></div>
                        <?php elseif (($bl['type'] ?? '') === 'image'): ?>
                          <div style="background:#fff;border-radius:10px;border:1px solid rgba(0,0,0,.07);overflow:hidden">
                            <?php if (!empty($bl['image_url'])): ?><a href="<?php echo esc_url($bl['image_url']); ?>" target="_blank"><img src="<?php echo esc_url($bl['image_url']); ?>" style="width:100%;max-height:400px;object-fit:contain;display:block;background:#f4f4f4" alt="Adjunto anotado"></a><?php endif; ?>
                            <?php if (!empty($bl['caption'])): ?><div style="padding:10px 14px;font-size:14px;color:var(--ttb-text);line-height:1.6;border-top:1px solid rgba(0,0,0,.06)"><?php echo nl2br(esc_html($bl['caption'])); ?></div><?php endif; ?>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <?php if ($rev->message): ?><p style="margin:10px 0 0;font-size:14px;color:var(--ttb-text);white-space:pre-line;line-height:1.6"><?php echo nl2br(esc_html($rev->message)); ?></p><?php endif; ?>
                    <?php $old_imgs = json_decode((string)$rev->images, true); if (is_array($old_imgs) && $old_imgs): ?>
                      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                        <?php foreach ($old_imgs as $img_url): ?><a href="<?php echo esc_url($img_url); ?>" target="_blank"><img src="<?php echo esc_url($img_url); ?>" style="height:80px;width:auto;border-radius:8px;border:1px solid rgba(0,0,0,.1);object-fit:cover" alt=""></a><?php endforeach; ?>
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

<?php
    // Reutilizamos los estilos y JS del anotador (misma clase)
    TTB_WebRev_Client::render_annotation_styles_public();
    self::render_webprog_js(
      (int)get_option('ttb_webprog_max_filesize', 5),
      (int)get_option('ttb_webprog_max_files', 10)
    );
  }

  private static function render_webprog_js($max_mb, $max_files) { ?>
<style>
.ttbwp-tab-btn--active{background:rgba(215,33,115,.10)!important;border-color:rgba(215,33,115,.35)!important;color:var(--ttb-pink)!important}
</style>
<script>
(function(){
  var MAX_MB    = <?php echo (int)$max_mb; ?>;
  var MAX_FILES = <?php echo (int)$max_files; ?>;
  var blockCount = 0;
  var imageBlockCount = 0;

  /* ─── Tabs ─── */
  document.querySelectorAll('.ttbwp-tab-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      document.querySelectorAll('.ttbwp-tab-btn').forEach(function(b){b.classList.remove('ttbwp-tab-btn--active');});
      btn.classList.add('ttbwp-tab-btn--active');
      var t=btn.getAttribute('data-tab');
      document.getElementById('ttbwp-panel-accept').style.display =(t==='accept') ?'block':'none';
      document.getElementById('ttbwp-panel-changes').style.display=(t==='changes')?'block':'none';
    });
  });

  /* ─── Validación fecha L-V ─── */
  var dateInput=document.getElementById('ttbwp-go-live-date');
  var dateError=document.getElementById('ttbwp-date-error');
  var acceptBtn=document.getElementById('ttbwp-accept-btn');
  function isWeekend(d){if(!d)return false;var day=new Date(d+'T12:00:00').getDay();return day===0||day===6;}
  if(dateInput){
    dateInput.addEventListener('change',function(){
      if(isWeekend(this.value)){dateError.style.display='block';this.style.borderColor='#f43f5e';acceptBtn.disabled=true;}
      else{dateError.style.display='none';this.style.borderColor='';acceptBtn.disabled=false;}
    });
    dateInput.closest('form').addEventListener('submit',function(e){
      if(isWeekend(dateInput.value)||!dateInput.value){e.preventDefault();dateError.style.display='block';}
    });
  }

  /* ─── Bloques ─── */
  document.getElementById('ttbwp-add-text').addEventListener('click',function(){addTextBlock();});
  document.getElementById('ttbwp-add-image').addEventListener('click',function(){addAnnotatedImageBlock();});
  function getContainer(){return document.getElementById('ttbwp-blocks-container');}

  /* ════ BLOQUE TEXTO ════ */
  function addTextBlock(initialHtml){
    blockCount++;
    var id='ttbwp-block-'+blockCount;
    var div=document.createElement('div');
    div.className='ttbwr-block'; /* reutilizamos estilos de webrev */
    div.setAttribute('data-type','text');
    div.setAttribute('data-id',id);
    div.innerHTML=
      '<div class="ttbwr-block-header">'+
        '<span class="ttbwr-block-label">✏️ Bloque de texto</span>'+
        '<div class="ttbwr-block-actions">'+
          '<button type="button" class="ttbwr-block-btn" data-move="up">↑</button>'+
          '<button type="button" class="ttbwr-block-btn" data-move="down">↓</button>'+
          '<button type="button" class="ttbwr-block-btn ttbwr-block-btn--delete" data-delete="1">✕</button>'+
        '</div>'+
      '</div>'+
      '<div class="ttbwr-wysiwyg-bar">'+
        '<button type="button" data-cmd="bold"><b>N</b></button>'+
        '<button type="button" data-cmd="italic"><i>C</i></button>'+
        '<button type="button" data-cmd="underline"><u>S</u></button>'+
        '<span class="ttbwr-sep"></span>'+
        '<button type="button" data-cmd="insertUnorderedList">• Lista</button>'+
        '<button type="button" data-cmd="insertOrderedList">1. Lista</button>'+
        '<span class="ttbwr-sep"></span>'+
        '<button type="button" data-cmd="formatBlock|<blockquote>">❝ Cita</button>'+
        '<button type="button" data-cmd="formatBlock|<p>">¶ Normal</button>'+
        '<span class="ttbwr-sep"></span>'+
        '<button type="button" data-cmd="removeFormat">✕ Formato</button>'+
      '</div>'+
      '<div class="ttbwr-editor" contenteditable="true" data-placeholder="Escribe aquí los cambios: indica la página, sección y qué quieres modificar…"></div>';
    getContainer().appendChild(div);
    var editor=div.querySelector('.ttbwr-editor');
    if(initialHtml) editor.innerHTML=initialHtml;
    div.querySelectorAll('.ttbwr-wysiwyg-bar button').forEach(function(btn){
      btn.addEventListener('mousedown',function(e){
        e.preventDefault();
        var cmd=btn.getAttribute('data-cmd');
        if(cmd.indexOf('|')!==-1){var p=cmd.split('|');document.execCommand(p[0],false,p[1]);}
        else document.execCommand(cmd,false,null);
        editor.focus();
      });
    });
    bindBlockActions(div);
    editor.focus();
  }

  /* ════ BLOQUE IMAGEN CON ANOTACIONES (mismo motor que webrev) ════ */
  function addAnnotatedImageBlock(){
    blockCount++; imageBlockCount++;
    var blockId='ttbwp-block-'+blockCount;
    var annoId='ttbwp-anno-'+imageBlockCount;
    var div=document.createElement('div');
    div.className='ttbwr-block';
    div.setAttribute('data-type','image');
    div.setAttribute('data-id',blockId);
    div.innerHTML=
      '<div class="ttbwr-block-header">'+
        '<span class="ttbwr-block-label">🖼️ Captura con anotaciones</span>'+
        '<div class="ttbwr-block-actions">'+
          '<button type="button" class="ttbwr-block-btn" data-move="up">↑</button>'+
          '<button type="button" class="ttbwr-block-btn" data-move="down">↓</button>'+
          '<button type="button" class="ttbwr-block-btn ttbwr-block-btn--delete" data-delete="1">✕</button>'+
        '</div>'+
      '</div>'+
      '<div class="ttbwr-img-block" id="'+annoId+'-wrap">'+
        '<div class="ttbwr-img-dropzone" id="'+annoId+'-dz" tabindex="0">'+
          '<p style="margin:0 0 6px;font-size:28px">📎</p>'+
          '<p style="margin:0 0 4px;font-weight:700;color:var(--ttb-text);font-size:14px">Arrastra una captura o haz clic para seleccionarla</p>'+
          '<p style="margin:0 0 6px;font-size:12px;color:var(--ttb-muted)">PNG, JPG, GIF, WEBP · Máx. '+MAX_MB+' MB</p>'+
          '<p style="margin:0;font-size:12px;color:var(--ttb-pink);font-weight:700">Podrás dibujar encima para señalar exactamente qué cambiar ✍️</p>'+
          '<input type="file" accept="image/*" style="display:none" id="'+annoId+'-input">'+
        '</div>'+
        '<div class="ttbwr-annotator" id="'+annoId+'-editor">'+
          '<div class="ttbwr-anno-toolbar" id="'+annoId+'-toolbar">'+
            '<button type="button" class="ttbwr-anno-tool-btn active" data-tool="pencil">✏️ Lápiz</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="highlighter">🖌️ Subrayado</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="arrow">➡️ Flecha</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="rect">⬜ Rect.</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="text">🔤 Texto</button>'+
            '<div class="ttbwr-anno-sep"></div>'+
            '<button type="button" class="ttbwr-anno-color-btn active" data-color="#ff3b3b" style="background:#ff3b3b"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#ff9500" style="background:#ff9500"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#ffcc00" style="background:#ffcc00"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#34c759" style="background:#34c759"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#007aff" style="background:#007aff"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#ffffff" style="background:#fff;border-color:#666"></button>'+
            '<div class="ttbwr-anno-sep"></div>'+
            '<button type="button" class="ttbwr-anno-size-btn" data-size="3">S</button>'+
            '<button type="button" class="ttbwr-anno-size-btn active" data-size="5">M</button>'+
            '<button type="button" class="ttbwr-anno-size-btn" data-size="10">L</button>'+
            '<button type="button" class="ttbwr-anno-size-btn" data-size="18">XL</button>'+
          '</div>'+
          '<div class="ttbwr-canvas-wrap" id="'+annoId+'-canvaswrap">'+
            '<canvas id="'+annoId+'-base" class="ttbwr-canvas-base"></canvas>'+
            '<canvas id="'+annoId+'-draw" class="ttbwr-canvas-draw"></canvas>'+
          '</div>'+
          '<div class="ttbwr-anno-bottom">'+
            '<div style="display:flex;gap:8px">'+
              '<button type="button" class="ttbwr-anno-undo-btn" id="'+annoId+'-undo">↩ Deshacer</button>'+
              '<button type="button" class="ttbwr-anno-clear-btn" id="'+annoId+'-clear">🗑 Borrar todo</button>'+
            '</div>'+
            '<button type="button" class="ttbwr-anno-done-btn" id="'+annoId+'-done">✅ Guardar anotaciones</button>'+
          '</div>'+
        '</div>'+
        '<div id="'+annoId+'-preview" style="display:none"></div>'+
        '<textarea class="ttbwr-img-caption" id="'+annoId+'-caption" placeholder="Describe el cambio: ¿qué elemento hay que modificar? ¿cómo debería quedar?"></textarea>'+
      '</div>';
    getContainer().appendChild(div);
    bindBlockActions(div);
    initAnnotatorEngine(annoId, div);
  }

  /* ════ Motor de anotaciones (compartido) ════ */
  function initAnnotatorEngine(annoId, blockEl){
    var dz=document.getElementById(annoId+'-dz');
    var fileInp=document.getElementById(annoId+'-input');
    var editor=document.getElementById(annoId+'-editor');
    var preview=document.getElementById(annoId+'-preview');
    var cWrap=document.getElementById(annoId+'-canvaswrap');
    var baseC=document.getElementById(annoId+'-base');
    var drawC=document.getElementById(annoId+'-draw');
    var bCtx=baseC.getContext('2d');
    var dCtx=drawC.getContext('2d');
    var state={tool:'pencil',color:'#ff3b3b',size:5,drawing:false,startX:0,startY:0,history:[],finalDataUrl:null,scale:1};

    dz.addEventListener('click',function(){fileInp.click();});
    dz.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' ')fileInp.click();});
    dz.addEventListener('dragover',function(e){e.preventDefault();dz.classList.add('dragover');});
    dz.addEventListener('dragleave',function(){dz.classList.remove('dragover');});
    dz.addEventListener('drop',function(e){e.preventDefault();dz.classList.remove('dragover');if(e.dataTransfer.files[0])loadImg(e.dataTransfer.files[0]);});
    fileInp.addEventListener('change',function(){if(fileInp.files[0])loadImg(fileInp.files[0]);});

    function loadImg(file){
      if(!file.type.startsWith('image/')){alert('Solo imágenes.');return;}
      if(file.size>MAX_MB*1024*1024){alert('Supera el límite de '+MAX_MB+' MB.');return;}
      var reader=new FileReader();
      reader.onload=function(e){
        var img=new Image();
        img.onload=function(){
          var maxW=cWrap.offsetWidth||700;
          var ratio=Math.min(1,maxW/img.width);
          var W=Math.round(img.width*ratio),H=Math.round(img.height*ratio);
          state.scale=img.width/W;
          baseC.width=img.width;baseC.height=img.height;
          drawC.width=img.width;drawC.height=img.height;
          baseC.style.width=W+'px';baseC.style.height=H+'px';
          drawC.style.width=W+'px';drawC.style.height=H+'px';
          cWrap.style.height=H+'px';
          bCtx.drawImage(img,0,0);
          dCtx.clearRect(0,0,drawC.width,drawC.height);
          state.history=[drawC.toDataURL()];
          dz.style.display='none';
          editor.classList.add('visible');
          state.finalDataUrl=null;
        };
        img.src=e.target.result;
      };
      reader.readAsDataURL(file);
    }

    document.getElementById(annoId+'-toolbar').querySelectorAll('[data-tool]').forEach(function(btn){
      btn.addEventListener('click',function(){
        state.tool=btn.getAttribute('data-tool');
        document.getElementById(annoId+'-toolbar').querySelectorAll('[data-tool]').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        drawC.style.cursor=state.tool==='text'?'text':'crosshair';
      });
    });
    document.getElementById(annoId+'-toolbar').querySelectorAll('[data-color]').forEach(function(btn){
      btn.addEventListener('click',function(){
        state.color=btn.getAttribute('data-color');
        document.getElementById(annoId+'-toolbar').querySelectorAll('[data-color]').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
      });
    });
    document.getElementById(annoId+'-toolbar').querySelectorAll('[data-size]').forEach(function(btn){
      btn.addEventListener('click',function(){
        state.size=parseInt(btn.getAttribute('data-size'));
        document.getElementById(annoId+'-toolbar').querySelectorAll('[data-size]').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
      });
    });

    document.getElementById(annoId+'-undo').addEventListener('click',function(){
      if(state.history.length>1){state.history.pop();var i=new Image();i.onload=function(){dCtx.clearRect(0,0,drawC.width,drawC.height);dCtx.drawImage(i,0,0);};i.src=state.history[state.history.length-1];}
      else{dCtx.clearRect(0,0,drawC.width,drawC.height);state.history=[drawC.toDataURL()];}
    });
    document.getElementById(annoId+'-clear').addEventListener('click',function(){
      if(!confirm('¿Borrar todas las anotaciones?'))return;
      dCtx.clearRect(0,0,drawC.width,drawC.height);state.history=[drawC.toDataURL()];
    });
    document.getElementById(annoId+'-done').addEventListener('click',function(){
      var merged=document.createElement('canvas');
      merged.width=baseC.width;merged.height=baseC.height;
      var mCtx=merged.getContext('2d');
      mCtx.drawImage(baseC,0,0);mCtx.drawImage(drawC,0,0);
      state.finalDataUrl=merged.toDataURL('image/jpeg', 0.88);
      blockEl.setAttribute('data-annotated-url',state.finalDataUrl);
      editor.classList.remove('visible');
      preview.style.display='block';preview.innerHTML='';
      var wrap=document.createElement('div');wrap.className='ttbwr-img-preview';
      var pImg=document.createElement('img');pImg.src=state.finalDataUrl;pImg.style.maxWidth='100%';
      var rm=document.createElement('button');rm.type='button';rm.className='ttbwr-img-preview-remove';rm.textContent='✕';
      rm.addEventListener('click',function(){preview.style.display='none';preview.innerHTML='';blockEl.removeAttribute('data-annotated-url');state.finalDataUrl=null;dCtx.clearRect(0,0,drawC.width,drawC.height);state.history=[];dz.style.display='';editor.classList.remove('visible');});
      wrap.appendChild(pImg);wrap.appendChild(rm);
      var reEdit=document.createElement('button');reEdit.type='button';reEdit.className='ttbwr-annotate-again-btn';reEdit.textContent='✏️ Editar anotaciones';
      reEdit.addEventListener('click',function(){preview.style.display='none';editor.classList.add('visible');});
      preview.appendChild(wrap);preview.appendChild(reEdit);
    });

    var snapshot=null;
    function getPos(e){
      var r=drawC.getBoundingClientRect();
      var cx,cy;
      if(e.touches){cx=e.touches[0].clientX;cy=e.touches[0].clientY;}else{cx=e.clientX;cy=e.clientY;}
      return{x:Math.round((cx-r.left)*state.scale),y:Math.round((cy-r.top)*state.scale)};
    }
    function setupCtx(ctx,alpha){ctx.strokeStyle=state.color;ctx.fillStyle=state.color;ctx.lineWidth=state.size*state.scale;ctx.lineCap='round';ctx.lineJoin='round';ctx.globalAlpha=alpha||1;}
    function pushHistory(){if(state.history.length>30)state.history.shift();state.history.push(drawC.toDataURL());}
    function drawArrow(ctx,x1,y1,x2,y2){var h=Math.max(16,state.size*state.scale*3),a=Math.atan2(y2-y1,x2-x1);ctx.beginPath();ctx.moveTo(x1,y1);ctx.lineTo(x2,y2);ctx.stroke();ctx.beginPath();ctx.moveTo(x2,y2);ctx.lineTo(x2-h*Math.cos(a-Math.PI/7),y2-h*Math.sin(a-Math.PI/7));ctx.lineTo(x2-h*Math.cos(a+Math.PI/7),y2-h*Math.sin(a+Math.PI/7));ctx.closePath();ctx.fill();}
    function drawRect(ctx,x1,y1,x2,y2){ctx.beginPath();ctx.rect(x1,y1,x2-x1,y2-y1);ctx.stroke();}
    function spawnText(cssX,cssY){var inp=document.createElement('input');inp.type='text';inp.className='ttbwr-text-input';inp.style.left=cssX+'px';inp.style.top=(cssY-16)+'px';inp.placeholder='Escribe aquí...';inp.style.fontSize=Math.max(12,state.size*3)+'px';inp.style.color=state.color;cWrap.style.position='relative';cWrap.appendChild(inp);inp.focus();function commit(){if(!inp.value.trim()){inp.remove();return;}setupCtx(dCtx,1);dCtx.font='bold '+(Math.max(14,state.size*state.scale*2.5))+'px sans-serif';dCtx.fillStyle=state.color;dCtx.shadowColor='rgba(0,0,0,0.7)';dCtx.shadowBlur=4;dCtx.fillText(inp.value,cssX*state.scale,(cssY-4)*state.scale);dCtx.shadowBlur=0;inp.remove();pushHistory();}inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();commit();}if(e.key==='Escape')inp.remove();});inp.addEventListener('blur',commit);}

    drawC.addEventListener('mousedown',function(e){
      var pos=getPos(e);state.startX=pos.x;state.startY=pos.y;
      if(state.tool==='text'){var r=drawC.getBoundingClientRect();spawnText(pos.x/state.scale,pos.y/state.scale);return;}
      state.drawing=true;
      if(state.tool==='arrow'||state.tool==='rect')snapshot=dCtx.getImageData(0,0,drawC.width,drawC.height);
      if(state.tool==='pencil'||state.tool==='highlighter'){setupCtx(dCtx,state.tool==='highlighter'?0.38:1);dCtx.beginPath();dCtx.moveTo(state.startX,state.startY);}
    });
    drawC.addEventListener('mousemove',function(e){
      if(!state.drawing)return;
      var pos=getPos(e);
      if(state.tool==='pencil'||state.tool==='highlighter'){setupCtx(dCtx,state.tool==='highlighter'?0.38:1);dCtx.lineTo(pos.x,pos.y);dCtx.stroke();}
      else if(state.tool==='arrow'||state.tool==='rect'){dCtx.putImageData(snapshot,0,0);setupCtx(dCtx,1);if(state.tool==='arrow')drawArrow(dCtx,state.startX,state.startY,pos.x,pos.y);else drawRect(dCtx,state.startX,state.startY,pos.x,pos.y);}
    });
    function onUp(e){
      if(!state.drawing)return;state.drawing=false;
      var pos=getPos(e);
      if(state.tool==='arrow'){dCtx.putImageData(snapshot,0,0);setupCtx(dCtx,1);drawArrow(dCtx,state.startX,state.startY,pos.x,pos.y);}
      if(state.tool==='rect'){dCtx.putImageData(snapshot,0,0);setupCtx(dCtx,1);drawRect(dCtx,state.startX,state.startY,pos.x,pos.y);}
      dCtx.globalAlpha=1;pushHistory();
    }
    drawC.addEventListener('mouseup',onUp);
    drawC.addEventListener('mouseleave',onUp);
    drawC.addEventListener('touchstart',function(e){e.preventDefault();var ev={clientX:e.touches[0].clientX,clientY:e.touches[0].clientY};drawC.dispatchEvent(Object.assign(new MouseEvent('mousedown'),{clientX:ev.clientX,clientY:ev.clientY}));},{passive:false});
    drawC.addEventListener('touchmove',function(e){e.preventDefault();drawC.dispatchEvent(Object.assign(new MouseEvent('mousemove'),{clientX:e.touches[0].clientX,clientY:e.touches[0].clientY}));},{passive:false});
    drawC.addEventListener('touchend',function(e){e.preventDefault();drawC.dispatchEvent(new MouseEvent('mouseup'));},{passive:false});
  }

  function bindBlockActions(block){
    block.querySelector('[data-delete]').addEventListener('click',function(){if(getContainer().querySelectorAll('.ttbwr-block').length<=1){alert('Debe haber al menos un bloque.');return;}block.remove();});
    block.querySelector('[data-move="up"]').addEventListener('click',function(){var p=block.previousElementSibling;if(p)getContainer().insertBefore(block,p);});
    block.querySelector('[data-move="down"]').addEventListener('click',function(){var n=block.nextElementSibling;if(n)getContainer().insertBefore(n,block);});
  }

  addTextBlock();

  /* ─── Envío ─── */
  document.getElementById('ttbwp-changes-form').addEventListener('submit',function(e){
    e.preventDefault();
    var blocks=[],imageFiles=[],container=getContainer(),blockEls=container.querySelectorAll('.ttbwr-block'),hasContent=false;
    blockEls.forEach(function(bl,idx){
      var type=bl.getAttribute('data-type');
      if(type==='text'){var html=bl.querySelector('.ttbwr-editor').innerHTML.trim(),plain=bl.querySelector('.ttbwr-editor').innerText.trim();if(plain)hasContent=true;blocks.push({type:'text',html:html,idx:idx});}
      else if(type==='image'){var caption=bl.querySelector('[id$="-caption"]').value.trim(),annotatedUrl=bl.getAttribute('data-annotated-url')||'';if(annotatedUrl||caption)hasContent=true;var fileIndex=-1;if(annotatedUrl){fileIndex=imageFiles.length;imageFiles.push({dataUrl:annotatedUrl,name:'anotacion-'+(imageFiles.length+1)+'.jpg',mimeType:'image/jpeg'});}blocks.push({type:'image',caption:caption,fileIndex:fileIndex,idx:idx});}
    });
    if(!hasContent){alert('Añade al menos un comentario o imagen antes de enviar.');return;}
    document.getElementById('ttbwp_blocks_json').value=JSON.stringify(blocks);
    var fd=new FormData(document.getElementById('ttbwp-changes-form'));
    imageFiles.forEach(function(f,i){var arr=f.dataUrl.split(','),mime=arr[0].match(/:(.*?);/)[1];var bstr=atob(arr[1]),n=bstr.length,u8=new Uint8Array(n);for(var j=0;j<n;j++)u8[j]=bstr.charCodeAt(j);var blob=new Blob([u8],{type:mime});fd.append('ttbwp_img_file_'+i,blob,f.name);});
    fd.set('ttbwp_img_count',imageFiles.length);
    var btn=document.getElementById('ttbwp-submit-btn');btn.disabled=true;btn.textContent='⏳ Enviando...';
    fetch(window.location.href,{method:'POST',body:fd})
      .then(function(r){
        if(!r.ok){ return r.text().then(function(t){ throw new Error('HTTP '+r.status+': '+t.substring(0,300)); }); }
        return r.text();
      })
      .then(function(html){
        var match=html.match(/window\.location\.replace\((.+?)\)/);
        if(match) window.location.replace(JSON.parse(match[1]));
        else {
          var phpErr=html.match(/(Fatal error|Warning|Parse error)[^<]*/i);
          if(phpErr) alert('Error del servidor: '+phpErr[0]);
          else window.location.reload();
        }
      })
      .catch(function(err){btn.disabled=false;btn.textContent='📨 Enviar cambios';alert('Error al enviar: '+err.message);});
  });
})();
</script>
<?php }

  private static function next_workday($ts) {
    $day = (int)date('N', $ts);
    if ($day === 6) $ts += 2 * 86400;
    if ($day === 7) $ts += 1 * 86400;
    return date('Y-m-d', $ts);
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

      $raw_date     = sanitize_text_field($_POST['go_live_date'] ?? '');
      $go_live_date = null;
      if ($raw_date) {
        $ts = strtotime($raw_date);
        $day = $ts ? (int)date('N', $ts) : 0;
        if ($ts && $ts > time() && $day >= 1 && $day <= 5) {
          $go_live_date = date('Y-m-d', $ts);
        }
      }

      $wpdb->update($projects_table, [
        'status'       => 'accepted',
        'go_live_date' => $go_live_date,
        'updated_at'   => TTB_WebProg_DB::now(),
      ], ['id' => $project->id]);

      $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id=%d", $project->id));

      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'accept',
        'message'    => $go_live_date ? 'Fecha de subida preferida: ' . $go_live_date : null,
        'images'     => null,
        'created_at' => TTB_WebProg_DB::now(),
      ]);

      (new TTB_WebProg_Mailer())->send_accepted_alert($project);
      TTB_WebProg_DB::log($project->id, 'web_accepted', 'client', ['round' => $round, 'go_live_date' => $go_live_date ?? 'no indicada']);
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
      if (!$has_content) self::js_redirect(TTB_WebProg_DB::client_url($token));

      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';

      $max_mb    = (int)get_option('ttb_webprog_max_filesize', 5);
      $max_files = (int)get_option('ttb_webprog_max_files', 10);
      $img_count = min((int)($_POST['ttbwp_img_count'] ?? 0), $max_files);
      $uploaded  = [];
      $uploaded_count = 0;

      for ($i = 0; $i < $img_count; $i++) {
        $key = 'ttbwp_img_file_' . $i;
        if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) continue;
        if ($_FILES[$key]['size'] > $max_mb * 1024 * 1024) continue;
        $type = $_FILES[$key]['type'];
        if (!in_array($type, ['image/jpeg','image/png','image/gif','image/webp'], true)) continue;
        $att_id = media_handle_sideload([
          'name'     => $_FILES[$key]['name'],
          'type'     => $type,
          'tmp_name' => $_FILES[$key]['tmp_name'],
          'error'    => $_FILES[$key]['error'],
          'size'     => $_FILES[$key]['size'],
        ], 0, null, [
          'post_title'  => 'WebProg anotacion - ' . $project->name . ' #' . $round . ' img' . ($i + 1),
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
          if (trim(wp_strip_all_tags($html)) === '') continue;
          $sanitized_blocks[] = ['type' => 'text', 'html' => $html];
        } elseif ($type === 'image') {
          $caption   = sanitize_textarea_field($b['caption'] ?? '');
          $file_idx  = (int)($b['fileIndex'] ?? -1);
          $image_url = ($file_idx >= 0 && isset($uploaded[$file_idx])) ? $uploaded[$file_idx] : '';
          if (!$image_url && !$caption) continue;
          $sanitized_blocks[] = ['type' => 'image', 'caption' => $caption, 'image_url' => $image_url];
        }
      }
      if (empty($sanitized_blocks)) self::js_redirect(TTB_WebProg_DB::client_url($token));

      $message_plain = '';
      foreach ($sanitized_blocks as $b) {
        if ($b['type'] === 'text') $message_plain .= wp_strip_all_tags($b['html']) . "\n\n";
        if ($b['type'] === 'image' && $b['caption']) $message_plain .= '[Imagen anotada] ' . $b['caption'] . "\n\n";
      }

      $wpdb->update($projects_table, [
        'status'        => 'changes_requested',
        'last_notified' => TTB_WebProg_DB::now(),
        'updated_at'    => TTB_WebProg_DB::now(),
      ], ['id' => $project->id]);

      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'change',
        'message'    => trim($message_plain),
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