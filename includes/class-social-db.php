<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_DB')) return;

class TTB_Social_DB {

  const SCHEMA_VERSION = 5; // v5: columna creative_urls (JSON array para múltiples archivos)

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

  public static function editorial_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_social_editorial';
  }

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $clients   = self::clients_table();
    $content   = self::content_table();
    $posts     = self::posts_table();
    $audit     = self::audit_table();
    $editorial = self::editorial_table();

    $sql1 = "CREATE TABLE $clients (
      id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      ttb_client_id BIGINT UNSIGNED NULL,
      name          VARCHAR(190) NOT NULL,
      emails        LONGTEXT NOT NULL,
      token         VARCHAR(64) NOT NULL,
      networks      LONGTEXT NULL,
      notes         TEXT NULL,
      status        VARCHAR(40) NOT NULL DEFAULT 'active',
      kit_digital   TINYINT(1) NOT NULL DEFAULT 0,
      created_at    DATETIME NOT NULL,
      updated_at    DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY token_unique (token),
      KEY status_idx (status),
      KEY ttb_client_idx (ttb_client_id)
    ) $charset;";

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

    $sql3 = "CREATE TABLE $posts (
      id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_id       BIGINT UNSIGNED NOT NULL,
      scheduled_date  DATE NOT NULL,
      network         VARCHAR(40) NOT NULL DEFAULT 'all',
      post_type       VARCHAR(40) NOT NULL DEFAULT 'image',
      copy_text       LONGTEXT NULL,
      creative_url    TEXT NULL,
      creative_urls   LONGTEXT NULL COMMENT 'JSON array con todas las URLs de archivos del post',
      creative_note   TEXT NULL,
      week_group      VARCHAR(20) NULL,
      status          VARCHAR(40) NOT NULL DEFAULT 'draft',
      client_note     TEXT NULL,
      notified_at     DATETIME NULL,
      approved_at     DATETIME NULL,
      created_at      DATETIME NOT NULL,
      updated_at      DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY client_idx (client_id),
      KEY date_idx (scheduled_date),
      KEY status_idx (status),
      KEY week_group_idx (week_group)
    ) $charset;";

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

    $sql5 = "CREATE TABLE $editorial (
      id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_id      BIGINT UNSIGNED NOT NULL,
      entry_date     DATE NOT NULL,
      pilar          VARCHAR(255) NOT NULL DEFAULT '',
      gancho         TEXT NULL,
      month_status   VARCHAR(20) NOT NULL DEFAULT 'draft',
      client_note    TEXT NULL,
      notified_at    DATETIME NULL,
      created_at     DATETIME NOT NULL,
      updated_at     DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY client_date (client_id, entry_date),
      KEY client_idx (client_id),
      KEY date_idx (entry_date),
      KEY month_status_idx (month_status)
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);

    self::migrate_add_ttb_client_id();
    self::migrate_v2();
    self::migrate_v3();
    self::migrate_v4();
    self::migrate_v5();

    update_option('ttb_social_schema_version', self::SCHEMA_VERSION);
  }

  private static function migrate_add_ttb_client_id() {
    global $wpdb;
    $table = self::clients_table();
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return;
    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'ttb_client_id'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `ttb_client_id` BIGINT UNSIGNED NULL AFTER `id`");
      $wpdb->query("ALTER TABLE `$table` ADD KEY `ttb_client_idx` (`ttb_client_id`)");
    }
  }

  private static function migrate_v2() {
    global $wpdb;
    $table = self::posts_table();
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return;

    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'week_group'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `week_group` VARCHAR(20) NULL AFTER `creative_note`");
      $wpdb->query("ALTER TABLE `$table` ADD KEY `week_group_idx` (`week_group`)");
    }

    $col_caption = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'caption'");
    $col_copy    = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'copy_text'");
    if (!empty($col_caption) && empty($col_copy)) {
      $wpdb->query("ALTER TABLE `$table` CHANGE `caption` `copy_text` LONGTEXT NULL");
    }

    $col_time = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'scheduled_time'");
    if (!empty($col_time)) {
      $wpdb->query("ALTER TABLE `$table` DROP COLUMN `scheduled_time`");
    }
  }

  private static function migrate_v3() {
    global $wpdb;
    $table = self::editorial_table();
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) {
      self::create_tables();
    }
  }

  private static function migrate_v4() {
    global $wpdb;
    $table = self::clients_table();
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return;
    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'kit_digital'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `kit_digital` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    }
  }

  /**
   * v5: Añade columna creative_urls (JSON array) a ttb_social_posts.
   * creative_url (singular) se conserva para retrocompatibilidad con posts existentes.
   */
  private static function migrate_v5() {
    global $wpdb;
    $table = self::posts_table();
    if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) return;
    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'creative_urls'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `creative_urls` LONGTEXT NULL AFTER `creative_url`");
    }
  }

  public static function run_migrations() {
    $current = (int) get_option('ttb_social_schema_version', 0);
    if ($current >= self::SCHEMA_VERSION) return;
    self::create_tables();
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

  public static function client_url($token) {
    return home_url('/briefing?social=' . urlencode($token));
  }

  public static function week_group_for_date($date_str) {
    $ts = strtotime($date_str);
    if (!$ts) return null;
    return date('o-\\WW', $ts);
  }

  public static function week_range_label($week_group) {
    if (!$week_group || !preg_match('/^(\d{4})-W(\d{2})$/', $week_group, $m)) return $week_group;
    $year = (int)$m[1];
    $week = (int)$m[2];
    $monday = new DateTime();
    $monday->setISODate($year, $week, 1);
    $sunday = clone $monday;
    $sunday->modify('+6 days');
    return $monday->format('d/m') . ' al ' . $sunday->format('d/m');
  }

  /**
   * Devuelve el array de URLs de archivos de un post.
   * Soporta tanto el nuevo campo creative_urls (JSON array) como el legacy creative_url.
   */
  public static function get_post_creative_urls($post) {
    if (!empty($post->creative_urls)) {
      $decoded = json_decode($post->creative_urls, true);
      if (is_array($decoded) && !empty($decoded)) {
        return $decoded;
      }
    }
    if (!empty($post->creative_url)) {
      return [$post->creative_url];
    }
    return [];
  }

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

  public static function editorial_month_statuses() {
    return [
      'draft'    => ['Borrador / Sin enviar', '#f3f4f6', '#e5e7eb', '#374151'],
      'sent'     => ['Enviado al cliente',    '#fffbeb', '#fde68a', '#92400e'],
      'approved' => ['Aprobado',              '#ecfdf5', '#6ee7b7', '#065f46'],
      'rejected' => ['Rechazado',             '#fff1f2', '#fecdd3', '#be123c'],
    ];
  }

  public static function get_editorial_month_status($client_id, $month) {
    global $wpdb;
    $table = self::editorial_table();
    [$year, $mon] = explode('-', $month);

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT month_status, client_note, notified_at FROM $table
       WHERE client_id=%d AND YEAR(entry_date)=%d AND MONTH(entry_date)=%d
       LIMIT 1",
      $client_id, (int)$year, (int)$mon
    ));

    return $row ?? null;
  }

  public static function set_editorial_month_status($client_id, $month, $status, $client_note = null) {
    global $wpdb;
    $table = self::editorial_table();
    [$year, $mon] = explode('-', $month);

    $update = ['month_status' => $status, 'updated_at' => self::now()];
    if ($status === 'sent') $update['notified_at'] = self::now();
    if ($client_note !== null) $update['client_note'] = $client_note;

    $wpdb->query($wpdb->prepare(
      "UPDATE $table SET month_status=%s, updated_at=%s " .
      ($status === 'sent' ? ", notified_at=%s " : "") .
      ($client_note !== null ? ", client_note=%s " : "") .
      "WHERE client_id=%d AND YEAR(entry_date)=%d AND MONTH(entry_date)=%d",
      ...array_values(array_filter([
        $status,
        self::now(),
        $status === 'sent' ? self::now() : null,
        $client_note !== null ? $client_note : null,
        $client_id,
        (int)$year,
        (int)$mon,
      ], fn($v) => $v !== null))
    ));
  }

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

  public static function clear_audit() {
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE " . self::audit_table());
  }
}