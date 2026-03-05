<?php
/**
 * ttb-login-handler.php
 *
 * Endpoint independiente para procesar el login/logout del portal.
 * NO depende del routing de WordPress en absoluto.
 *
 * El formulario de login hace POST aquí directamente.
 * Tras procesar, redirige siempre a /briefing.
 */

// Cargar WordPress mínimo
$wp_load = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load)) die('Error de configuración.');
require_once $wp_load;

// Solo aceptar POST (o GET para logout)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── Logout ───────────────────────────────────────────────────
if (isset($_GET['ttb_action']) && $_GET['ttb_action'] === 'logout') {
  ttblh_clear_session_cookie();
  ttblh_redirect(home_url('/briefing'));
}

// ── Solo POST a partir de aquí ───────────────────────────────
if ($method !== 'POST') {
  ttblh_redirect(home_url('/briefing'));
}

$u = sanitize_text_field(wp_unslash($_POST['username'] ?? ''));
$p = (string)($_POST['password'] ?? '');

// Campos vacíos
if ($u === '' || $p === '') {
  $msg = ($u === '' && $p === '')
    ? 'Introduce tu usuario y contraseña.'
    : ($u === '' ? 'El campo usuario es obligatorio.' : 'El campo contraseña es obligatorio.');
  ttblh_flash('error', $msg);
  ttblh_redirect(home_url('/briefing'));
}

// ── Admin ────────────────────────────────────────────────────
$admin_user = (string)get_option('ttb_admin_user', 'tictac');
$admin_hash = (string)get_option('ttb_admin_pass_hash', '');

if ($u === $admin_user) {
  if ($admin_hash && password_verify($p, $admin_hash)) {
    ttblh_set_session('admin', 0);
    ttblh_redirect(home_url('/briefing'));
  }
  ttblh_flash('error', 'Contraseña incorrecta. Inténtalo de nuevo.');
  ttblh_redirect(home_url('/briefing'));
}

// ── Cliente ──────────────────────────────────────────────────
global $wpdb;
$table  = $wpdb->prefix . 'ttb_clients';
$client = $wpdb->get_row($wpdb->prepare(
  "SELECT * FROM $table WHERE username = %s LIMIT 1", $u
));

if ($client) {
  if (password_verify($p, $client->pass_hash)) {
    ttblh_set_session('client', (int)$client->id);
    ttblh_redirect(home_url('/briefing'));
  }
  ttblh_flash('error', 'Contraseña incorrecta. Inténtalo de nuevo.');
  ttblh_redirect(home_url('/briefing'));
}

// ── No encontrado ────────────────────────────────────────────
ttblh_flash('error', 'Usuario no encontrado. Revisa el email de invitación.');
ttblh_redirect(home_url('/briefing'));


// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════

function ttblh_set_session($role, $client_id) {
  $secret = ttblh_secret();
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
}

function ttblh_clear_session_cookie() {
  $is_https = strpos(home_url(), 'https://') === 0;
  setcookie('ttb_session', '', time() - 3600, '/', '', $is_https, true);
  setcookie('ttb_session', '', time() - 3600, '/', '', false, true);
  unset($_COOKIE['ttb_session']);
}

function ttblh_flash($type, $text) {
  set_transient('ttb_flash', ['type' => $type, 'text' => $text], 60);
}

function ttblh_secret() {
  $secret = get_option('ttb_secret_key');
  if (!$secret) {
    $secret = bin2hex(random_bytes(32));
    update_option('ttb_secret_key', $secret);
  }
  return (string)$secret;
}

function ttblh_redirect($url) {
  if (headers_sent()) {
    echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    exit;
  }
  header('Location: ' . $url, true, 302);
  exit;
}