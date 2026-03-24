<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_DB')) return;

/**
 * TTB_Social_DB
 * Gestión de tablas para el módulo Redes Sociales
 *
 * Tablas:
 *   ttb_social_clients  → clientes con magic token para subir contenido
 *   ttb_social_content  → archivos subidos por el cliente
 *   ttb_social_posts    → publicaciones programadas (el calendario)
 *   ttb_social_audit    → log de auditoría
 */
class TTB_Social_DB {

  public static function clients_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_social_clients';
  }

  public static function content_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_social_content';
  }

  public static function posts_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_social_posts';
  }

  public static function audit_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_social_audit';
  }

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $clients = self::clients_table();
    $content = self::content_table();
    $posts   = self::posts_table();
    $audit   = self::audit_table();

    // Clientes de redes sociales (token único para magic link)
    $sql1 = "CREATE TABLE $clients (
      id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name          VARCHAR(190) NOT NULL,
      emails        LONGTEXT NOT NULL,
      token         VARCHAR(64) NOT NULL,
      networks      LONGTEXT NULL,
      notes         TEXT NULL,
      status        VARCHAR(40) NOT NULL DEFAULT 'active',
      created_at    DATETIME NOT NULL,
      updated_at    DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY token_unique (token),
      KEY status_idx (status)
    ) $charset;";

    // Contenido subido por los clientes (fotos, vídeos, textos)
    $sql2 = "CREATE TABLE $content (
      id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_id     BIGINT UNSIGNED NOT NULL,
      type          VARCHAR(20) NOT NULL DEFAULT 'image',
      file_url      TEXT NULL,
      caption       TEXT NULL,
      note          TEXT NULL,
      used          TINYINT(1) NOT NULL DEFAULT 0,
      created_at    DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY client_idx (client_id),
      KEY used_idx (used)
    ) $charset;";

    // Publicaciones programadas (calendario)
    $sql3 = "CREATE TABLE $posts (
      id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_id       BIGINT UNSIGNED NOT NULL,
      scheduled_date  DATE NOT NULL,
      scheduled_time  TIME NULL,
      network         VARCHAR(40) NOT NULL DEFAULT 'instagram',
      post_type       VARCHAR(40) NOT NULL DEFAULT 'image',
      caption         LONGTEXT NULL,
      creative_url    TEXT NULL,
      creative_note   TEXT NULL,
      status          VARCHAR(40) NOT NULL DEFAULT 'draft',
      client_note     TEXT NULL,
      notified_at     DATETIME NULL,
      approved_at     DATETIME NULL,
      created_at      DATETIME NOT NULL,
      updated_at      DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY client_idx (client_id),
      KEY date_idx (scheduled_date),
      KEY status_idx (status)
    ) $charset;";

    // Auditoría
    $sql4 = "CREATE TABLE $audit (
      id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_id   BIGINT UNSIGNED NULL,
      post_id     BIGINT UNSIGNED NULL,
      event       VARCHAR(80) NOT NULL,
      actor       VARCHAR(20) NOT NULL DEFAULT 'system',
      detail      LONGTEXT NULL,
      ip          VARCHAR(45) NULL,
      ua          VARCHAR(255) NULL,
      created_at  DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY client_idx (client_id),
      KEY post_idx (post_id),
      KEY event_idx (event),
      KEY actor_idx (actor),
      KEY created_idx (created_at)
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
  }

  public static function now() {
    return current_time('mysql');
  }

  public static function generate_token() {
    return bin2hex(random_bytes(32));
  }

  public static function get_client_by_token($token) {
    global $wpdb;
    $table = self::clients_table();
    return $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $table WHERE token = %s LIMIT 1",
      sanitize_text_field($token)
    ));
  }

  /** URL pública para que el cliente suba contenido y vea sus posts */
  public static function client_url($token) {
    return home_url('/briefing?social=' . urlencode($token));
  }

  /** Redes disponibles */
  public static function networks() {
    return [
      'instagram' => ['Instagram',  '📸'],
      'facebook'  => ['Facebook',   '📘'],
      'tiktok'    => ['TikTok',     '🎵'],
      'linkedin'  => ['LinkedIn',   '💼'],
      'twitter'   => ['X / Twitter','🐦'],
      'threads'   => ['Threads',    '🧵'],
    ];
  }

  /** Tipos de post */
  public static function post_types() {
    return [
      'image'    => ['Imagen',    '🖼️'],
      'carousel' => ['Carrusel',  '🎠'],
      'reel'     => ['Reel',      '🎬'],
      'story'    => ['Story',     '📱'],
      'video'    => ['Vídeo',     '🎥'],
      'text'     => ['Solo texto','✍️'],
    ];
  }

  /** Estados de un post */
  public static function post_statuses() {
    return [
      'draft'            => ['Borrador',           '#f3f4f6', '#e5e7eb', '#374151', 'ttb-status--draft'],
      'pending_approval' => ['Pendiente aprobación','#fffbeb', '#fde68a', '#92400e', 'ttb-status--pending'],
      'approved'         => ['Aprobado',            '#ecfdf5', '#6ee7b7', '#065f46', 'ttb-status--sent'],
      'rejected'         => ['Rechazado',           '#fff1f2', '#fecdd3', '#be123c', 'ttb-status--danger'],
      'published'        => ['Publicado',           '#eff6ff', '#bfdbfe', '#1d4ed8', 'ttb-status--progress'],
      'changes_made'     => ['Cambios aplicados',   '#fdf4ff', '#e9d5ff', '#7e22ce', 'ttb-status--purple'],
    ];
  }

  /**
   * Registra un evento de auditoría.
   */
  public static function log($client_id, $post_id, $event, $actor = 'system', $detail = []) {
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
      'client_id' => $client_id ? (int)$client_id : null,
      'post_id'   => $post_id   ? (int)$post_id   : null,
      'event'     => sanitize_text_field($event),
      'actor'     => sanitize_text_field($actor),
      'detail'    => !empty($detail) ? wp_json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
      'ip'        => $ip,
      'ua'        => $ua,
      'created_at'=> self::now(),
    ]);
  }
}