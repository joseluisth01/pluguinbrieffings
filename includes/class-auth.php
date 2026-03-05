<?php
if (!defined('ABSPATH')) exit;

class TTB_Auth {

  const COOKIE    = 'ttb_session';
  const TTL       = 60 * 60 * 8; // 8h
  const FLASH_KEY = 'ttb_flash';

  public function init() {
    add_action('init', [$this, 'handle'], 1);
  }

  public function handle() {
    // Solo actuar en /briefing
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/briefing') === false) return;

    // ── Logout ──────────────────────────────────────────
    if (isset($_GET['ttb_logout'])) {
      $this->logout();
      $this->redirect(home_url('/briefing'));
    }

    // ── Login ────────────────────────────────────────────
    if (!isset($_POST['ttb_login'])) return;

    $u = sanitize_text_field($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');

    // Campos vacíos
    if ($u === '' && $p === '') {
      $this->flash('error', 'Introduce tu usuario y contraseña.');
      $this->redirect(home_url('/briefing'));
    }
    if ($u === '') {
      $this->flash('error', 'El campo usuario es obligatorio.');
      $this->redirect(home_url('/briefing'));
    }
    if ($p === '') {
      $this->flash('error', 'El campo contraseña es obligatorio.');
      $this->redirect(home_url('/briefing'));
    }

    // ── Comprobar admin ──────────────────────────────────
    $admin_user = (string)get_option('ttb_admin_user', 'tictac');
    $admin_hash = (string)get_option('ttb_admin_pass_hash', '');

    if ($u === $admin_user) {
      if ($admin_hash && password_verify($p, $admin_hash)) {
        $this->set_session(['role' => 'admin', 'client_id' => 0]);
        $this->redirect(home_url('/briefing'));
      }
      $this->flash('error', 'Contraseña incorrecta. Inténtalo de nuevo.');
      $this->redirect(home_url('/briefing'));
    }

    // ── Comprobar cliente ────────────────────────────────
    $client = $this->get_client_by_username($u);
    if ($client) {
      if (password_verify($p, $client->pass_hash)) {
        $this->set_session(['role' => 'client', 'client_id' => (int)$client->id]);
        $this->redirect(home_url('/briefing'));
      }
      $this->flash('error', 'Contraseña incorrecta. Inténtalo de nuevo.');
      $this->redirect(home_url('/briefing'));
    }

    // ── Usuario no encontrado ────────────────────────────
    $this->flash('error', 'Usuario no encontrado. Revisa el email de invitación.');
    $this->redirect(home_url('/briefing'));
  }

  /**
   * Redirect robusto: fallback a meta-refresh si las cabeceras ya fueron enviadas.
   */
  private function redirect($url) {
    if (headers_sent()) {
      echo '<meta http-equiv="refresh" content="0;url=' . esc_url($url) . '">';
      echo '<script>window.location.href=' . json_encode($url) . ';</script>';
      exit;
    }
    wp_safe_redirect($url);
    exit;
  }

  private function get_client_by_username($username) {
    global $wpdb;
    $table = TTB_DB::clients_table();
    return $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $table WHERE username = %s LIMIT 1",
      $username
    ));
  }

  private function is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on')   return true;
    if (strpos(home_url(), 'https://') === 0) return true;
    return false;
  }

  public function logout() {
    $secure = $this->is_https();
    setcookie(self::COOKIE, '', time() - 3600, '/', '', $secure, true);
    setcookie(self::COOKIE, '', time() - 3600, '/', '', false,   true);
    unset($_COOKIE[self::COOKIE]);
  }

  public function current() {
    $raw = $_COOKIE[self::COOKIE] ?? '';
    if (!$raw) return null;

    $parts = explode('.', $raw);
    if (count($parts) !== 2) return null;

    [$payload_b64, $sig] = $parts;
    $payload = base64_decode($payload_b64);
    if (!$payload) return null;

    $expected = hash_hmac('sha256', $payload_b64, self::get_secret());
    if (!hash_equals($expected, $sig)) return null;

    $data = json_decode($payload, true);
    if (!is_array($data)) return null;

    if (empty($data['exp']) || time() > (int)$data['exp']) return null;

    return $data;
  }

  private function set_session($data) {
    $secure = $this->is_https();

    $data['exp'] = time() + self::TTL;
    $payload     = wp_json_encode($data);
    $payload_b64 = base64_encode($payload);
    $sig         = hash_hmac('sha256', $payload_b64, self::get_secret());
    $cookie      = $payload_b64 . '.' . $sig;

    setcookie(self::COOKIE, $cookie, $data['exp'], '/', '', $secure, true);
    $_COOKIE[self::COOKIE] = $cookie;
  }

  private static function get_secret() {
    $secret = get_option('ttb_secret_key');
    if (!$secret) {
      $secret = bin2hex(random_bytes(32));
      update_option('ttb_secret_key', $secret);
    }
    return (string)$secret;
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

  // ── Flash messages ──────────────────────────────────────
  // Key fija (sin IP) para que sobreviva el POST → redirect → GET
  // aunque haya un proxy o load balancer de por medio.

  public function flash($type, $text) {
    set_transient(self::FLASH_KEY, ['type' => $type, 'text' => $text], 60);
  }

  public function consume_flash() {
    $m = get_transient(self::FLASH_KEY);
    if ($m) delete_transient(self::FLASH_KEY);
    return $m ?: null;
  }
}