<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Client')) return;

/**
 * TTB_Social_Client — v8
 * Añadido aviso de fecha límite de aprobación (7 días antes) en:
 * - El modal de detalle de cada post
 * - El banner general del calendario cuando hay posts próximos al límite
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_ed_client_action'])) {
      TTB_Social_Editorial_Client::render($client, $token, false);
      return;
    }

    TTB_Social_DB::log($client->id, null, 'client_view', 'client', []);

    $is_main_portal = (($_GET['ttb_return'] ?? '') === 'main');

    $tab          = sanitize_text_field($_GET['stab'] ?? 'calendar');
    $filter_month = sanitize_text_field($_GET['filter_month'] ?? date('Y-m'));

    [$year, $month] = array_map('intval', explode('-', $filter_month . '-01'));
    $first_day  = mktime(0, 0, 0, $month, 1, $year);
    $days_in    = (int)date('t', $first_day);
    $start_dow  = (int)date('N', $first_day);
    $prev_month = date('Y-m', strtotime('-1 month', $first_day));
    $next_month = date('Y-m', strtotime('+1 month', $first_day));
    $month_name = date_i18n('F Y', $first_day);

    if ($is_main_portal) {
      $nav_base    = home_url('/briefing?ctab=social');
      $form_action = esc_url(home_url('/briefing'));
    } else {
      $nav_base    = TTB_Social_DB::client_url($token);
      $form_action = esc_url(TTB_Social_DB::client_url($token));
    }

    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $content_table = TTB_Social_DB::content_table();
    $statuses      = TTB_Social_DB::post_statuses();
    $today         = current_time('Y-m-d');
    $deadline_limit = date('Y-m-d', strtotime('+7 days', strtotime($today)));

    $posts_raw = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $posts_table
       WHERE client_id=%d AND status != 'draft'
         AND YEAR(scheduled_date)=%d AND MONTH(scheduled_date)=%d
       ORDER BY scheduled_date ASC",
      $client->id, $year, $month
    ));

    $posts_by_day = [];
    foreach ($posts_raw as $p) {
      $posts_by_day[(int)date('j', strtotime($p->scheduled_date))][] = $p;
    }

    $all_pending = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $posts_table WHERE client_id=%d AND status='pending_approval' ORDER BY scheduled_date ASC",
      $client->id
    ));

    // Posts pendientes que están cerca del límite (7 días o menos)
    $urgent_pending = array_filter($all_pending, function($p) use ($today, $deadline_limit) {
      return $p->scheduled_date >= $today && $p->scheduled_date <= $deadline_limit;
    });

    $client_content = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $content_table WHERE client_id=%d ORDER BY created_at DESC LIMIT 50",
      $client->id
    ));

    ?>
    <style>
    .ttb-social-tabs { display:flex; gap:10px; flex-wrap:wrap; margin:14px 0 18px; }
    .ttb-social-tab { text-decoration:none; padding:10px 20px; border-radius:999px; border:1px solid var(--ttb-border); background:#fff; color:var(--ttb-text); font-weight:800; font-size:14px; transition:background .15s,border-color .15s,color .15s; }
    .ttb-social-tab--active { background:rgba(215,33,115,.10); border-color:rgba(215,33,115,.35); color:var(--ttb-pink); }
    .ttb-social-tab:hover:not(.ttb-social-tab--active) { border-color:rgba(215,33,115,.25); color:var(--ttb-pink); background:rgba(215,33,115,.04); }
    .ttb-dropzone { border:2px dashed var(--ttb-border); border-radius:14px; padding:36px 20px; text-align:center; cursor:pointer; background:#fafafa; transition:border-color .2s, background .2s; }
    .ttb-dropzone:hover, .ttb-dropzone.dragover { border-color:var(--ttb-pink); background:rgba(215,33,115,.03); }
    .ttb-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:10px; margin-top:12px; }
    .ttb-preview-item { position:relative; border-radius:10px; overflow:hidden; border:1px solid var(--ttb-border); background:#f0f0f0; aspect-ratio:1; }
    .ttb-preview-item img, .ttb-preview-item video { width:100%; height:100%; object-fit:cover; display:block; }
    .ttb-preview-remove { position:absolute; top:5px; right:5px; background:#e11d48; color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:10px; font-weight:900; cursor:pointer; line-height:20px; text-align:center; }

    .ttbc-post-overlay {
      position:fixed; inset:0; background:rgba(0,0,0,.5);
      backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);
      display:none; align-items:center; justify-content:center;
      z-index:99999; padding:16px;
    }
    .ttbc-post-overlay.active { display:flex; }
    .ttbc-post-modal {
      background:#fff; border-radius:20px; padding:28px 24px;
      max-width:540px; width:100%; max-height:85vh; overflow-y:auto;
      box-shadow:0 24px 64px rgba(0,0,0,.22); position:relative;
      animation: ttbcModalIn .3s cubic-bezier(.34,1.56,.64,1) both;
    }
    @keyframes ttbcModalIn {
      from { opacity:0; transform:translateY(20px) scale(.96); }
      to   { opacity:1; transform:translateY(0) scale(1); }
    }
    .ttbc-post-modal::before {
      content:''; position:absolute; top:0; left:0; right:0; height:4px;
      background:linear-gradient(90deg,var(--ttb-pink),#e63a86);
      border-radius:20px 20px 0 0;
    }
    .ttbc-post-close {
      position:absolute; top:14px; right:16px;
      background:#f3f4f6; border:none; border-radius:50%;
      width:32px; height:32px; font-size:16px; cursor:pointer;
      line-height:32px; text-align:center; color:var(--ttb-muted);
      transition: background .15s;
    }
    .ttbc-post-close:hover { background:#e5e7eb; }

    /* Aviso de fecha límite en el modal */
    .ttb-deadline-banner {
      border-radius:12px;
      padding:12px 16px;
      margin-bottom:16px;
      font-size:13px;
      line-height:1.5;
    }
    .ttb-deadline-banner--urgent {
      background:#fff1f2;
      border:1.5px solid #fecdd3;
      color:#be123c;
    }
    .ttb-deadline-banner--warning {
      background:#fff7ed;
      border:1.5px solid #fed7aa;
      color:#9a3412;
    }
    .ttb-deadline-banner--info {
      background:#eff6ff;
      border:1.5px solid #bfdbfe;
      color:#1d4ed8;
    }
    </style>

    <!-- Modal detalle post -->
    <div class="ttbc-post-overlay" id="ttbc-post-overlay">
      <div class="ttbc-post-modal">
        <button class="ttbc-post-close" id="ttbc-post-close" type="button">✕</button>
        <div id="ttbc-post-body"></div>
      </div>
    </div>

    <div class="ttb-container">
      <div class="ttb-card ttb-card--header">
        <h2>Redes Sociales</h2>
        <p class="ttb-muted">Hola, <strong><?php echo esc_html($client->name); ?></strong>. Aquí tienes tus publicaciones y puedes enviarnos contenido.</p>
      </div>

      <?php if (!empty($urgent_pending) && $tab === 'calendar'): ?>
        <?php
        $min_days_left = PHP_INT_MAX;
        foreach ($urgent_pending as $up) {
          $dl = (int)ceil((strtotime($up->scheduled_date) - strtotime($today)) / 86400);
          if ($dl < $min_days_left) $min_days_left = $dl;
        }
        $urgent_count = count($urgent_pending);
        if ($min_days_left <= 2) {
          $banner_cls = 'border-color:rgba(190,18,60,.4);background:rgba(190,18,60,.04)';
          $icon = '🚨';
          $deadline_text = $min_days_left === 0
            ? 'El plazo termina HOY'
            : 'Solo queda' . ($min_days_left === 1 ? '' : 'n') . ' <strong>' . $min_days_left . ' día' . ($min_days_left === 1 ? '' : 's') . '</strong> para pedir cambios';
          $text_color = 'color:#be123c';
        } elseif ($min_days_left <= 5) {
          $banner_cls = 'border-color:rgba(154,60,18,.35);background:#fff7ed';
          $icon = '⏰';
          $deadline_text = 'Quedan <strong>' . $min_days_left . ' días</strong> para solicitar cambios en los posts más próximos';
          $text_color = 'color:#9a3412';
        } else {
          $banner_cls = 'border-color:rgba(29,78,216,.25);background:#eff6ff';
          $icon = '📅';
          $deadline_text = 'Revisa y aprueba antes de que se cumpla el plazo de 7 días previos a la publicación';
          $text_color = 'color:#1d4ed8';
        }
        ?>
        <div class="ttb-card" style="border:1.5px solid;<?php echo $banner_cls; ?>">
          <h3 style="margin:0 0 6px;<?php echo $text_color; ?>"><?php echo $icon; ?> <?php echo $urgent_count === 1 ? '1 publicación pendiente de aprobación está próxima al plazo límite' : $urgent_count . ' publicaciones pendientes están próximas al plazo límite'; ?></h3>
          <p style="margin:0 0 10px;font-size:14px;<?php echo $text_color; ?>;line-height:1.6"><?php echo $deadline_text; ?>. Pasada esa fecha <strong>se publicarán tal y como están</strong>.</p>
          <p style="margin:0;font-size:13px;<?php echo $text_color; ?>">Haz clic en el post del calendario para verlo y aprobarlo o pedir cambios.</p>
        </div>
      <?php elseif (!empty($all_pending) && $tab === 'calendar'): ?>
        <div class="ttb-card" style="border-color:rgba(215,33,115,.35);background:rgba(215,33,115,.03)">
          <h3 style="margin:0 0 4px;color:var(--ttb-pink)">
            Tienes <?php echo count($all_pending); ?> publicación<?php echo count($all_pending) > 1 ? 'es' : ''; ?> pendiente<?php echo count($all_pending) > 1 ? 's' : ''; ?> de aprobación
          </h3>
          <p class="ttb-muted" style="margin:0 0 6px">Haz clic en el post del calendario para ver la creatividad y aprobarla o pedir cambios.</p>
          <p style="margin:0;font-size:13px;color:var(--ttb-muted)">⏰ <strong>Recuerda:</strong> solo se aceptan cambios hasta 7 días antes de la fecha de publicación. Pasado ese plazo el post se publicará tal y como está.</p>
        </div>
      <?php endif; ?>

      <div class="ttb-social-tabs">
        <a href="<?php echo esc_url($nav_base . '&stab=calendar&filter_month=' . urlencode($filter_month)); ?>"
           class="ttb-social-tab <?php echo $tab === 'calendar' ? 'ttb-social-tab--active' : ''; ?>">
          📆 Mis publicaciones
        </a>
        <a href="<?php echo esc_url($nav_base . '&stab=editorial'); ?>"
           class="ttb-social-tab <?php echo $tab === 'editorial' ? 'ttb-social-tab--active' : ''; ?>">
          📅 Calendario Editorial
        </a>
        <a href="<?php echo esc_url($nav_base . '&stab=content'); ?>"
           class="ttb-social-tab <?php echo $tab === 'content' ? 'ttb-social-tab--active' : ''; ?>">
          📎 Enviar contenido
        </a>
      </div>

      <?php if ($tab === 'calendar'): ?>

        <div class="ttb-card">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap">
            <a href="<?php echo esc_url($nav_base . '&stab=calendar&filter_month=' . $prev_month); ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8592;</a>
            <h3 style="margin:0;font-size:18px;text-transform:capitalize"><?php echo esc_html($month_name); ?></h3>
            <a href="<?php echo esc_url($nav_base . '&stab=calendar&filter_month=' . $next_month); ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8594;</a>
          </div>

          <?php
          self::render_client_posts_store($posts_raw, $token, $statuses, $is_main_portal, $form_action, $today, $deadline_limit);
          TTB_Social_Admin::render_calendar_grid(
            $posts_by_day, $days_in, $start_dow,
            $year, $month,
            esc_url($nav_base),
            $statuses, $filter_month, 0, false
          );
          ?>

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

          <!-- Aviso informativo fijo sobre la política de cambios -->
          <div style="margin-top:16px;background:#f9fafb;border-radius:12px;padding:12px 16px;border:1px solid var(--ttb-border)">
            <p style="margin:0;font-size:13px;color:var(--ttb-muted);line-height:1.5">
              ⏰ <strong>Política de aprobación:</strong> Solo se aceptan cambios hasta <strong>7 días antes</strong> de la fecha de publicación. Pasado ese plazo, el post se publicará tal y como está.
            </p>
          </div>

          <!-- Aviso contacto urgente -->
          <div style="margin-top:10px;background:#f0fdf4;border-radius:12px;padding:12px 16px;border:1px solid #bbf7d0">
            <p style="margin:0;font-size:13px;color:#166534;line-height:1.6">
              💬 <strong>¿Necesitas contactar con nosotros de forma urgente?</strong><br>
              Escríbenos por <a href="https://wa.me/34957048147" target="_blank" rel="noopener" style="color:#166534;font-weight:700;text-decoration:underline">WhatsApp (+34 957 04 81 47)</a> o al correo <a href="mailto:comunicacion@tictac-comunicacion.es" style="color:#166534;font-weight:700;text-decoration:underline">comunicacion@tictac-comunicacion.es</a>.
            </p>
          </div>
        </div>

      <?php elseif ($tab === 'editorial'): ?>

        <?php TTB_Social_Editorial_Client::render($client, $token, $is_main_portal); ?>

      <?php else: ?>

        <div class="ttb-card">
          <h3 style="margin:0 0 6px">Envíanos tu contenido</h3>
          <p class="ttb-muted" style="margin:0 0 18px">Sube fotos o vídeos que quieras usar en tus publicaciones, o escríbenos una idea.</p>

          <!-- Aviso plazo de entrega de material -->
          <div style="background:linear-gradient(135deg,#fefce8,#fff);border:2px solid #fde68a;border-radius:16px;padding:18px 20px;margin-bottom:20px">
            <p style="margin:0 0 8px;font-size:14px;font-weight:900;color:#92400e">🗓️ Plazo mínimo de entrega de material</p>
            <p style="margin:0 0 10px;font-size:14px;color:#92400e;line-height:1.6">Si la publicación depende de material vuestro (fotos, vídeos, información, etc.), este deberá enviarse con un <strong>mínimo de 10 días de antelación</strong> respecto a la fecha de publicación prevista.</p>
            <p style="margin:0;font-size:13px;color:#b45309;line-height:1.6;background:#fffbeb;border-radius:10px;padding:10px 14px;border:1px solid #fcd34d">⚠️ En caso de no recibir el material a tiempo, la publicación será <strong>sustituida por contenido alternativo</strong> propuesto por la agencia, sin posibilidad de incluir el material fuera de plazo en esa publicación concreta.</p>
          </div>

          <form method="post" action="<?php echo $form_action; ?>" enctype="multipart/form-data" id="ttb-social-upload-form">
            <?php wp_nonce_field('ttb_social_upload_' . $token); ?>
            <input type="hidden" name="ttb_social_action" value="upload">
            <input type="hidden" name="ttb_social_token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="ttb_return" value="<?php echo $is_main_portal ? 'main' : ''; ?>">
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
    (function(){
      /* Modal detalle post */
      var overlay  = document.getElementById('ttbc-post-overlay');
      var body     = document.getElementById('ttbc-post-body');
      var closeBtn = document.getElementById('ttbc-post-close');

      if (overlay) {
        window.ttbOpenPostDetail = function(postId) {
          var store = document.getElementById('ttb-client-post-data-' + postId);
          if (!store) return;

          body.innerHTML = '';
          var clone = store.cloneNode(true);
          clone.style.display = 'block';
          clone.removeAttribute('id');

          var rejectToggle = clone.querySelector('[data-reject-toggle]');
          var rejectForm   = clone.querySelector('[data-reject-form]');
          if (rejectToggle && rejectForm) {
            rejectToggle.addEventListener('click', function() {
              rejectForm.style.display = 'block';
              rejectToggle.style.display = 'none';
              var textarea = rejectForm.querySelector('textarea');
              if (textarea) setTimeout(function() { textarea.focus(); }, 100);
            });
          }

          body.appendChild(clone);
          body.querySelectorAll('video').forEach(function(v) { v.load(); });
          overlay.classList.add('active');
          document.body.style.overflow = 'hidden';
        };

        window.ttbClosePostDetail = function() {
          overlay.querySelectorAll('video').forEach(function(v) { v.pause(); });
          overlay.classList.remove('active');
          document.body.style.overflow = '';
        };

        closeBtn.addEventListener('click', ttbClosePostDetail);
        overlay.addEventListener('click', function(e) {
          if (e.target === overlay) ttbClosePostDetail();
        });
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && overlay.classList.contains('active')) ttbClosePostDetail();
        });
      }

      /* ═══════════════════════════════════════════
         CARRUSEL MULTI-ARCHIVO
      ═══════════════════════════════════════════ */
      (function(){
        var carouselState = {};
        function getState(id, total) {
          if (!carouselState[id]) carouselState[id] = { current: 0, total: total };
          return carouselState[id];
        }
        function applySlide(id) {
          var s     = carouselState[id];
          var track = document.getElementById(id + '-track');
          var dot   = document.getElementById(id + '-dot');
          if (track) track.style.transform = 'translateX(-' + (s.current * 100) + '%)';
          if (dot)   dot.textContent = (s.current + 1) + ' / ' + s.total;
          for (var i = 0; i < s.total; i++) {
            var thumb = document.getElementById(id + '-thumb-' + i);
            if (thumb) thumb.style.borderColor = (i === s.current) ? 'var(--ttb-pink)' : 'var(--ttb-border)';
          }
        }
        window.ttbCarouselNext = function(id, total) {
          var s = getState(id, total); s.current = (s.current + 1) % s.total; applySlide(id);
        };
        window.ttbCarouselPrev = function(id, total) {
          var s = getState(id, total); s.current = (s.current - 1 + s.total) % s.total; applySlide(id);
        };
        window.ttbCarouselGoTo = function(id, idx, total) {
          var s = getState(id, total); s.current = idx; applySlide(id);
        };
      })();

      /* Upload drag & drop */
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
        fetch(form.action || window.location.href, {method:'POST', body:fd})
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
   * Store de posts oculto en el DOM.
   * Incluye aviso de fecha límite en el detalle de cada post.
   */
  private static function render_client_posts_store($posts, $token, $statuses, $is_main_portal = false, $form_action = '', $today = '', $deadline_limit = '') {
    if (!$form_action) {
      $form_action = esc_url(TTB_Social_DB::client_url($token));
    }
    if (!$today)          $today          = current_time('Y-m-d');
    if (!$deadline_limit) $deadline_limit = date('Y-m-d', strtotime('+7 days', strtotime($today)));

    echo '<div id="ttb-client-posts-store" style="display:none">';
    foreach ($posts as $post) {
      [$sl,$sbg,$sbc,$sco] = $statuses[$post->status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];
      $date_fmt  = date_i18n('l, j \d\e F \d\e Y', strtotime($post->scheduled_date));
      $copy_text = $post->copy_text ?? '';

      // Calcular días hasta la fecha límite de cambios (7 días antes de la publicación)
      $pub_ts       = strtotime($post->scheduled_date);
      $cutoff_ts    = $pub_ts - (7 * 86400);
      $today_ts     = strtotime($today);
      $days_to_cutoff = (int)ceil(($cutoff_ts - $today_ts) / 86400);
      $past_deadline  = $today >= date('Y-m-d', $cutoff_ts);
      $near_deadline  = !$past_deadline && $post->scheduled_date <= $deadline_limit;

      ob_start();
      ?>
      <div id="ttb-client-post-data-<?php echo (int)$post->id; ?>" style="display:none">
        <div style="margin-bottom:14px">
          <div style="margin-bottom:6px">
            <span style="display:inline-block;font-size:12px;font-weight:800;padding:4px 12px;border-radius:999px;background:<?php echo $sbg; ?>;border:1px solid <?php echo $sbc; ?>;color:<?php echo $sco; ?>"><?php echo esc_html($sl); ?></span>
          </div>
          <p style="margin:0;font-size:16px;font-weight:800;color:var(--ttb-text)"><?php echo esc_html($date_fmt); ?></p>
        </div>

        <?php
        // Aviso de fecha límite — solo en posts pending_approval
        if ($post->status === 'pending_approval'):
          if ($past_deadline):
        ?>
          <div class="ttb-deadline-banner ttb-deadline-banner--urgent" style="margin-bottom:14px">
            <strong>🔴 Plazo de cambios finalizado</strong><br>
            El plazo para solicitar cambios ha concluido. Esta publicación se realizará tal y como está.
          </div>
        <?php elseif ($near_deadline && $days_to_cutoff <= 2): ?>
          <div class="ttb-deadline-banner ttb-deadline-banner--urgent" style="margin-bottom:14px">
            <strong>🚨 Plazo casi agotado</strong> — Solo queda<?php echo $days_to_cutoff === 1 ? '' : 'n'; ?> <strong><?php echo $days_to_cutoff; ?> día<?php echo $days_to_cutoff === 1 ? '' : 's'; ?></strong> para pedir cambios.<br>
            Pasado ese plazo la publicación se realizará tal y como está.
          </div>
        <?php elseif ($near_deadline): ?>
          <div class="ttb-deadline-banner ttb-deadline-banner--warning" style="margin-bottom:14px">
            <strong>⏰ Plazo próximo</strong> — Tienes hasta el <strong><?php echo esc_html(date_i18n('d/m/Y', $cutoff_ts)); ?></strong> para pedir cambios.<br>
            Pasada esa fecha la publicación se realizará tal y como está.
          </div>
        <?php else: ?>
          <div class="ttb-deadline-banner ttb-deadline-banner--info" style="margin-bottom:14px">
            <strong>📅 Fecha límite de cambios:</strong> <?php echo esc_html(date_i18n('d/m/Y', $cutoff_ts)); ?><br>
            Solo se aceptan cambios hasta 7 días antes de la publicación.
          </div>
        <?php
          endif;
        endif;
        ?>

        <?php
        // Obtener todas las URLs (nuevo campo creative_urls o legacy creative_url)
        $post_all_urls = TTB_Social_DB::get_post_creative_urls($post);
        $post_url_count = count($post_all_urls);
        if ($post_url_count > 0):
          $carousel_id = 'ttbc-carousel-' . (int)$post->id;
        ?>
        <?php if ($post_url_count === 1): ?>
          <?php $is_vid = in_array(strtolower(pathinfo($post_all_urls[0], PATHINFO_EXTENSION)), ['mp4','mov','webm'], true); ?>
          <div style="border-radius:12px;overflow:hidden;margin-bottom:14px;border:1px solid var(--ttb-border)">
            <?php if ($is_vid): ?>
              <video src="<?php echo esc_url($post_all_urls[0]); ?>" controls style="width:100%;max-height:280px;display:block;background:#111"></video>
            <?php else: ?>
              <a href="<?php echo esc_url($post_all_urls[0]); ?>" target="_blank">
                <img src="<?php echo esc_url($post_all_urls[0]); ?>" style="width:100%;max-height:320px;object-fit:cover;display:block" alt="Creatividad">
              </a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <!-- Carrusel multi-archivo -->
          <div style="position:relative;margin-bottom:14px;border-radius:12px;overflow:hidden;border:1px solid var(--ttb-border);background:#f0f0f0" id="<?php echo esc_attr($carousel_id); ?>">
            <!-- Track de slides -->
            <div style="display:flex;transition:transform .35s cubic-bezier(.4,0,.2,1);will-change:transform" id="<?php echo esc_attr($carousel_id); ?>-track">
              <?php foreach ($post_all_urls as $ci => $curl): ?>
                <?php $is_v = in_array(strtolower(pathinfo($curl, PATHINFO_EXTENSION)), ['mp4','mov','webm'], true); ?>
                <div style="flex:0 0 100%;width:100%">
                  <?php if ($is_v): ?>
                    <video src="<?php echo esc_url($curl); ?>" controls style="width:100%;max-height:280px;display:block;background:#111"></video>
                  <?php else: ?>
                    <a href="<?php echo esc_url($curl); ?>" target="_blank">
                      <img src="<?php echo esc_url($curl); ?>" style="width:100%;max-height:320px;object-fit:cover;display:block" alt="Archivo <?php echo $ci + 1; ?>">
                    </a>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <!-- Botones de navegación -->
            <button type="button" onclick="ttbCarouselPrev('<?php echo esc_js($carousel_id); ?>', <?php echo $post_url_count; ?>)"
              style="position:absolute;left:8px;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.55);border:none;border-radius:50%;width:32px;height:32px;color:#fff;font-size:16px;cursor:pointer;line-height:32px;text-align:center;z-index:2">‹</button>
            <button type="button" onclick="ttbCarouselNext('<?php echo esc_js($carousel_id); ?>', <?php echo $post_url_count; ?>)"
              style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.55);border:none;border-radius:50%;width:32px;height:32px;color:#fff;font-size:16px;cursor:pointer;line-height:32px;text-align:center;z-index:2">›</button>
            <!-- Indicador -->
            <div id="<?php echo esc_attr($carousel_id); ?>-dot" style="position:absolute;bottom:8px;left:0;right:0;text-align:center;font-size:12px;font-weight:700;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.7)">
              1 / <?php echo $post_url_count; ?>
            </div>
          </div>
          <!-- Miniaturas -->
          <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap">
            <?php foreach ($post_all_urls as $ci => $curl): ?>
              <?php $is_v = in_array(strtolower(pathinfo($curl, PATHINFO_EXTENSION)), ['mp4','mov','webm'], true); ?>
              <button type="button"
                onclick="ttbCarouselGoTo('<?php echo esc_js($carousel_id); ?>', <?php echo $ci; ?>, <?php echo $post_url_count; ?>)"
                id="<?php echo esc_attr($carousel_id); ?>-thumb-<?php echo $ci; ?>"
                style="width:52px;height:52px;border-radius:8px;overflow:hidden;border:2px solid <?php echo $ci === 0 ? 'var(--ttb-pink)' : 'var(--ttb-border)'; ?>;background:#f0f0f0;padding:0;cursor:pointer;flex-shrink:0">
                <?php if ($is_v): ?>
                  <span style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:18px;background:#1a1a2e">🎬</span>
                <?php else: ?>
                  <img src="<?php echo esc_url($curl); ?>" style="width:100%;height:100%;object-fit:cover;display:block" alt="">
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($copy_text): ?>
          <div style="background:#f9fafb;border-radius:10px;padding:12px 14px;margin-bottom:12px;font-size:14px;color:var(--ttb-text);line-height:1.7;white-space:pre-line;border-left:3px solid var(--ttb-pink)"><?php echo esc_html($copy_text); ?></div>
        <?php endif; ?>

        <?php if ($post->creative_note): ?>
          <div style="background:#fdf4ff;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#7e22ce;border:1px solid #e9d5ff"><?php echo esc_html($post->creative_note); ?></div>
        <?php endif; ?>

        <?php if ($post->status === 'rejected' && $post->client_note): ?>
          <div style="background:#fff1f2;border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px;color:#be123c;border:1px solid #fecdd3">
            <strong style="display:block;margin-bottom:4px">Tu comentario:</strong>
            <?php echo nl2br(esc_html($post->client_note)); ?>
          </div>
        <?php endif; ?>

        <?php if ($post->status === 'pending_approval' && !$past_deadline): ?>
          <div style="border-top:1px solid var(--ttb-border);margin-top:16px;padding-top:16px">
            <p style="margin:0 0 12px;font-size:14px;font-weight:700;color:var(--ttb-text)">¿Qué quieres hacer con esta publicación?</p>

            <form method="post" action="<?php echo $form_action; ?>" style="margin-bottom:10px">
              <?php wp_nonce_field('ttb_social_approve_' . (int)$post->id . '_' . $token); ?>
              <input type="hidden" name="ttb_social_action" value="approve">
              <input type="hidden" name="ttb_social_token" value="<?php echo esc_attr($token); ?>">
              <input type="hidden" name="post_id" value="<?php echo (int)$post->id; ?>">
              <input type="hidden" name="ttb_return" value="<?php echo $is_main_portal ? 'main' : ''; ?>">
              <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#10b981,#059669);width:100%">
                ✅ Aprobar esta publicación
              </button>
            </form>

            <button type="button" class="ttb-btn ttb-btn--ghost" data-reject-toggle
              style="width:100%;color:#e11d48;border-color:#fecdd3">
              ✏️ Tengo cambios que pedir
            </button>

            <div data-reject-form style="display:none;margin-top:12px">
              <form method="post" action="<?php echo $form_action; ?>">
                <?php wp_nonce_field('ttb_social_approve_' . (int)$post->id . '_' . $token); ?>
                <input type="hidden" name="ttb_social_action" value="reject">
                <input type="hidden" name="ttb_social_token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="post_id" value="<?php echo (int)$post->id; ?>">
                <input type="hidden" name="ttb_return" value="<?php echo $is_main_portal ? 'main' : ''; ?>">
                <label style="display:block;font-weight:700;font-size:13px;color:var(--ttb-text);margin-bottom:6px">
                  Cuéntanos qué cambiarías:
                </label>
                <textarea name="client_note" class="ttb-textarea" style="min-height:100px;margin-bottom:10px"
                  placeholder="Imagen, texto, tono, color... Con detalle nos ayudas a acertar." required></textarea>
                <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#e11d48,#be123c);width:100%">
                  📨 Enviar comentarios
                </button>
              </form>
            </div>
          </div>

        <?php elseif ($post->status === 'pending_approval' && $past_deadline): ?>
          <div style="background:#f9fafb;border-radius:10px;padding:14px 16px;margin-top:14px;border:1px solid var(--ttb-border);text-align:center">
            <p style="margin:0;font-size:14px;color:var(--ttb-muted)">El plazo de aprobación ha concluido. Esta publicación se realizará tal y como está.</p>
          </div>

        <?php elseif ($post->status === 'approved'): ?>
          <div style="background:#ecfdf5;border-radius:10px;padding:14px 16px;margin-top:14px;border:1px solid #6ee7b7;font-size:14px;color:#065f46;font-weight:700;text-align:center">
            ✅ Has aprobado esta publicación. ¡Gracias!
          </div>
        <?php elseif ($post->status === 'published'): ?>
          <div style="background:#eff6ff;border-radius:10px;padding:14px 16px;margin-top:14px;border:1px solid #bfdbfe;font-size:14px;color:#1d4ed8;font-weight:700;text-align:center">
            📢 Esta publicación ya está publicada.
          </div>
        <?php endif; ?>
      </div>
      <?php
      echo ob_get_clean();
    }
    echo '</div>';
  }

  private static function js_redirect($url) {
    $return = sanitize_text_field($_POST['ttb_return'] ?? $_GET['ttb_return'] ?? '');
    if ($return === 'main') {
      $stab         = sanitize_text_field($_POST['stab'] ?? $_GET['stab'] ?? 'calendar');
      $filter_month = sanitize_text_field($_POST['filter_month'] ?? $_GET['filter_month'] ?? date('Y-m'));
      $url = home_url('/briefing?ctab=social&stab=' . urlencode($stab) . '&filter_month=' . urlencode($filter_month));
    }
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

    $return = sanitize_text_field($_POST['ttb_return'] ?? '');
    if ($return === 'main') {
      $_POST['ttb_return'] = 'main';
      $_GET['stab']        = 'content';
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

    // Verificar que aún está dentro del plazo
    $cutoff_date = date('Y-m-d', strtotime($post->scheduled_date) - (7 * 86400));
    if (current_time('Y-m-d') > $cutoff_date) {
      // Fuera de plazo: no se puede rechazar
      self::js_redirect(TTB_Social_DB::client_url($token));
    }

    $post->client_note = $client_note;
    $wpdb->update($posts_table, ['status' => 'rejected', 'client_note' => $client_note, 'updated_at' => TTB_Social_DB::now()], ['id' => $post_id]);
    (new TTB_Social_Mailer())->send_rejected_alert($client, $post);
    TTB_Social_DB::log($client->id, $post_id, 'post_rejected', 'client', ['note' => $client_note]);

    self::js_redirect(TTB_Social_DB::client_url($token));
  }
}