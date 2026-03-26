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

    $i18n = [
      'es' => [
        'greeting'      => 'Hola',
        'sub'           => 'Este es tu espacio de trabajo con TicTac. Aquí puedes rellenar tus prebriefings y seguir el avance de cada proyecto.',
        'no_svc'        => 'No tienes servicios asignados todavía.',
        'tab_brief'     => 'Prebriefing',
        'tab_design'    => 'Diseño',
        'tab_social'    => 'Redes Sociales',
        'tab_web'       => 'Prog. Web',
        'locked_title'  => 'Paso previo necesario',
        'locked_msg'    => 'Para que nuestro equipo empiece a trabajar, primero necesitamos que rellenes el prebriefing de este servicio.',
        'locked_btn'    => 'Ir al Prebriefing →',
        'wip_title'     => '¡Recibido! El equipo está en ello 🚀',
        'wip_msg'       => 'Hemos recibido tu prebriefing y ya estamos trabajando. Cuando tengamos algo listo te avisaremos por email y aparecerá aquí directamente.',
        'ready_design'  => 'Ver revisión de diseño',
        'ready_social'  => 'Ir a mi portal de Redes',
        'ready_web'     => 'Ver revisión de web',
        'project_older' => 'Proyecto anterior',
        'project_active'=> 'Proyecto activo',
        'show_project'  => 'Ver proyecto →',
        'hide_project'  => 'Ocultar ↑',
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
        'project_older' => 'Previous project',
        'project_active'=> 'Active project',
        'show_project'  => 'View project →',
        'hide_project'  => 'Hide ↑',
      ],
    ];
    $t = $i18n[$lang];

    $active_tab = sanitize_text_field($_GET['ctab'] ?? 'briefing');
    $base_url   = home_url('/briefing');

    $module_states = self::get_module_states($client_id, $services, $wpdb);

    echo '<div class="ttb-container">';

    echo '<div class="ttb-card ttb-card--header">';
    echo '<h2>' . esc_html($t['greeting']) . ', ' . esc_html($c->name) . ' 👋</h2>';
    echo '<p class="ttb-muted">' . esc_html($t['sub']) . '</p>';
    echo '</div>';

    if (!$services) {
      echo '<div class="ttb-card"><p class="ttb-muted">' . esc_html($t['no_svc']) . '</p></div></div>';
      return;
    }

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

      .ttbc-panel { display: none; }
      .ttbc-panel.active { display: block; }

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

      /* ── Multi-project styles ── */
      .ttbc-project-block {
        margin-bottom: 16px;
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid var(--ttb-border);
        box-shadow: 0 4px 14px rgba(0,0,0,.06);
      }
      .ttbc-project-block--active {
        border-color: rgba(215,33,115,.25);
        box-shadow: 0 6px 20px rgba(215,33,115,.10);
      }
      .ttbc-project-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        background: #f9fafb;
        cursor: pointer;
        user-select: none;
        gap: 12px;
        flex-wrap: wrap;
        transition: background .15s;
      }
      .ttbc-project-block--active .ttbc-project-header {
        background: rgba(215,33,115,.06);
      }
      .ttbc-project-header:hover {
        background: rgba(215,33,115,.08);
      }
      .ttbc-project-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }
      .ttbc-project-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--ttb-text);
      }
      .ttbc-project-badge-active {
        font-size: 11px;
        font-weight: 900;
        padding: 3px 10px;
        border-radius: 999px;
        background: rgba(215,33,115,.12);
        border: 1px solid rgba(215,33,115,.3);
        color: var(--ttb-pink);
      }
      .ttbc-project-badge-old {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 999px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        color: #6b7280;
      }
      .ttbc-project-date {
        font-size: 12px;
        color: var(--ttb-muted);
      }
      .ttbc-project-toggle-btn {
        font-size: 12px;
        font-weight: 700;
        color: var(--ttb-pink);
        background: none;
        border: 1.5px solid rgba(215,33,115,.3);
        border-radius: 8px;
        padding: 5px 12px;
        cursor: pointer;
        transition: background .15s;
        white-space: nowrap;
      }
      .ttbc-project-toggle-btn:hover {
        background: rgba(215,33,115,.08);
      }
      .ttbc-project-body {
        overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease;
      }
      .ttbc-project-body.collapsed {
        max-height: 0 !important;
        opacity: 0;
        pointer-events: none;
      }
      .ttbc-project-body.expanded {
        opacity: 1;
      }
      .ttbc-project-status-pill {
        display: inline-block;
        font-size: 11px;
        font-weight: 800;
        padding: 3px 9px;
        border-radius: 999px;
      }
    </style>';

    // Render tabs
    echo '<div class="ttbc-tabs">';

    $brief_active = ($active_tab === 'briefing') ? ' active' : '';
    echo '<a class="ttbc-tab' . $brief_active . '" href="' . esc_url(add_query_arg('ctab', 'briefing', $base_url)) . '">';
    echo '📋 ' . esc_html($t['tab_brief']);
    echo '</a>';

    $module_tabs = [
      'design' => ['icon' => '🎨', 'label' => $t['tab_design'],  'tab_key' => 'design'],
      'social' => ['icon' => '📣', 'label' => $t['tab_social'],  'tab_key' => 'social'],
      'web'    => ['icon' => '🌐', 'label' => $t['tab_web'],     'tab_key' => 'web'],
    ];

    foreach ($module_tabs as $svc => $cfg) {
      if (!in_array($svc, $services, true)) continue;

      $state      = $module_states[$svc . '_state'] ?? 'locked';
      $tab_active = ($active_tab === $cfg['tab_key']) ? ' active' : '';

      if ($state === 'locked') {
        echo '<span class="ttbc-tab locked">';
        echo $cfg['icon'] . ' ' . esc_html($cfg['label']);
        echo ' <span class="ttbc-tab-badge ttbc-badge-lock">🔒</span>';
        echo '</span>';
      } elseif ($state === 'wip') {
        echo '<a class="ttbc-tab' . $tab_active . '" href="' . esc_url(add_query_arg('ctab', $cfg['tab_key'], $base_url)) . '">';
        echo $cfg['icon'] . ' ' . esc_html($cfg['label']);
        echo ' <span class="ttbc-tab-badge ttbc-badge-wip">⚙️</span>';
        echo '</a>';
      } else {
        // ready: puede tener múltiples proyectos
        $count = count($module_states[$svc . '_projects'] ?? []);
        echo '<a class="ttbc-tab' . $tab_active . '" href="' . esc_url(add_query_arg('ctab', $cfg['tab_key'], $base_url)) . '">';
        echo $cfg['icon'] . ' ' . esc_html($cfg['label']);
        if ($count > 1) {
          echo ' <span class="ttbc-tab-badge" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8">' . $count . '</span>';
        } else {
          echo ' <span class="ttbc-tab-badge ttbc-badge-ready">✓</span>';
        }
        echo '</a>';
      }
    }

    echo '</div>';

    // Panels
    $brief_panel_active = ($active_tab === 'briefing') ? ' active' : '';
    echo '<div class="ttbc-panel' . $brief_panel_active . '" id="ttbc-panel-briefing">';
    self::render_briefing_panel($client_id, $c, $services, $lang);
    echo '</div>';

    foreach ($module_tabs as $svc => $cfg) {
      if (!in_array($svc, $services, true)) continue;

      $state        = $module_states[$svc . '_state'] ?? 'locked';
      $panel_active = ($active_tab === $cfg['tab_key']) ? ' active' : '';

      echo '<div class="ttbc-panel' . $panel_active . '" id="ttbc-panel-' . esc_attr($cfg['tab_key']) . '">';

      if ($state === 'locked') {
        self::render_locked($t, $base_url);
      } elseif ($state === 'wip') {
        self::render_wip($t, $svc, $lang);
      } else {
        // Ready: render multiple projects
        self::render_module_projects($svc, $module_states, $t, $lang);
      }

      echo '</div>';
    }

    echo '</div>';
  }

  /* ════════════════════════════════════════════════════
     ESTADO DE MÓDULOS — Soporte multi-proyecto
  ════════════════════════════════════════════════════ */

  private static function get_module_states($client_id, $services, $wpdb) {
    $states = [];

    foreach ($services as $svc) {
      if ($svc === 'seo') continue;

      $briefing_sent = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT sent FROM " . TTB_DB::answers_table() . " WHERE client_id=%d AND service=%s",
        $client_id, $svc
      ));

      if (!$briefing_sent) {
        $states[$svc . '_state'] = 'locked';
        continue;
      }

      switch ($svc) {
        case 'design':
          // Buscar TODOS los proyectos de diseño de este cliente
          $client_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM " . TTB_DB::clients_table() . " WHERE id=%d LIMIT 1",
            $client_id
          ));
          $projects = TTB_WebRev_DB::get_projects_by_client_name((string)$client_name);

          if (empty($projects)) {
            $states[$svc . '_state'] = 'wip';
          } else {
            $states[$svc . '_state']    = 'ready';
            $states[$svc . '_projects'] = $projects;
          }
          break;

        case 'social':
          $sc_client = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token FROM " . TTB_Social_DB::clients_table() . " WHERE ttb_client_id=%d LIMIT 1",
            $client_id
          ));
          if ($sc_client) {
            $states[$svc . '_state']           = 'ready';
            $states['social_token']            = $sc_client->token;
            $has_posts = (int)$wpdb->get_var($wpdb->prepare(
              "SELECT COUNT(*) FROM " . TTB_Social_DB::posts_table() . " WHERE client_id=%d AND status != 'draft'",
              (int)$sc_client->id
            ));
            $states['social_has_posts'] = $has_posts > 0;
          } else {
            $states[$svc . '_state'] = 'wip';
          }
          break;

        case 'web':
          // Buscar TODOS los proyectos web de este cliente
          $client_name = $client_name ?? $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM " . TTB_DB::clients_table() . " WHERE id=%d LIMIT 1",
            $client_id
          ));
          $projects = TTB_WebProg_DB::get_projects_by_client_name((string)$client_name);

          if (empty($projects)) {
            $states[$svc . '_state'] = 'wip';
          } else {
            $states[$svc . '_state']    = 'ready';
            $states[$svc . '_projects'] = $projects;
          }
          break;
      }
    }

    return $states;
  }

  /* ════════════════════════════════════════════════════
     PANEL: PREBRIEFING
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
     PANEL: MÓDULO BLOQUEADO
  ════════════════════════════════════════════════════ */

  private static function render_locked($t, $base_url) {
    echo '<div class="ttb-card">';
    echo '<div class="ttbc-locked-box">';
    echo '<span class="ttbc-locked-icon">🔒</span>';
    echo '<h3>' . esc_html($t['locked_title']) . '</h3>';
    echo '<p>' . esc_html($t['locked_msg']) . '</p>';
    echo '<a href="' . esc_url(add_query_arg('ctab', 'briefing', home_url('/briefing'))) . '" class="ttb-btn">'
      . esc_html($t['locked_btn']) . '</a>';
    echo '</div>';
    echo '</div>';
  }

  /* ════════════════════════════════════════════════════
     PANEL: MÓDULO EN PROGRESO
  ════════════════════════════════════════════════════ */

  private static function render_wip($t, $svc, $lang) {
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
     PANEL: MÚLTIPLES PROYECTOS
     Renderiza todos los proyectos del módulo.
     El primero (más reciente) expandido, los demás plegados.
  ════════════════════════════════════════════════════ */

  private static function render_module_projects($svc, $module_states, $t, $lang) {

    // Social: caso especial (no tiene multi-proyecto)
    if ($svc === 'social') {
      $token     = $module_states['social_token'] ?? '';
      $has_posts = $module_states['social_has_posts'] ?? false;
      if (!$token) { self::render_wip($t, $svc, $lang); return; }
      if (!$has_posts) {
        self::render_social_waiting($t, $token, $lang);
      } else {
        $_GET['ttb_return'] = 'main';
        TTB_Social_Client::render($token);
      }
      return;
    }

    // Design o Web: multi-proyecto
    $projects = $module_states[$svc . '_projects'] ?? [];
    if (empty($projects)) {
      self::render_wip($t, $svc, $lang);
      return;
    }

    $statuses_design = [
      'pending'           => ['⏳ Pendiente',          '#fffbeb','#fde68a','#92400e'],
      'changes_requested' => ['✏️ Cambios solicitados', '#fffbeb','#fcd34d','#92400e'],
      'accepted'          => ['✅ Diseño aceptado',     '#ecfdf5','#6ee7b7','#065f46'],
    ];
    $statuses_web = [
      'pending'           => ['⏳ Pendiente',          '#fffbeb','#fde68a','#92400e'],
      'changes_requested' => ['✏️ Cambios solicitados', '#fffbeb','#fcd34d','#92400e'],
      'accepted'          => ['✅ Web aceptada',        '#ecfdf5','#6ee7b7','#065f46'],
    ];
    $statuses = ($svc === 'design') ? $statuses_design : $statuses_web;

    $total = count($projects);

    foreach ($projects as $idx => $project) {
      $is_first   = ($idx === 0);
      $project_id = (int)$project->id;
      $block_id   = 'ttbc-proj-' . $svc . '-' . $project_id;

      // Determinar título visible
      $display_title = '';
      if (!empty($project->title)) {
        $display_title = $project->title;
      } else {
        $display_title = ($svc === 'design')
          ? ($lang === 'en' ? 'Design Project' : 'Proyecto de Diseño')
          : ($lang === 'en' ? 'Web Project' : 'Proyecto Web');
        if ($total > 1) {
          $display_title .= ' ' . ($total - $idx);
        }
      }

      [$sl, $sbg, $sbc, $sco] = $statuses[$project->status] ?? ['—','#f3f4f6','#e5e7eb','#374151'];

      $date_fmt = date_i18n('d/m/Y', strtotime($project->created_at));

      echo '<div class="ttbc-project-block' . ($is_first ? ' ttbc-project-block--active' : '') . '" id="' . esc_attr($block_id) . '">';

      // Header (clickable para plegar/desplegar)
      echo '<div class="ttbc-project-header" onclick="ttbcToggleProject(\'' . esc_js($block_id) . '\')" role="button" tabindex="0" onkeydown="if(event.key===\'Enter\'||event.key===\' \')ttbcToggleProject(\'' . esc_js($block_id) . '\')">';
      echo '<div class="ttbc-project-header-left">';
      echo '<span class="ttbc-project-title">' . esc_html($display_title) . '</span>';
      if ($is_first) {
        echo '<span class="ttbc-project-badge-active">' . esc_html($t['project_active']) . '</span>';
      } else {
        echo '<span class="ttbc-project-badge-old">' . esc_html($t['project_older']) . '</span>';
      }
      echo '<span class="ttbc-project-status-pill" style="background:' . $sbg . ';border:1px solid ' . $sbc . ';color:' . $sco . '">' . esc_html($sl) . '</span>';
      echo '<span class="ttbc-project-date">' . esc_html($date_fmt) . '</span>';
      echo '</div>';

      $btn_label = $is_first ? esc_html($t['hide_project']) : esc_html($t['show_project']);
      echo '<button type="button" class="ttbc-project-toggle-btn" id="' . esc_attr($block_id) . '-btn">' . $btn_label . '</button>';
      echo '</div>';

      // Body (expandido si es el primero, plegado si no)
      $initial_class = $is_first ? 'expanded' : 'collapsed';
      $initial_style = $is_first ? 'max-height:9999px;' : 'max-height:0;';
      echo '<div class="ttbc-project-body ' . $initial_class . '" id="' . esc_attr($block_id) . '-body" style="' . $initial_style . '">';

      // Renderizar el contenido del proyecto
      if ($svc === 'design') {
        TTB_WebRev_Client::render($project->token);
      } else {
        TTB_WebProg_Client::render($project->token);
      }

      echo '</div>';
      echo '</div>';
    }

    // JavaScript para toggle
    echo '<script>
    (function(){
      window.ttbcToggleProject = window.ttbcToggleProject || function(blockId) {
        var body = document.getElementById(blockId + \'-body\');
        var btn  = document.getElementById(blockId + \'-btn\');
        var block = document.getElementById(blockId);
        if (!body) return;

        var isCollapsed = body.classList.contains(\'collapsed\');
        var isActive    = block.classList.contains(\'ttbc-project-block--active\');
        var labelShow   = \'' . esc_js($t['show_project']) . '\';
        var labelHide   = \'' . esc_js($t['hide_project']) . '\';

        if (isCollapsed) {
          body.style.maxHeight = body.scrollHeight + \'px\';
          body.classList.remove(\'collapsed\');
          body.classList.add(\'expanded\');
          body.addEventListener(\'transitionend\', function onEnd() {
            body.style.maxHeight = \'9999px\';
            body.removeEventListener(\'transitionend\', onEnd);
          });
          if (btn) btn.textContent = labelHide;
        } else {
          body.style.maxHeight = body.scrollHeight + \'px\';
          body.getBoundingClientRect();
          body.style.maxHeight = \'0\';
          body.classList.add(\'collapsed\');
          body.classList.remove(\'expanded\');
          if (btn) btn.textContent = labelShow;
        }
      };
    })();
    </script>';
  }
}