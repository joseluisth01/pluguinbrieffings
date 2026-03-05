<?php
if (!defined('ABSPATH')) exit;

class TTB_Auth {

  const COOKIE = 'ttb_session';
  const TTL = 60 * 60 * 8; // 8h

  public function init() {
    add_action('init', [$this, 'handle']);
  }

  public function handle() {

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    // ✅ Importante: este auth SOLO debe actuar en el portal /briefing
    // Evita autologin “raro” o comportamiento en /wp-admin, /, /wp-login, etc.
    if (strpos($uri, '/briefing') !== 0) {
      return;
    }

    TTB_Logger::log('Auth handle start', ['method' => $method, 'uri' => $uri]);

    // ✅ Logout del portal (acepta dos formatos)
    // /briefing?ttb_logout=1  (nuevo)
    // /briefing?ttb_action=logout (compat)
    if (
      isset($_GET['ttb_logout']) ||
      (isset($_GET['ttb_action']) && $_GET['ttb_action'] === 'logout')
    ) {
      $this->logout();
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }

    // autologin por URL: /briefing?ttb_u=usuario&ttb_p=contraseña
    if (isset($_GET['ttb_u'], $_GET['ttb_p'])) {
      $u = sanitize_text_field($_GET['ttb_u']);
      $p = (string)$_GET['ttb_p'];

      $admin_user = (string)get_option('ttb_admin_user', 'tictac');
      $admin_hash = (string)get_option('ttb_admin_pass_hash', '');

      if ($u === $admin_user) {
        // Recovery: si el hash está vacío, lo regeneramos con la contraseña introducida
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

      wp_safe_redirect(home_url('/briefing'));
      exit;
    }

    // login submit por formulario
    if (isset($_POST['ttb_login'])) {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_login')) {
        TTB_Logger::log('Login nonce failed');
        $this->flash('error', 'Sesión inválida. Recarga y prueba otra vez.');
        wp_safe_redirect(home_url('/briefing'));
        exit;
      }

      $u = sanitize_text_field($_POST['username'] ?? '');
      $p = (string)($_POST['password'] ?? '');

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
    // Mejor dejarlo vacío para que el navegador use el host actual.
    // (Evita líos con www/no-www).
    return '';
  }

  private function get_client_by_username($username) {
    global $wpdb;
    $table = TTB_DB::clients_table();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s LIMIT 1", $username));
  }

  public function logout() {
    $path   = '/';
    $domain = $this->cookie_domain();
    $secure = $this->is_https();

    // Borrar cookie en variantes secure/no-secure para evitar “se queda pegada”
    setcookie(self::COOKIE, '', time() - 3600, $path, $domain, $secure, true);
    setcookie(self::COOKIE, '', time() - 3600, $path, $domain, false, true);

    unset($_COOKIE[self::COOKIE]);

    TTB_Logger::log('Logged out (cookie cleared)', ['secure' => $secure]);
  }

  public function current() {
    $raw = $_COOKIE[self::COOKIE] ?? '';
    if (!$raw) return null;

    $parts = explode('.', $raw);
    if (count($parts) !== 2) return null;

    [$payload_b64, $sig] = $parts;
    $payload = base64_decode($payload_b64);
    if (!$payload) return null;

    // Acepta ambas llaves por compatibilidad:
    // 1) ttb_secret_key (handler)
    // 2) wp_salt('auth') (versiones anteriores)
    $keys = array_unique([
      $this->secret(),
      wp_salt('auth'),
    ]);

    $valid = false;
    foreach ($keys as $k) {
      $expected = hash_hmac('sha256', $payload_b64, $k);
      if (hash_equals($expected, $sig)) { $valid = true; break; }
    }
    if (!$valid) return null;

    $data = json_decode($payload, true);
    if (!is_array($data)) return null;

    if (empty($data['exp']) || time() > (int)$data['exp']) return null;

    return $data;
  }

  private function set_session($data) {
    $data['exp'] = time() + self::TTL;

    $payload = wp_json_encode($data);
    $payload_b64 = base64_encode($payload);
    $sig = hash_hmac('sha256', $payload_b64, $this->secret());
    $cookie = $payload_b64 . '.' . $sig;

    $path   = '/';
    $domain = $this->cookie_domain();
    $secure = $this->is_https();

    setcookie(self::COOKIE, $cookie, $data['exp'], $path, $domain, $secure, true);
    $_COOKIE[self::COOKIE] = $cookie;

    TTB_Logger::log('Session cookie set', [
      'role'      => ($data['role'] ?? ''),
      'client_id' => ($data['client_id'] ?? 0),
      'secure'    => $secure
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