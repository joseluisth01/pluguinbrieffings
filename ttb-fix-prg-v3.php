<?php
/**
 * ttb-fix-prg-v3.php
 *
 * Corrige el método flash_and_redirect() en los 4 archivos admin.
 * El problema: wp_safe_redirect() falla porque el HTML ya empezó a enviarse.
 * La solución: usar JavaScript redirect en su lugar.
 *
 * URL: .../tictac-briefing-portal/ttb-fix-prg-v3.php?key=ttb-fix-2026
 * Borra el archivo después de ejecutarlo.
 */

$secret = 'ttb-fix-2026';
if (($_GET['key'] ?? '') !== $secret) {
    die('Acceso denegado.');
}

$plugin_dir = __DIR__ . '/includes/';

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>TTB Fix PRG v3</title>';
echo '<style>body{font-family:monospace;padding:20px;background:#f4f5f7} .ok{color:green} .err{color:red} .warn{color:orange}</style></head><body>';
echo '<h1>TTB Fix PRG v3 — Corrigiendo flash_and_redirect()</h1>';

$files = [
    'class-webprog-admin.php' => 'ttb_webprog_admin_flash',
    'class-webrev-admin.php'  => 'ttb_webrev_admin_flash',
    'class-social-admin.php'  => 'ttb_social_admin_flash',
    'class-admin-ui.php'      => 'ttb_admin_flash',
];

// El método correcto que usa JS redirect en lugar de wp_safe_redirect
$correct_method_template = '  private static function flash_and_redirect($type, $text, $url = null) {
    set_transient(\'%s\', [\'type\' => $type, \'text\' => $text], 60);
    if (!$url) $url = home_url(\'/briefing\');
    echo \'<script>window.location.replace(\' . json_encode($url) . \');</script>\';
    exit;
  }';

foreach ($files as $filename => $transient_key) {
    $filepath = $plugin_dir . $filename;
    echo "<h2>{$filename}</h2>";

    if (!file_exists($filepath)) {
        echo "<p class='err'>❌ No encontrado</p>";
        continue;
    }

    $content = file_get_contents($filepath);

    // Comprobar si flash_and_redirect existe
    if (strpos($content, 'function flash_and_redirect') === false) {
        echo "<p class='err'>❌ flash_and_redirect() no existe en este archivo — ejecuta v2 primero</p>";
        continue;
    }

    // Reemplazar CUALQUIER versión del método flash_and_redirect con la correcta (JS redirect)
    $correct_method = sprintf($correct_method_template, $transient_key);

    // Regex que captura el método completo independientemente de su contenido actual
    $pattern = '/private static function flash_and_redirect\(\$type,\s*\$text,\s*\$url\s*=\s*null\)\s*\{[^}]+\}/s';

    $new_content = preg_replace($pattern, $correct_method, $content, 1, $count);

    if ($count === 0) {
        echo "<p class='err'>❌ No se pudo encontrar el método con regex</p>";
        // Intentar búsqueda más simple
        $start = strpos($content, 'private static function flash_and_redirect(');
        if ($start !== false) {
            // Encontrar el cierre del método contando llaves
            $brace_count = 0;
            $method_start = $start;
            $in_method = false;
            for ($i = $start; $i < strlen($content); $i++) {
                if ($content[$i] === '{') { $brace_count++; $in_method = true; }
                if ($content[$i] === '}') { $brace_count--; }
                if ($in_method && $brace_count === 0) {
                    $method_end = $i + 1;
                    break;
                }
            }
            if (isset($method_end)) {
                $new_content = substr($content, 0, $method_start) . $correct_method . substr($content, $method_end);
                $count = 1;
                echo "<p class='ok'>✅ Método encontrado con búsqueda manual</p>";
            }
        }
    }

    if ($count > 0) {
        $backup = $filepath . '.bak-v3-' . date('Ymd-His');
        copy($filepath, $backup);

        if (file_put_contents($filepath, $new_content) !== false) {
            echo "<p class='ok'>✅ flash_and_redirect() corregido para usar JS redirect</p>";
            echo "<p>Backup: " . basename($backup) . "</p>";
        } else {
            copy($backup, $filepath);
            echo "<p class='err'>❌ Error al guardar</p>";
        }
    } else {
        echo "<p class='err'>❌ No se pudo reemplazar el método</p>";
    }
}

echo '<hr>';
echo '<h2>¿Qué cambió?</h2>';
echo '<p>Antes: <code>wp_safe_redirect($url); exit;</code> — fallaba porque el HTML ya había empezado a enviarse.</p>';
echo '<p>Ahora: <code>echo \'&lt;script&gt;window.location.replace(url);&lt;/script&gt;\'; exit;</code> — funciona siempre.</p>';
echo '<p><strong style="color:red">⚠️ Borra este archivo del servidor.</strong></p>';
echo '</body></html>';