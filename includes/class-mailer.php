<?php
if (!defined('ABSPATH')) exit;

class TTB_Mailer {

  public function send_client_access($name, $email, $username, $password, $services) {
    $portal = home_url('/briefing');
    $logo   = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';
    $pink   = '#D72173';
    $dark   = '#1a1a2e';

    $map = ['design'=>'Diseño','social'=>'Redes Sociales','seo'=>'SEO','web'=>'Web'];
    $labels = [];
    if (is_array($services)) foreach ($services as $s) $labels[] = $map[$s] ?? $s;
    $services_txt = $labels ? implode(', ', $labels) : '—';

    // Iconos por servicio
    $icons = ['design'=>'🎨','social'=>'📣','seo'=>'🚀','web'=>'🌐'];
    $service_pills = '';
    if (is_array($services)) {
      foreach ($services as $s) {
        $icon  = $icons[$s] ?? '✅';
        $label = $map[$s] ?? $s;
        $service_pills .= '
          <span style="display:inline-block;background:rgba(255,255,255,.18);color:#fff;
                       font-weight:700;font-size:13px;padding:5px 14px;border-radius:999px;
                       margin:3px 4px;border:1px solid rgba(255,255,255,.3)">
            '.$icon.' '.$label.'
          </span>';
      }
    }

    $subject = '👋 Ya puedes rellenar tu Briefing — TicTac Comunicación';

    $message = '
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">

  <!-- Contenedor principal -->
  <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">

    <!-- CABECERA con gradiente -->
    <tr>
      <td align="center" style="background:linear-gradient(135deg,'.$pink.' 0%,#a8005a 100%);padding:40px 32px 32px">
        <img src="'.$logo.'" alt="TicTac Comunicación" width="160"
             style="display:block;margin:0 auto 20px;filter:drop-shadow(0 4px 16px rgba(0,0,0,.2))">
        <h1 style="margin:0 0 8px;color:#fff;font-size:22px;font-weight:900;letter-spacing:-.3px;line-height:1.2">
          ¡Tu briefing te espera! ⏱️
        </h1>
        <p style="margin:0 0 18px;color:rgba(255,255,255,.85);font-size:15px">
          Cuéntanos todo sobre tu proyecto y ponemos las pilas
        </p>
        <!-- Pills de servicios -->
        <div style="margin-top:4px">'.$service_pills.'</div>
      </td>
    </tr>

    <!-- CUERPO blanco -->
    <tr>
      <td style="background:#ffffff;padding:36px 40px">

        <p style="margin:0 0 6px;font-size:17px;color:#1a1a2e;font-weight:700">
          Hola, <span style="color:'.$pink.'">' . esc_html($name) . '</span> 👋
        </p>
        <p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.6">
          Hemos creado tu acceso personal al portal de briefings de TicTac.
          Rellénalo con calma — puedes guardarlo y retomarlo cuando quieras.
          Cuando lo envíes, nuestro equipo lo revisará y nos pondremos en contacto contigo para arrancar.
        </p>

        <!-- Caja de credenciales -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#fdf2f7;border:1.5px solid #f9a8d4;border-radius:14px;margin-bottom:28px">
          <tr>
            <td style="padding:22px 24px">
              <p style="margin:0 0 14px;font-size:13px;font-weight:900;color:'.$pink.';
                         text-transform:uppercase;letter-spacing:.08em">
                🔐 Tus datos de acceso
              </p>

              <!-- Enlace -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px">
                <tr>
                  <td width="90" style="font-size:13px;font-weight:700;color:#6b7280;padding:6px 0">Enlace</td>
                  <td style="padding:6px 0">
                    <a href="'.$portal.'" target="_blank" rel="noopener"
                       style="color:'.$pink.';font-weight:700;font-size:14px;text-decoration:none">
                      '.esc_html($portal).' →
                    </a>
                  </td>
                </tr>
                <tr>
                  <td style="font-size:13px;font-weight:700;color:#6b7280;padding:6px 0">Usuario</td>
                  <td style="padding:6px 0">
                    <code style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;
                                  padding:4px 10px;font-size:14px;color:#1a1a2e;font-weight:700">
                      '.esc_html($username).'
                    </code>
                  </td>
                </tr>
                <tr>
                  <td style="font-size:13px;font-weight:700;color:#6b7280;padding:6px 0">Contraseña</td>
                  <td style="padding:6px 0">
                    <code style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;
                                  padding:4px 10px;font-size:14px;color:#1a1a2e;font-weight:700">
                      '.esc_html($password).'
                    </code>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- CTA Button -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px">
          <tr>
            <td align="center">
              <a href="'.$portal.'" target="_blank" rel="noopener"
                 style="display:inline-block;background:linear-gradient(135deg,'.$pink.' 0%,#a8005a 100%);
                        color:#fff;text-decoration:none;font-weight:900;font-size:16px;
                        padding:16px 40px;border-radius:14px;
                        box-shadow:0 8px 24px rgba(215,33,115,.35);letter-spacing:.01em">
                Ir a mi Briefing →
              </a>
            </td>
          </tr>
        </table>

        <!-- Tips -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f9fafb;border-radius:12px;margin-bottom:24px">
          <tr>
            <td style="padding:18px 20px">
              <p style="margin:0 0 10px;font-size:13px;font-weight:900;color:#374151;text-transform:uppercase;letter-spacing:.06em">
                💡 Tips para rellenarlo bien
              </p>
              <p style="margin:0 0 6px;font-size:14px;color:#4b5563;line-height:1.5">
                ✔️ &nbsp;No hay respuestas correctas ni incorrectas, sé tú mismo.
              </p>
              <p style="margin:0 0 6px;font-size:14px;color:#4b5563;line-height:1.5">
                ✔️ &nbsp;Cuanto más detalle nos des, mejor resultado obtendrás.
              </p>
              <p style="margin:0;font-size:14px;color:#4b5563;line-height:1.5">
                ✔️ &nbsp;Puedes guardar y continuar cuando quieras, sin prisa.
              </p>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6">
          ¿Tienes dudas? Responde a este email y te ayudamos encantados. 🤝
        </p>

      </td>
    </tr>

    <!-- PIE -->
    <tr>
      <td align="center"
          style="background:#1a1a2e;padding:24px 32px">
        <p style="margin:0 0 6px;font-size:13px;color:rgba(255,255,255,.5)">
          © ' . date('Y') . ' TicTac Comunicación Digital
        </p>
        <p style="margin:0;font-size:11px;color:rgba(255,255,255,.3);max-width:440px;line-height:1.5">
          Este mensaje y sus archivos adjuntos van dirigidos exclusivamente a su destinatario,
          pudiendo contener información confidencial sometida a secreto profesional.
        </p>
      </td>
    </tr>

  </table>
  <!-- fin contenedor -->

</td></tr>
</table>

</body>
</html>';

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($email, $subject, $message, $headers);
  }

