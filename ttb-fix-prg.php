<?php
/**
 * ttb-fix-prg-v2.php
 * 
 * Segunda pasada: añade flash_and_redirect() y corrige los handlers.
 * El script anterior (v1) ya aplicó los cambios A, B y C.
 * Este aplica el cambio D: que cada handler haga redirect + exit.
 * 
 * Sube este archivo a la carpeta del plugin y accede UNA VEZ a:
 * https://tictac-comunicacion.es/wp-content/plugins/tictac-briefing-portal/ttb-fix-prg-v2.php?key=ttb-fix-2026
 * Después, BÓRRALO.
 */

$secret = 'ttb-fix-2026';
if (($_GET['key'] ?? '') !== $secret) {
    die('Acceso denegado. Añade ?key=' . $secret . ' a la URL.');
}

$plugin_dir = __DIR__ . '/includes/';

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>TTB Fix PRG v2</title>';
echo '<style>body{font-family:monospace;padding:20px;background:#f4f5f7} .ok{color:green} .err{color:red} .warn{color:orange}</style></head><body>';
echo '<h1>TTB Fix PRG v2 — Corrigiendo handlers</h1>';

// ══════════════════════════════════════════════════════════════════════════════
// Configuración de cada archivo
// ══════════════════════════════════════════════════════════════════════════════
$files = [
  'class-webprog-admin.php' => [
    'transient' => 'ttb_webprog_admin_flash',
    'section'   => 'revisiones-web',
    'tab_param' => 'wptab',
    // Cada entrada: [texto a buscar al final del handler, tab destino]
    'patterns'  => [
      // Error create
      ["'error', 'Nombre, al menos un email y la URL de la web son obligatorios.'", 'projects'],
      // Success create
      ["'success', 'Proyecto creado y email enviado.'", 'projects'],
      // Error edit
      ["'error', 'Todos los campos son obligatorios.'", 'projects'],
      // Success edit
      ["'success', 'Proyecto actualizado.'", 'projects'],
      // Success delete
      ["'success', 'Proyecto eliminado.'", 'projects'],
      // Success resend
      ["'success', 'Email reenviado.'", 'projects'],
      // Success settings
      ["'success', 'Configuración guardada.'", 'settings'],
    ],
  ],
  'class-webrev-admin.php' => [
    'transient' => 'ttb_webrev_admin_flash',
    'section'   => 'revisiones-dis',
    'tab_param' => 'wrtab',
    'patterns'  => [
      ["'error', 'Nombre, al menos un email y el enlace Figma (desktop) son obligatorios.'", 'projects'],
      ["'success', 'Proyecto creado y email enviado.'", 'projects'],
      ["'error', 'Nombre, emails y el enlace Figma (desktop) son obligatorios.'", 'projects'],
      ["'success', 'Proyecto actualizado.'", 'projects'],
      ["'success', 'Proyecto eliminado.'", 'projects'],
      ["'success', 'Email reenviado.'", 'projects'],
      ["'success', 'Configuración guardada.'", 'settings'],
    ],
  ],
  'class-social-admin.php' => [
    'transient' => 'ttb_social_admin_flash',
    'section'   => 'redes-sociales',
    'tab_param' => 'sstab',
    'patterns'  => [
      ["'error', 'Nombre y al menos un email son obligatorios.'", 'clients'],
      ["'success', 'Cliente creado y email de bienvenida enviado.'", 'clients'],
      ["'error', 'Todos los campos obligatorios son necesarios.'", 'clients'],
      ["'success', 'Cliente actualizado.'", 'clients'],
      ["'success', 'Cliente eliminado.'", 'clients'],
      ["'success', 'Email de acceso reenviado.'", 'clients'],
      ["'error', 'Cliente y fecha son obligatorios.'", 'calendar'],
      ["'success', 'Publicación creada y notificación enviada al cliente.'", 'calendar'],
      ["'error', 'Datos incompletos.'", 'calendar'],
      ["'success', 'Post eliminado.'", 'calendar'],
      ["'success', 'Estado actualizado.'", 'calendar'],
      ["'success', 'Archivo eliminado.'", 'content'],
      ["'success', 'Configuración guardada.'", 'settings'],
    ],
  ],
  'class-admin-ui.php' => [
    'transient' => 'ttb_admin_flash',
    'section'   => 'briefings',
    'tab_param' => 'tab',
    'patterns'  => [
      ["'error', 'Nombre y email son obligatorios.'", 'clients'],
      ["'success', 'Cliente creado y email enviado.'", 'clients'],
      ["'success', 'Cliente actualizado correctamente.'", 'clients'],
      ["'success', 'Cliente eliminado.'", 'clients'],
      ["'success', 'Email reenviado.'", 'clients'],
      ["'success', 'Formularios guardados.'", 'forms'],
    ],
  ],
];

