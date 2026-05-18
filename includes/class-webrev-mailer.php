<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebRev_Mailer')) return;

/**
 * TTB_WebRev_Mailer
 * Emails del módulo Revisiones Diseños.
 */
class TTB_WebRev_Mailer {

  private $pink = '#d41472';
  private $logo = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';

  public function send_review_invitation($project) {
    $emails = $this->parse_emails($project->emails);
    if (!$emails) return;

    $subject = get_option(
      'ttb_webrev_email_subject',
      '🎨 Tu diseño web está listo para revisar — TicTac Comunicación'
    );

    $intro = get_option(
      'ttb_webrev_email_intro',
      'Hemos preparado el diseño de tu proyecto. Accede al enlace para revisarlo y darnos tu feedback.'
    );

    $btn = get_option(
      'ttb_webrev_email_btn',
      'Ver mi diseño →'
    );

    $url = TTB_WebRev_DB::client_url($project->token);
    $html = $this->tpl_invitation($project, $url, $intro, $btn);

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($emails as $email) {
      wp_mail(trim($email), $subject, $html, $headers);
    }
  }

  public function send_accepted_alert($project) {
    $to = $this->internal_recipients($project, false);
    if (!$to) return;

    $subject = '✅ Diseño aceptado — ' . $project->name;
    $html    = $this->tpl_internal_alert($project, 'accepted');
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    wp_mail($to, $subject, $html, $headers);
  }

public function send_changes_alert($project, $revision) {
  $to = $this->internal_recipients($project, true);
  if (!$to) return;

  /*
   * Evita duplicados:
   * Si por cualquier motivo WordPress procesa dos veces el mismo envío,
   * no se volverá a enviar el email de la misma revisión.
   */
  $lock_key = 'ttb_webrev_changes_email_sent_' . (int)$revision->id;

  if (get_transient($lock_key)) {
    return;
  }

  set_transient($lock_key, 1, HOUR_IN_SECONDS);

  $project_name = $this->project_display_name($project);
  $subject = '✏️ Cambios solicitados — ' . $project_name . ' — Ronda #' . (int)$revision->round;
  $html    = $this->tpl_changes_alert($project, $revision);

  /*
   * Un único correo con varios destinatarios.
   * Así no se genera un email separado por cada destinatario interno.
   */
  $headers = [
    'Content-Type: text/html; charset=UTF-8'
  ];

  wp_mail($to, $subject, $html, $headers);
}

  public function send_admin_message_to_client($project, $message) {
    $emails = $this->parse_emails($project->emails);
    if (!$emails) return;

    $url     = TTB_WebRev_DB::client_url($project->token);
    $subject = '💬 Respuesta sobre tu diseño — ' . $project->name;
    $html    = $this->tpl_client_chat_message($project, $message, $url);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($emails as $email) {
      wp_mail(trim($email), $subject, $html, $headers);
    }
  }

  public function send_client_message_alert($project, $message) {
    $to = $this->internal_recipients($project, true);
    if (!$to) return;

    $subject = '💬 Nuevo mensaje del cliente — ' . $project->name;
    $html    = $this->tpl_internal_chat_message($project, $message);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    wp_mail($to, $subject, $html, $headers);
  }

  private function parse_emails($raw) {
    $emails = json_decode((string)$raw, true);

    if (!is_array($emails)) {
      $emails = explode(',', (string)$raw);
    }

    $emails = array_map('trim', $emails);
    $emails = array_filter($emails, 'is_email');

    return array_values(array_unique($emails));
  }

  private function internal_recipients($project = null, $include_seo = false) {
    $to_hola     = get_option('ttb_webrev_notify_hola', 'hola@tictac-comunicacion.es');
    $to_creativo = get_option('ttb_webrev_notify_creativo', 'creativo@tictac-comunicacion.es');

    $recipients = array_filter(array_map('trim', [
      $to_hola,
      $to_creativo,
    ]));

    if ($include_seo && $project && !empty($project->notify_seo)) {
      $recipients[] = 'seo@tictac-comunicacion.es';
    }

    $recipients = array_unique(array_filter($recipients, 'is_email'));

    return array_values($recipients);
  }

