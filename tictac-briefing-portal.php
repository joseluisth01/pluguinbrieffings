<?php
/**
 * Plugin Name: TicTac Briefing Portal (Standalone)
 * Description: Portal /briefing con login independiente + admin frontend + clientes + formularios por servicio + Google Drive.
 * Version: 1.2.1
 * Author: TicTac Comunicación
 */

if (!defined('ABSPATH')) exit;

define('TTB_VERSION', '1.2.1');
define('TTB_PATH', plugin_dir_path(__FILE__));
define('TTB_URL',  plugin_dir_url(__FILE__));

require_once TTB_PATH . 'includes/class-db.php';
require_once TTB_PATH . 'includes/class-activator.php';
require_once TTB_PATH . 'includes/class-deactivator.php';
require_once TTB_PATH . 'includes/class-router.php';
require_once TTB_PATH . 'includes/class-auth.php';
require_once TTB_PATH . 'includes/class-forms.php';
require_once TTB_PATH . 'includes/class-mailer.php';
require_once TTB_PATH . 'includes/class-drive.php';
require_once TTB_PATH . 'includes/class-admin-ui.php';
require_once TTB_PATH . 'includes/class-client-ui.php';

register_activation_hook(__FILE__,   ['TTB_Activator',   'activate']);
register_deactivation_hook(__FILE__, ['TTB_Deactivator', 'deactivate']);

// ══════════════════════════════════════════════════════════════
// INTERCEPCIÓN TEMPRANA — se ejecuta en muplugins_loaded,
// antes de que CUALQUIER plugin pueda redirigir al login de WP.
// ══════════════════════════════════════════════════════════════

function ttb_is_briefing_request() {
  $uri  = $_SERVER['REQUEST_URI'] ?? '';
  $path = trim((string)parse_url($uri, PHP_URL_PATH), '/');
  // Acepta tanto /briefing como /briefing/
  return ($path === 'briefing');
}

if (ttb_is_briefing_request()) {

  // --- Desactivar plugins de "force login" conocidos ---
  add_filter('wpfl_bypass',                    '__return_true',  999);   // Force Login
  add_filter('password_protected_is_active',   '__return_false', 999);   // Password Protected
  add_filter('pda_pre_check_access',           '__return_false', 999);   // Prevent Direct Access
  add_filter('wpmem_login_redirect',           '__return_false', 999);   // WP-Members

  // Interceptar wp_redirect hacia wp-login.php
  add_filter('wp_redirect', function($location, $status) {
    if (strpos($location, 'wp-login.php') !== false) {
      return home_url('/briefing');
    }
    return $location;
  }, 1, 2);

  // --- Procesar el POST de login/logout MUY temprano ---
  // Lo hacemos aquí (en el propio archivo del plugin, fuera de cualquier hook)
  // para garantizar que se ejecuta antes que cualquier tema o plugin.
  add_action('muplugins_loaded', 'ttb_early_auth_handle', 1);
  add_action('plugins_loaded',   'ttb_early_auth_handle', 1);
}

function ttb_early_auth_handle() {
  // Evitar doble ejecución
  static $done = false;
  if ($done) return;
  $done = true;

  $uri = $_SERVER['REQUEST_URI'] ?? '';
  if (strpos($uri, '/briefing') === false) return;

  // ── Logout ──────────────────────────────────────────────────
  if (isset($_GET['ttb_logout'])) {
    $auth = new TTB_Auth();
    $auth->logout();
    ttb_redirect(home_url('/briefing'));
  }

  // ── Login POST ───────────────────────────────────────────────
  if (!isset($_POST['ttb_login'])) return;

  $u = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
  $p = isset($_POST['password']) ? (string)$_POST['password'] : '';

  $flash_key = 'ttb_flash';

  // Validación de campos vacíos
  if ($u === '' || $p === '') {
    $msg = ($u === '' && $p === '')
      ? 'Introduce tu usuario y contraseña.'
      : ($u === '' ? 'El campo usuario es obligatorio.' : 'El campo contraseña es obligatorio.');
    set_transient($flash_key, ['type' => 'error', 'text' => $msg], 60);
    ttb_redirect(home_url('/briefing'));
  }

  // Obtener secret para sesión
  $secret = get_option('ttb_secret_key');
  if (!$secret) {
    $secret = bin2hex(random_bytes(32));
    update_option('ttb_secret_key', $secret);
  }

  // Función interna para crear cookie de sesión
  $set_session = function($role, $client_id) use ($secret) {
    $is_https = (
      (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
      (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
      strpos(home_url(), 'https://') === 0
    );
    $data = [
      'role'      => $role,
      'client_id' => $client_id,
      'exp'       => time() + (60 * 60 * 8),
    ];
    $payload     = wp_json_encode($data);
    $payload_b64 = base64_encode($payload);
    $sig         = hash_hmac('sha256', $payload_b64, $secret);
    $cookie      = $payload_b64 . '.' . $sig;
    setcookie('ttb_session', $cookie, $data['exp'], '/', '', $is_https, true);
    $_COOKIE['ttb_session'] = $cookie;
  };

  // ── Comprobar admin ───────────────────────────────────────────
  $admin_user = (string)get_option('ttb_admin_user', 'tictac');
  $admin_hash = (string)get_option('ttb_admin_pass_hash', '');

  if ($u === $admin_user) {
    if ($admin_hash && password_verify($p, $admin_hash)) {
      $set_session('admin', 0);
      ttb_redirect(home_url('/briefing'));
    }
    set_transient($flash_key, ['type' => 'error', 'text' => 'Contraseña incorrecta. Inténtalo de nuevo.'], 60);
    ttb_redirect(home_url('/briefing'));
  }

  // ── Comprobar cliente ─────────────────────────────────────────
  global $wpdb;
  $table  = $wpdb->prefix . 'ttb_clients';
  $client = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table WHERE username = %s LIMIT 1", $u
  ));

  if ($client) {
    if (password_verify($p, $client->pass_hash)) {
      $set_session('client', (int)$client->id);
      ttb_redirect(home_url('/briefing'));
    }
    set_transient($flash_key, ['type' => 'error', 'text' => 'Contraseña incorrecta. Inténtalo de nuevo.'], 60);
    ttb_redirect(home_url('/briefing'));
  }

  // ── Usuario no encontrado ─────────────────────────────────────
  set_transient($flash_key, ['type' => 'error', 'text' => 'Usuario no encontrado. Revisa el email de invitación.'], 60);
  ttb_redirect(home_url('/briefing'));
}

/**
 * Redirect sin depender de wp_safe_redirect (puede no estar disponible aún).
 */
function ttb_redirect($url) {
  if (headers_sent()) {
    echo '<meta http-equiv="refresh" content="0;url=' . esc_url($url) . '">';
    echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    exit;
  }
  header('Location: ' . $url, true, 302);
  exit;
}

// ══════════════════════════════════════════════════════════════
// CARGA NORMAL DEL PLUGIN
// ══════════════════════════════════════════════════════════════

add_action('plugins_loaded', function () {
  (new TTB_Router())->init();
  (new TTB_Auth())->init();
  (new TTB_Forms())->init();
});