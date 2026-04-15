<?php
if (!defined('ABSPATH')) exit;

class TTB_DB {

  public static function clients_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_clients';
  }

  public static function answers_table() {
    global $wpdb;
    return $wpdb->prefix . 'ttb_answers';
  }

  public static function now() {
    return current_time('mysql');
  }

  /**
   * Migración: añade columnas faltantes a ttb_clients si no existen.
   * Se llama desde plugins_loaded para garantizar que siempre están presentes.
   */
  public static function run_migrations() {
    global $wpdb;
    $table = self::clients_table();

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) return;

    // Columna emails
    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'emails'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `emails` LONGTEXT NULL AFTER `email`");
      $wpdb->query("UPDATE `$table` SET `emails` = JSON_ARRAY(`email`) WHERE `emails` IS NULL");
    }

    // Columna pass_raw — guarda la contraseña en texto plano para poder reenviarla
    // Se cifra con base64 (ofuscación mínima, no seguridad criptográfica).
    // La seguridad real viene del hash bcrypt en pass_hash.
    $col2 = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'pass_raw'");
    if (empty($col2)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `pass_raw` VARCHAR(500) NULL AFTER `pass_hash`");
    }
  }
}