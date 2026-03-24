<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Cron')) return;

/**
 * TTB_Social_Cron
 * - Reenvía notificaciones de aprobación a clientes que no han respondido
 * - (Futuro: alertas de publicaciones del día)
 */
class TTB_Social_Cron {

  const HOOK = 'ttb_social_check_reminders';

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
    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $clients_table = TTB_Social_DB::clients_table();

    $days        = max(1, (int)get_option('ttb_social_resend_days', 2));
    $max_resends = (int)get_option('ttb_social_max_resends', 3);

    // Posts en estado pending_approval sin respuesta durante N días
    $pending = $wpdb->get_results($wpdb->prepare(
      "SELECT p.*, c.name AS client_name, c.emails AS client_emails, c.token AS client_token
       FROM $posts_table p
       INNER JOIN $clients_table c ON c.id = p.client_id
       WHERE p.status = 'pending_approval'
         AND (p.notified_at IS NULL OR p.notified_at < DATE_SUB(NOW(), INTERVAL %d DAY))",
      $days
    ));

    if (!$pending) return;

    $mailer = new TTB_Social_Mailer();

    foreach ($pending as $post) {
      // Construir objeto client simulado
      $client        = new stdClass();
      $client->id    = $post->client_id;
      $client->name  = $post->client_name;
      $client->emails= $post->client_emails;
      $client->token = $post->client_token;

      $mailer->send_post_approval($client, $post);

      $wpdb->update($posts_table, [
        'notified_at' => TTB_Social_DB::now(),
        'updated_at'  => TTB_Social_DB::now(),
      ], ['id' => $post->id]);

      TTB_Social_DB::log($post->client_id, $post->id, 'cron_reminder_sent', 'cron', [
        'post_id'     => $post->id,
        'days_config' => $days,
      ]);

      TTB_Logger::log('Social cron: reminder sent', [
        'post_id'   => $post->id,
        'client_id' => $post->client_id,
      ]);
    }
  }
}