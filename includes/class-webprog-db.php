<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebProg_DB')) return;

/**
 * TTB_WebProg_DB
 * Gestión de tablas para el módulo Revisiones Prog. Web
 */
class TTB_WebProg_DB {

  const SCHEMA_VERSION = 3; // v3: añade columna title

  public static function projects_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webprog_projects';
  }

  public static function revisions_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webprog_revisions';
  }

  public static function audit_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webprog_audit';
  }

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $projects  = self::projects_table();
    $revisions = self::revisions_table();
    $audit     = self::audit_table();

    $sql1 = "CREATE TABLE $projects (
      id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name          VARCHAR(190) NOT NULL,
      title         VARCHAR(255) NULL,
      emails        LONGTEXT NOT NULL,
      web_url       TEXT NOT NULL,
      token         VARCHAR(64) NOT NULL,
      status        VARCHAR(40) NOT NULL DEFAULT 'pending',
      go_live_date  DATE NULL,
      last_notified DATETIME NULL,
      notif_count   INT UNSIGNED NOT NULL DEFAULT 0,
      created_at    DATETIME NOT NULL,
      updated_at    DATETIME NOT NULL,
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

    self::migrate_v2();
    self::migrate_v3();

    update_option('ttb_webprog_schema_version', self::SCHEMA_VERSION);
  }

  private static function migrate_v2() {
    global $wpdb;
    $table = self::projects_table();
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) return;
    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'go_live_date'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `go_live_date` DATE NULL AFTER `status`");
    }
  }

  private static function migrate_v3() {
    global $wpdb;
    $table = self::projects_table();
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) return;
    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'title'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `title` VARCHAR(255) NULL AFTER `name`");
    }
  }

  public static function run_migrations() {
    $current = (int) get_option('ttb_webprog_schema_version', 0);
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
      "SELECT * FROM $table WHERE token = %s LIMIT 1",
      sanitize_text_field($token)
    ));
  }

  /**
   * Obtiene todos los proyectos web vinculados a un cliente por nombre.
   * Devuelve el más reciente primero.
   */
  public static function get_projects_by_client_name($name) {
    global $wpdb;
    $table = self::projects_table();
    return $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table WHERE name = %s ORDER BY created_at DESC",
      $name
    ));
  }

  public static function client_url($token) {
    return home_url('/briefing?webprog=' . urlencode($token));
  }

  public static function format_go_live($date_str) {
    if (!$date_str) return null;
    $ts = strtotime($date_str);
    if (!$ts) return null;
    return date_i18n('l, j \d\e F \d\e Y', $ts);
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