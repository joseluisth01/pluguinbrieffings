<?php
if (!defined('ABSPATH')) exit;

class TTB_Auth {

  const COOKIE = 'ttb_session';
  const TTL = 60 * 60 * 8; // 8h

  public function init() {
    add_action('init', [$this, 'handle']);
  }

  public function handle() {
    // logout
    if (isset($_GET['ttb_logout'])) {
      $this->logout();
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }

    // login submit
    if (isset($_POST['ttb_login'])) {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_login')) {
        $this->flash('error', 'Sesión inválida. Recarga y prueba otra vez.');
        wp_safe_redirect(home_url('/briefing'));
        exit;
      }

      $u = sanitize_text_field($_POST['username'] ?? '');
      $p = (string)($_POST['password'] ?? '');

      // admin?
      $admin_user = (string)get_option('ttb_admin_user', 'tictac');
      $admin_hash = (string)get_option('ttb_admin_pass_hash', '');

      if ($u === $admin_user && $admin_hash && password_verify($p, $admin_hash)) {
        $this->set_session(['role'=>'admin','client_id'=>0]);
        wp_safe_redirect(home_url('/briefing'));
        exit;
      }

      // client?
      $client = $this->get_client_by_username($u);
      if ($client && password_verify($p, $client->pass_hash)) {
        $this->set_session(['role'=>'client','client_id'=>(int)$client->id]);
        wp_safe_redirect(home_url('/briefing'));
        exit;
      }

      $this->flash('error', 'Usuario o contraseña incorrectos.');
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }
  }

  private function get_client_by_username($username) {
    global $wpdb;
    $table = TTB_DB::clients_table();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s LIMIT 1", $username));
  }

  public function logout() {
    setcookie(self::COOKIE, '', time()-3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
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

    $expected = hash_hmac('sha256', $payload_b64, wp_salt('auth'));
    if (!hash_equals($expected, $sig)) return null;

    $data = json_decode($payload, true);
    if (!is_array($data)) return null;

    if (empty($data['exp']) || time() > (int)$data['exp']) return null;

    return $data;
  }

  private function set_session($data) {
    $data['exp'] = time() + self::TTL;
    $payload = wp_json_encode($data);
    $payload_b64 = base64_encode($payload);
    $sig = hash_hmac('sha256', $payload_b64, wp_salt('auth'));
    $cookie = $payload_b64 . '.' . $sig;

    setcookie(self::COOKIE, $cookie, $data['exp'], COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    $_COOKIE[self::COOKIE] = $cookie;
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
