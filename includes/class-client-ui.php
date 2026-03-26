<?php
if (!defined('ABSPATH')) exit;

class TTB_Client_UI {

  public static function render($client_id) {
    global $wpdb;
    $clients = TTB_DB::clients_table();

    $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients WHERE id=%d", $client_id));
    if (!$c) {
      echo '<div class="ttb-card"><p>Client not found / Cliente no encontrado.</p></div>';
      return;
    }

    $services = json_decode((string)$c->services, true);
    if (!is_array($services)) $services = [];

    $lang = in_array($c->lang ?? '', ['es', 'en'], true) ? $c->lang : 'es';

    // ── Textos i18n ──────────────────────────────────────────────────
    $i18n = [
      'es' => [
        'greeting'      => 'Hola',
        'sub'           => 'Este es tu espacio de trabajo con TicTac. Aquí puedes rellenar tus prebriefings y seguir el avance de cada proyecto.',
        'no_svc'        => 'No tienes servicios asignados todavía.',
        'tab_brief'     => 'Prebriefing',
        'tab_design'    => 'Diseño',
        'tab_social'    => 'Redes Sociales',
        'tab_web'       => 'Prog. Web',
        // Estado 1: falta prebriefing
        'locked_title'  => 'Paso previo necesario',
        'locked_msg'    => 'Para que nuestro equipo empiece a trabajar, primero necesitamos que rellenes el prebriefing de este servicio.',
        'locked_btn'    => 'Ir al Prebriefing →',
        // Estado 2: prebriefing OK, equipo trabajando
        'wip_title'     => '¡Recibido! El equipo está en ello 🚀',
        'wip_msg'       => 'Hemos recibido tu prebriefing y ya estamos trabajando. Cuando tengamos algo listo te avisaremos por email y aparecerá aquí directamente.',
        // Estado 3: proyecto listo, botón de acceso
        'ready_design'  => 'Ver revisión de diseño',
        'ready_social'  => 'Ir a mi portal de Redes',
        'ready_web'     => 'Ver revisión de web',
      ],
      'en' => [
        'greeting'      => 'Hello',
        'sub'           => 'This is your workspace with TicTac. Fill in your pre-briefings here and track the progress of each project.',
        'no_svc'        => 'No services assigned yet.',
        'tab_brief'     => 'Pre-briefing',
        'tab_design'    => 'Design',
        'tab_social'    => 'Social Media',
        'tab_web'       => 'Web Dev',
        'locked_title'  => 'Previous step required',
        'locked_msg'    => 'For our team to start working, we first need you to fill in the pre-briefing for this service.',
        'locked_btn'    => 'Go to Pre-briefing →',
        'wip_title'     => 'Received! The team is on it 🚀',
        'wip_msg'       => 'We have received your pre-briefing and are already working on it. When we have something ready we will email you and it will appear here.',
        'ready_design'  => 'View design review',
        'ready_social'  => 'Go to my Social portal',
        'ready_web'     => 'View web review',
      ],
    ];
    $t = $i18n[$lang];

    // ── Pestaña activa ───────────────────────────────────────────────
    $active_tab = sanitize_text_field($_GET['ctab'] ?? 'briefing');
    $base_url   = home_url('/briefing');

    // ── Estado de cada módulo ────────────────────────────────────────
    // Precarga todo lo necesario en una sola pasada para evitar N queries
    $module_states = self::get_module_states($client_id, $services, $wpdb);

    echo '<div class="ttb-container">';

    // ── Cabecera ─────────────────────────────────────────────────────
    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2>' . esc_html($t['greeting']) . ', ' . esc_html($c->name) . ' 👋</h2>';
    echo '<p class="ttb-muted">' . esc_html($t['sub']) . '</p>';
    echo '</div>';

    if (!$services) {
      echo '<div class="ttb-card"><p class="ttb-muted">' . esc_html($t['no_svc']) . '</p></div></div>';
      return;
    }

    // ── CSS de pestañas del cliente ──────────────────────────────────
    echo '<style>
      .ttbc-tabs {
        display: flex;
        gap: 0;
        flex-wrap: wrap;
        border-bottom: 2px solid #f0e8ef;
        margin: 10px 0 0;
      }
      .ttbc-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 13px 20px;
        font-size: 14px;
        font-weight: 700;
        color: var(--ttb-muted);
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        border-radius: 10px 10px 0 0;
        background: transparent;
        transition: color .2s, background .2s, border-color .2s;
        white-space: nowrap;
        position: relative;
      }
      .ttbc-tab:hover {
        color: var(--ttb-pink);
        background: rgba(215,33,115,.06);
      }
      .ttbc-tab.active {
        color: var(--ttb-pink);
        border-bottom-color: var(--ttb-pink);
        background: rgba(215,33,115,.07);
        font-weight: 800;
      }
      .ttbc-tab.locked {
        opacity: .55;
        cursor: default;
        pointer-events: none;
      }
      .ttbc-tab.locked:hover {
        color: var(--ttb-muted);
        background: transparent;
      }
      /* Badge de estado en pestaña */
      .ttbc-tab-badge {
        font-size: 10px;
        font-weight: 900;
        padding: 2px 7px;
        border-radius: 999px;
        line-height: 1.4;
      }
      .ttbc-badge-lock   { background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; }
      .ttbc-badge-wip    { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
      .ttbc-badge-ready  { background:#ecfdf5; color:#065f46; border:1px solid #6ee7b7; }

      /* Paneles */
      .ttbc-panel { display: none; }
      .ttbc-panel.active { display: block; }

      /* Estado bloqueado */
      .ttbc-locked-box {
        text-align: center;
        padding: 60px 24px 48px;
      }
      .ttbc-locked-icon {
        font-size: 56px;
        display: block;
        margin: 0 auto 20px;
        line-height: 1;
      }
      .ttbc-locked-box h3 {
        margin: 0 0 12px;
        font-size: 20px;
        color: var(--ttb-text);
      }
      .ttbc-locked-box p {
        margin: 0 0 28px;
        font-size: 15px;
        color: var(--ttb-muted);
        max-width: 480px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.6;
      }

      /* Estado en progreso */
      .ttbc-wip-box {
        text-align: center;
        padding: 60px 24px 48px;
      }
      .ttbc-wip-icon {
        font-size: 56px;
        display: block;
        margin: 0 auto 20px;
        line-height: 1;
        animation: ttbcPulse 2s ease-in-out infinite;
      }
      @keyframes ttbcPulse {
        0%,100% { transform: scale(1); }
        50%      { transform: scale(1.08); }
      }
      .ttbc-wip-box h3 {
        margin: 0 0 12px;
        font-size: 20px;
        color: var(--ttb-text);
      }
      .ttbc-wip-box p {
        margin: 0 auto;
        font-size: 15px;
        color: var(--ttb-muted);
        max-width: 500px;
        line-height: 1.6;
      }
      /* Barra de progreso animada */
      .ttbc-progress {
        max-width: 340px;
        margin: 28px auto 0;
        background: #f3f4f6;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
      }
      .ttbc-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--ttb-pink), #e63a86);
        border-radius: 999px;
        animation: ttbcProgress 2.5s ease-in-out infinite;
      }
      @keyframes ttbcProgress {
        0%   { width: 15%; margin-left: 0; }
        50%  { width: 40%; margin-left: 30%; }
        100% { width: 15%; margin-left: 85%; }
      }
    </style>';

