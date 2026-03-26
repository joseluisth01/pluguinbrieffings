<?php
if (!defined('ABSPATH')) exit;

class TTB_Auth {

  const COOKIE = 'ttb_session';
  const TTL = 60 * 60 * 8; // 8h

  public function init() {
    add_action('init', [$this, 'handle']);
  }

  public function handle() {

    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    // Solo actuar en el portal /briefing
    if (strpos($uri, '/briefing') !== 0) {
      return;
    }

    // Ignorar HEAD requests
    if ($method === 'HEAD') {
      return;
    }

    TTB_Logger::log('Auth handle start', ['method' => $method, 'uri' => $uri]);

    // Logout del portal
    if (
      isset($_GET['ttb_logout']) ||
      (isset($_GET['ttb_action']) && $_GET['ttb_action'] === 'logout')
    ) {
      $this->logout();
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }

    // Autologin por URL: /briefing?ttb_u=usuario&ttb_p=contraseña
    // Preserva cualquier parámetro extra como ctab, stab, filter_month, etc.
    if (isset($_GET['ttb_u'], $_GET['ttb_p'])) {
      $u = sanitize_text_field($_GET['ttb_u']);
      $p = wp_unslash((string)$_GET['ttb_p']);

      $admin_user = (string)get_option('ttb_admin_user', 'tictac');
      $admin_hash = (string)get_option('ttb_admin_pass_hash', '');

      if ($u === $admin_user) {
        if (!$admin_hash) {
          $admin_hash = password_hash($p, PASSWORD_DEFAULT);
          update_option('ttb_admin_pass_hash', $admin_hash);
          TTB_Logger::log('Admin hash was missing; regenerated from provided password');
        }

        if ($admin_hash && password_verify($p, $admin_hash)) {
          $this->set_session(['role' => 'admin', 'client_id' => 0]);
          // Preservar parámetros extra tras login de admin
          $redirect = $this->build_redirect_after_login();
          wp_safe_redirect($redirect);
          exit;
        }
      }

      $client = $this->get_client_by_username($u);
      if ($client && password_verify($p, $client->pass_hash)) {
        $this->set_session(['role' => 'client', 'client_id' => (int)$client->id]);
        // Preservar parámetros extra (ctab, stab, filter_month, etc.)
        $redirect = $this->build_redirect_after_login();
        wp_safe_redirect($redirect);
        exit;
      }

      // Autologin fallido
      TTB_Logger::log('Autologin failed', ['username' => $u]);
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }

    // Login submit por formulario
    if (isset($_POST['ttb_login'])) {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_login')) {
        TTB_Logger::log('Login nonce failed');
        $this->flash('error', 'Sesión inválida. Recarga y prueba otra vez.');
        wp_safe_redirect(home_url('/briefing'));
        exit;
      }

      $u = sanitize_text_field($_POST['username'] ?? '');
      $p = wp_unslash((string)($_POST['password'] ?? ''));

      TTB_Logger::log('Login attempt', ['username' => $u]);

      $admin_user = (string)get_option('ttb_admin_user', 'tictac');
      $admin_hash = (string)get_option('ttb_admin_pass_hash', '');

      if ($u === $admin_user) {
        if (!$admin_hash) {
          $admin_hash = password_hash($p, PASSWORD_DEFAULT);
          update_option('ttb_admin_pass_hash', $admin_hash);
          TTB_Logger::log('Admin hash was missing; regenerated from provided password');
        }

        if ($admin_hash && password_verify($p, $admin_hash)) {
          $this->set_session(['role' => 'admin', 'client_id' => 0]);
          wp_safe_redirect(home_url('/briefing'));
          exit;
        }
      }

      $client = $this->get_client_by_username($u);
      if ($client && password_verify($p, $client->pass_hash)) {
        $this->set_session(['role' => 'client', 'client_id' => (int)$client->id]);
        wp_safe_redirect(home_url('/briefing'));
        exit;
      }

      $this->flash('error', 'Usuario o contraseña incorrectos.');
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }
  }

  /**
   * Construye la URL de redirección tras autologin preservando parámetros extra.
   * Filtra ttb_u y ttb_p (credenciales) pero conserva ctab, stab, filter_month, etc.
   */
  private function build_redirect_after_login() {
    // Parámetros que NO deben aparecer en la URL final (credenciales)
    $remove = ['ttb_u', 'ttb_p'];

    $params = $_GET;
    foreach ($remove as $key) {
      unset($params[$key]);
    }

    // Si no quedan parámetros útiles, ir a /briefing limpio
    if (empty($params)) {
      return home_url('/briefing');
    }

    // Sanitizar cada parámetro
    $clean = [];
    foreach ($params as $k => $v) {
      $clean[sanitize_key($k)] = sanitize_text_field($v);
    }

    return home_url('/briefing?' . http_build_query($clean));
  }

  private function secret() {
    $secret = get_option('ttb_secret_key');
    if (!$secret) {
      $secret = bin2hex(random_bytes(32));
      update_option('ttb_secret_key', $secret);
    }
    return (string)$secret;
  }

  private function is_https() {
    return (
      is_ssl() ||
      (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
      (strpos(home_url(), 'https://') === 0)
    );
  }

  private function cookie_domain() {
    return '';
  }

  private function get_client_by_username($username) {
    global $wpdb;
    $table = TTB_DB::clients_table();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s LIMIT 1", $username));
  }

  public function logout() {
    $secure = $this->is_https();
    setcookie(self::COOKIE, '', [
      'expires'  => time() - 3600,
      'path'     => '/',
      'domain'   => '',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    unset($_COOKIE[self::COOKIE]);
    TTB_Logger::log('Logged out');
  }

  public function current() {
    $raw = $_COOKIE[self::COOKIE] ?? '';
    if (!$raw) {
      TTB_Logger::log('Auth::current - no cookie found');
      return null;
    }

    $parts = explode('.', $raw);
    if (count($parts) !== 2) {
      TTB_Logger::log('Auth::current - cookie malformed');
      return null;
    }

    [$payload_b64, $sig] = $parts;
    $payload = base64_decode($payload_b64);
    if (!$payload) {
      TTB_Logger::log('Auth::current - base64 decode failed');
      return null;
    }

    $keys = array_unique([
      $this->secret(),
      wp_salt('auth'),
    ]);

    $valid = false;
    foreach ($keys as $k) {
      $expected = hash_hmac('sha256', $payload_b64, $k);
      if (hash_equals($expected, $sig)) { $valid = true; break; }
    }
    if (!$valid) {
      TTB_Logger::log('Auth::current - HMAC invalid');
      return null;
    }

    $data = json_decode($payload, true);
    if (!is_array($data)) {
      TTB_Logger::log('Auth::current - JSON decode failed');
      return null;
    }

    if (empty($data['exp']) || time() > (int)$data['exp']) {
      TTB_Logger::log('Auth::current - session expired');
      return null;
    }

    return $data;
  }

  private function set_session($data) {
    $data['exp'] = time() + self::TTL;

    $payload = wp_json_encode($data);
    $payload_b64 = base64_encode($payload);
    $sig = hash_hmac('sha256', $payload_b64, $this->secret());
    $cookie = $payload_b64 . '.' . $sig;

    $secure = $this->is_https();

    setcookie(self::COOKIE, $cookie, [
      'expires'  => $data['exp'],
      'path'     => '/',
      'domain'   => '',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    $_COOKIE[self::COOKIE] = $cookie;

    TTB_Logger::log('Session cookie set', [
      'role'      => ($data['role'] ?? ''),
      'client_id' => ($data['client_id'] ?? 0),
      'secure'    => $secure,
    ]);
  }

  public function is_admin() {
    $s = $this->current();
    return $s && ($s['role'] ?? '') === 'admin';
  }

  public function is_client() {
    $s = $this->current();
    return $s && ($s['role'] ?? '') === 'client';
  }

  public function client_id() {
    $s = $this->current();
    return (int)($s['client_id'] ?? 0);
  }

  public function flash($type, $text) {
    set_transient('ttb_flash', ['type'=>$type,'text'=>$text], 60);
  }

  public function consume_flash() {
    $m = get_transient('ttb_flash');
    if ($m) delete_transient('ttb_flash');
    return $m;
  }
}