  /**
   * Aviso interno al jefe de departamento cuando un cliente envía su briefing.
   */
  public function send_department_alert($client_name, $client_email, $service) {
    $logo  = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';
    $pink  = '#D72173';

    $dept_emails = [
      'web'    => 'produccion@tictac-comunicacion.es',
      'seo'    => 'seo@tictac-comunicacion.es',
      'social' => 'comunicacion@tictac-comunicacion.es',
      'design' => 'creativo@tictac-comunicacion.es',
    ];
    $dept_names = [
      'web'    => 'Web',
      'seo'    => 'SEO',
      'social' => 'Redes Sociales',
      'design' => 'Diseño',
    ];

    $to = $dept_emails[$service] ?? null;
    if (!$to) return;

    $dept       = $dept_names[$service] ?? strtoupper($service);
    $portal_url = home_url('/briefing');
    $subject    = '✅ Briefing de ' . $dept . ' recibido — ' . $client_name;

    $message = '
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0">
<tr><td align="center">
  <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.12)">
    <tr>
      <td align="center" style="background:linear-gradient(135deg,'.$pink.' 0%,#a8005a 100%);padding:28px 32px">
        <img src="'.$logo.'" alt="TicTac" width="130" style="display:block;margin:0 auto">
      </td>
    </tr>
    <tr>
      <td style="background:#ffffff;padding:32px 36px">
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;margin-bottom:24px">
          <tr>
            <td style="padding:20px 24px">
              <p style="margin:0 0 4px;font-size:20px;font-weight:900;color:#065f46">✅ ¡Briefing recibido!</p>
              <p style="margin:0;font-size:14px;color:#047857">
                El briefing de <strong>'.esc_html($dept).'</strong> ya está listo para que lo revises.
              </p>
            </td>
          </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f9fafb;border-radius:12px;margin-bottom:24px">
          <tr>
            <td style="padding:18px 22px">
              <p style="margin:0 0 10px;font-size:12px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em">
                Datos del cliente
              </p>
              <p style="margin:0 0 6px;font-size:15px;color:#1a1a2e">
                <strong>Nombre:</strong> '.esc_html($client_name).'
              </p>
              <p style="margin:0 0 6px;font-size:15px;color:#1a1a2e">
                <strong>Email:</strong>
                <a href="mailto:'.esc_attr($client_email).'" style="color:'.$pink.'">'.esc_html($client_email).'</a>
              </p>
              <p style="margin:0;font-size:15px;color:#1a1a2e">
                <strong>Servicio:</strong> '.esc_html($dept).'
              </p>
            </td>
          </tr>
        </table>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
          <tr>
            <td align="center">
              <a href="'.esc_url($portal_url).'" target="_blank" rel="noopener"
                 style="display:inline-block;background:linear-gradient(135deg,'.$pink.' 0%,#a8005a 100%);
                        color:#fff;text-decoration:none;font-weight:900;font-size:15px;
                        padding:14px 36px;border-radius:12px;
                        box-shadow:0 8px 20px rgba(215,33,115,.30)">
                Ver respuestas en el portal →
              </a>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;line-height:1.5">
          Aviso automático del portal de briefings · TicTac Comunicación
        </p>
      </td>
    </tr>
    <tr>
      <td align="center" style="background:#1a1a2e;padding:18px 32px">
        <p style="margin:0;font-size:12px;color:rgba(255,255,255,.4)">
          © ' . date('Y') . ' TicTac Comunicación Digital
        </p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>';

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($to, $subject, $message, $headers);
  }
}