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
$lbl_send      = $lang === 'en' ? 'Submit pre-briefing' : 'Enviar prebriefing';
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

/* ── Multicheck pills ── */
.ttb-multicheck-pill {
  transition: background .15s, border-color .15s, color .15s !important;
}
.ttb-multicheck-pill:hover {
  border-color: rgba(215,33,115,.35) !important;
  color: var(--ttb-pink) !important;
  background: rgba(215,33,115,.05) !important;
}
</style>

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

          <?php elseif ($type === 'multicheck'): ?>
            <?php
              // Normalizar el valor guardado: puede ser array, string CSV o string JSON
              if (is_array($val)) {
                $checked_vals = $val;
              } elseif (is_string($val) && $val !== '') {
                $decoded = json_decode($val, true);
                $checked_vals = is_array($decoded) ? $decoded : array_map('trim', explode(',', $val));
              } else {
                $checked_vals = [];
              }
            ?>
            <div
              class="ttb-multicheck<?php echo $err ? ' ttb-input--invalid' : ''; ?>"
              style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0 2px;"
              aria-describedby="<?php echo $err ? 'err_' . esc_attr($id) : ''; ?>"
              role="group"
              aria-labelledby="label_<?php echo esc_attr($id); ?>"
            >
              <?php foreach ((array)$options as $opt): ?>
                <?php $is_checked = in_array((string)$opt, array_map('strval', $checked_vals), true); ?>
                <label
                  class="ttb-multicheck-pill"
                  style="
                    display:inline-flex;
                    align-items:center;
                    gap:7px;
                    cursor:pointer;
                    background:<?php echo $is_checked ? 'rgba(215,33,115,.10)' : '#fff'; ?>;
                    border:1.5px solid <?php echo $is_checked ? 'rgba(215,33,115,.40)' : 'var(--ttb-border)'; ?>;
                    border-radius:999px;
                    padding:7px 16px;
                    font-size:13px;
                    font-weight:700;
                    color:<?php echo $is_checked ? 'var(--ttb-pink)' : 'var(--ttb-muted)'; ?>;
                    user-select:none;
                  "
                >
                  <input
                    type="checkbox"
                    name="f[<?php echo esc_attr($id); ?>][]"
                    value="<?php echo esc_attr($opt); ?>"
                    <?php checked($is_checked); ?>
                    style="display:none;"
                    class="ttb-multicheck-input"
                  >
                  <?php echo esc_html($opt); ?>
                </label>
              <?php endforeach; ?>
            </div>
            <p style="font-size:12px;color:var(--ttb-muted);margin:4px 0 0;">
              <?php echo $lang === 'en' ? 'You can select multiple options.' : 'Puedes seleccionar varias opciones.'; ?>
            </p>

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
  // ── Collapse / expand ──────────────────────────────────────
  document.querySelectorAll('.ttb-collapse-btn').forEach(function(btn) {
    if (btn._ttbInit) return;
    btn._ttbInit = true;

    btn.addEventListener('click', function() {
      var targetId = btn.getAttribute('data-target');
      var wrapper  = document.getElementById(targetId);
      if (!wrapper) return;

      var isCollapsed = wrapper.classList.contains('collapsed');

      if (isCollapsed) {
        wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
        wrapper.classList.remove('collapsed');
        wrapper.classList.add('expanded');
        btn.setAttribute('aria-expanded', 'true');
        btn.textContent = btn.getAttribute('data-label-expand');
        wrapper.addEventListener('transitionend', function onEnd() {
          wrapper.style.maxHeight = '9999px';
          wrapper.removeEventListener('transitionend', onEnd);
        });
      } else {
        wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
        wrapper.getBoundingClientRect();
        wrapper.style.maxHeight = '0px';
        wrapper.classList.add('collapsed');
        wrapper.classList.remove('expanded');
        btn.setAttribute('aria-expanded', 'false');
        btn.textContent = btn.getAttribute('data-label-collapse');
      }
    });
  });

  // ── Multicheck: toggle visual al hacer clic en la píldora ──
  document.querySelectorAll('.ttb-multicheck').forEach(function(container) {
    container.querySelectorAll('.ttb-multicheck-pill').forEach(function(pill) {
      var input = pill.querySelector('.ttb-multicheck-input');
      if (!input) return;

      function syncStyle() {
        if (input.checked) {
          pill.style.background    = 'rgba(215,33,115,.10)';
          pill.style.borderColor   = 'rgba(215,33,115,.40)';
          pill.style.color         = 'var(--ttb-pink)';
        } else {
          pill.style.background    = '#fff';
          pill.style.borderColor   = 'var(--ttb-border)';
          pill.style.color         = 'var(--ttb-muted)';
        }
      }

      // Sincronizar estado inicial (por si el PHP ya lo marcó)
      syncStyle();

      pill.addEventListener('click', function(e) {
        // El click en el label ya activa el checkbox nativo;
        // solo actualizamos el estilo visual tras el cambio.
        setTimeout(syncStyle, 0);
      });
    });
  });

  // ── Spinner de envío ───────────────────────────────────────
  var overlay = document.getElementById('ttbSendingOverlay');
  var overlayLabel = document.getElementById('ttbSendingLabel');

  document.querySelectorAll('.ttb-formgrid').forEach(function(form) {
    if (form._ttbSpinInit) return;
    form._ttbSpinInit = true;

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

      var hidden = document.createElement('input');
      hidden.type  = 'hidden';
      hidden.name  = 'submit_mode';
      hidden.value = mode;
      form.appendChild(hidden);

      form.querySelectorAll('button[type="submit"]').forEach(function(b) {
        b.disabled = true;
      });
    });
  });
})();
</script>