  private function project_display_name($project) {
    $name = $project->name ?? '';
    $title = $project->title ?? '';

    if ($title) {
      return $name . ' — ' . $title;
    }

    return $name;
  }

  private function tpl_invitation($project, $url, $intro, $btn) {
    $pink = $this->pink;
    $logo = $this->logo;
    $project_name = $this->project_display_name($project);

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
<tr>
<td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:28px 32px">
<img src="' . esc_url($logo) . '" alt="TicTac" width="130" style="display:block;margin:0 auto">
</td>
</tr>
<tr>
<td style="background:#fff;padding:32px 36px">
<h2 style="margin:0 0 10px;color:#1a1a2e;font-size:22px">Tu diseño está listo para revisar</h2>
<p style="margin:0 0 18px;color:#4b5563;font-size:15px;line-height:1.6">' . esc_html($intro) . '</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:24px">
<p style="margin:0;color:#1a1a2e;font-size:14px"><strong>Proyecto:</strong> ' . esc_html($project_name) . '</p>
</div>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);color:#fff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px">' . esc_html($btn) . '</a>
</td></tr>
</table>
<p style="margin:24px 0 0;color:#6b7280;font-size:13px;line-height:1.6">Desde el portal podrás aceptar el diseño o solicitar cambios con capturas y anotaciones.</p>
</td>
</tr>
<tr>
<td align="center" style="background:#1a1a2e;padding:18px 32px">
<p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>';
  }

  private function tpl_internal_alert($project, $type) {
    $pink = $this->pink;
    $logo = $this->logo;
    $project_name = $this->project_display_name($project);
    $portal = home_url('/briefing?section=revisiones-dis&wrtab=revisions&project=' . (int)$project->id);

    $title = $type === 'accepted'
      ? '✅ Diseño aceptado'
      : 'Actualización de revisión';

    $text = $type === 'accepted'
      ? 'El cliente ha aceptado el diseño desde el portal de revisiones.'
      : 'Hay una actualización en el portal de revisión de diseño.';

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
<tr>
<td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:28px 32px">
<img src="' . esc_url($logo) . '" alt="TicTac" width="130" style="display:block;margin:0 auto">
</td>
</tr>
<tr>
<td style="background:#fff;padding:32px 36px">
<h2 style="margin:0 0 10px;color:#1a1a2e;font-size:22px">' . esc_html($title) . '</h2>
<p style="margin:0 0 18px;color:#4b5563;font-size:15px;line-height:1.6">' . esc_html($text) . '</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:24px">
<p style="margin:0;color:#1a1a2e;font-size:14px"><strong>Proyecto:</strong> ' . esc_html($project_name) . '</p>
</div>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
<a href="' . esc_url($portal) . '" target="_blank" rel="noopener" style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);color:#fff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px">Ver proyecto →</a>
</td></tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="background:#1a1a2e;padding:18px 32px">
<p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>';
  }

  private function tpl_changes_alert($project, $revision) {
    $pink = $this->pink;
    $logo = $this->logo;
    $project_name = $this->project_display_name($project);
    $portal = home_url('/briefing?section=revisiones-dis&wrtab=revisions&project=' . (int)$project->id);

    $blocks = [];
    if (!empty($revision->images)) {
      $decoded = json_decode($revision->images, true);
      if (is_array($decoded)) {
        $blocks = $decoded;
      }
    }

    $blocks_html = '';

    if ($blocks) {
      foreach ($blocks as $block) {
        if (($block['type'] ?? '') === 'text') {
          $html = wp_kses_post($block['html'] ?? '');
          if ($html) {
            $blocks_html .= '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin:12px 0;color:#1a1a2e;font-size:14px;line-height:1.7">' . $html . '</div>';
          }
        }

        if (($block['type'] ?? '') === 'image') {
          $caption = sanitize_textarea_field($block['caption'] ?? '');
          $image = esc_url($block['image_url'] ?? '');

          $blocks_html .= '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 16px;margin:12px 0">';
          if ($caption) {
            $blocks_html .= '<p style="margin:0 0 12px;color:#1a1a2e;font-size:14px;line-height:1.7">' . nl2br(esc_html($caption)) . '</p>';
          }
          if ($image) {
            $blocks_html .= '<a href="' . $image . '" target="_blank" rel="noopener" style="color:' . $pink . ';font-weight:900;text-decoration:none">Ver captura anotada →</a>';
          }
          $blocks_html .= '</div>';
        }
      }
    } else {
      $blocks_html = '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin:12px 0;color:#1a1a2e;font-size:14px;line-height:1.7">' . nl2br(esc_html($revision->message ?? '')) . '</div>';
    }

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
<tr>
<td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:28px 32px">
<img src="' . esc_url($logo) . '" alt="TicTac" width="130" style="display:block;margin:0 auto">
</td>
</tr>
<tr>
<td style="background:#fff;padding:32px 36px">
<h2 style="margin:0 0 10px;color:#1a1a2e;font-size:22px">✏️ El cliente ha solicitado cambios</h2>
<p style="margin:0 0 8px;color:#1a1a2e;font-size:15px"><strong>Proyecto:</strong> ' . esc_html($project_name) . '</p>
<p style="margin:0 0 20px;color:#6b7280;font-size:14px">Ronda #' . (int)$revision->round . '</p>
' . $blocks_html . '
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px">
<tr><td align="center">
<a href="' . esc_url($portal) . '" target="_blank" rel="noopener" style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);color:#fff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px">Ver revisión completa →</a>
</td></tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="background:#1a1a2e;padding:18px 32px">
<p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>';
  }

  private function tpl_client_chat_message($project, $message, $url) {
    $pink = $this->pink;
    $logo = $this->logo;
    $project_name = $this->project_display_name($project);

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
<tr>
<td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:28px 32px">
<img src="' . esc_url($logo) . '" alt="TicTac" width="130" style="display:block;margin:0 auto">
</td>
</tr>
<tr>
<td style="background:#fff;padding:32px 36px">
<h2 style="margin:0 0 10px;color:#1a1a2e;font-size:22px">💬 Tienes una respuesta sobre tu diseño</h2>
<p style="margin:0 0 10px;color:#4b5563;font-size:15px;line-height:1.6">El equipo de TicTac Comunicación te ha dejado un mensaje en el portal de revisión.</p>
<p style="margin:0 0 18px;color:#1a1a2e;font-size:14px"><strong>Proyecto:</strong> ' . esc_html($project_name) . '</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;margin-bottom:24px;color:#1a1a2e;font-size:15px;line-height:1.7">' . nl2br(esc_html($message)) . '</div>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);color:#fff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px">Responder en el portal →</a>
</td></tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="background:#1a1a2e;padding:18px 32px">
<p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>';
  }

  private function tpl_internal_chat_message($project, $message) {
    $pink = $this->pink;
    $logo = $this->logo;
    $project_name = $this->project_display_name($project);
    $portal = home_url('/briefing?section=revisiones-dis&wrtab=revisions&project=' . (int)$project->id);

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
<tr>
<td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:28px 32px">
<img src="' . esc_url($logo) . '" alt="TicTac" width="130" style="display:block;margin:0 auto">
</td>
</tr>
<tr>
<td style="background:#fff;padding:32px 36px">
<h2 style="margin:0 0 10px;color:#1a1a2e;font-size:22px">💬 Nuevo mensaje del cliente</h2>
<p style="margin:0 0 6px;color:#1a1a2e;font-size:15px"><strong>Proyecto:</strong> ' . esc_html($project_name) . '</p>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;margin:18px 0 24px;color:#1a1a2e;font-size:15px;line-height:1.7">' . nl2br(esc_html($message)) . '</div>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
<a href="' . esc_url($portal) . '" target="_blank" rel="noopener" style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);color:#fff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px">Ver y responder →</a>
</td></tr>
</table>
</td>
</tr>
<tr>
<td align="center" style="background:#1a1a2e;padding:18px 32px">
<p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>';
  }
}