<?php
if (!defined('ABSPATH')) exit;

$auth      = new TTB_Auth();
$client_id = $auth->client_id();

// Recuperar errores y valores temporales (solo si hubo un envío fallido)
$state     = TTB_Forms::consume_form_state($client_id, $svc);
$errors    = $state['errors'];  // [ field_id => 'mensaje de error' ]
$tmp       = $state['values'];  // valores que el usuario había escrito

// Si hay valores temporales los usamos; si no, los guardados en BD
$display_answers = $tmp ?: $answers;

$sent_badge = $sent
  ? '<span class="ttb-pill">ENVIADO</span>'
  : '<span class="ttb-pill ttb-pill--draft">NO ENVIADO</span>';
?>
<div class="ttb-card<?php echo $errors ? ' ttb-card--has-errors' : ''; ?>">
  <div class="ttb-formhead">
    <h3><?php echo esc_html($title); ?> <?php echo $sent_badge; // phpcs:ignore ?></h3>
    <p class="ttb-muted">Los campos con * son obligatorios.</p>
  </div>

  <form method="post" action="<?php echo esc_url(home_url('/briefing')); ?>" class="ttb-formgrid" novalidate>
    <?php wp_nonce_field('ttb_form_'.$svc); ?>
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
        $field_cls = $err ? ' ttb-field--error' : '';
      ?>
      <div class="ttb-field<?php echo $field_cls; ?>">
        <label for="ttbf_<?php echo esc_attr($id); ?>">
          <?php echo esc_html($label); ?><?php echo $required ? ' <span class="ttb-required" aria-hidden="true">*</span>' : ''; ?>
        </label>

        <?php if ($type === 'textarea'): ?>
          <textarea
            id="ttbf_<?php echo esc_attr($id); ?>"
            class="ttb-textarea<?php echo $err ? ' ttb-input--invalid' : ''; ?>"
            name="f[<?php echo esc_attr($id); ?>]"
            <?php echo $required ? 'required' : ''; ?>
            aria-describedby="<?php echo $err ? 'err_'.esc_attr($id) : ''; ?>"
          ><?php echo esc_textarea((string)$val); ?></textarea>

        <?php elseif ($type === 'select'): ?>
          <select
            id="ttbf_<?php echo esc_attr($id); ?>"
            class="ttb-input<?php echo $err ? ' ttb-input--invalid' : ''; ?>"
            name="f[<?php echo esc_attr($id); ?>]"
            <?php echo $required ? 'required' : ''; ?>
            aria-describedby="<?php echo $err ? 'err_'.esc_attr($id) : ''; ?>"
          >
            <option value="">— Selecciona —</option>
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
            aria-describedby="<?php echo $err ? 'err_'.esc_attr($id) : ''; ?>"
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
      <button class="ttb-btn ttb-btn--ghost" type="submit" name="submit_mode" value="save">Guardar</button>
      <button class="ttb-btn" type="submit" name="submit_mode" value="send">Enviar briefing</button>
    </div>
  </form>
</div>