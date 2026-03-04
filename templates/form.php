<?php
if (!defined('ABSPATH')) exit;

$auth      = new TTB_Auth();
$client_id = $auth->client_id();

if (!isset($lang)) {
  $lang = TTB_Forms::get_client_lang($client_id);
}

$state   = TTB_Forms::consume_form_state($client_id, $svc);
$errors  = $state['errors'];
$tmp     = $state['values'];

$display_answers = $tmp ?: $answers;

$lbl_required  = $lang === 'en' ? 'Required fields are marked with *' : 'Los campos con * son obligatorios.';
$lbl_save      = $lang === 'en' ? 'Save' : 'Guardar';
$lbl_send      = $lang === 'en' ? 'Submit briefing' : 'Enviar briefing';
$lbl_select    = $lang === 'en' ? '— Select —' : '— Selecciona —';
$pill_sent     = $lang === 'en' ? 'SUBMITTED' : 'ENVIADO';
$pill_draft    = $lang === 'en' ? 'NOT SUBMITTED' : 'NO ENVIADO';
$lbl_collapse  = $lang === 'en' ? 'Show form' : 'Ver formulario';
$lbl_expand    = $lang === 'en' ? 'Hide form' : 'Ocultar formulario';
$lbl_sending   = $lang === 'en' ? 'Sending...' : 'Enviando...';
$lbl_saving    = $lang === 'en' ? 'Saving...' : 'Guardando...';
$lbl_completed = $lang === 'en' ? 'Completed ✓' : 'Completado ✓';

$sent_badge = $sent
  ? '<span class="ttb-pill">' . $pill_sent . '</span>'
  : '<span class="ttb-pill ttb-pill--draft">' . $pill_draft . '</span>';

// Si ya fue enviado Y no hay errores activos → empieza plegado
$starts_collapsed = $sent && empty($errors);
$form_id = 'ttb-form-' . esc_attr($svc);
?>

<style>
.ttb-form-wrapper {
  overflow: hidden;
  transition: max-height 0.45s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.35s ease;
}
.ttb-form-wrapper.collapsed {
  max-height: 0 !important;
  opacity: 0;
  pointer-events: none;
}
.ttb-form-wrapper.expanded {
  opacity: 1;
}

.ttb-collapse-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  color: var(--ttb-muted);
  padding: 0;
  margin-left: 10px;
  text-decoration: underline;
  transition: color 0.2s;
}
.ttb-collapse-btn:hover { color: var(--ttb-pink); }

.ttb-card-head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}
.ttb-card-head-left {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

/* Spinner overlay */
.ttb-sending-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(255,255,255,0.75);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  z-index: 99998;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 18px;
}
.ttb-sending-overlay.active {
  display: flex;
}
.ttb-spinner {
  width: 54px;
  height: 54px;
  border: 5px solid #f3d0e4;
  border-top-color: var(--ttb-pink);
  border-radius: 50%;
  animation: ttb-spin 0.8s linear infinite;
}
@keyframes ttb-spin {
  to { transform: rotate(360deg); }
}
.ttb-sending-label {
  font-size: 16px;
  font-weight: 800;
  color: var(--ttb-pink);
  letter-spacing: 0.02em;
}
</style>

<!-- Overlay de carga (uno global, reutilizable por todos los formularios) -->
<?php if (!defined('TTB_OVERLAY_RENDERED')): define('TTB_OVERLAY_RENDERED', true); ?>
<div class="ttb-sending-overlay" id="ttbSendingOverlay" aria-live="assertive" role="status">
  <div class="ttb-spinner"></div>
  <div class="ttb-sending-label" id="ttbSendingLabel"><?php echo esc_html($lbl_sending); ?></div>
</div>
<?php endif; ?>