    // ── Renderizar pestañas ──────────────────────────────────────────
    echo '<div class="ttbc-tabs">';

    // Pestaña Prebriefing siempre primera
    $brief_active = ($active_tab === 'briefing') ? ' active' : '';
    echo '<a class="ttbc-tab' . $brief_active . '" href="' . esc_url(add_query_arg('ctab', 'briefing', $base_url)) . '">';
    echo '📋 ' . esc_html($t['tab_brief']);
    echo '</a>';

    // Pestañas de módulos según servicios contratados
    $module_tabs = [
      'design' => ['icon' => '🎨', 'label' => $t['tab_design'],  'tab_key' => 'design'],
      'social' => ['icon' => '📣', 'label' => $t['tab_social'],  'tab_key' => 'social'],
      'web'    => ['icon' => '🌐', 'label' => $t['tab_web'],     'tab_key' => 'web'],
      // SEO no tiene módulo visual, solo prebriefing, así que no genera pestaña de módulo
    ];

    foreach ($module_tabs as $svc => $cfg) {
      if (!in_array($svc, $services, true)) continue;

      $state      = $module_states[$svc] ?? 'locked';
      $tab_active = ($active_tab === $cfg['tab_key']) ? ' active' : '';

      if ($state === 'locked') {
        // Pestaña con candado, no clickable
        echo '<span class="ttbc-tab locked">';
        echo $cfg['icon'] . ' ' . esc_html($cfg['label']);
        echo ' <span class="ttbc-tab-badge ttbc-badge-lock">🔒</span>';
        echo '</span>';
      } elseif ($state === 'wip') {
        // Pestaña clickable pero sin proyecto listo
        echo '<a class="ttbc-tab' . $tab_active . '" href="' . esc_url(add_query_arg('ctab', $cfg['tab_key'], $base_url)) . '">';
        echo $cfg['icon'] . ' ' . esc_html($cfg['label']);
        echo ' <span class="ttbc-tab-badge ttbc-badge-wip">⚙️</span>';
        echo '</a>';
      } else {
        // ready: proyecto disponible
        echo '<a class="ttbc-tab' . $tab_active . '" href="' . esc_url(add_query_arg('ctab', $cfg['tab_key'], $base_url)) . '">';
        echo $cfg['icon'] . ' ' . esc_html($cfg['label']);
        echo ' <span class="ttbc-tab-badge ttbc-badge-ready">✓</span>';
        echo '</a>';
      }
    }

