<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Client')) return;

/**
 * TTB_Social_Client — v3
 * Fixes:
 *  - Posts del cliente almacenados en el DOM (mismo patrón que admin)
 *  - Botones aprobar/rechazar visibles y funcionando dentro del modal
 *  - Chip muestra solo "Post" (sin caption)
 */
class TTB_Social_Client {

  public static function render($token) {
    $client = TTB_Social_DB::get_client_by_token($token);

    if (!$client || $client->status !== 'active') {
      echo '<div class="ttb-card" style="text-align:center;padding:48px 24px">
        <p style="font-size:40px;margin:0 0 12px">🔗</p>
        <h2>Enlace no válido</h2>
        <p class="ttb-muted">Este enlace no existe o ha sido desactivado. Contacta con TicTac Comunicación.</p>
      </div>';
      TTB_Social_DB::log(null, null, 'invalid_token_access', 'client', ['token_partial' => substr($token, 0, 8) . '…']);
      return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_social_action'])) {
      self::handle_post($client, $token);
      return;
    }

    TTB_Social_DB::log($client->id, null, 'client_view', 'client', []);

    $tab          = sanitize_text_field($_GET['stab'] ?? 'calendar');
    $filter_month = sanitize_text_field($_GET['filter_month'] ?? date('Y-m'));

    [$year, $month] = array_map('intval', explode('-', $filter_month . '-01'));
    $first_day  = mktime(0, 0, 0, $month, 1, $year);
    $days_in    = (int)date('t', $first_day);
    $start_dow  = (int)date('N', $first_day);
    $prev_month = date('Y-m', strtotime('-1 month', $first_day));
    $next_month = date('Y-m', strtotime('+1 month', $first_day));
    $month_name = date_i18n('F Y', $first_day);

    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $content_table = TTB_Social_DB::content_table();
    $statuses      = TTB_Social_DB::post_statuses();

    // Posts del mes (solo no-borrador)
    $posts_raw = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $posts_table
       WHERE client_id=%d AND status != 'draft'
         AND YEAR(scheduled_date)=%d AND MONTH(scheduled_date)=%d
       ORDER BY scheduled_time ASC",
      $client->id, $year, $month
    ));

    $posts_by_day = [];
    foreach ($posts_raw as $p) {
      $posts_by_day[(int)date('j', strtotime($p->scheduled_date))][] = $p;
    }

    // Todos los pendientes (cualquier mes)
    $all_pending = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $posts_table WHERE client_id=%d AND status='pending_approval' ORDER BY scheduled_date ASC",
      $client->id
    ));

    // Contenido previo
    $client_content = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $content_table WHERE client_id=%d ORDER BY created_at DESC LIMIT 50",
      $client->id
    ));

    ?>
    <style>
    .ttb-social-tabs { display:flex; gap:10px; flex-wrap:wrap; margin:14px 0 18px; }
    .ttb-social-tab { text-decoration:none; padding:10px 20px; border-radius:999px; border:1px solid var(--ttb-border); background:#fff; color:var(--ttb-text); font-weight:800; font-size:14px; }
    .ttb-social-tab--active { background:rgba(215,33,115,.10); border-color:rgba(215,33,115,.35); color:var(--ttb-pink); }
    .ttb-dropzone { border:2px dashed var(--ttb-border); border-radius:14px; padding:36px 20px; text-align:center; cursor:pointer; background:#fafafa; transition:border-color .2s, background .2s; }
    .ttb-dropzone:hover, .ttb-dropzone.dragover { border-color:var(--ttb-pink); background:rgba(215,33,115,.03); }
    .ttb-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:10px; margin-top:12px; }
    .ttb-preview-item { position:relative; border-radius:10px; overflow:hidden; border:1px solid var(--ttb-border); background:#f0f0f0; aspect-ratio:1; }
    .ttb-preview-item img, .ttb-preview-item video { width:100%; height:100%; object-fit:cover; display:block; }
    .ttb-preview-remove { position:absolute; top:5px; right:5px; background:#e11d48; color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:10px; font-weight:900; cursor:pointer; line-height:20px; text-align:center; }
    </style>

    <div class="ttb-container">
      <div class="ttb-card ttb-card--header">
        <h2>Redes Sociales</h2>
        <p class="ttb-muted">Hola, <strong><?php echo esc_html($client->name); ?></strong>. Aquí tienes tus publicaciones y puedes enviarnos contenido.</p>
      </div>

      <?php if (!empty($all_pending)): ?>
        <div class="ttb-card" style="border-color:rgba(215,33,115,.35);background:rgba(215,33,115,.03)">
          <h3 style="margin:0 0 4px;color:var(--ttb-pink)">
            Tienes <?php echo count($all_pending); ?> publicación<?php echo count($all_pending) > 1 ? 'es' : ''; ?> pendiente<?php echo count($all_pending) > 1 ? 's' : ''; ?> de aprobación
          </h3>
          <p class="ttb-muted" style="margin:0">Haz clic en el post del calendario para ver la creatividad y aprobarla o pedir cambios.</p>
        </div>
      <?php endif; ?>

      <!-- Tabs -->
      <div class="ttb-social-tabs">
        <a href="<?php echo esc_url(TTB_Social_DB::client_url($token) . '&stab=calendar&filter_month=' . urlencode($filter_month)); ?>"
           class="ttb-social-tab <?php echo $tab === 'calendar' ? 'ttb-social-tab--active' : ''; ?>">Mis publicaciones</a>
        <a href="<?php echo esc_url(TTB_Social_DB::client_url($token) . '&stab=content'); ?>"
           class="ttb-social-tab <?php echo $tab === 'content' ? 'ttb-social-tab--active' : ''; ?>">Enviar contenido</a>
      </div>

      <?php if ($tab === 'calendar'): ?>

        <div class="ttb-card">
          <!-- Navegación mes -->
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap">
            <a href="<?php echo esc_url(TTB_Social_DB::client_url($token) . '&stab=calendar&filter_month=' . $prev_month); ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8592;</a>
            <h3 style="margin:0;font-size:18px;text-transform:capitalize"><?php echo esc_html($month_name); ?></h3>
            <a href="<?php echo esc_url(TTB_Social_DB::client_url($token) . '&stab=calendar&filter_month=' . $next_month); ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8594;</a>
          </div>

          <?php
          // Store de posts en el DOM (para el modal)
          self::render_client_posts_store($posts_raw, $token, $statuses);

          // Grid del calendario (mismo helper que el admin, is_admin=false)
          TTB_Social_Admin::render_calendar_grid(
            $posts_by_day, $days_in, $start_dow,
            $year, $month,
            esc_url(TTB_Social_DB::client_url($token)),
            $statuses, $filter_month, 0, false
          );
          ?>

          <!-- Leyenda -->
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px;font-size:12px">
            <?php foreach ([
              ['#fffbeb','#fde68a','#92400e','Pendiente tu aprobación'],
              ['#ecfdf5','#6ee7b7','#065f46','Aprobado'],
              ['#fff1f2','#fecdd3','#be123c','Cambios solicitados'],
              ['#eff6ff','#bfdbfe','#1d4ed8','Publicado'],
            ] as [$bg,$bc,$co,$lbl]): ?>
              <span style="display:inline-flex;align-items:center;gap:5px">
                <span style="width:12px;height:12px;border-radius:3px;background:<?php echo $bg; ?>;border:1px solid <?php echo $bc; ?>;display:inline-block"></span>
                <span style="color:var(--ttb-muted)"><?php echo esc_html($lbl); ?></span>
              </span>
            <?php endforeach; ?>
          </div>
        </div>

      <?php else: ?>

        <!-- Subir contenido -->
        <div class="ttb-card">
          <h3 style="margin:0 0 6px">Envíanos tu contenido</h3>
          <p class="ttb-muted" style="margin:0 0 18px">Sube fotos o vídeos que quieras usar en tus publicaciones, o escríbenos una idea.</p>

          <form method="post" action="<?php echo esc_url(TTB_Social_DB::client_url($token)); ?>" enctype="multipart/form-data" id="ttb-social-upload-form">
            <?php wp_nonce_field('ttb_social_upload_' . $token); ?>
            <input type="hidden" name="ttb_social_action" value="upload">
            <input type="hidden" name="ttb_social_token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="ttb_file_count" id="ttb_file_count" value="0">

            <div class="ttb-dropzone" id="ttb-social-dropzone" tabindex="0">
              <p style="font-size:32px;margin:0 0 8px">📎</p>
              <p style="font-weight:700;color:var(--ttb-text);margin:0 0 4px">Arrastra archivos aquí o haz clic para seleccionar</p>
              <p style="font-size:13px;color:var(--ttb-muted);margin:0">Fotos y vídeos · PNG, JPG, MP4, MOV · Máx. <?php echo (int)get_option('ttb_social_max_filesize', 50); ?> MB</p>
              <input type="file" id="ttb-social-file-input" accept="image/*,video/*" multiple style="display:none">
            </div>

            <div class="ttb-preview-grid" id="ttb-preview-grid"></div>

            <div style="margin-top:16px">
              <label style="font-weight:700;font-size:14px;color:var(--ttb-text);display:block;margin-bottom:6px">
                Nota o idea para el equipo <span style="font-weight:400;color:var(--ttb-muted)">(opcional)</span>
              </label>
              <textarea name="content_note" class="ttb-textarea" style="min-height:90px" placeholder="Ej: Fotos del evento del viernes. El texto podría ser 'Nueva colección disponible'. Tono fresco y cercano."></textarea>
            </div>

            <div class="ttb-actions" style="margin-top:16px">
              <button class="ttb-btn" type="submit" id="ttb-upload-btn">Enviar al equipo</button>
            </div>
          </form>
        </div>

        <?php if (!empty($client_content)): ?>
          <div class="ttb-card">
            <h3 style="margin:0 0 14px">Contenido enviado anteriormente</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px">
              <?php foreach ($client_content as $item): ?>
                <?php $is_video = in_array(strtolower(pathinfo($item->file_url ?? '', PATHINFO_EXTENSION)), ['mp4','mov','avi','webm'], true); ?>
                <div style="border-radius:10px;overflow:hidden;border:1px solid var(--ttb-border);background:#f9fafb;position:relative">
                  <?php if ($item->type === 'text' || !$item->file_url): ?>
                    <div style="padding:10px;font-size:12px;color:var(--ttb-text);background:#fff;min-height:80px;display:flex;align-items:center;line-height:1.5"><?php echo esc_html(mb_substr($item->caption ?? '', 0, 60)); ?></div>
                  <?php elseif ($is_video): ?>
                    <div style="aspect-ratio:1;background:#111;display:flex;align-items:center;justify-content:center;font-size:24px">🎬</div>
                  <?php else: ?>
                    <a href="<?php echo esc_url($item->file_url); ?>" target="_blank">
                      <img src="<?php echo esc_url($item->file_url); ?>" style="width:100%;aspect-ratio:1;object-fit:cover;display:block" alt="">
                    </a>
                  <?php endif; ?>
                  <?php if ($item->used): ?>
                    <div style="position:absolute;top:4px;right:4px;background:rgba(6,95,70,.85);color:#fff;font-size:10px;font-weight:900;padding:2px 6px;border-radius:999px">Usado</div>
                  <?php endif; ?>
                  <div style="padding:5px 8px;font-size:11px;color:var(--ttb-muted)"><?php echo esc_html(date_i18n('d/m/Y', strtotime($item->created_at))); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>

    <script>
    // ── Drag & Drop subida ─────────────────────────────────
    (function(){
      var MAX_MB  = <?php echo (int)get_option('ttb_social_max_filesize', 50); ?>;
      var files   = [];
      var dz      = document.getElementById('ttb-social-dropzone');
      var input   = document.getElementById('ttb-social-file-input');
      var grid    = document.getElementById('ttb-preview-grid');
      var countEl = document.getElementById('ttb_file_count');
      if (!dz) return;

      dz.addEventListener('click', function(){ input.click(); });
      dz.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' ')input.click(); });
      dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('dragover'); });
      dz.addEventListener('dragleave', function(){ dz.classList.remove('dragover'); });
      dz.addEventListener('drop', function(e){ e.preventDefault(); dz.classList.remove('dragover'); addFiles(Array.from(e.dataTransfer.files)); });
      input.addEventListener('change', function(){ addFiles(Array.from(input.files)); input.value=''; });

      function addFiles(newFiles) {
        newFiles.forEach(function(f){
          if (f.size > MAX_MB*1024*1024) { alert('"'+f.name+'" supera el límite de '+MAX_MB+' MB.'); return; }
          files.push(f); renderPreview(f, files.length-1);
        });
        updateCount();
      }
      function renderPreview(file, idx) {
        var isVideo = file.type.startsWith('video/');
        var div = document.createElement('div'); div.className='ttb-preview-item';
        var rm = document.createElement('button'); rm.type='button'; rm.className='ttb-preview-remove'; rm.textContent='✕';
        rm.addEventListener('click', function(){ files[idx]=null; div.remove(); updateCount(); });
        if (isVideo) { var vid=document.createElement('video'); vid.src=URL.createObjectURL(file); vid.muted=true; div.appendChild(vid); }
        else { var img=document.createElement('img'); img.src=URL.createObjectURL(file); div.appendChild(img); }
        div.appendChild(rm); grid.appendChild(div);
      }
      function updateCount() { countEl.value = files.filter(Boolean).length; }

      var form = document.getElementById('ttb-social-upload-form');
      form.addEventListener('submit', function(e){
        var activeFiles = files.filter(Boolean);
        var note = form.querySelector('[name="content_note"]').value.trim();
        if (activeFiles.length===0 && !note) { e.preventDefault(); alert('Selecciona al menos un archivo o escribe una nota.'); return; }
        e.preventDefault();
        var btn = document.getElementById('ttb-upload-btn'); btn.disabled=true; btn.textContent='Subiendo...';
        var fd = new FormData(form);
        activeFiles.forEach(function(f,i){ fd.append('social_file_'+i, f, f.name); });
        fd.set('ttb_file_count', activeFiles.length);
        fetch(window.location.href, {method:'POST', body:fd})
          .then(function(r){ return r.text(); })
          .then(function(html){
            var match = html.match(/window\.location\.replace\((.+?)\)/);
            if (match) window.location.replace(JSON.parse(match[1])); else window.location.reload();
          })
          .catch(function(){ btn.disabled=false; btn.textContent='Enviar al equipo'; alert('Error al subir. Inténtalo de nuevo.'); });
      });
    })();
    </script>
    <?php
  }

  /**
   * Renderiza el HTML de cada post en divs ocultos #ttb-client-post-data-{id}
   * El JS del calendario los clona al abrir el modal (igual que en admin).
   */
  private static function render_client_posts_store($posts, $token, $statuses) {
    echo '<div id="ttb-client-posts-store" style="display:none">';
    foreach ($posts as $post) {
      [$sl,$sbg,$sbc,$sco] = $statuses[$post->status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];
      $date_fmt = date_i18n('l, j \d\e F \d\e Y', strtotime($post->scheduled_date));
      $time_str = $post->scheduled_time ? ' · ' . substr($post->scheduled_time, 0, 5) . 'h' : '';

      ob_start();
      ?>
      <div id="ttb-client-post-data-<?php echo (int)$post->id; ?>" style="display:none">
        <!-- Cabecera -->
        <div style="margin-bottom:14px">
          <div style="margin-bottom:6px">
            <span style="display:inline-block;font-size:12px;font-weight:800;padding:4px 12px;border-radius:999px;background:<?php echo $sbg; ?>;border:1px solid <?php echo $sbc; ?>;color:<?php echo $sco; ?>"><?php echo esc_html($sl); ?></span>
          </div>
          <p style="margin:0;font-size:14px;font-weight:700;color:var(--ttb-text)"><?php echo esc_html($date_fmt . $time_str); ?></p>
        </div>

        <!-- Creatividad -->
        <?php if ($post->creative_url): ?>
          <?php $is_vid = in_array(strtolower(pathinfo($post->creative_url, PATHINFO_EXTENSION)), ['mp4','mov','webm'], true); ?>
          <div style="border-radius:12px;overflow:hidden;margin-bottom:14px;border:1px solid var(--ttb-border)">
            <?php if ($is_vid): ?>
              <video src="<?php echo esc_url($post->creative_url); ?>" controls style="width:100%;max-height:280px;display:block;background:#111"></video>
            <?php else: ?>
              <a href="<?php echo esc_url($post->creative_url); ?>" target="_blank">
                <img src="<?php echo esc_url($post->creative_url); ?>" style="width:100%;max-height:320px;object-fit:cover;display:block" alt="Creatividad">
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Caption -->
        <?php if ($post->caption): ?>
          <div style="background:#f9fafb;border-radius:10px;padding:12px 14px;margin-bottom:12px;font-size:14px;color:var(--ttb-text);line-height:1.7;white-space:pre-line;border-left:3px solid var(--ttb-pink)"><?php echo esc_html($post->caption); ?></div>
        <?php endif; ?>

        <!-- Nota del equipo -->
        <?php if ($post->creative_note): ?>
          <div style="background:#fdf4ff;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#7e22ce;border:1px solid #e9d5ff"><?php echo esc_html($post->creative_note); ?></div>
        <?php endif; ?>

        <!-- Comentario propio (si rechazó) -->
        <?php if ($post->status === 'rejected' && $post->client_note): ?>
          <div style="background:#fff1f2;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#be123c;border:1px solid #fecdd3">
            <strong style="display:block;margin-bottom:4px">Tu comentario:</strong>
            <?php echo nl2br(esc_html($post->client_note)); ?>
          </div>
        <?php endif; ?>

        <!-- Acciones (solo si pendiente de aprobación) -->
        <?php if ($post->status === 'pending_approval'): ?>
          <div style="border-top:1px solid var(--ttb-border);margin-top:16px;padding-top:16px">
            <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:var(--ttb-text)">¿Qué quieres hacer con esta publicación?</p>

            <!-- Aprobar -->
            <form method="post" action="<?php echo esc_url(TTB_Social_DB::client_url($token)); ?>" style="margin-bottom:10px">
              <?php wp_nonce_field('ttb_social_approve_' . (int)$post->id . '_' . $token); ?>
              <input type="hidden" name="ttb_social_action" value="approve">
              <input type="hidden" name="ttb_social_token" value="<?php echo esc_attr($token); ?>">
              <input type="hidden" name="post_id" value="<?php echo (int)$post->id; ?>">
              <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#10b981,#059669);width:100%">
                Aprobar esta publicación
              </button>
            </form>

            <!-- Rechazar con comentario -->
            <div id="ttb-reject-toggle-<?php echo (int)$post->id; ?>">
              <button type="button" class="ttb-btn ttb-btn--ghost" style="width:100%;color:#e11d48;border-color:#fecdd3"
                onclick="
                  document.getElementById('ttb-reject-form-<?php echo (int)$post->id; ?>').style.display='block';
                  this.style.display='none';
                ">
                Tengo cambios que pedir
              </button>
            </div>
            <div id="ttb-reject-form-<?php echo (int)$post->id; ?>" style="display:none;margin-top:10px">
              <form method="post" action="<?php echo esc_url(TTB_Social_DB::client_url($token)); ?>">
                <?php wp_nonce_field('ttb_social_approve_' . (int)$post->id . '_' . $token); ?>
                <input type="hidden" name="ttb_social_action" value="reject">
                <input type="hidden" name="ttb_social_token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="post_id" value="<?php echo (int)$post->id; ?>">
                <textarea name="client_note" class="ttb-textarea" style="min-height:80px;margin-bottom:10px"
                  placeholder="Cuéntanos qué cambiarías: imagen, texto, tono, color... Con detalle nos ayudas a acertar." required></textarea>
                <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#e11d48,#be123c);width:100%">
                  Enviar comentarios
                </button>
              </form>
            </div>
          </div>
        <?php elseif ($post->status === 'approved'): ?>
          <div style="background:#ecfdf5;border-radius:10px;padding:12px 14px;margin-top:14px;border:1px solid #6ee7b7;font-size:14px;color:#065f46;font-weight:700">
            Has aprobado esta publicación. ¡Gracias!
          </div>
        <?php elseif ($post->status === 'published'): ?>
          <div style="background:#eff6ff;border-radius:10px;padding:12px 14px;margin-top:14px;border:1px solid #bfdbfe;font-size:14px;color:#1d4ed8;font-weight:700">
            Esta publicación ya está publicada.
          </div>
        <?php endif; ?>
      </div>
      <?php
      echo ob_get_clean();
    }
    echo '</div>';
  }

  private static function js_redirect($url) {
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($url)) . ');</script>';
    exit;
  }

  private static function handle_post($client, $token) {
    $action = sanitize_text_field($_POST['ttb_social_action'] ?? '');
    switch ($action) {
      case 'upload':  self::handle_upload($client, $token);  break;
      case 'approve': self::handle_approve($client, $token); break;
      case 'reject':  self::handle_reject($client, $token);  break;
      default: self::js_redirect(TTB_Social_DB::client_url($token));
    }
  }

  private static function handle_upload($client, $token) {
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_upload_' . $token)) {
      TTB_Social_DB::log($client->id, null, 'nonce_failed', 'client', ['action' => 'upload']);
      self::js_redirect(TTB_Social_DB::client_url($token) . '&stab=content');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    global $wpdb;
    $content_table = TTB_Social_DB::content_table();
    $max_mb        = (int)get_option('ttb_social_max_filesize', 50);
    $file_count    = min((int)($_POST['ttb_file_count'] ?? 0), 20);
    $note          = sanitize_textarea_field($_POST['content_note'] ?? '');
    $uploaded      = 0;

    for ($i = 0; $i < $file_count; $i++) {
      $key = 'social_file_' . $i;
      if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) continue;
      if ($_FILES[$key]['size'] > $max_mb * 1024 * 1024) continue;
      $mime = $_FILES[$key]['type'];
      if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/quicktime','video/webm'], true)) continue;

      $att_id = media_handle_sideload([
        'name'     => $_FILES[$key]['name'],
        'type'     => $mime,
        'tmp_name' => $_FILES[$key]['tmp_name'],
        'error'    => $_FILES[$key]['error'],
        'size'     => $_FILES[$key]['size'],
      ], 0, null, ['post_title' => 'Social - ' . $client->name . ' - ' . date('Y-m-d'), 'post_status' => 'private']);

      if (!is_wp_error($att_id)) {
        $wpdb->insert($content_table, [
          'client_id'  => $client->id,
          'type'       => strpos($mime, 'video') !== false ? 'video' : 'image',
          'file_url'   => wp_get_attachment_url($att_id),
          'caption'    => $note,
          'note'       => $note,
          'used'       => 0,
          'created_at' => TTB_Social_DB::now(),
        ]);
        $uploaded++;
      }
    }

    if ($uploaded === 0 && $note) {
      $wpdb->insert($content_table, [
        'client_id'  => $client->id,
        'type'       => 'text',
        'file_url'   => null,
        'caption'    => $note,
        'note'       => $note,
        'used'       => 0,
        'created_at' => TTB_Social_DB::now(),
      ]);
      $uploaded = 1;
    }

    if ($uploaded > 0) {
      (new TTB_Social_Mailer())->send_content_received($client, $uploaded);
      TTB_Social_DB::log($client->id, null, 'content_uploaded', 'client', ['files' => $uploaded]);
    }

    self::js_redirect(TTB_Social_DB::client_url($token) . '&stab=content');
  }

  private static function handle_approve($client, $token) {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_approve_' . $post_id . '_' . $token)) {
      TTB_Social_DB::log($client->id, $post_id, 'nonce_failed', 'client', ['action' => 'approve']);
      self::js_redirect(TTB_Social_DB::client_url($token));
    }

    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d AND client_id=%d", $post_id, $client->id));
    if (!$post) self::js_redirect(TTB_Social_DB::client_url($token));

    $wpdb->update($posts_table, ['status' => 'approved', 'approved_at' => TTB_Social_DB::now(), 'updated_at' => TTB_Social_DB::now()], ['id' => $post_id]);
    (new TTB_Social_Mailer())->send_approved_alert($client, $post);
    TTB_Social_DB::log($client->id, $post_id, 'post_approved', 'client', ['date' => $post->scheduled_date]);

    self::js_redirect(TTB_Social_DB::client_url($token));
  }

  private static function handle_reject($client, $token) {
    $post_id     = (int)($_POST['post_id'] ?? 0);
    $client_note = sanitize_textarea_field($_POST['client_note'] ?? '');
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_social_approve_' . $post_id . '_' . $token)) {
      TTB_Social_DB::log($client->id, $post_id, 'nonce_failed', 'client', ['action' => 'reject']);
      self::js_redirect(TTB_Social_DB::client_url($token));
    }

    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts_table WHERE id=%d AND client_id=%d", $post_id, $client->id));
    if (!$post) self::js_redirect(TTB_Social_DB::client_url($token));

    $post->client_note = $client_note;
    $wpdb->update($posts_table, ['status' => 'rejected', 'client_note' => $client_note, 'updated_at' => TTB_Social_DB::now()], ['id' => $post_id]);
    (new TTB_Social_Mailer())->send_rejected_alert($client, $post);
    TTB_Social_DB::log($client->id, $post_id, 'post_rejected', 'client', ['note' => $client_note]);

    self::js_redirect(TTB_Social_DB::client_url($token));
  }
}