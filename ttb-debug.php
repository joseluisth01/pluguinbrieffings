<?php
/**
 * TTB DEBUG — Sube este archivo a la raíz del plugin y visita:
 *   https://tictac-comunicacion.es/briefing?ttb_debug=1   (GET, ver estado actual)
 *   Luego intenta hacer login y vuelve a visitar la URL de debug.
 *
 * BORRA ESTE ARCHIVO cuando hayas terminado de diagnosticar.
 */

// Solo se activa si lo llaman directamente desde el plugin via include
// o si existe la query var ttb_debug en la URL.
if (!defined('ABSPATH')) {
  // Cargado directamente — cargar WP
  $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
  if (!file_exists($wp_load)) die('wp-load.php not found');
  require_once $wp_load;
}

if (!isset($_GET['ttb_debug'])) return;

// Solo admins de WP o desde localhost pueden ver esto
$allowed = (
  current_user_can('administrator') ||
  in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) ||
  ($_GET['ttb_debug_key'] ?? '') === 'tictac2024debug'
);
if (!$allowed) die('No autorizado. Añade &ttb_debug_key=tictac2024debug a la URL.');

header('Content-Type: text/html; charset=UTF-8');
nocache_headers();

$log_file = WP_CONTENT_DIR . '/ttb-debug.log';

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>TTB Debug</title>
<style>
  body { font-family: monospace; background: #0f0f0f; color: #d4d4d4; padding: 20px; font-size: 13px; }
  h2 { color: #e879f9; border-bottom: 1px solid #333; padding-bottom: 6px; }
  h3 { color: #38bdf8; margin-top: 24px; }
  .ok  { color: #4ade80; }
  .err { color: #f87171; }
  .warn { color: #fbbf24; }
  pre { background: #1a1a1a; border: 1px solid #333; padding: 12px; border-radius: 6px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
  table { border-collapse: collapse; width: 100%; }
  td, th { padding: 6px 10px; border: 1px solid #333; text-align: left; vertical-align: top; }
  th { background: #1a1a1a; color: #94a3b8; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
  .badge-ok  { background: #14532d; color: #4ade80; }
  .badge-err { background: #450a0a; color: #f87171; }
</style>
</head>
<body>
<h2>🔍 TTB Briefing Portal — Debug Panel</h2>
<p>Generado: <?= date('Y-m-d H:i:s') ?> | IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'n/a') ?></p>

<?php

// ── 1. Variables de entorno ───────────────────────────────────
echo '<h3>1. Entorno WordPress</h3><table>';
$rows = [
  'WP Version'         => get_bloginfo('version'),
  'PHP Version'        => phpversion(),
  'home_url'           => home_url(),
  'site_url'           => site_url(),
  'WP_DEBUG'           => defined('WP_DEBUG') && WP_DEBUG ? '<span class="ok">true</span>' : 'false',
  'WP_DEBUG_LOG'       => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? '<span class="ok">true</span>' : '<span class="warn">false</span>',
  'HTTPS detectado'    => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strpos(home_url(), 'https://') === 0) ? '<span class="ok">Sí</span>' : '<span class="warn">No</span>',
  'REQUEST_URI'        => htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'n/a'),
  'REQUEST_METHOD'     => $_SERVER['REQUEST_METHOD'] ?? 'n/a',
  'headers_sent()'     => headers_sent($file, $line) ? "<span class=\"err\">SÍ (en $file línea $line)</span>" : '<span class="ok">No</span>',
];
foreach ($rows as $k => $v) {
  echo "<tr><th>$k</th><td>$v</td></tr>";
}
echo '</table>';

// ── 2. Plugin cargado ─────────────────────────────────────────
echo '<h3>2. Estado del Plugin TTB</h3><table>';

$plugin_file = dirname(__FILE__) . '/tictac-briefing-portal.php';
$plugin_exists = file_exists($plugin_file);
echo '<tr><th>Archivo principal</th><td>' . ($plugin_exists ? '<span class="ok">Existe</span>' : '<span class="err">NO ENCONTRADO</span>') . '</td></tr>';

$classes = ['TTB_Auth', 'TTB_Router', 'TTB_Forms', 'TTB_DB', 'TTB_Mailer', 'TTB_Drive', 'TTB_Admin_UI', 'TTB_Client_UI'];
foreach ($classes as $cls) {
  $ok = class_exists($cls);
  echo "<tr><th>class $cls</th><td>" . ($ok ? '<span class="ok">✓ cargada</span>' : '<span class="err">✗ NO cargada</span>') . "</td></tr>";
}

// Opciones de BD
$admin_user = get_option('ttb_admin_user', '');
$admin_hash = get_option('ttb_admin_pass_hash', '');
$secret_key = get_option('ttb_secret_key', '');
echo '<tr><th>ttb_admin_user</th><td>' . ($admin_user ? '<span class="ok">' . esc_html($admin_user) . '</span>' : '<span class="err">VACÍO — plugin no activado correctamente</span>') . '</td></tr>';
echo '<tr><th>ttb_admin_pass_hash</th><td>' . ($admin_hash ? '<span class="ok">Existe (' . strlen($admin_hash) . ' chars)</span>' : '<span class="err">VACÍO</span>') . '</td></tr>';
echo '<tr><th>ttb_secret_key</th><td>' . ($secret_key ? '<span class="ok">Existe (' . strlen($secret_key) . ' chars)</span>' : '<span class="err">VACÍO</span>') . '</td></tr>';

// Verificar hash
if ($admin_hash) {
  $test = password_verify('Sipilu2019', $admin_hash);
  echo '<tr><th>Hash verifica "Sipilu2019"</th><td>' . ($test ? '<span class="ok">✓ SÍ</span>' : '<span class="err">✗ NO — contraseña cambiada o hash corrupto</span>') . '</td></tr>';
}

echo '</table>';

// ── 3. Tablas de BD ───────────────────────────────────────────
echo '<h3>3. Base de Datos</h3><table>';
global $wpdb;
$clients_table = $wpdb->prefix . 'ttb_clients';
$answers_table = $wpdb->prefix . 'ttb_answers';

$tables_exist = $wpdb->get_var("SHOW TABLES LIKE '$clients_table'") === $clients_table;
echo '<tr><th>' . $clients_table . '</th><td>' . ($tables_exist ? '<span class="ok">✓ Existe</span>' : '<span class="err">✗ NO EXISTE — desactiva y reactiva el plugin</span>') . '</td></tr>';
$answers_exist = $wpdb->get_var("SHOW TABLES LIKE '$answers_table'") === $answers_table;
echo '<tr><th>' . $answers_table . '</th><td>' . ($answers_exist ? '<span class="ok">✓ Existe</span>' : '<span class="err">✗ NO EXISTE</span>') . '</td></tr>';

if ($tables_exist) {
  $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $clients_table");
  echo "<tr><th>Clientes en BD</th><td>$count</td></tr>";
}
echo '</table>';

// ── 4. Rewrite rules ─────────────────────────────────────────
echo '<h3>4. Rewrite Rules (relevantes)</h3>';
$rules = get_option('rewrite_rules', []);
$briefing_rules = array_filter((array)$rules, function($v) {
  return strpos($v, 'ttb_portal') !== false;
});
if ($briefing_rules) {
  echo '<pre class="ok">' . print_r($briefing_rules, true) . '</pre>';
} else {
  echo '<pre class="err">⚠ No hay reglas de rewrite para ttb_portal. Ve a Ajustes → Enlaces permanentes y guarda.</pre>';
}

// ── 5. Query vars actuales ────────────────────────────────────
echo '<h3>5. Query Vars actuales</h3>';
echo '<pre>' . htmlspecialchars(print_r($GLOBALS['wp']->query_vars ?? [], true)) . '</pre>';

// ── 6. Cookie de sesión TTB ───────────────────────────────────
echo '<h3>6. Cookie de Sesión TTB</h3><table>';
$cookie_raw = $_COOKIE['ttb_session'] ?? '';
if ($cookie_raw) {
  echo '<tr><th>Cookie existe</th><td><span class="ok">Sí</span></td></tr>';
  $parts = explode('.', $cookie_raw);
  if (count($parts) === 2) {
    $payload = base64_decode($parts[0]);
    $data = json_decode($payload, true);
    echo '<tr><th>Payload decodificado</th><td><pre>' . htmlspecialchars(print_r($data, true)) . '</pre></td></tr>';
    if (!empty($data['exp'])) {
      $expires_in = $data['exp'] - time();
      echo '<tr><th>Expira en</th><td>' . ($expires_in > 0 ? round($expires_in/3600, 1) . ' horas' : '<span class="err">EXPIRADA</span>') . '</td></tr>';
    }
    // Verificar firma
    if ($secret_key) {
      $expected = hash_hmac('sha256', $parts[0], $secret_key);
      $valid = hash_equals($expected, $parts[1]);
      echo '<tr><th>Firma válida</th><td>' . ($valid ? '<span class="ok">✓ Sí</span>' : '<span class="err">✗ No — secret_key cambiada?</span>') . '</td></tr>';
    }
  }
} else {
  echo '<tr><th>Cookie existe</th><td><span class="warn">No hay cookie de sesión TTB (no logueado)</span></td></tr>';
}
echo '</table>';

// ── 7. Flash transient ────────────────────────────────────────
echo '<h3>7. Flash Transient (mensaje pendiente)</h3>';
$flash = get_transient('ttb_flash');
if ($flash) {
  echo '<pre class="' . ($flash['type'] === 'error' ? 'err' : 'ok') . '">' . htmlspecialchars(print_r($flash, true)) . '</pre>';
  echo '<p><a href="?ttb_debug=1&ttb_debug_key=' . ($_GET['ttb_debug_key'] ?? '') . '&clear_flash=1" style="color:#f87171">Limpiar flash</a></p>';
  if (isset($_GET['clear_flash'])) delete_transient('ttb_flash');
} else {
  echo '<pre>No hay flash pendiente.</pre>';
}

// ── 8. Plugins activos ────────────────────────────────────────
echo '<h3>8. Plugins Activos (posibles conflictos)</h3>';
$active = get_option('active_plugins', []);
$suspicious = ['force-login', 'password-protected', 'wpmembers', 'wp-members', 'restrict', 'lock', 'maintenance', 'coming-soon', 'under-construction', 'jetpack'];
echo '<table><tr><th>Plugin</th><th>¿Posible conflicto?</th></tr>';
foreach ($active as $p) {
  $is_suspicious = false;
  foreach ($suspicious as $s) {
    if (stripos($p, $s) !== false) { $is_suspicious = true; break; }
  }
  $flag = $is_suspicious ? '<span class="warn">⚠ Revisar</span>' : '';
  echo "<tr><td>" . esc_html($p) . "</td><td>$flag</td></tr>";
}
echo '</table>';

// ── 9. Hooks en 'init' ────────────────────────────────────────
echo '<h3>9. Hooks registrados en "init" (prioridades)</h3>';
global $wp_filter;
if (!empty($wp_filter['init'])) {
  $init_hooks = [];
  foreach ($wp_filter['init']->callbacks as $priority => $callbacks) {
    foreach ($callbacks as $cb) {
      $fn = $cb['function'];
      $name = is_array($fn)
        ? (is_object($fn[0]) ? get_class($fn[0]) : $fn[0]) . '::' . $fn[1]
        : (is_string($fn) ? $fn : '[closure]');
      $init_hooks[] = ['priority' => $priority, 'callback' => $name];
    }
  }
  usort($init_hooks, fn($a,$b) => $a['priority'] <=> $b['priority']);
  echo '<table><tr><th>Prioridad</th><th>Callback</th></tr>';
  foreach ($init_hooks as $h) {
    $highlight = (stripos($h['callback'], 'ttb') !== false || stripos($h['callback'], 'login') !== false || stripos($h['callback'], 'redirect') !== false) ? ' style="background:#1c1c00"' : '';
    echo "<tr$highlight><td>{$h['priority']}</td><td>" . htmlspecialchars($h['callback']) . "</td></tr>";
  }
  echo '</table>';
}

// ── 10. Simular login ─────────────────────────────────────────
echo '<h3>10. Test Manual de Login</h3>';
echo '<form method="post" action="' . home_url('/briefing') . '" style="background:#1a1a1a;padding:16px;border-radius:8px;max-width:400px">';
echo '<p style="color:#94a3b8;margin:0 0 12px">Envía un POST de prueba al portal para ver qué pasa:</p>';
echo '<input type="text" name="username" value="tictac" style="width:100%;padding:8px;margin-bottom:8px;background:#0f0f0f;border:1px solid #333;color:#fff;border-radius:4px"><br>';
echo '<input type="password" name="password" value="Sipilu2019" style="width:100%;padding:8px;margin-bottom:8px;background:#0f0f0f;border:1px solid #333;color:#fff;border-radius:4px"><br>';
echo '<input type="hidden" name="ttb_login" value="1">';
echo '<button type="submit" style="background:#D72173;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-weight:bold">Probar Login →</button>';
echo '</form>';

// ── 11. Log reciente ──────────────────────────────────────────
echo '<h3>11. Log de errores PHP reciente</h3>';
$php_log = ini_get('error_log');
$wp_log  = WP_CONTENT_DIR . '/debug.log';
foreach ([$wp_log, $php_log] as $lf) {
  if ($lf && file_exists($lf) && is_readable($lf)) {
    $lines = array_slice(file($lf), -60);
    $relevant = array_filter($lines, fn($l) => stripos($l, 'ttb') !== false || stripos($l, 'briefing') !== false || stripos($l, 'redirect') !== false);
    if ($relevant) {
      echo "<p style='color:#94a3b8'>Desde: <code>$lf</code></p>";
      echo '<pre>' . htmlspecialchars(implode('', $relevant)) . '</pre>';
    } else {
      echo "<p style='color:#555'>$lf — sin líneas relevantes de TTB.</p>";
    }
    break;
  }
}

// ── 12. $_POST y $_SERVER del último request ──────────────────
echo '<h3>12. $_POST actual</h3>';
$safe_post = $_POST;
if (isset($safe_post['password'])) $safe_post['password'] = '***';
echo '<pre>' . htmlspecialchars(print_r($safe_post, true)) . '</pre>';

echo '<h3>13. Cabeceras HTTP enviadas</h3>';
$sent_headers = headers_list();
echo '<pre>' . htmlspecialchars(implode("\n", $sent_headers)) . '</pre>';

?>
<p style="color:#555;margin-top:40px">⚠ Recuerda eliminar <code>ttb-debug.php</code> cuando termines.</p>
</body>
</html>
<?php exit;