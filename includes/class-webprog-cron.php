<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebProg_Cron')) return;

/**
 * TTB_WebProg_Cron
 * Gestiona el reenvío automático de emails a clientes de Prog. Web que no han respondido.
 */
class TTB_WebProg_Cron {

  const HOOK = 'ttb_webprog_check_reminders';

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

  /**
   * Lógica principal: busca proyectos pendientes sin respuesta
   * y les reenvía el email si han pasado los días configurados.
   */
  public static function run() {
    global $wpdb;
    $table = TTB_WebProg_DB::projects_table();

    $days        = max(1, (int)get_option('ttb_webprog_resend_days', 7));
    $max_resends = (int)get_option('ttb_webprog_max_resends', 3);

    $pending = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table
       WHERE status IN ('pending','changes_requested')
         AND (last_notified IS NULL OR last_notified < DATE_SUB(NOW(), INTERVAL %d DAY))",
      $days
    ));

    if (!$pending) return;

    $mailer = new TTB_WebProg_Mailer();

    foreach ($pending as $project) {
      if ($max_resends > 0 && (int)$project->notif_count >= $max_resends) {
        TTB_Logger::log('WebProg cron: max resends reached, skipping', [
          'project_id'  => $project->id,
          'notif_count' => (int)$project->notif_count,
          'max_resends' => $max_resends,
        ]);
        continue;
      }

      $mailer->send_review_invitation($project);

      $new_count = (int)$project->notif_count + 1;

      $wpdb->update($table, [
        'last_notified' => TTB_WebProg_DB::now(),
        'notif_count'   => $new_count,
        'updated_at'    => TTB_WebProg_DB::now(),
      ], ['id' => $project->id]);

      TTB_WebProg_DB::log($project->id, 'cron_reminder_sent', 'cron', [
        'notif_count' => $new_count,
        'status'      => $project->status,
        'days_config' => $days,
      ]);

      TTB_Logger::log('WebProg cron: reminder sent', [
        'project_id'  => $project->id,
        'name'        => $project->name,
        'notif_count' => $new_count,
      ]);
    }
  }
}