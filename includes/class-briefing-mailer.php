<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Briefing_Mailer')) return;

/**
 * TTB_Briefing_Mailer — v1.1
 * Fixes:
 * - Todos los estilos de <a> en UNA SOLA LÍNEA (clientes de email rompen multilínea).
 * - background-color en lugar de background (mejor soporte en Outlook).
 * - Nombres de documentos leídos del JSON array o del campo legacy doc_name.
 */
class TTB_Briefing_Mailer {

  private $pink = '#D72173';
  private $logo = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';

  public function send_briefing_ready($briefing, $client) {
    $emails = $this->parse_emails($client->emails ?? '');
    if (!$emails) return;
    $url     = $this->smart_url($briefing->token);
    $subject = '📄 Tu Briefing está listo para revisar — TicTac Comunicación';
    $message = $this->tpl_briefing_ready($client->name, $url, $briefing);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_reminder($briefing, $client) {
    $emails = $this->parse_emails($client->emails ?? '');
    if (!$emails) return;
    $url     = $this->smart_url($briefing->token);
    $subject = '⏰ Recordatorio: tienes un Briefing pendiente de revisar — TicTac Comunicación';
    $message = $this->tpl_reminder($client->name, $url, $briefing);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_resources_reminder($briefing, $client) {
    $emails = $this->parse_emails($client->emails ?? '');
    if (!$emails) return;
    $url       = $this->smart_url($briefing->token);
    $drive_url = $briefing->shared_folder_url ?? '';
    $subject   = '📦 Recuerda subir tus recursos a Drive — TicTac Comunicación';
    $message   = $this->tpl_resources_reminder($client->name, $url, $drive_url);
    $headers   = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($emails as $email) wp_mail(trim($email), $subject, $message, $headers);
  }

  public function send_accepted_internal($briefing, $client) {
    $to      = $this->internal_emails();
    $subject = '✅ Briefing aceptado — ' . $client->name;
    $message = $this->tpl_internal_response($briefing, $client, 'accepted');
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($to as $email) wp_mail($email, $subject, $message, $headers);
  }

  public function send_rejected_internal($briefing, $client) {
    $to      = $this->internal_emails();
    $subject = '✏️ Briefing rechazado con comentarios — ' . $client->name;
    $message = $this->tpl_internal_response($briefing, $client, 'rejected');
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    foreach ($to as $email) wp_mail($email, $subject, $message, $headers);
  }

  // ─── HELPERS ─────────────────────────────────────────────

  public function parse_emails($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return array_filter($decoded, 'is_email');
    return array_filter(array_map('trim', explode(',', (string)$raw)), 'is_email');
  }

  private function smart_url($token) {
    return home_url('/briefing?ttb_briefing_entry=' . urlencode($token));
  }

  private function internal_emails() {
    $a = get_option('ttb_briefing_notify_a', 'hola@tictac-comunicacion.es');
    $b = get_option('ttb_briefing_notify_b', '');
    return array_filter(array_map('trim', array_filter([$a, $b])));
  }

  /**
   * Extrae los nombres de los documentos adjuntos.
   * Soporta JSON array (nuevo) y campo legacy doc_name.
   */
  private function get_doc_names($briefing) {
    $raw = (string)($briefing->doc_url ?? '');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded)) {
      return array_map(fn($d) => $d['name'] ?? 'Documento', $decoded);
    }
    $name = (string)($briefing->doc_name ?? '');
    return $name ? [$name] : ['Documento adjunto'];
  }

