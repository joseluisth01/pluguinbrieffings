<?php
if (!defined('ABSPATH')) exit;

class TTB_Router {

  const SLUG = 'briefing';

  public function init() {
    add_action('init', [$this, 'add_rewrite']);
    add_filter('query_vars', [$this, 'add_qv']);
    add_action('template_redirect', [$this, 'render'], 0);

    add_action('wp_enqueue_scripts', function () {
      if ($this->is_portal()) {
        wp_enqueue_style('ttb-portal', TTB_URL.'assets/css/portal.css', [], TTB_VERSION);
      }
    });
  }

  public function add_rewrite() {
    add_rewrite_rule('^' . self::SLUG . '/?$', 'index.php?ttb_portal=1', 'top');
  }

  public function add_qv($vars) {
    $vars[] = 'ttb_portal';
    return $vars;
  }

  private function is_portal() {
    return ((int) get_query_var('ttb_portal') === 1);
  }

  public function render() {
    if (!$this->is_portal()) return;

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
      status_header(200);
      nocache_headers();
      exit;
    }

    if (!defined('DONOTCACHEPAGE'))   define('DONOTCACHEPAGE', true);
    if (!defined('DONOTCACHEDB'))     define('DONOTCACHEDB', true);
    if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);

    if (!headers_sent()) {
      header_remove('Last-Modified');
      header_remove('ETag');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0', true);
      header('Pragma: no-cache', true);
      header('Expires: Thu, 01 Jan 1970 00:00:00 GMT', true);
      header('Surrogate-Control: no-store', true);
      header('X-Accel-Expires: 0', true);
      header('X-Cache-Enabled: false', true);
      header('X-LiteSpeed-Cache-Control: no-cache', true);
      header('Vary: Cookie, Authorization', true);
      header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    }

    nocache_headers();

    add_filter('wp_robots', function ($robots) {
      $robots['noindex']   = true;
      $robots['nofollow']  = true;
      $robots['noarchive'] = true;
      return $robots;
    });

    // ── ¿Es un magic link de revisión Diseños? ──────────────
    $webrev_token = sanitize_text_field($_GET['webrev'] ?? '');
    if ($webrev_token) {
      include TTB_PATH . 'templates/portal-shell-webrev.php';
      exit;
    }

    // ── ¿Es un magic link de revisión Prog. Web? ────────────
    $webprog_token = sanitize_text_field($_GET['webprog'] ?? '');
    if ($webprog_token) {
      include TTB_PATH . 'templates/portal-shell-webprog.php';
      exit;
    }

    include TTB_PATH . 'templates/portal-shell.php';
    exit;
  }
}