    echo '</div>'; // .ttbc-tabs

    // ── Paneles de contenido ─────────────────────────────────────────

    // Panel: Prebriefing
    $brief_panel_active = ($active_tab === 'briefing') ? ' active' : '';
    echo '<div class="ttbc-panel' . $brief_panel_active . '" id="ttbc-panel-briefing">';
    self::render_briefing_panel($client_id, $c, $services, $lang);
    echo '</div>';

    // Paneles de módulos
    foreach ($module_tabs as $svc => $cfg) {
      if (!in_array($svc, $services, true)) continue;

      $state        = $module_states[$svc] ?? 'locked';
      $panel_active = ($active_tab === $cfg['tab_key']) ? ' active' : '';

      echo '<div class="ttbc-panel' . $panel_active . '" id="ttbc-panel-' . esc_attr($cfg['tab_key']) . '">';

      if ($state === 'locked') {
        // No debería llegar aquí porque la pestaña no es clickable,
        // pero por seguridad mostramos el bloqueo igualmente
        self::render_locked($t, $base_url);
      } elseif ($state === 'wip') {
        self::render_wip($t, $svc, $lang);
      } else {
        // Estado ready: cargar el portal del módulo inline
        self::render_module_ready($svc, $module_states, $t, $lang);
      }

      echo '</div>';
    }

