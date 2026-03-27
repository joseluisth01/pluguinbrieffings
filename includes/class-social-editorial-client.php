<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Editorial_Client')) return;

/**
 * TTB_Social_Editorial_Client
 * Vista del cliente para el Calendario Editorial.
 * - Muestra el calendario del mes con días marcados
 * - Clic en día → modal solo lectura con Pilar + Gancho/Tema
 * - Una sola acción por mes: ¿Apruebas el calendario editorial de [Mes]?
 * - Si rechaza → textarea con motivo
 */
class TTB_Social_Editorial_Client {

  public static function render($client, $token, $is_main_portal = false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_ed_client_action'])) {
      self::handle_post($client, $token, $is_main_portal);
      return;
    }

    global $wpdb;
    $ed_table = TTB_Social_DB::editorial_table();

    $filter_month = sanitize_text_field($_GET['ed_month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) $filter_month = date('Y-m');

    [$year, $month] = array_map('intval', explode('-', $filter_month));
    $first_day  = mktime(0, 0, 0, $month, 1, $year);
    $days_in    = (int)date('t', $first_day);
    $start_dow  = (int)date('N', $first_day);
    $prev_month = date('Y-m', strtotime('-1 month', $first_day));
    $next_month = date('Y-m', strtotime('+1 month', $first_day));
    $month_name = date_i18n('F Y', $first_day);
    $month_name_cap = ucfirst($month_name);

    // Entries del mes
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $ed_table
       WHERE client_id=%d AND YEAR(entry_date)=%d AND MONTH(entry_date)=%d
       ORDER BY entry_date ASC",
      $client->id, $year, $month
    ));

    $entries = [];
    foreach ($rows as $r) {
      $day_num = (int)date('j', strtotime($r->entry_date));
      $entries[$day_num] = $r;
    }

    // Estado del mes
    $month_status_row = TTB_Social_DB::get_editorial_month_status($client->id, $filter_month);
    $month_status     = $month_status_row->month_status ?? 'draft';
    $month_statuses   = TTB_Social_DB::editorial_month_statuses();
    [$ms_label, $ms_bg, $ms_bc, $ms_co] = $month_statuses[$month_status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];

    // URL base para navegación
    if ($is_main_portal) {
      $nav_base = home_url('/briefing?ctab=social&stab=editorial');
      $form_action = esc_url(home_url('/briefing'));
    } else {
      $nav_base    = TTB_Social_DB::client_url($token) . '&stab=editorial';
      $form_action = esc_url(TTB_Social_DB::client_url($token));
    }

