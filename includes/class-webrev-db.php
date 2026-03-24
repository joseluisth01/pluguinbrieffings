<?php
if (!defined('ABSPATH')) exit;

/**
 * TTB_WebRev_DB
 * Gestión de tablas para el módulo Revisiones Diseños
 */
class TTB_WebRev_DB {

  const SCHEMA_VERSION = 2;

  public static function projects_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webrev_projects';
  }

  public static function revisions_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webrev_revisions';
  }

  public static function audit_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webrev_audit';
  }

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $projects  = self::projects_table();
    $revisions = self::revisions_table();
    $audit     = self::audit_table();

    $sql1 = "CREATE TABLE $projects (
      id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name            VARCHAR(190) NOT NULL,
      emails          LONGTEXT NOT NULL,
      figma_url       TEXT NOT NULL,
      figma_url_mobile TEXT NULL,
      token           VARCHAR(64) NOT NULL,
      status          VARCHAR(40) NOT NULL DEFAULT 'pending',
      last_notified   DATETIME NULL,
      notif_count     INT UNSIGNED NOT NULL DEFAULT 0,
      created_at      DATETIME NOT NULL,
      updated_at      DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY token_unique (token),
      KEY status_idx (status)
    ) $charset;";

    $sql2 = "CREATE TABLE $revisions (
      id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      project_id  BIGINT UNSIGNED NOT NULL,
      round       INT UNSIGNED NOT NULL DEFAULT 1,
      type        VARCHAR(20) NOT NULL DEFAULT 'change',
      message     LONGTEXT NULL,
      images      LONGTEXT NULL,
      created_at  DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY project_idx (project_id),
      KEY round_idx (round)
    ) $charset;";

    $sql3 = "CREATE TABLE $audit (
      id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      project_id  BIGINT UNSIGNED NULL,
      event       VARCHAR(80) NOT NULL,
      actor       VARCHAR(20) NOT NULL DEFAULT 'system',
      detail      LONGTEXT NULL,
      ip          VARCHAR(45) NULL,
      ua          VARCHAR(255) NULL,
      created_at  DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY project_idx (project_id),
      KEY event_idx (event),
      KEY actor_idx (actor),
      KEY created_idx (created_at)
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);

    // Migración: añadir figma_url_mobile si no existe
    self::migrate_v2();

    update_option('ttb_webrev_schema_version', self::SCHEMA_VERSION);
  }

  private static function migrate_v2() {
    global $wpdb;
    $table = self::projects_table();

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) return;

    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'figma_url_mobile'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `figma_url_mobile` TEXT NULL AFTER `figma_url`");
    }
  }

  /**
   * Ejecuta migraciones si la versión almacenada es inferior a la actual.
   * Llamar desde plugins_loaded para cubrir actualizaciones sin reactivar el plugin.
   */
  public static function run_migrations() {
    $current = (int) get_option('ttb_webrev_schema_version', 0);
    if ($current >= self::SCHEMA_VERSION) return;
    self::create_tables();
  }

  public static function now() {
    return current_time('mysql');
  }

  public static function generate_token() {
    return bin2hex(random_bytes(32));
  }

  public static function get_project_by_token($token) {
    global $wpdb;
    $table = self::projects_table();
    return $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $table WHERE token = %s LIMIT 1", sanitize_text_field($token)
    ));
  }

  public static function client_url($token) {
    return home_url('/briefing?webrev=' . urlencode($token));
  }

  public static function log($project_id, $event, $actor = 'system', $detail = []) {
    global $wpdb;
    $table = self::audit_table();

    if (is_array($detail)) {
      foreach (['password', 'pass', 'p', 'token', 'pass_hash'] as $k) {
        if (isset($detail[$k])) $detail[$k] = '***';
      }
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = isset($_SERVER['HTTP_USER_AGENT'])
      ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
      : null;

    $wpdb->insert($table, [
      'project_id' => $project_id ? (int)$project_id : null,
      'event'      => sanitize_text_field($event),
      'actor'      => sanitize_text_field($actor),
      'detail'     => !empty($detail) ? wp_json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
      'ip'         => $ip,
      'ua'         => $ua,
      'created_at' => self::now(),
    ]);
  }
}