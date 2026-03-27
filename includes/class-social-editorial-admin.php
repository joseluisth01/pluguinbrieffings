<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Editorial_Admin')) return;

/**
 * TTB_Social_Editorial_Admin
 * Pestaña "Calendario Editorial" dentro del módulo de Redes Sociales.
 *
 * Flujo:
 * - Admin selecciona cliente + mes
 * - Hace clic en días del calendario → modal con Pilar + Gancho/Tema
 * - Puede editar o borrar cualquier entrada
 * - Cuando el mes está listo pulsa "Mandar Calendario Editorial Mensual"
 *   → cambia month_status a 'sent' y envía email de aviso al cliente
 */
class TTB_Social_Editorial_Admin {

  private static $flash = null;

  public static function render($sc = 0) {
    self::handle_save_entry();
    self::handle_delete_entry();
    self::handle_send_month();

    if (self::$flash) {
      $cls = self::$flash['type'] === 'success' ? 'ttb-alert--success' : 'ttb-alert--error';
      echo '<div class="ttb-alert ' . $cls . '">' . esc_html(self::$flash['text']) . '</div>';
    }

    global $wpdb;
    $sc_table = TTB_Social_DB::clients_table();
    $clients  = $wpdb->get_results("SELECT id, name FROM $sc_table WHERE status='active' ORDER BY name ASC");

    // Cliente activo: sc global del módulo tiene prioridad
    $filter_client = $sc ?: (int)($_GET['ed_client'] ?? 0);
    $filter_month  = sanitize_text_field($_GET['ed_month'] ?? date('Y-m'));

    // Validar formato del mes
    if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
      $filter_month = date('Y-m');
    }

    [$year, $month] = array_map('intval', explode('-', $filter_month));
    $first_day  = mktime(0, 0, 0, $month, 1, $year);
    $days_in    = (int)date('t', $first_day);
    $start_dow  = (int)date('N', $first_day); // 1=Lun ... 7=Dom
    $prev_month = date('Y-m', strtotime('-1 month', $first_day));
    $next_month = date('Y-m', strtotime('+1 month', $first_day));
    $month_name = date_i18n('F Y', $first_day);