    ?>
    <style>
    .ttb-ed-client-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 4px;
    }
    .ttb-ed-client-dayname {
      text-align: center;
      font-size: 11px;
      font-weight: 900;
      color: var(--ttb-muted);
      text-transform: uppercase;
      letter-spacing: .06em;
      padding: 6px 0;
    }
    .ttb-ed-client-cell {
      min-height: 72px;
      border-radius: 10px;
      border: 1.5px solid var(--ttb-border);
      background: #fff;
      padding: 6px 8px;
      position: relative;
    }
    .ttb-ed-client-cell.has-content {
      background: rgba(215,33,115,.06);
      border-color: rgba(215,33,115,.25);
      cursor: pointer;
      transition: border-color .2s, background .2s;
    }
    .ttb-ed-client-cell.has-content:hover {
      border-color: var(--ttb-pink);
      background: rgba(215,33,115,.10);
    }
    .ttb-ed-client-cell.is-empty-slot {
      background: #f9fafb;
      border-color: transparent;
    }
    .ttb-ed-client-cell.is-today {
      border-color: var(--ttb-pink) !important;
    }
    .ttb-ed-client-daynum {
      font-size: 12px;
      font-weight: 900;
      color: var(--ttb-muted);
      margin-bottom: 3px;
    }
    .ttb-ed-client-cell.is-today .ttb-ed-client-daynum { color: var(--ttb-pink); }
    .ttb-ed-client-pilar {
      font-size: 10px;
      font-weight: 800;
      color: var(--ttb-pink);
      background: rgba(215,33,115,.10);
      border-radius: 4px;
      padding: 2px 5px;
      margin-bottom: 2px;
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .ttb-ed-client-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--ttb-pink);
      display: inline-block;
      margin-top: 2px;
    }

    /* Modal cliente editorial */
    .ttb-ed-client-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.45);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      z-index: 99999;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .ttb-ed-client-overlay.active { display: flex; }
    .ttb-ed-client-modal {
      background: #fff;
      border-radius: 20px;
      padding: 32px 28px 28px;
      max-width: 440px;
      width: 100%;
      box-shadow: 0 24px 64px rgba(0,0,0,.2);
      position: relative;
      animation: ttbModalUp .3s cubic-bezier(.34,1.56,.64,1) both;
    }
    .ttb-ed-client-modal::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--ttb-pink), #e63a86);
      border-radius: 20px 20px 0 0;
    }
    .ttb-ed-client-modal-close {
      position: absolute;
      top: 14px; right: 16px;
      background: #f3f4f6;
      border: none;
      border-radius: 50%;
      width: 30px; height: 30px;
      font-size: 15px;
      cursor: pointer;
      line-height: 30px;
      text-align: center;
      color: var(--ttb-muted);
    }
    .ttb-ed-client-modal-close:hover { background: #e5e7eb; }
    </style>

    <!-- Modal detalle día (solo lectura) -->
    <div class="ttb-ed-client-overlay" id="ttb-ed-client-overlay" role="dialog" aria-modal="true">
      <div class="ttb-ed-client-modal">
        <button class="ttb-ed-client-modal-close" type="button" onclick="ttbEdClientClose()">✕</button>
        <p id="ttb-ed-client-date-label" style="margin:0 0 4px;font-size:13px;font-weight:700;color:var(--ttb-muted);text-transform:capitalize"></p>
        <div id="ttb-ed-client-pilar-block" style="margin-bottom:16px">
          <p style="margin:0 0 4px;font-size:11px;font-weight:900;color:var(--ttb-pink);text-transform:uppercase;letter-spacing:.06em">Pilar de Contenido</p>
          <p id="ttb-ed-client-pilar" style="margin:0;font-size:16px;font-weight:800;color:var(--ttb-text)"></p>
        </div>
        <div id="ttb-ed-client-gancho-block">
          <p style="margin:0 0 4px;font-size:11px;font-weight:900;color:var(--ttb-pink);text-transform:uppercase;letter-spacing:.06em">Gancho / Tema</p>
          <p id="ttb-ed-client-gancho" style="margin:0;font-size:15px;color:var(--ttb-text);line-height:1.6"></p>
        </div>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--ttb-border);text-align:right">
          <button class="ttb-btn ttb-btn--ghost" type="button" onclick="ttbEdClientClose()">Cerrar</button>
        </div>
      </div>
    </div>

    <div class="ttb-card">
      <!-- Cabecera navegación -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap;justify-content:space-between">
        <div style="display:flex;align-items:center;gap:10px">
          <a href="<?php echo esc_url($nav_base . '&ed_month=' . $prev_month); ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8592;</a>
          <h3 style="margin:0;font-size:18px;text-transform:capitalize"><?php echo esc_html($month_name); ?></h3>
          <a href="<?php echo esc_url($nav_base . '&ed_month=' . $next_month); ?>" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8594;</a>
        </div>
        <!-- Badge estado mes -->
        <?php if ($month_status !== 'draft'): ?>
          <span style="display:inline-block;font-size:12px;font-weight:800;padding:5px 14px;border-radius:999px;background:<?php echo $ms_bg; ?>;border:1px solid <?php echo $ms_bc; ?>;color:<?php echo $ms_co; ?>"><?php echo esc_html($ms_label); ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($entries) && $month_status === 'draft'): ?>
        <div style="text-align:center;padding:40px 24px;color:var(--ttb-muted)">
          <p style="font-size:32px;margin:0 0 10px">📅</p>
          <p style="font-size:15px;margin:0">Aún no hay contenido planificado para <?php echo esc_html($month_name_cap); ?>.</p>
          <p style="font-size:13px;margin:6px 0 0">Tu equipo de TicTac está preparando el calendario editorial. ¡Pronto lo verás aquí!</p>
        </div>
      <?php else: ?>

        <!-- Calendario -->
        <div class="ttb-ed-client-grid">
          <?php
          $day_names = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
          foreach ($day_names as $dn) echo '<div class="ttb-ed-client-dayname">' . esc_html($dn) . '</div>';
          $today_day   = (int)date('j');
          $today_month = (int)date('m');
          $today_year  = (int)date('Y');
          $is_cur_month = ($today_year === $year && $today_month === $month);
          for ($blank = 1; $blank < $start_dow; $blank++): ?>
            <div class="ttb-ed-client-cell is-empty-slot"></div>
          <?php endfor;
          for ($day = 1; $day <= $days_in; $day++):
            $has     = isset($entries[$day]);
            $entry   = $has ? $entries[$day] : null;
            $is_today = $is_cur_month && $day === $today_day;
            $cell_cls = 'ttb-ed-client-cell';
            if ($has) $cell_cls .= ' has-content';
            if ($is_today) $cell_cls .= ' is-today';
            if (!$has) $cell_cls .= ' is-empty-slot';
          ?>
            <div
              class="<?php echo $cell_cls; ?>"
              <?php if ($has): ?>
              onclick="ttbEdClientOpen('<?php echo esc_js(sprintf('%04d-%02d-%02d', $year, $month, $day)); ?>', '<?php echo esc_js($entry->pilar); ?>', '<?php echo esc_js(str_replace(["\n","\r"], ' ', $entry->gancho ?? '')); ?>')"
              title="<?php echo esc_attr($entry->pilar); ?>"
              <?php endif; ?>
            >
              <div class="ttb-ed-client-daynum"><?php echo $day; ?></div>
              <?php if ($has): ?>
                <span class="ttb-ed-client-pilar"><?php echo esc_html($entry->pilar); ?></span>
                <span class="ttb-ed-client-dot"></span>
              <?php endif; ?>
            </div>
          <?php endfor;
          $total_cells = $start_dow - 1 + $days_in;
          $remainder   = $total_cells % 7;
          if ($remainder > 0) for ($i = 0; $i < (7 - $remainder); $i++) echo '<div class="ttb-ed-client-cell is-empty-slot"></div>';
          ?>
        </div>

        <!-- Leyenda -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px;font-size:12px">
          <span style="display:inline-flex;align-items:center;gap:5px">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--ttb-pink);display:inline-block"></span>
            <span style="color:var(--ttb-muted)">Día con contenido planificado — haz clic para ver</span>
          </span>
        </div>

        <!-- Acción mensual: aprobar o rechazar -->
        <?php if ($month_status === 'sent'): ?>
          <div style="margin-top:24px;background:linear-gradient(135deg,#f0f9ff,#fff);border:2px solid #bae6fd;border-radius:18px;padding:24px">
            <p style="margin:0 0 6px;font-size:18px;font-weight:900;color:#0369a1">📋 Revisión del calendario editorial</p>
            <p style="margin:0 0 20px;font-size:15px;color:#0369a1;line-height:1.6">
              ¿Apruebas el calendario editorial del mes de <strong><?php echo esc_html($month_name_cap); ?></strong>?
            </p>

            <!-- Aprobar -->
            <form method="post" action="<?php echo $form_action; ?>" style="margin-bottom:12px">
              <?php wp_nonce_field('ttb_ed_client_action_' . $client->id . '_' . $token); ?>
              <input type="hidden" name="ttb_ed_client_action" value="approve">
              <input type="hidden" name="ttb_ed_token"     value="<?php echo esc_attr($token); ?>">
              <input type="hidden" name="ttb_ed_client_id" value="<?php echo (int)$client->id; ?>">
              <input type="hidden" name="ed_month"         value="<?php echo esc_attr($filter_month); ?>">
              <?php if ($is_main_portal): ?>
                <input type="hidden" name="ttb_return" value="main">
                <input type="hidden" name="stab"       value="editorial">
                <input type="hidden" name="ed_month"   value="<?php echo esc_attr($filter_month); ?>">
              <?php endif; ?>
              <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#10b981,#059669);width:100%;font-size:15px;padding:14px">
                ✅ Sí, apruebo el calendario de <?php echo esc_html($month_name_cap); ?>
              </button>
            </form>

            <!-- Rechazar -->
            <div id="ttb-ed-reject-toggle">
              <button type="button" class="ttb-btn ttb-btn--ghost" style="width:100%;color:#e11d48;border-color:#fecdd3" onclick="document.getElementById('ttb-ed-reject-form').style.display='block';document.getElementById('ttb-ed-reject-toggle').style.display='none'">
                ✏️ No, tengo comentarios o cambios que pedir
              </button>
            </div>

            <div id="ttb-ed-reject-form" style="display:none;margin-top:12px">
              <form method="post" action="<?php echo $form_action; ?>">
                <?php wp_nonce_field('ttb_ed_client_action_' . $client->id . '_' . $token); ?>
                <input type="hidden" name="ttb_ed_client_action" value="reject">
                <input type="hidden" name="ttb_ed_token"     value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="ttb_ed_client_id" value="<?php echo (int)$client->id; ?>">
                <input type="hidden" name="ed_month"         value="<?php echo esc_attr($filter_month); ?>">
                <?php if ($is_main_portal): ?>
                  <input type="hidden" name="ttb_return" value="main">
                  <input type="hidden" name="stab"       value="editorial">
                <?php endif; ?>
                <label style="display:block;font-weight:700;font-size:14px;margin-bottom:8px;color:var(--ttb-text)">
                  Cuéntanos qué cambiarías o qué no te encaja:
                </label>
                <textarea name="ed_client_note" class="ttb-textarea" style="min-height:100px;margin-bottom:10px" placeholder="Ej: El día 15 preferiría un contenido más educativo. El gancho del día 20 no refleja bien nuestro tono..." required></textarea>
                <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#e11d48,#be123c);width:100%">
                  📨 Enviar mis comentarios
                </button>
              </form>
            </div>
          </div>

        <?php elseif ($month_status === 'approved'): ?>
          <div style="margin-top:20px;background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:16px 20px;text-align:center">
            <p style="margin:0;font-size:15px;font-weight:800;color:#065f46">✅ Has aprobado el calendario editorial de <?php echo esc_html($month_name_cap); ?>. ¡Gracias!</p>
          </div>

        <?php elseif ($month_status === 'rejected'): ?>
          <div style="margin-top:20px;background:#fff1f2;border:1.5px solid #fecdd3;border-radius:14px;padding:16px 20px">
            <p style="margin:0 0 8px;font-size:15px;font-weight:800;color:#be123c">📨 Has enviado tus comentarios sobre <?php echo esc_html($month_name_cap); ?></p>
            <?php if (!empty($month_status_row->client_note)): ?>
              <p style="margin:0;font-size:13px;color:#be123c;line-height:1.6;background:#fff;border-radius:10px;padding:10px 14px;border:1px solid #fecdd3"><?php echo nl2br(esc_html($month_status_row->client_note)); ?></p>
            <?php endif; ?>
            <p style="margin:8px 0 0;font-size:12px;color:#e11d48">Nuestro equipo revisará tus comentarios y actualizará el calendario.</p>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>

    <script>
    (function(){
      window.ttbEdClientOpen = function(dateStr, pilar, gancho) {
        var parts  = dateStr.split('-');
        var d      = new Date(parts[0], parts[1]-1, parts[2]);
        var days   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        var months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        var label  = days[d.getDay()] + ', ' + parseInt(parts[2]) + ' de ' + months[d.getMonth()] + ' de ' + parts[0];

        document.getElementById('ttb-ed-client-date-label').textContent = label;
        document.getElementById('ttb-ed-client-pilar').textContent  = pilar || '—';
        document.getElementById('ttb-ed-client-gancho').textContent  = gancho || '—';

        var ganchoBlock = document.getElementById('ttb-ed-client-gancho-block');
        if (ganchoBlock) ganchoBlock.style.display = gancho ? 'block' : 'none';

        document.getElementById('ttb-ed-client-overlay').classList.add('active');
        document.body.style.overflow = 'hidden';
      };

      window.ttbEdClientClose = function() {
        document.getElementById('ttb-ed-client-overlay').classList.remove('active');
        document.body.style.overflow = '';
      };

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') ttbEdClientClose();
      });
      document.getElementById('ttb-ed-client-overlay').addEventListener('click', function(e) {
        if (e.target === this) ttbEdClientClose();
      });
    })();
    </script>
    <?php
  }

  /* ════════════════════════════════
     HANDLE POST: aprobar / rechazar
  ════════════════════════════════ */
  private static function handle_post($client, $token, $is_main_portal) {
    $action    = sanitize_text_field($_POST['ttb_ed_client_action'] ?? '');
    $client_id = (int)($_POST['ttb_ed_client_id'] ?? 0);
    $month     = sanitize_text_field($_POST['ed_month'] ?? date('Y-m'));

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_ed_client_action_' . $client->id . '_' . $token)) {
      self::js_redirect($token, $month, $is_main_portal);
    }

    if ($action === 'approve') {
      TTB_Social_DB::set_editorial_month_status($client_id, $month, 'approved');
      (new TTB_Social_Mailer())->send_editorial_client_response($client, $month, 'approved', null);
      TTB_Social_DB::log($client_id, null, 'editorial_approved', 'client', ['month' => $month]);

    } elseif ($action === 'reject') {
      $note = sanitize_textarea_field($_POST['ed_client_note'] ?? '');
      TTB_Social_DB::set_editorial_month_status($client_id, $month, 'rejected', $note);
      (new TTB_Social_Mailer())->send_editorial_client_response($client, $month, 'rejected', $note);
      TTB_Social_DB::log($client_id, null, 'editorial_rejected', 'client', ['month' => $month, 'note' => mb_substr($note, 0, 100)]);
    }

    self::js_redirect($token, $month, $is_main_portal);
  }

  private static function js_redirect($token, $month, $is_main_portal) {
    if ($is_main_portal) {
      $url = home_url('/briefing?ctab=social&stab=editorial&ed_month=' . urlencode($month));
    } else {
      $url = TTB_Social_DB::client_url($token) . '&stab=editorial&ed_month=' . urlencode($month);
    }
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($url)) . ');</script>';
    exit;
  }
}