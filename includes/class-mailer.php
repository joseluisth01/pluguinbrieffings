<?php
if (!defined('ABSPATH')) exit;

class TTB_Mailer {

  /**
   * @param string $lang 'es' | 'en'
   */
  public function send_client_access($name, $email, $username, $password, $services, $lang = 'es') {
    if ($lang === 'en') {
      $this->send_client_access_en($name, $email, $username, $password, $services);
    } else {
      $this->send_client_access_es($name, $email, $username, $password, $services);
    }
  }

  /* ─────────────────────────────────────────────
     EMAIL ACCESO — ESPAÑOL
  ───────────────────────────────────────────── */
  private function send_client_access_es($name, $email, $username, $password, $services) {
    $portal = home_url('/briefing');
    $logo   = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';
    $pink   = '#D72173';

    // Sin emojis en las pills — solo texto, Outlook los rompe
    $map   = ['design' => 'Diseno', 'social' => 'Redes Sociales', 'seo' => 'SEO', 'web' => 'Web', 'reservas' => 'Reservas'];
    $service_pills = $this->build_pills($services, $map);

    $subject = 'Tus accesos al Portal de Cliente - TicTac Comunicacion';
    $message = $this->tpl_access_es($name, $username, $password, $portal, $logo, $pink, $service_pills);

    wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
  }

  /* ─────────────────────────────────────────────
     EMAIL ACCESO — ENGLISH
  ───────────────────────────────────────────── */
  private function send_client_access_en($name, $email, $username, $password, $services) {
    $portal = home_url('/briefing');
    $logo   = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';
    $pink   = '#D72173';

    $map   = ['design' => 'Design', 'social' => 'Social Media', 'seo' => 'SEO', 'web' => 'Web', 'reservas' => 'Reservations'];
    $service_pills = $this->build_pills($services, $map);

    $subject = 'Your Client Portal access - TicTac Comunicacion';
    $message = $this->tpl_access_en($name, $username, $password, $portal, $logo, $pink, $service_pills);

    wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
  }

  /* ─────────────────────────────────────────────
     HELPER: pills de servicios (sin emojis)
  ───────────────────────────────────────────── */
  private function build_pills($services, $map) {
    $pills = '';
    if (is_array($services)) {
      foreach ($services as $s) {
        $label = $map[$s] ?? strtoupper($s);
        $pills .= '<span style="display:inline-block;background:rgba(255,255,255,.20);color:#ffffff;font-weight:700;font-size:13px;padding:5px 16px;border-radius:999px;margin:3px 4px;border:1px solid rgba(255,255,255,.4);font-family:Arial,Helvetica,sans-serif">' . esc_html($label) . '</span>';
      }
    }
    return $pills;
  }

  private function build_autologin_url($portal, $username, $password) {
    return $portal
      . '?ttb_u=' . rawurlencode($username)
      . '&ttb_p=' . rawurlencode($password);
  }

