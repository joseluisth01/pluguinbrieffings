<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Briefing_DB')) return;

/**
 * TTB_Briefing_DB
 * Gestión de tablas para el módulo Briefing (post-prebriefing).
 *
 * Flujo:
 * 1. Admin crea un briefing para un cliente: adjunta PDF/DOC + carpeta Drive
 * 2. Se crea automáticamente subcarpeta "00. DOC - COMPARTIDA CON EL CLIENTE"
 *    dentro de la carpeta Drive indicada y se comparte con los emails del cliente.
 * 3. Cliente ve el documento, puede aceptarlo o rechazarlo con comentario.
 * 4. Cliente sube recursos a la carpeta Drive compartida.
 * 5. Admin recibe notificación de aceptación/rechazo.
 */
class TTB_Briefing_DB {

  const SCHEMA_VERSION = 1;

  public static function briefings_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_briefings';
  }

  public static function audit_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_briefing_audit';
  }

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $briefings = self::briefings_table();
    $audit     = self::audit_table();

    // Tabla principal de briefings
    $sql1 = "CREATE TABLE $briefings (
      id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      ttb_client_id     BIGINT UNSIGNED NOT NULL,
      service           VARCHAR(40) NOT NULL DEFAULT 'general',
      title             VARCHAR(255) NOT NULL DEFAULT '',
      doc_url           TEXT NULL COMMENT 'URL del PDF/DOC adjunto (WordPress media)',
      doc_name          VARCHAR(255) NULL COMMENT 'Nombre original del archivo',
      doc_mime          VARCHAR(100) NULL COMMENT 'MIME type del archivo',
      drive_folder_url  TEXT NULL COMMENT 'URL de la carpeta Drive raíz indicada por admin',
      shared_folder_id  VARCHAR(200) NULL COMMENT 'ID de la subcarpeta compartida creada automáticamente',
      shared_folder_url TEXT NULL COMMENT 'URL de la carpeta compartida con el cliente',
      token             VARCHAR(64) NOT NULL,
      status            VARCHAR(40) NOT NULL DEFAULT 'pending' COMMENT 'pending|accepted|rejected|resources_pending|completed',
      client_note       TEXT NULL COMMENT 'Comentario del cliente al aceptar/rechazar',
      notified_at       DATETIME NULL,
      notif_count       INT UNSIGNED NOT NULL DEFAULT 0,
      resources_reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
      responded_at      DATETIME NULL,
      created_at        DATETIME NOT NULL,
      updated_at        DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY token_unique (token),
      KEY client_idx (ttb_client_id),
      KEY service_idx (service),
      KEY status_idx (status)
    ) $charset;";

    // Tabla de auditoría
    $sql2 = "CREATE TABLE $audit (
      id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      briefing_id  BIGINT UNSIGNED NULL,
      client_id    BIGINT UNSIGNED NULL,
      event        VARCHAR(80) NOT NULL,
      actor        VARCHAR(20) NOT NULL DEFAULT 'system',
      detail       LONGTEXT NULL,
      ip           VARCHAR(45) NULL,
      ua           VARCHAR(255) NULL,
      created_at   DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY briefing_idx (briefing_id),
      KEY client_idx (client_id),
      KEY event_idx (event),
      KEY created_idx (created_at)
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);

    update_option('ttb_briefing_schema_version', self::SCHEMA_VERSION);
  }

  public static function run_migrations() {
    $current = (int) get_option('ttb_briefing_schema_version', 0);
    if ($current >= self::SCHEMA_VERSION) return;
    self::create_tables();
  }

  public static function now() {
    return current_time('mysql');
  }

  public static function generate_token() {
    return bin2hex(random_bytes(32));
  }

  public static function get_briefing_by_token($token) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM " . self::briefings_table() . " WHERE token = %s LIMIT 1",
      sanitize_text_field($token)
    ));
  }

  /**
   * Obtiene todos los briefings de un cliente central.
   */
  public static function get_by_client($client_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM " . self::briefings_table() . " WHERE ttb_client_id = %d ORDER BY created_at DESC",
      (int)$client_id
    ));
  }

  /**
   * URL del portal de cliente para este briefing (magic link).
   */
  public static function client_url($token) {
    return home_url('/briefing?ttb_briefing=' . urlencode($token));
  }

  /**
   * Extraer ID de carpeta de Drive desde su URL.
   */
  public static function extract_drive_folder_id($url) {
    if (!$url) return null;
    // Formato: https://drive.google.com/drive/folders/FOLDER_ID
    if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $url, $m)) {
      return $m[1];
    }
    // Formato: https://drive.google.com/drive/u/0/folders/FOLDER_ID
    if (preg_match('#folders/([a-zA-Z0-9_-]+)#', $url, $m)) {
      return $m[1];
    }
    return null;
  }

  public static function log($briefing_id, $client_id, $event, $actor = 'system', $detail = []) {
    global $wpdb;
    $table = self::audit_table();

    if (is_array($detail)) {
      foreach (['password', 'pass', 'p', 'token', 'pass_hash'] as $k) {
        if (isset($detail[$k])) $detail[$k] = '***';
      }
    }

    $wpdb->insert($table, [
      'briefing_id' => $briefing_id ? (int)$briefing_id : null,
      'client_id'   => $client_id   ? (int)$client_id   : null,
      'event'       => sanitize_text_field($event),
      'actor'       => sanitize_text_field($actor),
      'detail'      => !empty($detail) ? wp_json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
      'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
      'ua'          => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
      'created_at'  => self::now(),
    ]);
  }

  public static function event_catalog() {
    return [
      'briefing_created'        => ['📄 Briefing creado',              '#ecfdf5', '#6ee7b7', '#065f46'],
      'briefing_updated'        => ['✏️ Briefing editado',              '#eff6ff', '#bfdbfe', '#1d4ed8'],
      'briefing_deleted'        => ['🗑️ Briefing eliminado',            '#fff1f2', '#fecdd3', '#be123c'],
      'email_sent'              => ['📧 Email enviado al cliente',      '#fdf4ff', '#e9d5ff', '#7e22ce'],
      'email_reminder_sent'     => ['⏰ Recordatorio enviado',          '#f5f3ff', '#ddd6fe', '#5b21b6'],
      'email_resources_reminder'=> ['📦 Recordatorio recursos enviado','#fff7ed', '#fed7aa', '#9a3412'],
      'client_view'             => ['👁️ Cliente vio el briefing',       '#f0f9ff', '#bae6fd', '#0369a1'],
      'briefing_accepted'       => ['✅ Briefing aceptado',             '#ecfdf5', '#6ee7b7', '#065f46'],
      'briefing_rejected'       => ['✏️ Briefing rechazado',            '#fffbeb', '#fcd34d', '#92400e'],
      'folder_created'          => ['📁 Carpeta Drive creada',          '#eff6ff', '#bfdbfe', '#1d4ed8'],
      'folder_shared'           => ['🔗 Carpeta Drive compartida',      '#ecfdf5', '#a7f3d0', '#065f46'],
      'nonce_failed'            => ['⚠️ Nonce inválido',                '#fff1f2', '#fecdd3', '#be123c'],
      'invalid_token'           => ['🚫 Token inválido',                '#fff1f2', '#fecdd3', '#be123c'],
    ];
  }
}