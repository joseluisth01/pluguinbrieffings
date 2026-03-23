<?php
if (!defined('ABSPATH')) exit;

/**
 * TTB_WebRev_Cron
 * Gestiona el reenvío automático de emails a clientes que no han respondido.
 */
class TTB_WebRev_Cron {

  const HOOK = 'ttb_webrev_check_reminders';

  public static function register() {
    add_action(self::HOOK, [__CLASS__, 'run']);

    if (!wp_next_scheduled(self::HOOK)) {
      // Ejecutar una vez al día
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
    $table = TTB_WebRev_DB::projects_table();

    $days        = max(1, (int)get_option('ttb_webrev_resend_days', 7));
    $max_resends = (int)get_option('ttb_webrev_max_resends', 3);

    // Solo proyectos pendientes (ni aceptados ni con cambios en curso ya resueltos)
    $pending = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table
       WHERE status IN ('pending','changes_requested')
         AND (last_notified IS NULL OR last_notified < DATE_SUB(NOW(), INTERVAL %d DAY))",
      $days
    ));

    if (!$pending) return;

    $mailer = new TTB_WebRev_Mailer();

    foreach ($pending as $project) {
      // Comprobar límite de reenvíos
      if ($max_resends > 0 && (int)$project->notif_count >= $max_resends) {
        continue;
      }

      $mailer->send_review_invitation($project);

      $wpdb->update($table, [
        'last_notified' => TTB_WebRev_DB::now(),
        'notif_count'   => (int)$project->notif_count + 1,
        'updated_at'    => TTB_WebRev_DB::now(),
      ], ['id' => $project->id]);

      TTB_Logger::log('WebRev cron: reminder sent', [
        'project_id'   => $project->id,
        'name'         => $project->name,
        'notif_count'  => (int)$project->notif_count + 1,
      ]);
    }
  }
}