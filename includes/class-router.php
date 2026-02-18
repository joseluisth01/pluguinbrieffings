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

    // NOINDEX + no cache
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    add_filter('wp_robots', function ($robots) {
      $robots['noindex'] = true;
      $robots['nofollow'] = true;
      $robots['noarchive'] = true;
      return $robots;
    });

    // Render shell dentro del theme
    include TTB_PATH . 'templates/portal-shell.php';
    exit;
  }
}
