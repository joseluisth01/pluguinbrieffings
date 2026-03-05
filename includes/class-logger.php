<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple file logger for TicTac Briefing Portal
 * Writes to: wp-content/uploads/ttb-briefing-portal/ttb.log
 */
class TTB_Logger {

  public static function log($message, $context = []) {
    try {
      $upload = wp_upload_dir();
      $dir = trailingslashit($upload['basedir']) . 'ttb-briefing-portal/';
      if (!file_exists($dir)) {
        wp_mkdir_p($dir);
      }
      $file = $dir . 'ttb.log';

      $ts = date_i18n('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? '-';

      if (is_array($context) && !empty($context)) {
        // Avoid logging passwords or secrets
        if (isset($context['password'])) $context['password'] = '***';
        if (isset($context['pass']))     $context['pass']     = '***';
        if (isset($context['p']))        $context['p']        = '***';
        $ctx = wp_json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      } else {
        $ctx = '';
      }

      $line = "[$ts] [$ip] $message";
      if ($ctx) $line .= " | $ctx";
      $line .= " | UA: " . $ua . PHP_EOL;

      // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
      file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
      // Last resort: do nothing
    }
  }
}