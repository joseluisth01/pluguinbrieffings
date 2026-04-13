<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Cron')) return;

/**
 * TTB_Social_Cron — v3
 * - Auto-publica posts cuando llega su fecha programada
 * - Recordatorios semanales (agrupa por week_group) en vez de por post individual
 * - Recordatorio un día antes de la publicación (configurable)
 * - Auto-aceptación de posts pending_approval cuando faltan 7 días o menos para su publicación
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
    self::auto_accept_deadline_posts(); // ← NUEVO: primero auto-aceptar los que ya no tienen tiempo
    self::auto_publish_past_posts();
    self::send_weekly_reminders();
    self::send_eve_reminders();
  }

  /**
   * NUEVO: AUTO-ACEPTACIÓN POR FECHA LÍMITE
   * Posts en estado 'pending_approval' cuya fecha de publicación es en 7 días o menos
   * se auto-aceptan automáticamente. Solo afecta a pending_approval, no a rejected.
   */
  private static function auto_accept_deadline_posts() {
    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $clients_table = TTB_Social_DB::clients_table();

    $deadline_date = date('Y-m-d', strtotime('+7 days', strtotime(current_time('Y-m-d'))));

    // Posts pending_approval con fecha de publicación en 7 días o menos (pero futura o de hoy)
    $to_auto_accept = $wpdb->get_results($wpdb->prepare(
      "SELECT p.*, c.name AS client_name, c.emails AS client_emails, c.token AS client_token
       FROM $posts_table p
       INNER JOIN $clients_table c ON c.id = p.client_id
       WHERE p.status = 'pending_approval'
         AND p.scheduled_date >= %s
         AND p.scheduled_date <= %s",
      current_time('Y-m-d'),
      $deadline_date
    ));

    if (!$to_auto_accept) return;

    foreach ($to_auto_accept as $post) {
      $wpdb->update($posts_table, [
        'status'      => 'approved',
        'approved_at' => TTB_Social_DB::now(),
        'updated_at'  => TTB_Social_DB::now(),
      ], ['id' => $post->id]);

      TTB_Social_DB::log($post->client_id, $post->id, 'post_auto_accepted', 'cron', [
        'scheduled_date' => $post->scheduled_date,
        'days_remaining' => (int)ceil((strtotime($post->scheduled_date) - strtotime(current_time('Y-m-d'))) / 86400),
      ]);

      TTB_Logger::log('Social cron: post auto-accepted (deadline)', [
        'post_id'        => $post->id,
        'client_id'      => $post->client_id,
        'scheduled_date' => $post->scheduled_date,
      ]);
    }

    if ($to_auto_accept) {
      TTB_Logger::log('Social cron: auto-accepted ' . count($to_auto_accept) . ' posts by deadline');
    }
  }

  /**
   * 1. AUTO-PUBLICACIÓN
   * Posts aprobados cuya fecha de publicación ya pasó → marcar como "published"
   */
  private static function auto_publish_past_posts() {
    global $wpdb;
    $posts_table = TTB_Social_DB::posts_table();
    $today       = current_time('Y-m-d');

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
   */
  private static function send_weekly_reminders() {
    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $clients_table = TTB_Social_DB::clients_table();

    $days        = max(1, (int)get_option('ttb_social_resend_days', 2));
    $max_resends = (int)get_option('ttb_social_max_resends', 3);

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
      $max_notif_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT DATE(notified_at)) FROM $posts_table
         WHERE client_id=%d AND week_group=%s AND notified_at IS NOT NULL",
        $group->client_id, $group->week_group
      ));

      if ($max_resends > 0 && $max_notif_count >= $max_resends) continue;

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
   */
  private static function send_eve_reminders() {
    $enabled = get_option('ttb_social_eve_reminder', '0');
    if ($enabled !== '1') return;

    global $wpdb;
    $posts_table   = TTB_Social_DB::posts_table();
    $clients_table = TTB_Social_DB::clients_table();

    $tomorrow = date('Y-m-d', strtotime('+1 day', strtotime(current_time('Y-m-d'))));

    $eve_posts = $wpdb->get_results($wpdb->prepare(
      "SELECT p.*, c.name AS client_name, c.emails AS client_emails, c.token AS client_token
       FROM $posts_table p
       INNER JOIN $clients_table c ON c.id = p.client_id
       WHERE p.status = 'pending_approval'
         AND p.scheduled_date = %s",
      $tomorrow
    ));

    if (!$eve_posts) return;

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