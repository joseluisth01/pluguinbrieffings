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
   * Migración: añade la columna `emails` a ttb_clients si no existe.
   * Se llama desde plugins_loaded para garantizar que siempre está presente.
   */
  public static function run_migrations() {
    global $wpdb;
    $table = self::clients_table();

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) return;

    $col = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'emails'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE `$table` ADD COLUMN `emails` LONGTEXT NULL AFTER `email`");
      // Rellenar emails con el email existente para los clientes ya creados
      $wpdb->query("UPDATE `$table` SET `emails` = JSON_ARRAY(`email`) WHERE `emails` IS NULL");
    }
  }
}