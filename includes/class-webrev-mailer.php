<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebRev_Mailer')) return;

/**
 * TTB_WebRev_Mailer
 * Emails del módulo Revisiones Diseños
 * v2: emails usan ttb_webrev_entry=TOKEN para llevar al portal con pestañas
 */
class TTB_WebRev_Mailer {

  private $pink  = '#D72173';
  private $logo  = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';

  /* ─────────────────────────────────────────────
     URL inteligente para emails al cliente
     Si tiene sesión → /briefing?ctab=design
     Si no tiene sesión → autologin con ctab=design
     Fallback → /briefing?webrev=TOKEN
  ───────────────────────────────────────────── */
  private function smart_url($token) {
    return home_url('/briefing?ttb_webrev_entry=' . urlencode($token));
  }

  /* ─────────────────────────────────────────────
     EMAIL AL CLIENTE: invitación a revisar Figma
  ───────────────────────────────────────────── */
  public function send_review_invitation($project) {
    $emails  = $this->parse_emails($project->emails);
    if (!$emails) return;

    $url         = $this->smart_url($project->token);
    $subject     = get_option('ttb_webrev_email_subject', '🎨 Tu diseño web está listo para revisar — TicTac Comunicación');
    $intro       = get_option('ttb_webrev_email_intro',   'Hemos preparado el diseño de tu proyecto. Accede al enlace para revisarlo y darnos tu feedback.');
    $btn_label   = get_option('ttb_webrev_email_btn',     'Ver mi diseño →');

    $message = $this->tpl_invitation($project->name, $url, $subject, $intro, $btn_label);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($emails as $email) {
      wp_mail(trim($email), $subject, $message, $headers);
    }
  }

  /* ─────────────────────────────────────────────
     EMAIL INTERNO: cliente aceptó el diseño
  ───────────────────────────────────────────── */
  public function send_accepted_alert($project) {
    $to_hola     = get_option('ttb_webrev_notify_hola',     'hola@tictac-comunicacion.es');
    $to_creativo = get_option('ttb_webrev_notify_creativo',  'creativo@tictac-comunicacion.es');
    $to          = array_filter(array_map('trim', [$to_hola, $to_creativo]));

    $subject = '✅ Diseño aceptado — ' . $project->name;
    $message = $this->tpl_internal_accepted($project);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($to as $email) {
      wp_mail($email, $subject, $message, $headers);
    }
  }

  /* ─────────────────────────────────────────────
     EMAIL INTERNO: cliente pidió cambios
  ───────────────────────────────────────────── */
  public function send_changes_alert($project, $revision) {
    $to_hola     = get_option('ttb_webrev_notify_hola',     'hola@tictac-comunicacion.es');
    $to_creativo = get_option('ttb_webrev_notify_creativo',  'creativo@tictac-comunicacion.es');
    $to          = array_filter(array_map('trim', [$to_hola, $to_creativo]));

    $round   = (int)$revision->round;
    $subject = '✏️ Cambios solicitados (ronda ' . $round . ') — ' . $project->name;
    $message = $this->tpl_internal_changes($project, $revision);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($to as $email) {
      wp_mail($email, $subject, $message, $headers);
    }
  }

  /* ─────────────────────────────────────────────
     HELPERS
  ───────────────────────────────────────────── */
  public function parse_emails($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return array_filter($decoded, 'is_email');
    return array_filter(array_map('trim', explode(',', (string)$raw)), 'is_email');
  }

  /* ─────────────────────────────────────────────
     TEMPLATES
  ───────────────────────────────────────────── */
  private function tpl_invitation($name, $url, $subject, $intro, $btn_label) {
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
      <td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:40px 32px 32px">
        <img src="' . $logo . '" alt="TicTac" width="150" style="display:block;margin:0 auto 20px">
        <h1 style="margin:0 0 8px;color:#fff;font-size:22px;font-weight:900">🎨 Tu diseño está listo</h1>
        <p style="margin:0;color:rgba(255,255,255,.85);font-size:15px">Ya puedes verlo y darnos tu feedback</p>
      </td>
    </tr>
    <tr>
      <td style="background:#fff;padding:36px 40px">
        <p style="margin:0 0 6px;font-size:17px;color:#1a1a2e;font-weight:700">Hola, <span style="color:' . $pink . '">' . esc_html($name) . '</span> 👋</p>
        <p style="margin:0 0 28px;font-size:15px;color:#4b5563;line-height:1.6">' . esc_html($intro) . '</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px">
          <tr>
            <td align="center">
              <a href="' . esc_url($url) . '" target="_blank" rel="noopener"
                 style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                        color:#fff;text-decoration:none;font-weight:900;font-size:16px;
                        padding:16px 40px;border-radius:14px;box-shadow:0 8px 24px rgba(215,33,115,.35)">
                ' . esc_html($btn_label) . '
              </a>
            </td>
          </tr>
        </table>
        <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.5">
          Si el botón no funciona, copia este enlace en tu navegador:<br>
          <a href="' . esc_url($url) . '" style="color:' . $pink . ';word-break:break-all">' . esc_url($url) . '</a>
        </p>
      </td>
    </tr>
    <tr>
      <td align="center" style="background:#1a1a2e;padding:20px 32px">
        <p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body></html>';
  }