  // ─── WRAPPER ─────────────────────────────────────────────

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
<tr><td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:32px 32px 28px">
<img src="' . $logo . '" alt="TicTac" width="140" style="display:block;margin:0 auto 16px">
' . $header_html . '
</td></tr>
<tr><td style="background:#ffffff;padding:32px 36px">
' . $body_html . '
</td></tr>
<tr><td align="center" style="background:#1a1a2e;padding:18px 32px">
<p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">&copy; ' . date('Y') . ' TicTac Comunicaci&oacute;n Digital</p>
</td></tr>
</table>
</td></tr>
</table>
</body></html>';
  }

  // ─── TEMPLATES ───────────────────────────────────────────

  private function tpl_briefing_ready($name, $url, $briefing) {
    $pink      = $this->pink;
    $drive_url = $briefing->shared_folder_url ?? '';
    $title     = !empty($briefing->title) ? esc_html($briefing->title) : 'Briefing de proyecto';
    $doc_names = $this->get_doc_names($briefing);
    $doc_count = count($doc_names);

    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">&#x1F4C4; Tu Briefing est&aacute; listo</h1><p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">' . $title . '</p>';

    // Bloque documentos
    $doc_block = '';
    if ($doc_count > 0) {
      $doc_label = $doc_count > 1 ? 'Documentos adjuntos (' . $doc_count . ')' : 'Documento adjunto';
      $doc_items = '';
      foreach ($doc_names as $dname) {
        $ext  = strtolower(pathinfo($dname, PATHINFO_EXTENSION));
        $icon = $ext === 'pdf' ? '&#x1F4C4;' : '&#x1F4DD;';
        $doc_items .= '<p style="margin:0 0 4px;font-size:14px;color:#1a1a2e;font-weight:700">' . $icon . ' ' . esc_html($dname) . '</p>';
      }
      $doc_block = '<div style="background:#fdf2f7;border:1.5px solid #f9a8d4;border-radius:14px;padding:18px 22px;margin-bottom:24px"><p style="margin:0 0 10px;font-size:13px;font-weight:900;color:' . $pink . ';text-transform:uppercase;letter-spacing:.06em">&#x1F4C4; ' . $doc_label . '</p>' . $doc_items . '</div>';
    }

    // Bloque Drive — BOTÓN EN UNA SOLA LÍNEA
    $drive_block = '';
    if ($drive_url) {
      $drive_block = '<div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:14px;padding:18px 22px;margin-bottom:24px"><p style="margin:0 0 6px;font-size:13px;font-weight:900;color:#1d4ed8;text-transform:uppercase;letter-spacing:.06em">&#x1F4C1; Tu carpeta de recursos</p><p style="margin:0 0 10px;font-size:14px;color:#1e40af;line-height:1.6">Hemos preparado una carpeta de Google Drive compartida contigo para que puedas subir todos los recursos de tu empresa: <strong>logos, fotograf&iacute;as, v&iacute;deos, colores corporativos, etc.</strong></p><p style="margin:0 0 16px;font-size:13px;color:#1e40af;font-weight:700">&#x26A0;&#xFE0F; <strong>Importante:</strong> hasta que no subas los recursos, nuestro equipo no podr&aacute; comenzar a trabajar.</p><a href="' . esc_url($drive_url) . '" target="_blank" rel="noopener" style="display:inline-block;background-color:#1d4ed8;color:#ffffff;text-decoration:none;font-weight:900;font-size:14px;padding:12px 24px;border-radius:10px;font-family:Arial,Helvetica,sans-serif;">&#x1F4C2; Abrir mi carpeta de Drive &rarr;</a></div>';
    }

    $body = '<p style="margin:0 0 18px;font-size:16px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span></p>'
          . '<p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.6">Hemos preparado el documento de briefing de tu proyecto. Rev&iacute;salo y d&aacute;nos tu confirmaci&oacute;n o com&eacute;ntanos los cambios que necesites.</p>'
          . $doc_block
          . $drive_block
          . '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px"><tr><td align="center"><a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="display:inline-block;background-color:' . $pink . ';color:#ffffff;text-decoration:none;font-weight:900;font-size:16px;padding:16px 40px;border-radius:14px;font-family:Arial,Helvetica,sans-serif;">Ver mi Briefing &rarr;</a></td></tr></table>'
          . '<p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.5">Si el bot&oacute;n no funciona:<br><a href="' . esc_url($url) . '" style="color:' . $pink . ';word-break:break-all">' . esc_url($url) . '</a></p>';

    return $this->wrap($header, $body);
  }

  private function tpl_reminder($name, $url, $briefing) {
    $pink  = $this->pink;
    $title = !empty($briefing->title) ? esc_html($briefing->title) : 'Briefing de proyecto';

    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">&#x23F0; Briefing pendiente de revisar</h1><p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">' . $title . '</p>';

    $body = '<p style="margin:0 0 18px;font-size:16px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span></p>'
          . '<p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.6">Queremos recordarte que tienes un documento de briefing esperando tu revisi&oacute;n y confirmaci&oacute;n. Tu aprobaci&oacute;n es necesaria para que nuestro equipo pueda continuar con el proyecto.</p>'
          . '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px"><tr><td align="center"><a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="display:inline-block;background-color:' . $pink . ';color:#ffffff;text-decoration:none;font-weight:900;font-size:16px;padding:16px 40px;border-radius:14px;font-family:Arial,Helvetica,sans-serif;">Revisar mi Briefing &rarr;</a></td></tr></table>'
          . '<p style="margin:0;font-size:13px;color:#9ca3af"><a href="' . esc_url($url) . '" style="color:' . $pink . ';word-break:break-all">' . esc_url($url) . '</a></p>';

    return $this->wrap($header, $body);
  }

  private function tpl_resources_reminder($name, $url, $drive_url) {
    $pink = $this->pink;

    $header = '<h1 style="margin:0 0 6px;color:#fff;font-size:20px;font-weight:900">&#x1F4E6; Recursos pendientes de subir</h1><p style="margin:0;color:rgba(255,255,255,.85);font-size:14px">Necesitamos tus archivos para comenzar</p>';

    $drive_block = '';
    if ($drive_url) {
      $drive_block = '<div style="margin-bottom:24px"><a href="' . esc_url($drive_url) . '" target="_blank" rel="noopener" style="display:inline-block;background-color:#1d4ed8;color:#ffffff;text-decoration:none;font-weight:900;font-size:14px;padding:12px 24px;border-radius:10px;font-family:Arial,Helvetica,sans-serif;">&#x1F4C2; Abrir carpeta de Drive &rarr;</a></div>';
    }

    $body = '<p style="margin:0 0 18px;font-size:16px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span></p>'
          . '<p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.6">Tu Briefing ya ha sido aceptado, pero todav&iacute;a estamos esperando los recursos de tu empresa para poder comenzar.</p>'
          . '<div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:14px;padding:18px 22px;margin-bottom:24px"><p style="margin:0 0 8px;font-size:14px;font-weight:900;color:#9a3412">&#x26A0;&#xFE0F; Nuestro equipo no puede empezar hasta que subas los recursos</p><p style="margin:0;font-size:14px;color:#c2410c;line-height:1.6">Sube a la carpeta compartida de Drive: <strong>logos, fotograf&iacute;as, v&iacute;deos, gu&iacute;as de estilo, colores corporativos</strong> y cualquier material visual de tu empresa.</p></div>'
          . $drive_block
          . '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px"><tr><td align="center"><a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="display:inline-block;background-color:' . $pink . ';color:#ffffff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 32px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;">Ver mi portal &rarr;</a></td></tr></table>';

    return $this->wrap($header, $body);
  }

  private function tpl_internal_response($briefing, $client, $action) {
    $pink       = $this->pink;
    $portal_url = home_url('/briefing?section=briefing-doc');
    $title      = !empty($briefing->title) ? esc_html($briefing->title) : 'Briefing de proyecto';

    if ($action === 'accepted') {
      $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">&#x2705; Briefing aceptado</h1>';
      $alert  = '<div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:18px 22px;margin-bottom:20px"><p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#065f46">&#x2705; &#xA1;Briefing aprobado!</p><p style="margin:0;font-size:14px;color:#047857">El cliente ha dado el visto bueno al documento de briefing.</p></div>';
    } else {
      $header = '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:900">&#x270F;&#xFE0F; Briefing con comentarios</h1>';
      $alert  = '<div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:14px;padding:18px 22px;margin-bottom:20px"><p style="margin:0 0 4px;font-size:18px;font-weight:900;color:#92400e">&#x270F;&#xFE0F; El cliente pide cambios</p><p style="margin:0;font-size:14px;color:#b45309">Ha enviado comentarios sobre el documento de briefing.</p></div>';
    }

    $note_block = '';
    if (!empty($briefing->client_note)) {
      $note_block = '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;margin-bottom:20px"><p style="margin:0 0 6px;font-size:12px;font-weight:900;color:#9ca3af;text-transform:uppercase">Comentario del cliente</p><p style="margin:0;font-size:15px;color:#1a1a2e;line-height:1.6;white-space:pre-line">' . esc_html($briefing->client_note) . '</p></div>';
    }

    $body = $alert
          . '<div style="background:#f9fafb;border-radius:12px;padding:16px 20px;margin-bottom:20px"><p style="margin:0 0 4px;font-size:14px;color:#1a1a2e"><strong>Cliente:</strong> ' . esc_html($client->name) . '</p><p style="margin:0;font-size:14px;color:#1a1a2e"><strong>Briefing:</strong> ' . $title . '</p></div>'
          . $note_block
          . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center"><a href="' . esc_url($portal_url) . '" target="_blank" rel="noopener" style="display:inline-block;background-color:' . $pink . ';color:#ffffff;text-decoration:none;font-weight:900;font-size:14px;padding:12px 28px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;">Ver en el portal &rarr;</a></td></tr></table>';

    return $this->wrap($header, $body);
  }
}