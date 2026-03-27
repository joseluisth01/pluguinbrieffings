<?php
/**
 * Plugin Name: TicTac Briefing Portal (Standalone)
 * Description: Portal /briefing con login independiente + admin frontend + clientes + formularios por servicio + Google Drive + Revisiones Diseños + Revisiones Prog. Web + Redes Sociales.
 * Version: 1.7.0
 * Author: TicTac Comunicación
 */

if (!defined('ABSPATH')) exit;

define('TTB_VERSION', '1.7.0');
define('TTB_PATH', plugin_dir_path(__FILE__));
define('TTB_URL',  plugin_dir_url(__FILE__));

// ── Core ──────────────────────────────────────────────────────
require_once TTB_PATH . 'includes/class-db.php';
require_once TTB_PATH . 'includes/class-logger.php';
require_once TTB_PATH . 'includes/class-activator.php';
require_once TTB_PATH . 'includes/class-deactivator.php';
require_once TTB_PATH . 'includes/class-router.php';
require_once TTB_PATH . 'includes/class-auth.php';
require_once TTB_PATH . 'includes/class-forms.php';
require_once TTB_PATH . 'includes/class-mailer.php';
require_once TTB_PATH . 'includes/class-drive.php';
require_once TTB_PATH . 'includes/ttb-icons.php';
require_once TTB_PATH . 'includes/class-clients-ui.php';
require_once TTB_PATH . 'includes/class-admin-ui.php';
require_once TTB_PATH . 'includes/class-client-ui.php';

// ── Módulo: Revisiones Diseños ────────────────────────────────
require_once TTB_PATH . 'includes/class-webrev-db.php';
require_once TTB_PATH . 'includes/class-webrev-mailer.php';
require_once TTB_PATH . 'includes/class-webrev-cron.php';
require_once TTB_PATH . 'includes/class-webrev-admin.php';
require_once TTB_PATH . 'includes/class-webrev-client.php';

// ── Módulo: Revisiones Prog. Web ──────────────────────────────
require_once TTB_PATH . 'includes/class-webprog-db.php';
require_once TTB_PATH . 'includes/class-webprog-mailer.php';
require_once TTB_PATH . 'includes/class-webprog-cron.php';
require_once TTB_PATH . 'includes/class-webprog-admin.php';
require_once TTB_PATH . 'includes/class-webprog-client.php';

// ── Módulo: Redes Sociales ────────────────────────────────────
require_once TTB_PATH . 'includes/class-social-db.php';
require_once TTB_PATH . 'includes/class-social-mailer.php';
require_once TTB_PATH . 'includes/class-social-cron.php';
require_once TTB_PATH . 'includes/class-social-admin.php';
require_once TTB_PATH . 'includes/class-social-client.php';

register_activation_hook(__FILE__,   ['TTB_Activator',   'activate']);
register_deactivation_hook(__FILE__, ['TTB_Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
  $auth = new TTB_Auth();

  (new TTB_Router())->init();
  $auth->init();
  (new TTB_Forms())->init();

  // ── Migraciones de BD ──────────────────────────────────────
  TTB_DB::run_migrations();
  TTB_WebRev_DB::run_migrations();
  TTB_WebProg_DB::run_migrations();
  TTB_Social_DB::run_migrations();    // ← NUEVO: migración v2 (week_group, copy_text, etc.)

  // Cron
  TTB_WebRev_Cron::register();
  TTB_WebProg_Cron::register();
  TTB_Social_Cron::register();

  // ✅ Logout de WordPress → también borra sesión del portal
  add_action('wp_logout', function () use ($auth) {
    $auth->logout();
  }, 1);
});