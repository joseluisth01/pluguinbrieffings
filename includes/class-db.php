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
}