    // Entries del mes para el cliente
    $entries = [];
    $month_status_row = null;
    if ($filter_client) {
      $ed_table = TTB_Social_DB::editorial_table();
      $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $ed_table
         WHERE client_id=%d AND YEAR(entry_date)=%d AND MONTH(entry_date)=%d
         ORDER BY entry_date ASC",
        $filter_client, $year, $month
      ));
      foreach ($rows as $r) {
        $day_num = (int)date('j', strtotime($r->entry_date));
        $entries[$day_num] = $r;
      }
      $month_status_row = TTB_Social_DB::get_editorial_month_status($filter_client, $filter_month);
    }

    $month_status   = $month_status_row->month_status ?? 'draft';
    $month_statuses = TTB_Social_DB::editorial_month_statuses();
    [$ms_label, $ms_bg, $ms_bc, $ms_co] = $month_statuses[$month_status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];

    $action_url = self::action_url();

    // ── Cabecera con selector de cliente y mes ──────────────────
    echo '<div class="ttb-card">';
    echo '<h3 style="margin:0 0 14px">📅 Calendario Editorial</h3>';

    echo '<form method="get" action="' . esc_url(home_url('/briefing')) . '" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">';
    echo '<input type="hidden" name="section" value="redes-sociales">';
    echo '<input type="hidden" name="sstab" value="editorial">';
    if ($sc) echo '<input type="hidden" name="sc_client" value="' . $sc . '">';

    // Selector cliente (solo si no hay sc global)
    if (!$sc) {
      echo '<div><label style="display:block;font-size:12px;font-weight:700;color:var(--ttb-muted);margin-bottom:4px">Cliente</label>';
      echo '<select name="ed_client" class="ttb-input" style="min-width:200px">';
      echo '<option value="">— Selecciona cliente —</option>';
      foreach ($clients as $c) {
        echo '<option value="' . (int)$c->id . '"' . selected($filter_client, (int)$c->id, false) . '>' . esc_html($c->name) . '</option>';
      }
      echo '</select></div>';
    }

    // Selector mes
    echo '<div><label style="display:block;font-size:12px;font-weight:700;color:var(--ttb-muted);margin-bottom:4px">Mes</label>';
    echo '<input type="month" name="ed_month" class="ttb-input" value="' . esc_attr($filter_month) . '" style="max-width:180px"></div>';
    echo '<button type="submit" class="ttb-btn">Ver</button>';
    echo '</form>';
    echo '</div>';

    if (!$filter_client) {
      echo '<div class="ttb-card" style="text-align:center;padding:40px 24px">';
      echo '<p style="font-size:32px;margin:0 0 12px">📅</p>';
      echo '<p style="color:var(--ttb-muted)">Selecciona un cliente para ver o editar su calendario editorial.</p>';
      echo '</div>';
      return;
    }

    // ── Estado del mes + botón de envío ────────────────────────
    echo '<div class="ttb-card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">';

    echo '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">';
    echo '<span style="font-size:15px;font-weight:800;color:var(--ttb-text)">Estado del mes:</span>';
    echo '<span style="display:inline-block;font-size:13px;font-weight:800;padding:5px 14px;border-radius:999px;background:' . $ms_bg . ';border:1px solid ' . $ms_bc . ';color:' . $ms_co . '">' . esc_html($ms_label) . '</span>';

    $entry_count = count($entries);
    echo '<span style="font-size:13px;color:var(--ttb-muted)">' . $entry_count . ' día' . ($entry_count !== 1 ? 's' : '') . ' rellenado' . ($entry_count !== 1 ? 's' : '') . '</span>';

    if ($month_status === 'rejected' && !empty($month_status_row->client_note)) {
      echo '<div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:8px 14px;font-size:13px;color:#be123c;max-width:400px">';
      echo '<strong>Motivo del rechazo:</strong> ' . nl2br(esc_html($month_status_row->client_note));
      echo '</div>';
    }
    echo '</div>';

    // Botones de acción
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';

    if ($entry_count > 0 && in_array($month_status, ['draft', 'rejected'], true)) {
      echo '<form method="post" action="' . $action_url . '" style="margin:0" onsubmit="return confirm(\'¿Enviar el calendario editorial de ' . esc_js($month_name) . ' al cliente? Recibirá un email de aviso.\')">';
      wp_nonce_field('ttb_editorial_send_month');
      echo '<input type="hidden" name="ed_client_id" value="' . $filter_client . '">';
      echo '<input type="hidden" name="ed_month" value="' . esc_attr($filter_month) . '">';
      echo '<button class="ttb-btn" name="ttb_editorial_send_month" value="1" style="background:linear-gradient(135deg,#10b981,#059669)">📨 Mandar Calendario Editorial Mensual</button>';
      echo '</form>';
    }

    if ($month_status === 'sent' || $month_status === 'approved') {
      echo '<form method="post" action="' . $action_url . '" style="margin:0" onsubmit="return confirm(\'¿Volver el mes a estado Borrador? El cliente ya no verá el aviso de aprobación.\')">';
      wp_nonce_field('ttb_editorial_send_month');
      echo '<input type="hidden" name="ed_client_id" value="' . $filter_client . '">';
      echo '<input type="hidden" name="ed_month" value="' . esc_attr($filter_month) . '">';
      echo '<input type="hidden" name="ed_revert_draft" value="1">';
      echo '<button class="ttb-btn ttb-btn--ghost" name="ttb_editorial_send_month" value="1">↩ Volver a borrador</button>';
      echo '</form>';
    }

    echo '</div>';
    echo '</div>';

    // ── Navegación del calendario ───────────────────────────────
    $nav_base = self::nav_url($filter_client, $sc);
    echo '<div class="ttb-card">';
    echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap">';
    echo '<a href="' . esc_url($nav_base . '&ed_month=' . $prev_month) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8592;</a>';
    echo '<h3 style="margin:0;font-size:18px;text-transform:capitalize">' . esc_html($month_name) . '</h3>';
    echo '<a href="' . esc_url($nav_base . '&ed_month=' . $next_month) . '" class="ttb-btn ttb-btn--ghost ttb-btn--sm">&#8594;</a>';
    echo '</div>';

    // ── Grid del calendario ─────────────────────────────────────
    self::render_admin_grid($entries, $days_in, $start_dow, $year, $month, $filter_client, $filter_month, $month_status, $action_url);

    // Leyenda
    echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:14px;font-size:12px">';
    echo '<span style="display:inline-flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:#eff6ff;border:1px solid #bfdbfe;display:inline-block"></span><span style="color:var(--ttb-muted)">Día con contenido</span></span>';
    echo '<span style="display:inline-flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:#f9fafb;border:1px dashed #d1d5db;display:inline-block"></span><span style="color:var(--ttb-muted)">Día vacío (clic para añadir)</span></span>';
    echo '</div>';
    echo '</div>';

    // ── Modal para añadir/editar entrada ───────────────────────
    self::render_entry_modal($filter_client, $filter_month, $action_url);
  }

  /* ════════════════════════════════
     GRID CALENDARIO ADMIN
  ════════════════════════════════ */
  private static function render_admin_grid($entries, $days_in, $start_dow, $year, $month, $client_id, $filter_month, $month_status, $action_url) {
    $today_day   = (int)date('j');
    $today_month = (int)date('m');
    $today_year  = (int)date('Y');
    $is_cur_month = ($today_year === $year && $today_month === $month);
    $day_names    = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    $can_edit     = in_array($month_status, ['draft', 'rejected', 'sent'], true); // admin siempre puede editar

    ?>
    <style>
    .ttb-ed-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
    }
    .ttb-ed-dayname {
      text-align: center;
      font-size: 11px;
      font-weight: 900;
      color: var(--ttb-muted);
      text-transform: uppercase;
      letter-spacing: .06em;
      padding: 6px 0;
    }
    .ttb-ed-cell {
      min-height: 80px;
      border-radius: 10px;
      border: 1.5px dashed #e5e7eb;
      background: #f9fafb;
      padding: 6px 8px;
      cursor: pointer;
      transition: border-color .2s, background .2s, box-shadow .2s;
      position: relative;
    }
    .ttb-ed-cell:hover {
      border-color: var(--ttb-pink);
      background: rgba(215,33,115,.04);
      box-shadow: 0 2px 10px rgba(215,33,115,.10);
    }
    .ttb-ed-cell.has-content {
      background: #eff6ff;
      border: 1.5px solid #bfdbfe;
      cursor: pointer;
    }
    .ttb-ed-cell.has-content:hover {
      border-color: #3b82f6;
      background: #dbeafe;
    }
    .ttb-ed-cell.is-today {
      border-color: var(--ttb-pink) !important;
      background: rgba(215,33,115,.04) !important;
    }
    .ttb-ed-cell.is-empty-slot {
      cursor: default;
      background: transparent;
      border-color: transparent;
    }
    .ttb-ed-cell.is-empty-slot:hover {
      background: transparent;
      border-color: transparent;
      box-shadow: none;
    }
    .ttb-ed-daynum {
      font-size: 12px;
      font-weight: 900;
      color: var(--ttb-muted);
      margin-bottom: 4px;
      line-height: 1;
    }
    .ttb-ed-cell.is-today .ttb-ed-daynum { color: var(--ttb-pink); }
    .ttb-ed-pilar {
      font-size: 10px;
      font-weight: 800;
      color: #1d4ed8;
      background: #dbeafe;
      border-radius: 4px;
      padding: 2px 5px;
      margin-bottom: 3px;
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .ttb-ed-gancho {
      font-size: 10px;
      color: #374151;
      line-height: 1.3;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .ttb-ed-add-hint {
      font-size: 10px;
      color: #d1d5db;
      text-align: center;
      position: absolute;
      bottom: 6px;
      left: 0;
      right: 0;
    }
    .ttb-ed-cell:hover .ttb-ed-add-hint { color: var(--ttb-pink); }

    /* Modal editorial */
    .ttb-ed-modal-overlay {
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
    .ttb-ed-modal-overlay.active { display: flex; }
    .ttb-ed-modal {
      background: #fff;
      border-radius: 20px;
      padding: 32px 28px 28px;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 24px 64px rgba(0,0,0,.2);
      position: relative;
      animation: ttbModalUp .3s cubic-bezier(.34,1.56,.64,1) both;
    }
    .ttb-ed-modal::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--ttb-pink), #e63a86);
      border-radius: 20px 20px 0 0;
    }
    .ttb-ed-modal-close {
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
      transition: background .15s;
    }
    .ttb-ed-modal-close:hover { background: #e5e7eb; }
    </style>

    <div class="ttb-ed-grid">
      <?php foreach ($day_names as $dn): ?>
        <div class="ttb-ed-dayname"><?php echo esc_html($dn); ?></div>
      <?php endforeach; ?>

      <?php for ($blank = 1; $blank < $start_dow; $blank++): ?>
        <div class="ttb-ed-cell is-empty-slot"></div>
      <?php endfor; ?>

      <?php for ($day = 1; $day <= $days_in; $day++): ?>
        <?php
        $has    = isset($entries[$day]);
        $entry  = $has ? $entries[$day] : null;
        $is_today = $is_cur_month && $day === $today_day;
        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $cell_cls = 'ttb-ed-cell';
        if ($has) $cell_cls .= ' has-content';
        if ($is_today) $cell_cls .= ' is-today';
        ?>
        <div class="<?php echo $cell_cls; ?>"
             onclick="ttbEdOpenModal(<?php echo (int)$client_id; ?>, '<?php echo esc_js($date_str); ?>', <?php echo $has ? (int)$entry->id : 'null'; ?>, <?php echo $has ? '\'' . esc_js($entry->pilar) . '\'' : '\'\''; ?>, <?php echo $has ? '\'' . esc_js(str_replace(["\n","\r"], ' ', $entry->gancho ?? '')) . '\'' : '\'\''; ?>)"
             title="<?php echo $has ? esc_attr($entry->pilar . ' — ' . ($entry->gancho ?? '')) : 'Clic para añadir'; ?>">
          <div class="ttb-ed-daynum"><?php echo $day; ?></div>
          <?php if ($has): ?>
            <span class="ttb-ed-pilar"><?php echo esc_html($entry->pilar); ?></span>
            <span class="ttb-ed-gancho"><?php echo esc_html($entry->gancho ?? ''); ?></span>
          <?php else: ?>
            <span class="ttb-ed-add-hint">+ añadir</span>
          <?php endif; ?>
        </div>
      <?php endfor; ?>

      <?php
      $total_cells = $start_dow - 1 + $days_in;
      $remainder   = $total_cells % 7;
      if ($remainder > 0) {
        for ($i = 0; $i < (7 - $remainder); $i++) {
          echo '<div class="ttb-ed-cell is-empty-slot"></div>';
        }
      }
      ?>
    </div>

    <script>
    (function(){
      window.ttbEdOpenModal = function(clientId, dateStr, entryId, pilar, gancho) {
        var overlay = document.getElementById('ttb-ed-modal-overlay');
        if (!overlay) return;

        // Formatear fecha legible
        var parts = dateStr.split('-');
        var d = new Date(parts[0], parts[1]-1, parts[2]);
        var days = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        var months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        var label = days[d.getDay()] + ' ' + parseInt(parts[2]) + ' de ' + months[d.getMonth()] + ' de ' + parts[0];

        document.getElementById('ttb-ed-modal-date-label').textContent = label;
        document.getElementById('ttb-ed-entry-date').value  = dateStr;
        document.getElementById('ttb-ed-client-id').value   = clientId;
        document.getElementById('ttb-ed-entry-id').value    = entryId || '';
        document.getElementById('ttb-ed-pilar').value       = pilar || '';
        document.getElementById('ttb-ed-gancho').value      = gancho || '';

        // Mostrar/ocultar botón eliminar
        var delBtn = document.getElementById('ttb-ed-delete-btn');
        if (delBtn) delBtn.style.display = entryId ? 'inline-flex' : 'none';

        // Título del modal
        document.getElementById('ttb-ed-modal-title').textContent = entryId ? '✏️ Editar entrada' : '➕ Nueva entrada';

        overlay.classList.add('active');
        setTimeout(function(){ document.getElementById('ttb-ed-pilar').focus(); }, 100);
      };

      window.ttbEdCloseModal = function() {
        var overlay = document.getElementById('ttb-ed-modal-overlay');
        if (overlay) overlay.classList.remove('active');
      };

      // Cerrar con Escape
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') ttbEdCloseModal();
      });

      // Cerrar al clicar fuera
      var overlay = document.getElementById('ttb-ed-modal-overlay');
      if (overlay) {
        overlay.addEventListener('click', function(e) {
          if (e.target === overlay) ttbEdCloseModal();
        });
      }
    })();
    </script>
    <?php
  }

  /* ════════════════════════════════
     MODAL AÑADIR / EDITAR ENTRADA
  ════════════════════════════════ */
  private static function render_entry_modal($client_id, $filter_month, $action_url) {
    ?>
    <div class="ttb-ed-modal-overlay" id="ttb-ed-modal-overlay" role="dialog" aria-modal="true">
      <div class="ttb-ed-modal">
        <button class="ttb-ed-modal-close" type="button" onclick="ttbEdCloseModal()">✕</button>
        <h3 id="ttb-ed-modal-title" style="margin:0 0 4px;font-size:18px;font-weight:900;color:var(--ttb-text)">➕ Nueva entrada</h3>
        <p id="ttb-ed-modal-date-label" style="margin:0 0 20px;font-size:14px;color:var(--ttb-pink);font-weight:700;text-transform:capitalize"></p>

        <form method="post" action="<?php echo esc_url($action_url); ?>" class="ttb-formgrid">
          <?php wp_nonce_field('ttb_editorial_save_entry'); ?>
          <input type="hidden" name="ttb_editorial_save_entry" value="1">
          <input type="hidden" name="ed_entry_id"     id="ttb-ed-entry-id"  value="">
          <input type="hidden" name="ed_client_id"    id="ttb-ed-client-id" value="<?php echo (int)$client_id; ?>">
          <input type="hidden" name="ed_entry_date"   id="ttb-ed-entry-date" value="">
          <input type="hidden" name="ed_month"        value="<?php echo esc_attr($filter_month); ?>">

          <div>
            <label style="display:block;font-weight:700;font-size:14px;margin-bottom:6px">
              Pilar de Contenido <span class="ttb-required">*</span>
            </label>
            <input
              type="text"
              id="ttb-ed-pilar"
              name="ed_pilar"
              class="ttb-input"
              placeholder="Ej: Educativo / Financiero"
              required
              maxlength="255"
            >
          </div>

          <div style="margin-top:12px">
            <label style="display:block;font-weight:700;font-size:14px;margin-bottom:6px">
              Gancho / Tema
            </label>
            <textarea
              id="ttb-ed-gancho"
              name="ed_gancho"
              class="ttb-textarea"
              style="min-height:90px"
              placeholder="Ej: ¿Te pueden conceder una hipoteca sin tener el 20% ahorrado?"
            ></textarea>
          </div>

          <div class="ttb-actions" style="margin-top:18px;justify-content:space-between">
            <button
              type="button"
              id="ttb-ed-delete-btn"
              class="ttb-btn ttb-btn--danger"
              style="display:none"
              onclick="ttbEdConfirmDelete()"
            >🗑️ Eliminar</button>
            <div style="display:flex;gap:8px">
              <button type="button" class="ttb-btn ttb-btn--ghost" onclick="ttbEdCloseModal()">Cancelar</button>
              <button type="submit" class="ttb-btn">Guardar</button>
            </div>
          </div>
        </form>

        <!-- Formulario oculto para eliminar -->
        <form method="post" action="<?php echo esc_url($action_url); ?>" id="ttb-ed-delete-form" style="display:none">
          <?php wp_nonce_field('ttb_editorial_delete_entry'); ?>
          <input type="hidden" name="ttb_editorial_delete_entry" value="1">
          <input type="hidden" name="ed_entry_id"  id="ttb-ed-del-id"    value="">
          <input type="hidden" name="ed_client_id" id="ttb-ed-del-cid"   value="<?php echo (int)$client_id; ?>">
          <input type="hidden" name="ed_month"     value="<?php echo esc_attr($filter_month); ?>">
        </form>
      </div>
    </div>

    <script>
    function ttbEdConfirmDelete() {
      if (!confirm('¿Eliminar esta entrada del calendario editorial?')) return;
      var entryId = document.getElementById('ttb-ed-entry-id').value;
      var delForm = document.getElementById('ttb-ed-delete-form');
      document.getElementById('ttb-ed-del-id').value  = entryId;
      delForm.submit();
    }
    </script>
    <?php
  }

  /* ════════════════════════════════
     ACCIONES POST
  ════════════════════════════════ */

  private static function handle_save_entry() {
    if (!isset($_POST['ttb_editorial_save_entry'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_editorial_save_entry')) return;

    $entry_id  = (int)($_POST['ed_entry_id']  ?? 0);
    $client_id = (int)($_POST['ed_client_id'] ?? 0);
    $date      = sanitize_text_field($_POST['ed_entry_date'] ?? '');
    $pilar     = sanitize_text_field($_POST['ed_pilar']      ?? '');
    $gancho    = sanitize_textarea_field($_POST['ed_gancho'] ?? '');
    $month     = sanitize_text_field($_POST['ed_month']      ?? date('Y-m'));

    if (!$client_id || !$date || !$pilar) {
      self::$flash = ['type' => 'error', 'text' => 'El Pilar de Contenido es obligatorio.'];
      return;
    }

    global $wpdb;
    $table = TTB_Social_DB::editorial_table();

    if ($entry_id) {
      // Editar
      $wpdb->update($table, [
        'pilar'      => $pilar,
        'gancho'     => $gancho,
        'updated_at' => TTB_Social_DB::now(),
      ], ['id' => $entry_id, 'client_id' => $client_id]);
      self::$flash = ['type' => 'success', 'text' => 'Entrada actualizada.'];
    } else {
      // Nueva (upsert por client_id + entry_date)
      $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE client_id=%d AND entry_date=%s LIMIT 1",
        $client_id, $date
      ));
      if ($existing) {
        $wpdb->update($table, [
          'pilar'      => $pilar,
          'gancho'     => $gancho,
          'updated_at' => TTB_Social_DB::now(),
        ], ['id' => (int)$existing]);
        self::$flash = ['type' => 'success', 'text' => 'Entrada actualizada.'];
      } else {
        $wpdb->insert($table, [
          'client_id'    => $client_id,
          'entry_date'   => $date,
          'pilar'        => $pilar,
          'gancho'       => $gancho,
          'month_status' => 'draft',
          'created_at'   => TTB_Social_DB::now(),
          'updated_at'   => TTB_Social_DB::now(),
        ]);
        self::$flash = ['type' => 'success', 'text' => 'Entrada añadida.'];
      }
    }

    // Redirect PRG preservando parámetros
    self::prg_redirect($client_id, $month);
  }

  private static function handle_delete_entry() {
    if (!isset($_POST['ttb_editorial_delete_entry'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_editorial_delete_entry')) return;

    $entry_id  = (int)($_POST['ed_entry_id']  ?? 0);
    $client_id = (int)($_POST['ed_client_id'] ?? 0);
    $month     = sanitize_text_field($_POST['ed_month'] ?? date('Y-m'));

    if (!$entry_id || !$client_id) return;

    global $wpdb;
    $wpdb->delete(TTB_Social_DB::editorial_table(), ['id' => $entry_id, 'client_id' => $client_id]);
    self::$flash = ['type' => 'success', 'text' => 'Entrada eliminada.'];
    self::prg_redirect($client_id, $month);
  }

  private static function handle_send_month() {
    if (!isset($_POST['ttb_editorial_send_month'])) return;
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_editorial_send_month')) return;

    $client_id    = (int)($_POST['ed_client_id']    ?? 0);
    $month        = sanitize_text_field($_POST['ed_month'] ?? date('Y-m'));
    $revert_draft = !empty($_POST['ed_revert_draft']);

    if (!$client_id) return;

    global $wpdb;
    $sc_table = TTB_Social_DB::clients_table();
    $client   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sc_table WHERE id=%d", $client_id));
    if (!$client) return;

    if ($revert_draft) {
      TTB_Social_DB::set_editorial_month_status($client_id, $month, 'draft');
      self::$flash = ['type' => 'success', 'text' => 'Mes vuelto a estado Borrador.'];
    } else {
      TTB_Social_DB::set_editorial_month_status($client_id, $month, 'sent');
      // Enviar email de aviso al cliente
      (new TTB_Social_Mailer())->send_editorial_month_ready($client, $month);
      TTB_Social_DB::log($client_id, null, 'editorial_month_sent', 'admin', ['month' => $month]);
      self::$flash = ['type' => 'success', 'text' => 'Calendario editorial enviado al cliente.'];
    }

    self::prg_redirect($client_id, $month);
  }

  /* ════════════════════════════════
     HELPERS
  ════════════════════════════════ */

  private static function action_url() {
    $sc = (int)($_GET['sc_client'] ?? 0);
    $params = ['section' => 'redes-sociales', 'sstab' => 'editorial'];
    if ($sc) $params['sc_client'] = $sc;
    return esc_url(home_url('/briefing?' . http_build_query($params)));
  }

  private static function nav_url($client_id, $sc) {
    $params = ['section' => 'redes-sociales', 'sstab' => 'editorial'];
    if ($sc) $params['sc_client'] = $sc;
    if (!$sc && $client_id) $params['ed_client'] = $client_id;
    return home_url('/briefing?' . http_build_query($params));
  }

  private static function prg_redirect($client_id, $month) {
    $sc = (int)($_GET['sc_client'] ?? 0);
    $params = ['section' => 'redes-sociales', 'sstab' => 'editorial', 'ed_month' => $month];
    if ($sc) $params['sc_client'] = $sc;
    if (!$sc && $client_id) $params['ed_client'] = $client_id;

    set_transient('ttb_admin_flash_editorial', self::$flash, 60);
    self::$flash = null;

    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) {
      header('Location: ' . esc_url_raw(home_url('/briefing?' . http_build_query($params))), true, 302);
      exit;
    }
    echo '<script>window.location.replace(' . wp_json_encode(home_url('/briefing?' . http_build_query($params))) . ');</script>';
    exit;
  }
}