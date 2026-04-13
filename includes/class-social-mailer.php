<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Mailer')) return;

class TTB_Social_Mailer {

  private $pink = '#D72173';
  private $logo = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';

  public function send_welcome($client) {
    $emails = $this->parse_emails($client->emails);
    if (!$emails) return;
    $url     = $this->smart_url($client->token);
    $subject = 'Tu portal de Redes Sociales está listo — TicTac Comunicación';
    $message = $this->tpl_welcome($client->name, $url);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_week_approval($client, $posts) {
    $emails = $this->parse_emails($client->emails);
    if (!$emails) return;

    $url   = $this->smart_url($client->token);
    $dates = array_map(fn($p) => $p->scheduled_date, $posts);
    sort($dates);
    $first_date = date_i18n('d/m', strtotime($dates[0]));
    $last_date  = date_i18n('d/m', strtotime(end($dates)));
    $count      = count($posts);
    $range_label = ($first_date === $last_date) ? $first_date : $first_date . ' al ' . $last_date;

    // Calcular la fecha límite de aprobación (7 días antes de la publicación más cercana)
    $earliest_date    = $dates[0];
    $deadline_ts      = strtotime($earliest_date) - (7 * 86400);
    $deadline_display = date_i18n('d/m/Y', $deadline_ts);
    $days_left        = (int)ceil(($deadline_ts - strtotime(current_time('Y-m-d'))) / 86400);

    $subject = $count > 1
      ? 'Tienes ' . $count . ' publicaciones para revisar (' . $range_label . ') — TicTac Comunicación'
      : 'Creatividad lista para revisar (' . $range_label . ') — TicTac Comunicación';

    $message = $this->tpl_week_approval($client->name, $url, $posts, $range_label, $deadline_display, $days_left);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_eve_reminder($client, $posts) {
    $emails = $this->parse_emails($client->emails);
    if (!$emails) return;
    $url   = $this->smart_url($client->token);
    $count = count($posts);
    $subject = '⚠️ ' . ($count > 1 ? $count . ' publicaciones se publican MAÑANA' : 'Tu publicación se publica MAÑANA')
             . ' — TicTac Comunicación';
    $message = $this->tpl_eve_reminder($client->name, $url, $posts);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_approved_alert($client, $post) {
    $to      = $this->internal_emails();
    $date    = date_i18n('d/m/Y', strtotime($post->scheduled_date));
    $subject = 'Post aprobado — ' . $client->name . ' — ' . $date;
    $message = $this->tpl_internal_approved($client, $post, $date);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($to as $email) wp_mail($email, $subject, $message, $headers);
  }

  public function send_rejected_alert($client, $post) {
    $to      = $this->internal_emails();
    $date    = date_i18n('d/m/Y', strtotime($post->scheduled_date));
    $subject = 'Post rechazado con comentarios — ' . $client->name . ' — ' . $date;
    $message = $this->tpl_internal_rejected($client, $post, $date);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($to as $email) wp_mail($email, $subject, $message, $headers);
  }

  public function send_content_received($client, $count) {
    $to      = $this->internal_emails();
    $subject = 'Nuevo contenido recibido — ' . $client->name . ' (' . $count . ' archivo' . ($count > 1 ? 's' : '') . ')';
    $portal  = home_url('/briefing?section=redes-sociales&sstab=content');
    $message = $this->tpl_content_received($client->name, $count, $portal);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($to as $email) wp_mail($email, $subject, $message, $headers);
  }

  public function send_editorial_month_ready($client, $month) {
    $emails = $this->parse_emails($client->emails);
    if (!$emails) return;

    $first_day  = mktime(0, 0, 0, (int)substr($month, 5, 2), 1, (int)substr($month, 0, 4));
    $month_name = ucfirst(date_i18n('F Y', $first_day));
    $url = $this->smart_url_editorial($client->token, $month);

    $subject = '📅 Tu calendario editorial de ' . $month_name . ' está listo — TicTac Comunicación';
    $message = $this->tpl_editorial_month_ready($client->name, $url, $month_name);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_editorial_client_response($client, $month, $action, $note = null) {
    $to         = $this->internal_emails();
    $first_day  = mktime(0, 0, 0, (int)substr($month, 5, 2), 1, (int)substr($month, 0, 4));
    $month_name = ucfirst(date_i18n('F Y', $first_day));

    if ($action === 'approved') {
      $subject = '✅ Calendario editorial aprobado — ' . $client->name . ' — ' . $month_name;
    } else {
      $subject = '✏️ Calendario editorial con comentarios — ' . $client->name . ' — ' . $month_name;
    }

    $portal_url = home_url('/briefing?section=redes-sociales&sstab=editorial&sc_client=' . (int)$client->id . '&ed_month=' . $month);
    $message    = $this->tpl_editorial_response_internal($client->name, $month_name, $action, $note, $portal_url);
    $headers    = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($to as $email) wp_mail($email, $subject, $message, $headers);
  }

  // Compatibilidad legacy
  public function send_post_approval($client, $post) {
    $this->send_week_approval($client, [$post]);
  }

  public function parse_emails($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return array_filter($decoded, 'is_email');
    return array_filter(array_map('trim', explode(',', (string)$raw)), 'is_email');
  }

  private function smart_url($token) {
    return home_url('/briefing?ttb_social_entry=' . urlencode($token));
  }

  private function smart_url_editorial($token, $month) {
    return home_url('/briefing?ttb_social_entry=' . urlencode($token) . '&stab=editorial&ed_month=' . urlencode($month));
  }

  private function internal_emails() {
    $a = get_option('ttb_social_notify_social', 'comunicacion@tictac-comunicacion.es');
    $b = get_option('ttb_social_notify_hola',   'hola@tictac-comunicacion.es');
    return array_filter(array_map('trim', [$a, $b]));
  }

  private function is_video_url($url) {
    if (!$url) return false;
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'mov', 'webm', 'avi', 'm4v'], true);
  }

  private function wrap($header_html, $body_html) {
    $pink = $this->pink;
    $logo = $this->logo;
    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
      <tr>
        <td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:32px 32px 28px">
          <img src="' . $logo . '" alt="TicTac" width="140" style="display:block;margin:0 auto 16px">
          ' . $header_html . '
        </td>
      </tr>
      <tr>
        <td style="background:#fff;padding:32px 36px">
          ' . $body_html . '
        </td>
      </tr>
      <tr>
        <td align="center" style="background:#1a1a2e;padding:18px 32px">
          <p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">&copy; ' . date('Y') . ' TicTac Comunicaci&oacute;n Digital</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>';
  }

  // ─────────────────────────────────────────────────────────
  // TEMPLATES
  // ─────────────────────────────────────────────────────────

  private function tpl_welcome($name, $url) {
    $pink   = $this->pink;
    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">Tu portal de Redes Sociales</h1>
               <p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">Revisa publicaciones y comparte tu contenido con nosotros</p>';
    $body = '
      <p style="margin:0 0 6px;font-size:17px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span></p>
      <p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.6">Hemos creado tu espacio para gestionar las redes sociales con TicTac.</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:12px;margin-bottom:24px">
        <tr><td style="padding:18px 20px">
          <p style="margin:0 0 8px;font-size:14px;color:#4b5563;line-height:1.5">&#x2714;&#xFE0F;&nbsp; Subir fotos y v&iacute;deos para tus publicaciones.</p>
          <p style="margin:0 0 8px;font-size:14px;color:#4b5563;line-height:1.5">&#x2714;&#xFE0F;&nbsp; Ver el calendario con las publicaciones programadas.</p>
          <p style="margin:0;font-size:14px;color:#4b5563;line-height:1.5">&#x2714;&#xFE0F;&nbsp; Aprobar o pedir cambios en las creatividades.</p>
        </td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
        <tr><td align="center">
          <a href="' . esc_url($url) . '" target="_blank" rel="noopener"
             style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                    color:#fff;text-decoration:none;font-weight:900;font-size:16px;
                    padding:16px 40px;border-radius:14px;box-shadow:0 8px 24px rgba(215,33,115,.35)">
            Acceder a mi portal &rarr;
          </a>
        </td></tr>
      </table>
      <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.5">
        Si el bot&oacute;n no funciona:<br>
        <a href="' . esc_url($url) . '" style="color:' . $pink . ';word-break:break-all">' . esc_url($url) . '</a>
      </p>';
    return $this->wrap($header, $body);
  }

  /**
   * Email semanal de aprobación — ahora incluye aviso de fecha límite.
   *
   * @param string $deadline_display  Fecha límite formateada (ej: "15/04/2026")
   * @param int    $days_left         Días que quedan hasta la fecha límite
   */
  private function tpl_week_approval($name, $url, $posts, $range_label, $deadline_display = '', $days_left = 999) {
    $pink  = $this->pink;
    $count = count($posts);
    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">' . ($count > 1 ? $count . ' publicaciones para revisar' : 'Creatividad lista para revisar') . '</h1>
               <p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">Semana del ' . esc_html($range_label) . '</p>';

    // Bloque de aviso fecha límite — siempre visible, color varía según urgencia
    $deadline_block = '';
    if ($deadline_display) {
      if ($days_left <= 0) {
        // Ya pasó la fecha límite (no debería llegar aquí, pero por si acaso)
        $dl_bg  = '#fff1f2'; $dl_bc = '#fecdd3'; $dl_co = '#be123c';
        $dl_icon = '🔴';
        $dl_msg  = 'El plazo de aprobaci&oacute;n ha finalizado. La publicaci&oacute;n se realizar&aacute; tal y como est&aacute;.';
      } elseif ($days_left <= 3) {
        $dl_bg  = '#fff1f2'; $dl_bc = '#fecdd3'; $dl_co = '#be123c';
        $dl_icon = '🚨';
        $dl_msg  = 'Solo quedan <strong>' . $days_left . ' d&iacute;a' . ($days_left === 1 ? '' : 's') . '</strong> para solicitar cambios. Pasado este plazo la publicaci&oacute;n se realizar&aacute; tal y como est&aacute;.';
      } elseif ($days_left <= 7) {
        $dl_bg  = '#fff7ed'; $dl_bc = '#fed7aa'; $dl_co = '#9a3412';
        $dl_icon = '⏰';
        $dl_msg  = 'Tienes hasta el <strong>' . $deadline_display . '</strong> para revisar y pedir cambios. Pasada esa fecha, la publicaci&oacute;n se realizar&aacute; tal y como est&aacute;.';
      } else {
        $dl_bg  = '#eff6ff'; $dl_bc = '#bfdbfe'; $dl_co = '#1d4ed8';
        $dl_icon = '📅';
        $dl_msg  = 'Tienes hasta el <strong>' . $deadline_display . '</strong> para revisar y pedir cambios. Pasada esa fecha, la publicaci&oacute;n se realizar&aacute; tal y como est&aacute;.';
      }
      $deadline_block = '
        <div style="background:' . $dl_bg . ';border:1.5px solid ' . $dl_bc . ';border-radius:14px;padding:16px 20px;margin-bottom:24px">
          <p style="margin:0 0 4px;font-size:13px;font-weight:900;color:' . $dl_co . ';text-transform:uppercase;letter-spacing:.05em">' . $dl_icon . ' Fecha l&iacute;mite de aprobaci&oacute;n</p>
          <p style="margin:0;font-size:14px;color:' . $dl_co . ';line-height:1.6">' . $dl_msg . '</p>
        </div>';
    }

    $posts_html = '';
    foreach ($posts as $i => $post) {
      $date_fmt  = date_i18n('l j \d\e F', strtotime($post->scheduled_date));
      $is_video  = ($post->post_type === 'video') || $this->is_video_url($post->creative_url ?? '');
      $copy_text = $post->copy_text ?? '';
      $posts_html .= '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:' . ($i < $count - 1 ? '16px' : '0') . '">';
      $posts_html .= '<p style="margin:0 0 4px;font-size:11px;font-weight:900;color:' . $pink . ';text-transform:uppercase;letter-spacing:.06em">&#x1F4CC; Post ' . ($i + 1) . ' de ' . $count . '</p>';
      $posts_html .= '<p style="margin:0 0 12px;font-size:15px;font-weight:700;color:#1a1a2e;text-transform:capitalize">' . esc_html($date_fmt) . '</p>';
      if ($post->creative_url && !$is_video) {
        $posts_html .= '<div style="border-radius:10px;overflow:hidden;margin-bottom:12px;border:1px solid #e5e7eb"><img src="' . esc_url($post->creative_url) . '" style="width:100%;max-height:300px;object-fit:cover;display:block" alt="Creatividad"></div>';
      } elseif ($is_video) {
        $posts_html .= '<div style="background:#1a1a2e;border-radius:10px;padding:20px;text-align:center;margin-bottom:12px"><p style="margin:0;font-size:28px">&#x1F3AC;</p><p style="margin:6px 0 0;color:#fff;font-size:13px;font-weight:700">V&iacute;deo — ver en el portal</p></div>';
      }
      if ($copy_text) {
        $posts_html .= '<div style="border-left:3px solid ' . $pink . ';padding:8px 14px;margin-bottom:10px;font-size:14px;color:#1a1a2e;line-height:1.6;white-space:pre-line">' . esc_html(mb_substr($copy_text, 0, 200)) . (mb_strlen($copy_text) > 200 ? '...' : '') . '</div>';
      }
      if ($post->creative_note) {
        $posts_html .= '<p style="margin:0;font-size:13px;color:#7e22ce;background:#fdf4ff;border-radius:8px;padding:8px 12px;border:1px solid #e9d5ff">' . esc_html($post->creative_note) . '</p>';
      }
      $posts_html .= '</div>';
    }

    $body = '
      <p style="margin:0 0 18px;font-size:16px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span></p>
      <p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.6">Hemos preparado ' . ($count > 1 ? 'las creatividades de la semana' : 'la creatividad') . ' del <strong>' . esc_html($range_label) . '</strong>. Rev&iacute;sala' . ($count > 1 ? 's' : '') . ' y danos el OK o cu&eacute;ntanos qu&eacute; cambiar&iacute;as.</p>'
      . $deadline_block
      . '<div style="margin-bottom:24px">' . $posts_html . '</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
        <tr><td align="center">
          <a href="' . esc_url($url) . '" target="_blank" rel="noopener"
             style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                    color:#fff;text-decoration:none;font-weight:900;font-size:16px;
                    padding:16px 36px;border-radius:14px;box-shadow:0 8px 24px rgba(215,33,115,.35)">
            Revisar y aprobar &rarr;
          </a>
        </td></tr>
      </table>
      <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.5">Si el bot&oacute;n no funciona:<br><a href="' . esc_url($url) . '" style="color:' . $pink . ';word-break:break-all">' . esc_url($url) . '</a></p>';
    return $this->wrap($header, $body);
  }

  private function tpl_eve_reminder($name, $url, $posts) {
    $pink  = $this->pink;
    $count = count($posts);
    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">&#x26A0;&#xFE0F; Se publica' . ($count > 1 ? 'n' : '') . ' MA&Ntilde;ANA</h1>
               <p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">' . $count . ' publicaci&oacute;n' . ($count > 1 ? 'es pendiente' . 's' : ' pendiente') . ' de aprobaci&oacute;n</p>';
    $list_html = '';
    foreach ($posts as $post) {
      $date_fmt     = date_i18n('l j \d\e F', strtotime($post->scheduled_date));
      $copy_preview = mb_substr($post->copy_text ?? '', 0, 80);
      $list_html .= '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 16px;margin-bottom:10px">';
      $list_html .= '<p style="margin:0 0 4px;font-size:14px;font-weight:700;color:#9a3412">' . esc_html($date_fmt) . '</p>';
      if ($copy_preview) $list_html .= '<p style="margin:0;font-size:13px;color:#c2410c;line-height:1.5">' . esc_html($copy_preview) . ($post->copy_text && mb_strlen($post->copy_text) > 80 ? '...' : '') . '</p>';
      $list_html .= '</div>';
    }
    $body = '
      <p style="margin:0 0 18px;font-size:16px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span></p>
      <p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.6">Tienes ' . $count . ' publicaci&oacute;n' . ($count > 1 ? 'es' : '') . ' programada' . ($count > 1 ? 's' : '') . ' para <strong>ma&ntilde;ana</strong> que a&uacute;n no ha' . ($count > 1 ? 'n' : '') . ' sido aprobada' . ($count > 1 ? 's' : '') . '. Para que podamos publicar a tiempo, necesitamos tu aprobaci&oacute;n hoy.</p>
      <div style="margin-bottom:24px">' . $list_html . '</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
        <tr><td align="center">
          <a href="' . esc_url($url) . '" target="_blank" rel="noopener"
             style="display:inline-block;background:linear-gradient(135deg,#ea580c,#c2410c);
                    color:#fff;text-decoration:none;font-weight:900;font-size:16px;
                    padding:16px 36px;border-radius:14px;box-shadow:0 8px 24px rgba(234,88,12,.35)">
            Aprobar ahora &rarr;
          </a>
        </td></tr>
      </table>';
    return $this->wrap($header, $body);
  }

  private function tpl_internal_approved($client, $post, $date) {
    $pink   = $this->pink;
    $portal = home_url('/briefing?section=redes-sociales&sstab=calendar');
    $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">Post aprobado</h1>';
    $body = '
      <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:18px 22px;margin-bottom:20px">
        <p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#065f46">&#x2705; &iexcl;El cliente ha dado el visto bueno!</p>
        <p style="margin:0;font-size:14px;color:#047857">Ya puedes programar la publicaci&oacute;n.</p>
      </div>
      <div style="background:#f9fafb;border-radius:12px;padding:16px 20px;margin-bottom:20px">
        <p style="margin:0 0 6px;font-size:14px;color:#1a1a2e"><strong>Cliente:</strong> ' . esc_html($client->name) . '</p>
        <p style="margin:0;font-size:14px;color:#1a1a2e"><strong>Fecha programada:</strong> ' . esc_html($date) . '</p>
      </div>
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
           style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                  color:#fff;text-decoration:none;font-weight:900;font-size:14px;padding:12px 28px;border-radius:12px">
          Ver en el portal &rarr;
        </a>
      </td></tr></table>';
    return $this->wrap($header, $body);
  }

  private function tpl_internal_rejected($client, $post, $date) {
    $pink   = $this->pink;
    $portal = home_url('/briefing?section=redes-sociales&sstab=calendar');
    $note   = $post->client_note ? esc_html($post->client_note) : '—';
    $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">Post rechazado con comentarios</h1>';
    $body = '
      <div style="background:#fff1f2;border:1.5px solid #fecdd3;border-radius:14px;padding:18px 22px;margin-bottom:20px">
        <p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#be123c">El cliente ha pedido cambios</p>
        <p style="margin:0;font-size:14px;color:#e11d48">Revisa su comentario y actualiza la creatividad.</p>
      </div>
      <div style="background:#f9fafb;border-radius:12px;padding:16px 20px;margin-bottom:16px">
        <p style="margin:0 0 4px;font-size:14px;color:#1a1a2e"><strong>Cliente:</strong> ' . esc_html($client->name) . '</p>
        <p style="margin:0;font-size:14px;color:#1a1a2e"><strong>Fecha:</strong> ' . esc_html($date) . '</p>
      </div>
      <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:16px 20px;margin-bottom:20px">
        <p style="margin:0 0 6px;font-size:12px;font-weight:900;color:#92400e;text-transform:uppercase">Comentario del cliente</p>
        <p style="margin:0;font-size:15px;color:#1a1a2e;line-height:1.6;white-space:pre-line">' . $note . '</p>
      </div>
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
           style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                  color:#fff;text-decoration:none;font-weight:900;font-size:14px;padding:12px 28px;border-radius:12px">
          Ver en el portal &rarr;
        </a>
      </td></tr></table>';
    return $this->wrap($header, $body);
  }

  private function tpl_content_received($client_name, $count, $portal) {
    $pink   = $this->pink;
    $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">Nuevo contenido recibido</h1>';
    $body = '
      <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:14px;padding:18px 22px;margin-bottom:20px">
        <p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#1d4ed8">' . esc_html($client_name) . '</p>
        <p style="margin:0;font-size:14px;color:#1d4ed8">Ha subido <strong>' . (int)$count . ' archivo' . ((int)$count > 1 ? 's' : '') . '</strong> nuevo' . ((int)$count > 1 ? 's' : '') . ' al portal.</p>
      </div>
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
           style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                  color:#fff;text-decoration:none;font-weight:900;font-size:14px;padding:12px 28px;border-radius:12px">
          Ver contenido en el portal &rarr;
        </a>
      </td></tr></table>';
    return $this->wrap($header, $body);
  }

  private function tpl_editorial_month_ready($name, $url, $month_name) {
    $pink   = $this->pink;
    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">&#x1F4C5; Calendario Editorial listo</h1>
               <p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">' . esc_html($month_name) . '</p>';
    $body = '
      <p style="margin:0 0 18px;font-size:16px;color:#1a1a2e;font-weight:700">
        Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span>
      </p>
      <p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.6">
        Hemos preparado el <strong>calendario editorial de ' . esc_html($month_name) . '</strong> con todos los contenidos planificados para ese mes.
        Entra al portal para revisarlo y darnos el OK o comentarnos cualquier cambio.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px">
        <tr><td align="center">
          <a href="' . esc_url($url) . '" target="_blank" rel="noopener"
             style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                    color:#fff;text-decoration:none;font-weight:900;font-size:16px;
                    padding:16px 40px;border-radius:14px;box-shadow:0 8px 24px rgba(215,33,115,.35)">
            Ver calendario editorial &rarr;
          </a>
        </td></tr>
      </table>
      <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.5">
        Si el bot&oacute;n no funciona:<br>
        <a href="' . esc_url($url) . '" style="color:' . $pink . ';word-break:break-all">' . esc_url($url) . '</a>
      </p>';
    return $this->wrap($header, $body);
  }

  private function tpl_editorial_response_internal($client_name, $month_name, $action, $note, $portal_url) {
    $pink = $this->pink;
    if ($action === 'approved') {
      $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">&#x2705; Calendario editorial aprobado</h1>';
      $alert  = '<div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:18px 22px;margin-bottom:20px">
                   <p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#065f46">&#x2705; &#xA1;Calendario aprobado!</p>
                   <p style="margin:0;font-size:14px;color:#047857">El cliente ha dado el visto bueno al calendario editorial de <strong>' . esc_html($month_name) . '</strong>.</p>
                 </div>';
      $note_block = '';
    } else {
      $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">&#x270F;&#xFE0F; Calendario con comentarios</h1>';
      $alert  = '<div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:14px;padding:18px 22px;margin-bottom:20px">
                   <p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#92400e">&#x270F;&#xFE0F; El cliente tiene comentarios</p>
                   <p style="margin:0;font-size:14px;color:#b45309">Ha pedido cambios en el calendario editorial de <strong>' . esc_html($month_name) . '</strong>.</p>
                 </div>';
      $note_block = $note ? '
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;margin-bottom:20px">
          <p style="margin:0 0 6px;font-size:12px;font-weight:900;color:#9ca3af;text-transform:uppercase">Comentario del cliente</p>
          <p style="margin:0;font-size:15px;color:#1a1a2e;line-height:1.6;white-space:pre-line">' . esc_html($note) . '</p>
        </div>' : '';
    }
    $body = '
      ' . $alert . '
      <div style="background:#f9fafb;border-radius:12px;padding:16px 20px;margin-bottom:20px">
        <p style="margin:0 0 4px;font-size:14px;color:#1a1a2e"><strong>Cliente:</strong> ' . esc_html($client_name) . '</p>
        <p style="margin:0;font-size:14px;color:#1a1a2e"><strong>Mes:</strong> ' . esc_html($month_name) . '</p>
      </div>
      ' . $note_block . '
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . esc_url($portal_url) . '" target="_blank" rel="noopener"
           style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                  color:#fff;text-decoration:none;font-weight:900;font-size:14px;
                  padding:12px 28px;border-radius:12px">
          Ver en el portal &rarr;
        </a>
      </td></tr></table>';
    return $this->wrap($header, $body);
  }
}