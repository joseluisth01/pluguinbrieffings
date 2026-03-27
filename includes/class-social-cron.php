<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Cron')) return;

/**
 * TTB_Social_Cron — v2
 * - Auto-publica posts cuando llega su fecha programada
 * - Recordatorios semanales (agrupa por week_group) en vez de por post individual
 * - Recordatorio un día antes de la publicación (configurable)
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
    self::auto_publish_past_posts();
    self::send_weekly_reminders();
    self::send_eve_reminders();
  }

  /**
   * 1. AUTO-PUBLICACIÓN
   * Posts aprobados cuya fecha de publicación ya pasó → marcar como "published"
   */
  private static function auto_publish_past_posts() {
    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $today       = current_time('Y-m-d');

    // Posts aprobados con fecha <= hoy → publicar
    $to_publish = $wpdb->get_results($wpdb->prepare(
      "SELECT id, client_id, scheduled_date FROM $posts_table
       WHERE status = 'approved' AND scheduled_date <= %s",
      $today
    ));

    foreach ($to_publish as $post) {
      $wpdb->update($posts_table, [
        'status'     => 'published',
        'updated_at' => TTB_Social_DB::now(),
      ], ['id' => $post->id]);

      TTB_Social_DB::log($post->client_id, $post->id, 'post_auto_published', 'cron', [
        'scheduled_date' => $post->scheduled_date,
      ]);
    }

    if ($to_publish) {
      TTB_Logger::log('Social cron: auto-published ' . count($to_publish) . ' posts');
    }
  }

  /**
   * 2. RECORDATORIOS SEMANALES
   * Agrupa posts pendientes por week_group y cliente.
   * Si todos los posts de esa semana siguen pendientes y han pasado N días
   * desde la última notificación → reenvía notificación semanal.
   */
  private static function send_weekly_reminders() {
    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $clients_table = TTB_Social_DB::clients_table();

    $days        = max(1, (int)get_option('ttb_social_resend_days', 2));
    $max_resends = (int)get_option('ttb_social_max_resends', 3);

    // Buscar posts pendientes agrupados por cliente + semana
    $pending_groups = $wpdb->get_results($wpdb->prepare(
      "SELECT p.client_id, p.week_group, MIN(p.notified_at) AS oldest_notified, COUNT(*) AS post_count,
              c.name AS client_name, c.emails AS client_emails, c.token AS client_token
       FROM $posts_table p
       INNER JOIN $clients_table c ON c.id = p.client_id
       WHERE p.status = 'pending_approval'
         AND p.week_group IS NOT NULL
       GROUP BY p.client_id, p.week_group
       HAVING oldest_notified IS NULL OR oldest_notified < DATE_SUB(NOW(), INTERVAL %d DAY)",
      $days
    ));

    if (!$pending_groups) return;

    $mailer = new TTB_Social_Mailer();

    foreach ($pending_groups as $group) {
      // Contar cuántas veces se ha notificado esta semana (usar el mayor notif count entre los posts)
      $max_notif_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT DATE(notified_at)) FROM $posts_table
         WHERE client_id=%d AND week_group=%s AND notified_at IS NOT NULL",
        $group->client_id, $group->week_group
      ));

      if ($max_resends > 0 && $max_notif_count >= $max_resends) continue;

      // Obtener todos los posts de esta semana
      $week_posts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $posts_table
         WHERE client_id=%d AND week_group=%s AND status='pending_approval'
         ORDER BY scheduled_date ASC",
        $group->client_id, $group->week_group
      ));

      if (!$week_posts) continue;

      $client        = new stdClass();
      $client->id    = $group->client_id;
      $client->name  = $group->client_name;
      $client->emails= $group->client_emails;
      $client->token = $group->client_token;

      $mailer->send_week_approval($client, $week_posts);

      // Actualizar notified_at de todos los posts de la semana
      $ids = array_map(fn($p) => (int)$p->id, $week_posts);
      $ids_str = implode(',', $ids);
      $wpdb->query($wpdb->prepare(
        "UPDATE $posts_table SET notified_at=%s, updated_at=%s WHERE id IN ($ids_str)",
        TTB_Social_DB::now(), TTB_Social_DB::now()
      ));

      TTB_Social_DB::log($group->client_id, null, 'cron_reminder_sent', 'cron', [
        'week_group'  => $group->week_group,
        'posts_count' => count($week_posts),
        'days_config' => $days,
      ]);

      TTB_Logger::log('Social cron: weekly reminder sent', [
        'client_id'  => $group->client_id,
        'week_group' => $group->week_group,
        'posts'      => count($week_posts),
      ]);
    }
  }

  /**
   * 3. RECORDATORIO DE VÍSPERA
   * Un día antes de la fecha de publicación, si el post sigue pendiente
   * de aprobación, se envía un recordatorio urgente.
   * Solo se activa si ttb_social_eve_reminder = '1'
   */
  private static function send_eve_reminders() {
    $enabled = get_option('ttb_social_eve_reminder', '0');
    if ($enabled !== '1') return;

    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $clients_table = TTB_Social_DB::clients_table();

    $tomorrow = date('Y-m-d', strtotime('+1 day', strtotime(current_time('Y-m-d'))));

    // Posts que se publican mañana y aún están pendientes
    $eve_posts = $wpdb->get_results($wpdb->prepare(
      "SELECT p.*, c.name AS client_name, c.emails AS client_emails, c.token AS client_token
       FROM $posts_table p
       INNER JOIN $clients_table c ON c.id = p.client_id
       WHERE p.status = 'pending_approval'
         AND p.scheduled_date = %s",
      $tomorrow
    ));

    if (!$eve_posts) return;

    // Agrupar por cliente
    $by_client = [];
    foreach ($eve_posts as $post) {
      $by_client[$post->client_id][] = $post;
    }

    $mailer = new TTB_Social_Mailer();

    foreach ($by_client as $client_id => $posts) {
      $first = $posts[0];
      $client        = new stdClass();
      $client->id    = $client_id;
      $client->name  = $first->client_name;
      $client->emails= $first->client_emails;
      $client->token = $first->client_token;

      $mailer->send_eve_reminder($client, $posts);

      foreach ($posts as $post) {
        TTB_Social_DB::log($client_id, $post->id, 'cron_eve_reminder', 'cron', [
          'scheduled_date' => $post->scheduled_date,
        ]);
      }

      TTB_Logger::log('Social cron: eve reminder sent', [
        'client_id' => $client_id,
        'posts'     => count($posts),
        'date'      => $tomorrow,
      ]);
    }
  }
}