  /* ─────────────────────────────────────────────
     TEMPLATE EMAIL ES

     PROBLEMA GMAIL (visto en inspector):
     Gmail reescribe el href del <a> con su proxy de redirección
     y su hoja de estilos global sobreescribe color con
     "a:-webkit-any-link { color: -webkit-link }" → texto azul/invisible.

     SOLUCIÓN GMAIL:
     - Envolver el texto del botón en un <span> con color:#ffffff !important
     - Añadir color:#ffffff !important directamente en el <a>
     - Usar tabla de celda única con bgcolor para que el fondo
       no dependa solo del CSS del <a>

     SOLUCIÓN OUTLOOK:
     - VML v:roundrect (condicional <!--[if mso]-->)
     - background-color sólido, nunca gradient
     - Sin emojis en títulos ni pills (se renderizan como bloques)
     - Todo &#x... eliminado de títulos, solo en cuerpo donde
       Outlook sí suele renderizar ✔

     EMOJIS:
     - Eliminados del subject, títulos h1/h2 y pills
     - Las checkmarks ✔ del cuerpo van como entidad &#x2714;
       que Outlook sí renderiza correctamente
  ───────────────────────────────────────────── */
  private function tpl_access_es($name, $username, $password, $portal, $logo, $pink, $pills) {
    $autologin_url  = $this->build_autologin_url($portal, $username, $password);
    $autologin_esc  = esc_attr($autologin_url);
    $autologin_show = esc_html($autologin_url);
    $name_esc = esc_html($name);
    $user_esc = esc_html($username);
    $pass_esc = esc_html($password);
    $year     = date('Y');

    return '<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Acceso al Portal de Cliente</title>
<!--[if mso]>
<xml>
<o:OfficeDocumentSettings>
<o:AllowPNG/>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
<![endif]-->
<style>
/* Gmail sobreescribe color de enlaces con su hoja de agente.
   Este bloque lo contrarresta para el boton. */
.btn-portal,
.btn-portal:link,
.btn-portal:visited,
.btn-portal:hover,
.btn-portal:active {
  color: #ffffff !important;
  text-decoration: none !important;
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:Arial,Helvetica,sans-serif">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f0f2f5" style="background-color:#f0f2f5">
<tr><td align="center" style="padding:32px 16px">

  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- CABECERA ROSA -->
    <tr>
      <td align="center" bgcolor="#D72173" style="background-color:#D72173;padding:40px 32px 32px;border-radius:20px 20px 0 0">
        <img src="' . $logo . '" alt="TicTac Comunicacion" width="160" style="display:block;margin:0 auto 20px;border:0;outline:0;text-decoration:none">
        <h1 style="margin:0 0 8px;color:#ffffff;font-size:22px;font-weight:900;font-family:Arial,Helvetica,sans-serif;line-height:1.3">
          Bienvenido a tu Portal de Cliente
        </h1>
        <p style="margin:0 0 18px;color:#fce4f0;font-size:15px;font-family:Arial,Helvetica,sans-serif">
          Tu espacio personal con TicTac Comunicacion
        </p>
        ' . ($pills ? '<div style="margin-top:4px">' . $pills . '</div>' : '') . '
      </td>
    </tr>

    <!-- CUERPO BLANCO -->
    <tr>
      <td bgcolor="#ffffff" style="background-color:#ffffff;padding:36px 40px">

        <p style="margin:0 0 6px;font-size:17px;color:#1a1a2e;font-weight:700;font-family:Arial,Helvetica,sans-serif">
          Hola, <span style="color:#D72173">' . $name_esc . '</span>
        </p>
        <p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.6;font-family:Arial,Helvetica,sans-serif">
          Hemos creado tu acceso personal al portal de cliente de TicTac. Desde aqui podras gestionar todos los servicios contratados: rellenar tus prebriefings, revisar disenos y mucho mas.
        </p>

        <!-- CREDENCIALES -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fdf2f7" style="background-color:#fdf2f7;border:1px solid #f9a8d4;border-radius:14px;margin-bottom:28px">
          <tr>
            <td style="padding:22px 24px">
              <p style="margin:0 0 16px;font-size:13px;font-weight:900;color:#D72173;text-transform:uppercase;letter-spacing:1px;font-family:Arial,Helvetica,sans-serif">
                Tus datos de acceso
              </p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="110" valign="middle" style="font-size:13px;font-weight:700;color:#6b7280;padding:6px 0;font-family:Arial,Helvetica,sans-serif">Usuario</td>
                  <td valign="middle" style="padding:6px 0">
                    <span style="display:inline-block;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:4px 12px;font-size:14px;color:#1a1a2e;font-weight:700;font-family:Courier New,Courier,monospace">' . $user_esc . '</span>
                  </td>
                </tr>
                <tr>
                  <td width="110" valign="middle" style="font-size:13px;font-weight:700;color:#6b7280;padding:6px 0;font-family:Arial,Helvetica,sans-serif">Contrasena</td>
                  <td valign="middle" style="padding:6px 0">
                    <span style="display:inline-block;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:4px 12px;font-size:14px;color:#1a1a2e;font-weight:700;font-family:Courier New,Courier,monospace">' . $pass_esc . '</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- BOTON
             GMAIL FIX: clase .btn-portal con !important en el <style> del head
                        + <span> interno con color forzado
                        + tabla padre con bgcolor como respaldo visual
             OUTLOOK FIX: VML v:roundrect en condicional <!--[if mso]-->
        -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px">
          <tr>
            <td align="center" bgcolor="#D72173" style="background-color:#D72173;border-radius:14px;padding:0">
              <!--[if mso]>
              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                href="' . $autologin_esc . '"
                style="height:52px;v-text-anchor:middle;width:300px;"
                arcsize="14%"
                strokecolor="#D72173"
                fillcolor="#D72173">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:900;">Acceder a mi Portal</center>
              </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-->
              <a href="' . $autologin_esc . '" target="_blank" rel="noopener" class="btn-portal"
                 style="display:block;background-color:#D72173;color:#ffffff;text-decoration:none;font-weight:900;font-size:16px;padding:16px 40px;border-radius:14px;font-family:Arial,Helvetica,sans-serif;line-height:1;text-align:center">
                <span style="color:#ffffff;font-size:16px;font-weight:900;font-family:Arial,Helvetica,sans-serif;text-decoration:none">Acceder a mi Portal &rarr;</span>
              </a>
              <!--<![endif]-->
            </td>
          </tr>
        </table>

        <!-- ENLACE FALLBACK -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px">
          <tr>
            <td align="center">
              <p style="margin:0;font-size:13px;color:#6b7280;font-family:Arial,Helvetica,sans-serif;line-height:1.6">
                Si el boton no funciona, haz clic en el siguiente enlace:<br>
                <a href="' . $autologin_esc . '" style="color:#D72173;font-size:12px;word-break:break-all;font-family:Arial,Helvetica,sans-serif">' . $autologin_show . '</a>
              </p>
            </td>
          </tr>
        </table>

        <!-- QUE PUEDES HACER -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9fafb" style="background-color:#f9fafb;border-radius:12px;margin-bottom:24px">
          <tr>
            <td style="padding:18px 20px">
              <p style="margin:0 0 12px;font-size:13px;font-weight:900;color:#374151;text-transform:uppercase;letter-spacing:1px;font-family:Arial,Helvetica,sans-serif">
                Que puedes hacer desde tu portal?
              </p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="24" valign="top" style="padding:0 8px 10px 0;font-size:15px;color:#D72173;font-family:Arial,Helvetica,sans-serif">&#x2714;</td>
                  <td style="font-size:14px;color:#4b5563;line-height:1.5;padding-bottom:10px;font-family:Arial,Helvetica,sans-serif">Rellenar y enviar tus prebriefings de cada servicio.</td>
                </tr>
                <tr>
                  <td width="24" valign="top" style="padding:0 8px 10px 0;font-size:15px;color:#D72173;font-family:Arial,Helvetica,sans-serif">&#x2714;</td>
                  <td style="font-size:14px;color:#4b5563;line-height:1.5;padding-bottom:10px;font-family:Arial,Helvetica,sans-serif">Revisar y aprobar disenos y desarrollos web.</td>
                </tr>
                <tr>
                  <td width="24" valign="top" style="padding:0 8px 0 0;font-size:15px;color:#D72173;font-family:Arial,Helvetica,sans-serif">&#x2714;</td>
                  <td style="font-size:14px;color:#4b5563;line-height:1.5;font-family:Arial,Helvetica,sans-serif">Gestionar publicaciones y contenido de redes sociales.</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6;font-family:Arial,Helvetica,sans-serif">
          Tienes dudas? Responde a este email y te ayudamos encantados.
        </p>

      </td>
    </tr>

    <!-- PIE -->
    <tr>
      <td align="center" bgcolor="#1a1a2e" style="background-color:#1a1a2e;padding:24px 32px;border-radius:0 0 20px 20px">
        <p style="margin:0 0 6px;font-size:13px;color:#888888;font-family:Arial,Helvetica,sans-serif">&copy; ' . $year . ' TicTac Comunicacion Digital</p>
        <p style="margin:0;font-size:11px;color:#555555;max-width:440px;line-height:1.5;font-family:Arial,Helvetica,sans-serif">
          Este mensaje y sus archivos adjuntos van dirigidos exclusivamente a su destinatario,
          pudiendo contener informacion confidencial sometida a secreto profesional.
        </p>
      </td>
    </tr>

  </table>

</td></tr>
</table>

</body>
</html>';
  }

  /* ─────────────────────────────────────────────
     TEMPLATE EMAIL EN
  ───────────────────────────────────────────── */
  private function tpl_access_en($name, $username, $password, $portal, $logo, $pink, $pills) {
    $autologin_url  = $this->build_autologin_url($portal, $username, $password);
    $autologin_esc  = esc_attr($autologin_url);
    $autologin_show = esc_html($autologin_url);
    $name_esc = esc_html($name);
    $user_esc = esc_html($username);
    $pass_esc = esc_html($password);
    $year     = date('Y');

    return '<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Client Portal Access</title>
<!--[if mso]>
<xml>
<o:OfficeDocumentSettings>
<o:AllowPNG/>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
<![endif]-->
<style>
.btn-portal,
.btn-portal:link,
.btn-portal:visited,
.btn-portal:hover,
.btn-portal:active {
  color: #ffffff !important;
  text-decoration: none !important;
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:Arial,Helvetica,sans-serif">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f0f2f5" style="background-color:#f0f2f5">
<tr><td align="center" style="padding:32px 16px">

  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- HEADER -->
    <tr>
      <td align="center" bgcolor="#D72173" style="background-color:#D72173;padding:40px 32px 32px;border-radius:20px 20px 0 0">
        <img src="' . $logo . '" alt="TicTac Comunicacion" width="160" style="display:block;margin:0 auto 20px;border:0;outline:0;text-decoration:none">
        <h1 style="margin:0 0 8px;color:#ffffff;font-size:22px;font-weight:900;font-family:Arial,Helvetica,sans-serif;line-height:1.3">
          Welcome to your Client Portal
        </h1>
        <p style="margin:0 0 18px;color:#fce4f0;font-size:15px;font-family:Arial,Helvetica,sans-serif">
          Your personal space with TicTac Comunicacion
        </p>
        ' . ($pills ? '<div style="margin-top:4px">' . $pills . '</div>' : '') . '
      </td>
    </tr>

    <!-- BODY -->
    <tr>
      <td bgcolor="#ffffff" style="background-color:#ffffff;padding:36px 40px">

        <p style="margin:0 0 6px;font-size:17px;color:#1a1a2e;font-weight:700;font-family:Arial,Helvetica,sans-serif">
          Hello, <span style="color:#D72173">' . $name_esc . '</span>
        </p>
        <p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.6;font-family:Arial,Helvetica,sans-serif">
          We have created your personal access to the TicTac client portal. From here you can manage all your contracted services: fill in your pre-briefings, review designs and much more.
        </p>

        <!-- CREDENTIALS -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fdf2f7" style="background-color:#fdf2f7;border:1px solid #f9a8d4;border-radius:14px;margin-bottom:28px">
          <tr>
            <td style="padding:22px 24px">
              <p style="margin:0 0 16px;font-size:13px;font-weight:900;color:#D72173;text-transform:uppercase;letter-spacing:1px;font-family:Arial,Helvetica,sans-serif">
                Your login details
              </p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="110" valign="middle" style="font-size:13px;font-weight:700;color:#6b7280;padding:6px 0;font-family:Arial,Helvetica,sans-serif">Username</td>
                  <td valign="middle" style="padding:6px 0">
                    <span style="display:inline-block;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:4px 12px;font-size:14px;color:#1a1a2e;font-weight:700;font-family:Courier New,Courier,monospace">' . $user_esc . '</span>
                  </td>
                </tr>
                <tr>
                  <td width="110" valign="middle" style="font-size:13px;font-weight:700;color:#6b7280;padding:6px 0;font-family:Arial,Helvetica,sans-serif">Password</td>
                  <td valign="middle" style="padding:6px 0">
                    <span style="display:inline-block;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:4px 12px;font-size:14px;color:#1a1a2e;font-weight:700;font-family:Courier New,Courier,monospace">' . $pass_esc . '</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- BUTTON -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px">
          <tr>
            <td align="center" bgcolor="#D72173" style="background-color:#D72173;border-radius:14px;padding:0">
              <!--[if mso]>
              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                href="' . $autologin_esc . '"
                style="height:52px;v-text-anchor:middle;width:300px;"
                arcsize="14%"
                strokecolor="#D72173"
                fillcolor="#D72173">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:900;">Access my Portal</center>
              </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-->
              <a href="' . $autologin_esc . '" target="_blank" rel="noopener" class="btn-portal"
                 style="display:block;background-color:#D72173;color:#ffffff;text-decoration:none;font-weight:900;font-size:16px;padding:16px 40px;border-radius:14px;font-family:Arial,Helvetica,sans-serif;line-height:1;text-align:center">
                <span style="color:#ffffff;font-size:16px;font-weight:900;font-family:Arial,Helvetica,sans-serif;text-decoration:none">Access my Portal &rarr;</span>
              </a>
              <!--<![endif]-->
            </td>
          </tr>
        </table>

        <!-- FALLBACK LINK -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px">
          <tr>
            <td align="center">
              <p style="margin:0;font-size:13px;color:#6b7280;font-family:Arial,Helvetica,sans-serif;line-height:1.6">
                If the button does not work, click the following link:<br>
                <a href="' . $autologin_esc . '" style="color:#D72173;font-size:12px;word-break:break-all;font-family:Arial,Helvetica,sans-serif">' . $autologin_show . '</a>
              </p>
            </td>
          </tr>
        </table>

        <!-- WHAT YOU CAN DO -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9fafb" style="background-color:#f9fafb;border-radius:12px;margin-bottom:24px">
          <tr>
            <td style="padding:18px 20px">
              <p style="margin:0 0 12px;font-size:13px;font-weight:900;color:#374151;text-transform:uppercase;letter-spacing:1px;font-family:Arial,Helvetica,sans-serif">
                What can you do from your portal?
              </p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="24" valign="top" style="padding:0 8px 10px 0;font-size:15px;color:#D72173;font-family:Arial,Helvetica,sans-serif">&#x2714;</td>
                  <td style="font-size:14px;color:#4b5563;line-height:1.5;padding-bottom:10px;font-family:Arial,Helvetica,sans-serif">Fill in and submit your pre-briefings for each service.</td>
                </tr>
                <tr>
                  <td width="24" valign="top" style="padding:0 8px 10px 0;font-size:15px;color:#D72173;font-family:Arial,Helvetica,sans-serif">&#x2714;</td>
                  <td style="font-size:14px;color:#4b5563;line-height:1.5;padding-bottom:10px;font-family:Arial,Helvetica,sans-serif">Review and approve designs and web developments.</td>
                </tr>
                <tr>
                  <td width="24" valign="top" style="padding:0 8px 0 0;font-size:15px;color:#D72173;font-family:Arial,Helvetica,sans-serif">&#x2714;</td>
                  <td style="font-size:14px;color:#4b5563;line-height:1.5;font-family:Arial,Helvetica,sans-serif">Manage social media posts and content.</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:14px;color:#9ca3af;line-height:1.6;font-family:Arial,Helvetica,sans-serif">
          Any questions? Reply to this email and we will be happy to help.
        </p>

      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td align="center" bgcolor="#1a1a2e" style="background-color:#1a1a2e;padding:24px 32px;border-radius:0 0 20px 20px">
        <p style="margin:0 0 6px;font-size:13px;color:#888888;font-family:Arial,Helvetica,sans-serif">&copy; ' . $year . ' TicTac Comunicacion Digital</p>
        <p style="margin:0;font-size:11px;color:#555555;max-width:440px;line-height:1.5;font-family:Arial,Helvetica,sans-serif">
          This message is intended solely for its recipient and may contain confidential information
          subject to professional secrecy.
        </p>
      </td>
    </tr>

  </table>

</td></tr>
</table>

</body>
</html>';
  }

  /* ─────────────────────────────────────────────
     AVISO INTERNO AL DEPARTAMENTO
  ───────────────────────────────────────────── */
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
      'design' => 'Diseno',
    ];

    $to = $dept_emails[$service] ?? null;
    if (!$to) return;

    $dept       = $dept_names[$service] ?? strtoupper($service);
    $portal_url = home_url('/briefing');
    $drive_url  = 'https://drive.google.com/drive/folders/17HJ0F4PePs9DxnJM8J6zAjCuU90MS6LQ?usp=drive_link';
    $subject    = 'Prebriefing de ' . $dept . ' recibido - ' . $client_name;
    $year       = date('Y');

    $message = '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f0f2f5" style="background-color:#f0f2f5">
<tr><td align="center" style="padding:32px 16px">
  <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%">
    <tr>
      <td align="center" bgcolor="#D72173" style="background-color:#D72173;padding:28px 32px;border-radius:20px 20px 0 0">
        <img src="' . $logo . '" alt="TicTac" width="130" style="display:block;margin:0 auto;border:0">
      </td>
    </tr>
    <tr>
      <td bgcolor="#ffffff" style="background-color:#ffffff;padding:32px 36px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ecfdf5" style="background-color:#ecfdf5;border:1px solid #6ee7b7;border-radius:14px;margin-bottom:24px">
          <tr>
            <td style="padding:20px 24px">
              <p style="margin:0 0 4px;font-size:20px;font-weight:900;color:#065f46;font-family:Arial,Helvetica,sans-serif">Prebriefing recibido!</p>
              <p style="margin:0;font-size:14px;color:#047857;font-family:Arial,Helvetica,sans-serif">
                El prebriefing de <strong>' . esc_html($dept) . '</strong> ya esta listo para que lo revises.
              </p>
            </td>
          </tr>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f9fafb" style="background-color:#f9fafb;border-radius:12px;margin-bottom:24px">
          <tr>
            <td style="padding:18px 22px">
              <p style="margin:0 0 10px;font-size:12px;font-weight:900;color:#9ca3af;text-transform:uppercase;font-family:Arial,Helvetica,sans-serif">Datos del cliente</p>
              <p style="margin:0 0 6px;font-size:15px;color:#1a1a2e;font-family:Arial,Helvetica,sans-serif"><strong>Nombre:</strong> ' . esc_html($client_name) . '</p>
              <p style="margin:0 0 6px;font-size:15px;color:#1a1a2e;font-family:Arial,Helvetica,sans-serif">
                <strong>Email:</strong>
                <a href="mailto:' . esc_attr($client_email) . '" style="color:' . $pink . '">' . esc_html($client_email) . '</a>
              </p>
              <p style="margin:0;font-size:15px;color:#1a1a2e;font-family:Arial,Helvetica,sans-serif"><strong>Servicio:</strong> ' . esc_html($dept) . '</p>
            </td>
          </tr>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
          <tr>
            <td align="center">
              <a href="' . esc_url($portal_url) . '" target="_blank" rel="noopener"
                 style="display:inline-block;background-color:' . $pink . ';color:#ffffff;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;">
                Ver respuestas en el portal
              </a>
            </td>
          </tr>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
          <tr>
            <td align="center">
              <a href="' . esc_url($drive_url) . '" target="_blank" rel="noopener"
                 style="display:inline-block;background-color:#ffffff;color:#1a1a2e;text-decoration:none;font-weight:900;font-size:15px;padding:14px 36px;border-radius:12px;border:2px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;">
                Ver carpeta en Google Drive
              </a>
            </td>
          </tr>
        </table>
        <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;line-height:1.5;font-family:Arial,Helvetica,sans-serif">
          Aviso automatico del portal de clientes - TicTac Comunicacion
        </p>
      </td>
    </tr>
    <tr>
      <td align="center" bgcolor="#1a1a2e" style="background-color:#1a1a2e;padding:18px 32px;border-radius:0 0 20px 20px">
        <p style="margin:0;font-size:12px;color:#888888;font-family:Arial,Helvetica,sans-serif">&copy; ' . $year . ' TicTac Comunicacion Digital</p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>';

    wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
  }
}