    echo '</div>'; // .ttb-container
  }

  /* ════════════════════════════════════════════════════
     ESTADO DE MÓDULOS
     Determina si cada módulo está locked / wip / ready
  ════════════════════════════════════════════════════ */

  private static function get_module_states($client_id, $services, $wpdb) {
    $states = [];

    foreach ($services as $svc) {
      // SEO no tiene módulo visual
      if ($svc === 'seo') continue;

      // Comprobar si el prebriefing de este servicio está enviado
      $briefing_sent = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT sent FROM " . TTB_DB::answers_table() . " WHERE client_id=%d AND service=%s",
        $client_id, $svc
      ));

      if (!$briefing_sent) {
        $states[$svc] = 'locked';
        continue;
      }

      // Prebriefing enviado → comprobar si el admin ya creó el proyecto
      switch ($svc) {
        case 'design':
          // Buscar proyecto en webrev vinculado al nombre del cliente
          // (los proyectos webrev se vinculan por ttb_client_id en una versión futura;
          //  por ahora buscamos por nombre exacto como hace el admin al crearlos)
          $project = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token FROM " . TTB_WebRev_DB::projects_table() . "
             WHERE name = (SELECT name FROM " . TTB_DB::clients_table() . " WHERE id=%d LIMIT 1)
             ORDER BY created_at DESC LIMIT 1",
            $client_id
          ));
          $states[$svc] = $project ? 'ready' : 'wip';
          if ($project) $states['design_token'] = $project->token;
          break;

        case 'social':
          $sc_client = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token FROM " . TTB_Social_DB::clients_table() . " WHERE ttb_client_id=%d LIMIT 1",
            $client_id
          ));
          if ($sc_client) {
            $states[$svc]           = 'ready';
            $states['social_token'] = $sc_client->token;
            $has_posts = (int)$wpdb->get_var($wpdb->prepare(
              "SELECT COUNT(*) FROM " . TTB_Social_DB::posts_table() . " WHERE client_id=%d AND status != 'draft'",
              (int)$sc_client->id
            ));
            $states['social_has_posts'] = $has_posts > 0;
          } else {
            $states[$svc] = 'wip';
          }
          break;

        case 'web':
          $project = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token FROM " . TTB_WebProg_DB::projects_table() . "
             WHERE name = (SELECT name FROM " . TTB_DB::clients_table() . " WHERE id=%d LIMIT 1)
             ORDER BY created_at DESC LIMIT 1",
            $client_id
          ));
          $states[$svc] = $project ? 'ready' : 'wip';
          if ($project) $states['web_token'] = $project->token;
          break;
      }
    }

    return $states;
  }

  /* ════════════════════════════════════════════════════
     PANEL: PREBRIEFING (el que ya existía)
  ════════════════════════════════════════════════════ */

  private static function render_briefing_panel($client_id, $c, $services, $lang) {
    $titles_es = [
      'design' => 'Prebriefing de Diseño',
      'social' => 'Prebriefing de Redes',
      'seo'    => 'Prebriefing de SEO',
      'web'    => 'Prebriefing de Web',
    ];
    $titles_en = [
      'design' => 'Design Pre-briefing',
      'social' => 'Social Media Pre-briefing',
      'seo'    => 'SEO Pre-briefing',
      'web'    => 'Web Pre-briefing',
    ];
    $titles = $lang === 'en' ? $titles_en : $titles_es;

    foreach ($services as $svc) {
      $schema  = TTB_Forms::get_schema($svc, $lang);
      $payload = TTB_Forms::get_client_answers($client_id, $svc);
      $answers = $payload['answers'];
      $sent    = (int)$payload['sent'];
      $title   = $titles[$svc] ?? strtoupper($svc);

      include TTB_PATH . 'templates/form.php';
    }
  }

  /* ════════════════════════════════════════════════════
     PANEL: MÓDULO BLOQUEADO (por si acaso)
  ════════════════════════════════════════════════════ */

  private static function render_locked($t, $base_url) {
    echo '<div class="ttb-card">';
    echo '<div class="ttbc-locked-box">';
    echo '<span class="ttbc-locked-icon">🔒</span>';
    echo '<h3>' . esc_html($t['locked_title']) . '</h3>';
    echo '<p>' . esc_html($t['locked_msg']) . '</p>';
    echo '<a href="' . esc_url(add_query_arg('ctab', 'briefing', $base_url)) . '" class="ttb-btn">'
      . esc_html($t['locked_btn']) . '</a>';
    echo '</div>';
    echo '</div>';
  }

  /* ════════════════════════════════════════════════════
     PANEL: MÓDULO EN PROGRESO (prebriefing OK, sin proyecto)
  ════════════════════════════════════════════════════ */

  private static function render_wip($t, $svc, $lang) {
    // Mensajes específicos por servicio
    $wip_details = [
      'es' => [
        'design' => 'Nuestro equipo de diseño está preparando tu propuesta visual. En cuanto tengamos el diseño listo para revisar te lo enviaremos por email.',
        'social' => 'Nuestro equipo de redes está elaborando tu estrategia de contenidos. Pronto tendrás acceso a tu calendario de publicaciones.',
        'web'    => 'Nuestro equipo de desarrollo está programando tu web. Cuando esté lista para que la revises te avisaremos.',
      ],
      'en' => [
        'design' => 'Our design team is preparing your visual proposal. As soon as the design is ready for review we will send it to you by email.',
        'social' => 'Our social media team is developing your content strategy. You will soon have access to your publication calendar.',
        'web'    => 'Our development team is building your website. When it is ready for your review we will let you know.',
      ],
    ];

    $msg = $wip_details[$lang][$svc] ?? $t['wip_msg'];

    echo '<div class="ttb-card">';
    echo '<div class="ttbc-wip-box">';
    echo '<span class="ttbc-wip-icon">⚙️</span>';
    echo '<h3>' . esc_html($t['wip_title']) . '</h3>';
    echo '<p>' . esc_html($msg) . '</p>';
    echo '<div class="ttbc-progress"><div class="ttbc-progress-bar"></div></div>';
    echo '</div>';
    echo '</div>';
  }



  /* ════════════════════════════════════════════════════
   PANEL: SOCIAL — portal activo pero sin posts aún
════════════════════════════════════════════════════ */

