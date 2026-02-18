<?php
if (!defined('ABSPATH')) exit;

$sent_badge = $sent ? '<span class="ttb-pill">ENVIADO</span>' : '<span class="ttb-pill ttb-pill--draft">NO ENVIADO</span>';
?>
<div class="ttb-card">
  <div class="ttb-formhead">
    <h3><?php echo esc_html($title); ?> <?php echo $sent_badge; // phpcs:ignore ?></h3>
    <p class="ttb-muted">Los campos con * son obligatorios.</p>
  </div>

  <form method="post" action="<?php echo esc_url(home_url('/briefing')); ?>" class="ttb-formgrid">
    <?php wp_nonce_field('ttb_form_'.$svc); ?>
    <input type="hidden" name="ttb_save_form" value="1">
    <input type="hidden" name="service" value="<?php echo esc_attr($svc); ?>">

    <?php foreach ($schema as $f): ?>
      <?php
        $id = $f['id'] ?? ''; if (!$id) continue;
        $label = $f['label'] ?? $id;
        $type = $f['type'] ?? 'text';
        $required = !empty($f['required']);
        $options = $f['options'] ?? [];
        $val = $answers[$id] ?? '';
      ?>
      <div class="ttb-field">
        <label><?php echo esc_html($label); ?><?php echo $required ? ' *' : ''; ?></label>

        <?php if ($type === 'textarea'): ?>
          <textarea class="ttb-textarea" name="f[<?php echo esc_attr($id); ?>]" <?php echo $required ? 'required' : ''; ?>><?php echo esc_textarea((string)$val); ?></textarea>

        <?php elseif ($type === 'select'): ?>
          <select class="ttb-input" name="f[<?php echo esc_attr($id); ?>]" <?php echo $required ? 'required' : ''; ?>>
            <option value="">— Selecciona —</option>
            <?php foreach ((array)$options as $opt): ?>
              <option value="<?php echo esc_attr($opt); ?>" <?php selected((string)$val, (string)$opt); ?>><?php echo esc_html($opt); ?></option>
            <?php endforeach; ?>
          </select>

        <?php else: ?>
          <input class="ttb-input" type="<?php echo esc_attr($type); ?>" name="f[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr((string)$val); ?>" <?php echo $required ? 'required' : ''; ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="ttb-actions">
      <button class="ttb-btn ttb-btn--ghost" type="submit" name="submit_mode" value="save">Guardar</button>
      <button class="ttb-btn" type="submit" name="submit_mode" value="send">Enviar briefing</button>
    </div>
  </form>
</div>