foreach ($files as $filename => $config) {
  $filepath = $plugin_dir . $filename;
  echo "<h2>{$filename}</h2>";

  if (!file_exists($filepath)) {
    echo "<p class='err'>❌ No encontrado</p>";
    continue;
  }

  $content = file_get_contents($filepath);

  // ── Paso 1: Añadir flash_and_redirect() si no existe ──
  if (strpos($content, 'function flash_and_redirect') === false) {
    $transient  = $config['transient'];
    $section    = $config['section'];
    $tab_param  = $config['tab_param'];
    $new_method = "\n  private static function flash_and_redirect(\$type, \$text, \$url = null) {\n"
                . "    set_transient('{$transient}', ['type' => \$type, 'text' => \$text], 60);\n"
                . "    if (!\$url) \$url = home_url('/briefing?section={$section}&{$tab_param}=projects');\n"
                . "    wp_safe_redirect(\$url);\n"
                . "    exit;\n"
                . "  }\n";

    // Insertar justo antes del método set_flash o del método render
    $insert_before = 'private static function set_flash(';
    $pos = strpos($content, $insert_before);
    if ($pos !== false) {
      // Retroceder hasta el inicio de la línea
      $line_start = strrpos(substr($content, 0, $pos), "\n") + 1;
      $content = substr($content, 0, $line_start) . $new_method . "\n" . substr($content, $line_start);
      echo "<p class='ok'>✅ Método flash_and_redirect() añadido</p>";
    } else {
      echo "<p class='warn'>⚠️ No se encontró set_flash para insertar antes</p>";
    }
  } else {
    echo "<p class='ok'>✅ flash_and_redirect() ya existe</p>";
  }

  // ── Paso 2: Corregir cada handler ──
  $section   = $config['section'];
  $tab_param = $config['tab_param'];
  $fixed     = 0;
  $not_found = [];

  foreach ($config['patterns'] as [$msg_args, $tab]) {
    $redirect_url = "home_url('/briefing?section={$section}&{$tab_param}={$tab}')";

    // Buscar el patrón: self::set_flash(MSG_ARGS); seguido de cualquier combinación de
    // $tab = '...'; y/o return; en las siguientes líneas
    // Regex: set_flash(MSG_ARGS) + optional whitespace + optional "$tab = 'X';" + optional "return;"
    $escaped_msg = preg_quote($msg_args, '/');

    // Pattern 1: set_flash(...); \n $tab = '...'; \n return;
    $pattern1 = '/(self::set_flash\(' . $escaped_msg . '\);)\s*\n(\s*\$tab\s*=\s*[\'"][^\'"]+[\'"];\s*\n)?(\s*return;\s*\n)?/';
    $replacement1 = "self::flash_and_redirect({$msg_args}, {$redirect_url});\n";

    $new_content = preg_replace($pattern1, $replacement1, $content, 1, $count);
    if ($count > 0) {
      $content = $new_content;
      $fixed++;
      continue;
    }

    // Pattern 2: set_flash(...); at end of method (no return, just $tab = ...)
    $pattern2 = '/(self::set_flash\(' . $escaped_msg . '\);)\s*\n(\s*\$tab\s*=\s*[\'"][^\'"]+[\'"];\s*)/';
    $replacement2 = "self::flash_and_redirect({$msg_args}, {$redirect_url});\n    ";

    $new_content = preg_replace($pattern2, $replacement2, $content, 1, $count);
    if ($count > 0) {
      $content = $new_content;
      $fixed++;
      continue;
    }

    $not_found[] = $msg_args;
  }

  echo "<p>Handlers corregidos: {$fixed} / " . count($config['patterns']) . "</p>";

  if (!empty($not_found)) {
    echo "<p class='warn'>⚠️ No encontrados:</p><ul>";
    foreach ($not_found as $nf) {
      echo "<li style='font-size:12px'>{$nf}</li>";
    }
    echo "</ul>";
  }

  // ── Paso 3: Guardar ──
  $backup = $filepath . '.bak-v2-' . date('Ymd-His');
  copy($filepath, $backup);

  if (file_put_contents($filepath, $content) !== false) {
    echo "<p class='ok'>✅ Guardado (backup: " . basename($backup) . ")</p>";
  } else {
    copy($backup, $filepath);
    echo "<p class='err'>❌ Error al guardar — backup restaurado</p>";
  }
}

// ── Caso especial: class-social-admin tiene un handler con string dinámico ──
// 'success', 'Post actualizado.' . ($renotify ? '...' : '')
// Ese no se puede parchear con regex simple — lo hacemos manualmente
$social_file = $plugin_dir . 'class-social-admin.php';
if (file_exists($social_file)) {
  $content = file_get_contents($social_file);

  $old = "self::set_flash('success', 'Post actualizado.' . (\$renotify ? ' Notificación reenviada al cliente.' : ''));";
  $new = "self::flash_and_redirect('success', 'Post actualizado.' . (\$renotify ? ' Notificación reenviada al cliente.' : ''), home_url('/briefing?section=redes-sociales&sstab=calendar'));";

  if (strpos($content, $old) !== false && strpos($content, $new) === false) {
    $content = str_replace($old, $new, $content);
    file_put_contents($social_file, $content);
    echo "<h3>class-social-admin.php (handler especial)</h3>";
    echo "<p class='ok'>✅ Handler 'Post actualizado' corregido</p>";
  }
}

echo '<hr><p><strong style="color:red">⚠️ Borra este archivo del servidor.</strong></p>';
echo '</body></html>';