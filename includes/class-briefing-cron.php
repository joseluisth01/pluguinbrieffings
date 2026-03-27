<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Briefing_Cron')) return;

/**
 * TTB_Briefing_Cron
 * Gestiona recordatorios automáticos del módulo Briefing:
 * 1. Recordatorio de revisión: si el cliente no ha respondido al briefing.
 * 2. Recordatorio de recursos: si el cliente aceptó pero no ha subido recursos.
 */
class TTB_Briefing_Cron {

  const HOOK = 'ttb_briefing_check_reminders';

  public static function register() {
    add_action(self::HOOK, [__CLASS__, 'run']);
    if (!wp_next_scheduled(self::HOOK)) {
      wp_schedule_event(time(), 'daily', self::HOOK);
    }
  }

  public static function deregister() {
    $ts = wp_next_scheduled(self::HOOK);
    if ($ts) wp_unschedule_event($ts, self::HOOK);
  }

  public static function run() {
    self::send_pending_reminders();
    self::send_resources_reminders();
  }

  /**
   * Recordatorio a clientes que no han respondido al briefing.
   */
  private static function send_pending_reminders() {
    global $wpdb;
    $table = TTB_Briefing_DB::briefings_table();

    $days        = max(1, (int)get_option('ttb_briefing_resend_days', 3));
    $max_resends = (int)get_option('ttb_briefing_max_resends', 5);

    $pending = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table
       WHERE status = 'pending'
         AND (notified_at IS NULL OR notified_at < DATE_SUB(NOW(), INTERVAL %d DAY))",
      $days
    ));

    if (!$pending) return;

    $mailer = new TTB_Briefing_Mailer();

    foreach ($pending as $b) {
      if ($max_resends > 0 && (int)$b->notif_count >= $max_resends) {
        TTB_Logger::log('Briefing cron: max resends reached', ['briefing_id' => $b->id]);
        continue;
      }

      $client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", (int)$b->ttb_client_id
      ));
      if (!$client) continue;

      $mailer->send_reminder($b, $client);

      $wpdb->update($table, [
        'notified_at' => TTB_Briefing_DB::now(),
        'notif_count' => (int)$b->notif_count + 1,
        'updated_at'  => TTB_Briefing_DB::now(),
      ], ['id' => $b->id]);

      TTB_Briefing_DB::log($b->id, $b->ttb_client_id, 'email_reminder_sent', 'cron', [
        'notif_count' => (int)$b->notif_count + 1,
        'days_config' => $days,
      ]);

      TTB_Logger::log('Briefing cron: reminder sent', ['briefing_id' => $b->id, 'client_id' => $b->ttb_client_id]);
    }
  }

  /**
   * Recordatorio a clientes que aceptaron el briefing pero aún no han subido recursos.
   */
  private static function send_resources_reminders() {
    global $wpdb;
    $table = TTB_Briefing_DB::briefings_table();

    // Solo si hay carpeta compartida configurada
    $days_resources = max(1, (int)get_option('ttb_briefing_resources_reminder_days', 2));

    // Briefings aceptados con carpeta, sin recordatorio de recursos enviado todavía
    // (o que ha pasado el tiempo configurado desde el último)
    $accepted = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table
       WHERE status = 'accepted'
         AND shared_folder_url IS NOT NULL
         AND shared_folder_url != ''
         AND resources_reminder_sent = 0
         AND responded_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
      $days_resources
    ));

    if (!$accepted) return;

    $mailer = new TTB_Briefing_Mailer();

    foreach ($accepted as $b) {
      $client = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . TTB_DB::clients_table() . " WHERE id=%d", (int)$b->ttb_client_id
      ));
      if (!$client) continue;

      $mailer->send_resources_reminder($b, $client);

      $wpdb->update($table, [
        'resources_reminder_sent' => 1,
        'updated_at'              => TTB_Briefing_DB::now(),
      ], ['id' => $b->id]);

      TTB_Briefing_DB::log($b->id, $b->ttb_client_id, 'email_resources_reminder', 'cron', []);
      TTB_Logger::log('Briefing cron: resources reminder sent', ['briefing_id' => $b->id]);
    }
  }
}