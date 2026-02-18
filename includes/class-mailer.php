<?php
if (!defined('ABSPATH')) exit;

class TTB_Mailer {

  public function send_client_access($name, $email, $username, $password, $services) {
    $portal = home_url('/briefing');
    $logo   = 'https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png';
    $pink   = '#D72173';

    $map = ['design'=>'Diseño','social'=>'Redes','seo'=>'SEO','web'=>'Web'];
    $labels = [];
    if (is_array($services)) foreach ($services as $s) $labels[] = $map[$s] ?? $s;
    $services_txt = $labels ? implode(', ', $labels) : '—';

    $subject = "Acceso a tu Briefing — TicTac Comunicación";

    $message = '
    <div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;border:1px solid #eee;border-radius:14px;overflow:hidden">
      <div style="padding:18px 22px;background:'.$pink.';color:#fff">
        <img src="'.$logo.'" alt="TicTac" style="height:44px;display:block;margin-bottom:10px">
        <h2 style="margin:0;font-size:18px;line-height:1.2">Acceso a tu portal de Briefing</h2>
      </div>
      <div style="padding:22px">
        <p style="margin:0 0 10px">Hola <strong>'.esc_html($name).'</strong>,</p>
        <p style="margin:0 0 14px">Para arrancar, necesitamos que completes el briefing de: <strong>'.$services_txt.'</strong>.</p>

        <div style="background:#fafafa;border:1px solid #eee;border-radius:10px;padding:14px;margin:14px 0">
          <p style="margin:0 0 6px"><strong>Enlace:</strong> <a href="'.$portal.'" target="_blank" rel="noopener">'.$portal.'</a></p>
          <p style="margin:0 0 6px"><strong>Usuario:</strong> '.esc_html($username).'</p>
          <p style="margin:0"><strong>Contraseña:</strong> '.esc_html($password).'</p>
        </div>

        <p style="margin:0 0 10px">Puedes guardar y continuar después. Cuando lo envíes, lo revisaremos y arrancamos 🚀</p>
        <p style="margin:0;color:#666;font-size:12px">— TicTac Comunicación</p>
      </div>
    </div>';

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($email, $subject, $message, $headers);
  }
}
