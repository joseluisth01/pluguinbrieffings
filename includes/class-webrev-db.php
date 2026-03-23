<?php
if (!defined('ABSPATH')) exit;

/**
 * TTB_WebRev_DB
 * Gestión de tablas para el módulo Revisiones Prog. Web
 */
class TTB_WebRev_DB {

  public static function projects_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webrev_projects';
  }

  public static function revisions_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_webrev_revisions';
  }

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $projects  = self::projects_table();
    $revisions = self::revisions_table();

    // Tabla principal de proyectos / clientes de revisión
    $sql1 = "CREATE TABLE $projects (
      id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name          VARCHAR(190) NOT NULL,
      emails        LONGTEXT NOT NULL,
      figma_url     TEXT NOT NULL,
      token         VARCHAR(64) NOT NULL,
      status        VARCHAR(40) NOT NULL DEFAULT 'pending',
      last_notified DATETIME NULL,
      notif_count   INT UNSIGNED NOT NULL DEFAULT 0,
      created_at    DATETIME NOT NULL,
      updated_at    DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY token_unique (token),
      KEY status_idx (status)
    ) $charset;";

    // Tabla de registros de revisión (rondas)
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

    dbDelta($sql1);
    dbDelta($sql2);
  }

  public static function now() {
    return current_time('mysql');
  }

  /** Genera un token único para magic link */
  public static function generate_token() {
    return bin2hex(random_bytes(32));
  }

  /** Recupera un proyecto por token */
  public static function get_project_by_token($token) {
    global $wpdb;
    $table = self::projects_table();
    return $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $table WHERE token = %s LIMIT 1", sanitize_text_field($token)
    ));
  }

  /** URL pública del cliente para un proyecto */
  public static function client_url($token) {
    return home_url('/briefing?webrev=' . urlencode($token));
  }
}