private static function render_social_waiting($t, $token, $lang) {
  $wip_msg = [
    'es' => 'Nuestro equipo de redes está elaborando tu estrategia de contenidos. En cuanto tengamos la primera publicación lista te avisaremos. Mientras tanto, puedes enviarnos fotos o ideas.',
    'en' => 'Our social media team is developing your content strategy. As soon as the first publication is ready we will let you know. In the meantime, you can send us photos or ideas.',
  ];
  $btn_content = ['es' => 'Enviar contenido al equipo →', 'en' => 'Send content to the team →'];
  $msg = $wip_msg[$lang] ?? $wip_msg['es'];
  $btn = $btn_content[$lang] ?? $btn_content['es'];
  $content_url = esc_url(home_url('/briefing?ctab=social&stab=content'));

  echo '<div class="ttb-card">';
  echo '<div class="ttbc-wip-box">';
  echo '<span class="ttbc-wip-icon">⚙️</span>';
  echo '<h3>' . esc_html($t['wip_title']) . '</h3>';
  echo '<p>' . esc_html($msg) . '</p>';
  echo '<div class="ttbc-progress"><div class="ttbc-progress-bar"></div></div>';
  echo '<div style="margin-top:24px"><a href="' . $content_url . '" class="ttb-btn">' . esc_html($btn) . '</a></div>';
  echo '</div></div>';
}

  /* ════════════════════════════════════════════════════
     PANEL: MÓDULO LISTO — cargar portal inline
  ════════════════════════════════════════════════════ */

  private static function render_module_ready($svc, $module_states, $t, $lang) {
    switch ($svc) {

      case 'design':
        $token = $module_states['design_token'] ?? '';
        if (!$token) { self::render_wip($t, $svc, $lang); return; }
        TTB_WebRev_Client::render($token);
        break;

      case 'social':
        $token     = $module_states['social_token'] ?? '';
        $has_posts = $module_states['social_has_posts'] ?? false;
        if (!$token) { self::render_wip($t, $svc, $lang); return; }
        if (!$has_posts) {
          self::render_social_waiting($t, $token, $lang);
        } else {
          $_GET['ttb_return'] = 'main';
          TTB_Social_Client::render($token);
        }
        break;

      case 'web':
        $token = $module_states['web_token'] ?? '';
        if (!$token) { self::render_wip($t, $svc, $lang); return; }
        TTB_WebProg_Client::render($token);
        break;
    }
  }
}