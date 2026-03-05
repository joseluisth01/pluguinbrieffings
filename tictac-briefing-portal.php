<?php
/**
 * Plugin Name: TicTacccc Briefing Portal (Standalone)
 * Description: Portal /briefing con login independiente + admin frontend + clientes + formularios por servicio + Google Drive.
 * Version: 1.2.0
 * Author: TicTac Comunicación
 */

if (!defined('ABSPATH')) exit;

define('TTB_VERSION', '1.2.0');
define('TTB_PATH', plugin_dir_path(__FILE__));
define('TTB_URL',  plugin_dir_url(__FILE__));

require_once TTB_PATH . 'includes/class-db.php';
require_once TTB_PATH . 'includes/class-logger.php';
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

add_action('plugins_loaded', function () {
  $auth = new TTB_Auth();

  (new TTB_Router())->init();
  $auth->init();
  (new TTB_Forms())->init();

  // ✅ Si cierras sesión de WordPress (wp-admin), también borra sesión del portal
  add_action('wp_logout', function () use ($auth) {
    $auth->logout();
  }, 1);
});