<div class="ttb-card<?php echo $errors ? ' ttb-card--has-errors' : ''; ?>" id="<?php echo $form_id; ?>-card">
  <div class="ttb-formhead">
    <div class="ttb-card-head-row">
      <div class="ttb-card-head-left">
        <h3 style="margin:0"><?php echo esc_html($title); ?></h3>
        <?php echo $sent_badge; // phpcs:ignore ?>
      </div>
      <button
        type="button"
        class="ttb-collapse-btn"
        data-target="<?php echo $form_id; ?>-body"
        data-label-collapse="<?php echo esc_attr($lbl_collapse); ?>"
        data-label-expand="<?php echo esc_attr($lbl_expand); ?>"
        aria-expanded="<?php echo $starts_collapsed ? 'false' : 'true'; ?>"
      >
        <?php echo $starts_collapsed ? esc_html($lbl_collapse) : esc_html($lbl_expand); ?>
      </button>
    </div>
    <?php if (!$starts_collapsed): ?>
      <p class="ttb-muted" style="margin-top:6px"><?php echo esc_html($lbl_required); ?></p>
    <?php endif; ?>
  </div>

  <div
    class="ttb-form-wrapper <?php echo $starts_collapsed ? 'collapsed' : 'expanded'; ?>"
    id="<?php echo $form_id; ?>-body"
    style="max-height: <?php echo $starts_collapsed ? '0px' : '9999px'; ?>;"
  >
    <form
      method="post"
      action="<?php echo esc_url(home_url('/briefing')); ?>"
      class="ttb-formgrid"
      novalidate
      data-svc="<?php echo esc_attr($svc); ?>"
      data-label-sending="<?php echo esc_attr($lbl_sending); ?>"
      data-label-saving="<?php echo esc_attr($lbl_saving); ?>"
    >
      <?php wp_nonce_field('ttb_form_' . $svc); ?>
      <input type="hidden" name="ttb_save_form" value="1">
      <input type="hidden" name="service" value="<?php echo esc_attr($svc); ?>">

      <?php foreach ($schema as $f): ?>
        <?php
          $id       = $f['id'] ?? ''; if (!$id) continue;
          $label    = $f['label'] ?? $id;
          $type     = $f['type'] ?? 'text';
          $required = !empty($f['required']);
          $options  = $f['options'] ?? [];
          $val      = $display_answers[$id] ?? '';
          $err      = $errors[$id] ?? '';
        ?>
        <div class="ttb-field<?php echo $err ? ' ttb-field--error' : ''; ?>">
          <label for="ttbf_<?php echo esc_attr($id); ?>">
            <?php echo esc_html($label); ?><?php echo $required ? ' <span class="ttb-required" aria-hidden="true">*</span>' : ''; ?>
          </label>

          <?php if ($type === 'textarea'): ?>
            <textarea
              id="ttbf_<?php echo esc_attr($id); ?>"
              class="ttb-textarea<?php echo $err ? ' ttb-input--invalid' : ''; ?>"
              name="f[<?php echo esc_attr($id); ?>]"
              <?php echo $required ? 'required' : ''; ?>
              aria-describedby="<?php echo $err ? 'err_' . esc_attr($id) : ''; ?>"
            ><?php echo esc_textarea((string)$val); ?></textarea>

          <?php elseif ($type === 'select'): ?>
            <select
              id="ttbf_<?php echo esc_attr($id); ?>"
              class="ttb-input<?php echo $err ? ' ttb-input--invalid' : ''; ?>"
              name="f[<?php echo esc_attr($id); ?>]"
              <?php echo $required ? 'required' : ''; ?>
              aria-describedby="<?php echo $err ? 'err_' . esc_attr($id) : ''; ?>"
            >
              <option value=""><?php echo esc_html($lbl_select); ?></option>
              <?php foreach ((array)$options as $opt): ?>
                <option value="<?php echo esc_attr($opt); ?>" <?php selected((string)$val, (string)$opt); ?>>
                  <?php echo esc_html($opt); ?>
                </option>
              <?php endforeach; ?>
            </select>

          <?php else: ?>
            <input
              id="ttbf_<?php echo esc_attr($id); ?>"
              class="ttb-input<?php echo $err ? ' ttb-input--invalid' : ''; ?>"
              type="<?php echo esc_attr($type); ?>"
              name="f[<?php echo esc_attr($id); ?>]"
              value="<?php echo esc_attr((string)$val); ?>"
              <?php echo $required ? 'required' : ''; ?>
              aria-describedby="<?php echo $err ? 'err_' . esc_attr($id) : ''; ?>"
            >
          <?php endif; ?>

          <?php if ($err): ?>
            <span class="ttb-field-error" id="err_<?php echo esc_attr($id); ?>" role="alert">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="none" aria-hidden="true" style="vertical-align:-2px;margin-right:4px">
                <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/>
                <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <?php echo esc_html($err); ?>
            </span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="ttb-actions">
        <button class="ttb-btn ttb-btn--ghost" type="submit" name="submit_mode" value="save">
          <?php echo esc_html($lbl_save); ?>
        </button>
        <button class="ttb-btn" type="submit" name="submit_mode" value="send">
          <?php echo esc_html($lbl_send); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  // ── Colapsar / expandir ──────────────────────────────
  document.querySelectorAll('.ttb-collapse-btn').forEach(function(btn) {
    if (btn._ttbInit) return;
    btn._ttbInit = true;

    btn.addEventListener('click', function() {
      var targetId = btn.getAttribute('data-target');
      var wrapper  = document.getElementById(targetId);
      if (!wrapper) return;

      var isCollapsed = wrapper.classList.contains('collapsed');

      if (isCollapsed) {
        // Expandir
        wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
        wrapper.classList.remove('collapsed');
        wrapper.classList.add('expanded');
        btn.setAttribute('aria-expanded', 'true');
        btn.textContent = btn.getAttribute('data-label-expand');
        // Quitar max-height fijo una vez terminada la transición
        wrapper.addEventListener('transitionend', function onEnd() {
          wrapper.style.maxHeight = '9999px';
          wrapper.removeEventListener('transitionend', onEnd);
        });
      } else {
        // Plegar: primero fijar altura actual, luego animar a 0
        wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
        // Forzar reflow
        wrapper.getBoundingClientRect();
        wrapper.style.maxHeight = '0px';
        wrapper.classList.add('collapsed');
        wrapper.classList.remove('expanded');
        btn.setAttribute('aria-expanded', 'false');
        btn.textContent = btn.getAttribute('data-label-collapse');
      }
    });
  });

  // ── Spinner de envío ────────────────────────────────
  var overlay = document.getElementById('ttbSendingOverlay');
  var overlayLabel = document.getElementById('ttbSendingLabel');

  document.querySelectorAll('.ttb-formgrid').forEach(function(form) {
    if (form._ttbSpinInit) return;
    form._ttbSpinInit = true;

    // Capturar qué botón se pulsó antes del submit
    form.querySelectorAll('button[name="submit_mode"]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        form._ttbClickedMode = btn.getAttribute('value');
      });
    });

    form.addEventListener('submit', function(e) {
      var mode = form._ttbClickedMode || 'save';

      var labelSending = form.getAttribute('data-label-sending') || 'Enviando...';
      var labelSaving  = form.getAttribute('data-label-saving')  || 'Guardando...';

      overlayLabel.textContent = (mode === 'send') ? labelSending : labelSaving;
      overlay.classList.add('active');

      // Inyectar el valor como hidden ANTES de deshabilitar los botones
      var hidden = document.createElement('input');
      hidden.type  = 'hidden';
      hidden.name  = 'submit_mode';
      hidden.value = mode;
      form.appendChild(hidden);

      // Deshabilitar botones para evitar doble envío
      form.querySelectorAll('button[type="submit"]').forEach(function(b) {
        b.disabled = true;
      });
    });
  });
})();
</script>