  private function tpl_internal_accepted($project) {
    $pink = $this->pink;
    $logo = $this->logo;
    $portal = home_url('/briefing?section=revisiones-dis');
    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
  <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
    <tr><td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:28px 32px">
      <img src="' . $logo . '" alt="TicTac" width="130" style="display:block;margin:0 auto">
    </td></tr>
    <tr><td style="background:#fff;padding:32px 36px">
      <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:20px 24px;margin-bottom:24px">
        <p style="margin:0 0 4px;font-size:20px;font-weight:900;color:#065f46">✅ ¡Diseño aceptado!</p>
        <p style="margin:0;font-size:14px;color:#047857">El cliente ha dado el visto bueno al diseño.</p>
      </div>
      <div style="background:#f9fafb;border-radius:12px;padding:18px 22px;margin-bottom:24px">
        <p style="margin:0 0 6px;font-size:14px;color:#1a1a2e"><strong>Cliente:</strong> ' . esc_html($project->name) . '</p>
        <p style="margin:0 0 6px;font-size:14px;color:#1a1a2e"><strong>Figma:</strong> <a href="' . esc_url($project->figma_url) . '" style="color:' . $pink . '">' . esc_html($project->figma_url) . '</a></p>
        <p style="margin:0;font-size:14px;color:#1a1a2e"><strong>Fecha:</strong> ' . date_i18n('d/m/Y H:i') . '</p>
      </div>
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
           style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                  color:#fff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px">
          Ver en el portal →
        </a>
      </td></tr></table>
    </td></tr>
    <tr><td align="center" style="background:#1a1a2e;padding:18px 32px">
      <p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>';
  }

  private function tpl_internal_changes($project, $revision) {
    $pink    = $this->pink;
    $logo    = $this->logo;
    $portal  = home_url('/briefing?section=revisiones-dis');
    $round   = (int)$revision->round;

    $blocks_raw = json_decode((string)$revision->images, true);
    $is_blocks  = is_array($blocks_raw) && !empty($blocks_raw) && isset($blocks_raw[0]['type']);

    $content_html = '';
    if ($is_blocks) {
      foreach ($blocks_raw as $bl) {
        $type = $bl['type'] ?? '';
        if ($type === 'text' && !empty($bl['html'])) {
          $content_html .= '<div style="margin-bottom:16px;font-size:15px;color:#1a1a2e;line-height:1.7">' . wp_kses_post($bl['html']) . '</div>';
        } elseif ($type === 'image') {
          $content_html .= '<div style="margin-bottom:16px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">';
          if (!empty($bl['image_url'])) {
            $content_html .= '<a href="' . esc_url($bl['image_url']) . '" target="_blank"><img src="' . esc_url($bl['image_url']) . '" style="width:100%;max-height:320px;object-fit:contain;display:block;background:#f4f4f4" alt="Adjunto"></a>';
          }
          if (!empty($bl['caption'])) {
            $content_html .= '<div style="padding:10px 14px;font-size:14px;color:#374151;line-height:1.6;border-top:1px solid #f3f4f6">' . nl2br(esc_html($bl['caption'])) . '</div>';
          }
          $content_html .= '</div>';
        }
      }
    } else {
      if ($revision->message) {
        $content_html .= '<p style="font-size:15px;color:#1a1a2e;line-height:1.6;white-space:pre-line">' . esc_html($revision->message) . '</p>';
      }
    }

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
  <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
    <tr><td align="center" style="background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);padding:28px 32px">
      <img src="' . $logo . '" alt="TicTac" width="130" style="display:block;margin:0 auto">
    </td></tr>
    <tr><td style="background:#fff;padding:32px 36px">
      <div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:14px;padding:20px 24px;margin-bottom:24px">
        <p style="margin:0 0 4px;font-size:20px;font-weight:900;color:#92400e">✏️ Cambios solicitados — Ronda ' . $round . '</p>
        <p style="margin:0;font-size:14px;color:#b45309">El cliente ha pedido modificaciones en el diseño.</p>
      </div>
      <div style="background:#f9fafb;border-radius:12px;padding:18px 22px;margin-bottom:24px">
        <p style="margin:0 0 6px;font-size:14px;color:#1a1a2e"><strong>Cliente:</strong> ' . esc_html($project->name) . '</p>
        <p style="margin:0;font-size:14px;color:#1a1a2e"><strong>Figma:</strong> <a href="' . esc_url($project->figma_url) . '" style="color:' . $pink . '">' . esc_html($project->figma_url) . '</a></p>
      </div>
      ' . ($content_html ? '<div style="margin-bottom:24px">' . $content_html . '</div>' : '') . '
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . esc_url($portal) . '" target="_blank" rel="noopener"
           style="display:inline-block;background:linear-gradient(135deg,' . $pink . ' 0%,#a8005a 100%);
                  color:#fff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px">
          Ver en el portal →
        </a>
      </td></tr></table>
    </td></tr>
    <tr><td align="center" style="background:#1a1a2e;padding:18px 32px">
      <p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">© ' . date('Y') . ' TicTac Comunicación Digital</p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>';
  }
}