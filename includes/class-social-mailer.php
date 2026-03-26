<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Social_Mailer')) return;

class TTB_Social_Mailer {

  private $pink = '#D72173';
  private $logo = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';

  public function send_welcome($client) {
    $emails = $this->parse_emails($client->emails);
    if (!$emails) return;
    // URL inteligente: si tiene sesión → ctab=social; si no → portal token
    $url     = $this->smart_url($client->token);
    $subject = 'Tu portal de Redes Sociales está listo — TicTac Comunicación';
    $message = $this->tpl_welcome($client->name, $url);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_post_approval($client, $post) {
    $emails = $this->parse_emails($client->emails);
    if (!$emails) return;
    // URL inteligente: si tiene sesión → ctab=social; si no → portal token
    $url            = $this->smart_url($client->token);
    $date_formatted = date_i18n('l, j \d\e F \d\e Y', strtotime($post->scheduled_date));
    $subject        = 'Creatividad lista para revisar — ' . $date_formatted . ' — TicTac Comunicación';
    $message        = $this->tpl_approval($client->name, $url, $post, $date_formatted);
    $headers        = ['Content-Type: text/html; charset=UTF-8'];
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

  public function parse_emails($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return array_filter($decoded, 'is_email');
    return array_filter(array_map('trim', explode(',', (string)$raw)), 'is_email');
  }

  /**
   * Genera la URL inteligente para los emails al cliente.
   * Usa ?ttb_social_entry=TOKEN — el portal principal detecta este parámetro:
   *   - Si el usuario tiene sesión activa → redirige a ctab=social (portal con pestañas)
   *   - Si no tiene sesión               → redirige a ?social=TOKEN (portal independiente de token)
   * Así el cliente siempre llega al sitio correcto sin importar si está logueado o no.
   */
  private function smart_url($token) {
    return home_url('/briefing?ttb_social_entry=' . urlencode($token));
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

  private function tpl_welcome($name, $url) {
    $pink   = $this->pink;
    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">Tu portal de Redes Sociales</h1>
               <p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">Revisa publicaciones y comparte tu contenido con nosotros</p>';
    $body = '
      <p style="margin:0 0 6px;font-size:17px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span></p>
      <p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.6">Hemos creado tu espacio para gestionar las redes sociales con TicTac. Desde aqu&iacute; podr&aacute;s:</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:12px;margin-bottom:24px">
        <tr><td style="padding:18px 20px">
          <p style="margin:0 0 8px;font-size:14px;color:#4b5563;line-height:1.5">&#x2714;&#xFE0F;&nbsp; Subir fotos y v&iacute;deos para tus publicaciones.</p>
          <p style="margin:0 0 8px;font-size:14px;color:#4b5563;line-height:1.5">&#x2714;&#xFE0F;&nbsp; Ver el calendario con las publicaciones programadas.</p>
          <p style="margin:0;font-size:14px;color:#4b5563;line-height:1.5">&#x2714;&#xFE0F;&nbsp; Aprobar o pedir cambios en las creatividades antes de publicarlas.</p>
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

  private function tpl_approval($name, $url, $post, $date_formatted) {
    $pink     = $this->pink;
    $is_video = ($post->post_type === 'video') || $this->is_video_url($post->creative_url ?? '');

    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">Creatividad lista para revisar</h1>
               <p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">' . esc_html($date_formatted) . '</p>';

    $creative_html = '';
    if ($post->creative_url) {
      if ($is_video) {
        $creative_html = '
          <div style="border-radius:12px;overflow:hidden;margin-bottom:20px;background:#1a1a2e;padding:32px 24px;text-align:center">
            <p style="margin:0 0 8px;font-size:36px">&#x1F3AC;</p>
            <p style="margin:0 0 6px;font-size:16px;font-weight:900;color:#fff">Creatividad en v&iacute;deo</p>
            <p style="margin:0 0 16px;font-size:13px;color:rgba(255,255,255,.7)">Accede al portal para reproducir y revisar el v&iacute;deo</p>
            <a href="' . esc_url($url) . '" target="_blank" rel="noopener"
               style="display:inline-block;background:' . $pink . ';color:#fff;text-decoration:none;
                      font-weight:900;font-size:14px;padding:12px 28px;border-radius:10px">
              Ver v&iacute;deo en el portal &rarr;
            </a>
          </div>';
      } else {
        $creative_html = '
          <div style="border-radius:12px;overflow:hidden;margin-bottom:20px;border:1px solid #f3f4f6">
            <img src="' . esc_url($post->creative_url) . '" style="width:100%;max-height:400px;object-fit:cover;display:block" alt="Creatividad">
          </div>';
      }
    }

    $caption_html = '';
    if ($post->caption) {
      $caption_html = '<div style="background:#f9fafb;border-radius:10px;padding:14px 18px;margin-bottom:18px;border-left:3px solid ' . $pink . '">
        <p style="margin:0 0 4px;font-size:11px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em">Texto de la publicaci&oacute;n</p>
        <p style="margin:0;font-size:15px;color:#1a1a2e;line-height:1.7;white-space:pre-line">' . esc_html($post->caption) . '</p>
      </div>';
    }

    $note_html = '';
    if ($post->creative_note) {
      $note_html = '<div style="background:#fdf4ff;border-radius:10px;padding:12px 16px;margin-bottom:18px;border:1px solid #e9d5ff">
        <p style="margin:0;font-size:14px;color:#7e22ce;line-height:1.6">' . esc_html($post->creative_note) . '</p>
      </div>';
    }

    $body = '
      <p style="margin:0 0 18px;font-size:16px;color:#1a1a2e;font-weight:700">
        Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span>
      </p>
      <p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.6">
        Hemos preparado la creatividad para tu publicaci&oacute;n del <strong>' . esc_html($date_formatted) . '</strong>.
        Rev&iacute;sala y danos el OK o cu&eacute;ntanos qu&eacute; cambiar&iacute;as.
      </p>
      ' . $creative_html . '
      ' . $caption_html . '
      ' . $note_html . '
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
      <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.5">
        Si el bot&oacute;n no funciona:<br>
        <a href="' . esc_url($url) . '" style="color:' . $pink . ';word-break:break-all">' . esc_url($url) . '</a>
      </p>';

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
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
          <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
             style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                    color:#fff;text-decoration:none;font-weight:900;font-size:14px;
                    padding:12px 28px;border-radius:12px">
            Ver en el portal &rarr;
          </a>
        </td></tr>
      </table>';
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
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
          <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
             style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                    color:#fff;text-decoration:none;font-weight:900;font-size:14px;
                    padding:12px 28px;border-radius:12px">
            Ver en el portal &rarr;
          </a>
        </td></tr>
      </table>';
    return $this->wrap($header, $body);
  }

  private function tpl_content_received($client_name, $count, $portal) {
    $pink   = $this->pink;
    $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">Nuevo contenido recibido</h1>';
    $body = '
      <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:14px;padding:18px 22px;margin-bottom:20px">
        <p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#1d4ed8">' . esc_html($client_name) . '</p>
        <p style="margin:0;font-size:14px;color:#1d4ed8">
          Ha subido <strong>' . (int)$count . ' archivo' . ((int)$count > 1 ? 's' : '') . '</strong> nuevo' . ((int)$count > 1 ? 's' : '') . ' al portal.
        </p>
      </div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
          <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
             style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                    color:#fff;text-decoration:none;font-weight:900;font-size:14px;
                    padding:12px 28px;border-radius:12px">
            Ver contenido en el portal &rarr;
          </a>
        </td></tr>
      </table>';
    return $this->wrap($header, $body);
  }
}