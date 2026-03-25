<?php
if (!defined('ABSPATH')) exit;

class TTB_Activator {

  public static function activate() {
    self::create_tables();
    self::seed_admin_credentials();
    self::seed_forms();
    (new TTB_Router())->add_rewrite();
    flush_rewrite_rules();
    TTB_WebRev_DB::create_tables();
    TTB_WebRev_Cron::register();
    TTB_WebProg_DB::create_tables();
    TTB_WebProg_Cron::register();
    TTB_Social_DB::create_tables();
    TTB_Social_Cron::register();
  }

  private static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $clients = TTB_DB::clients_table();
    $answers = TTB_DB::answers_table();

    $sql1 = "CREATE TABLE $clients (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(190) NOT NULL,
      email VARCHAR(190) NOT NULL,
      username VARCHAR(190) NOT NULL,
      pass_hash VARCHAR(255) NOT NULL,
      services LONGTEXT NULL,
      lang VARCHAR(5) NOT NULL DEFAULT 'es',
      status VARCHAR(40) NOT NULL DEFAULT 'pendiente',
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY username_unique (username),
      KEY email_idx (email)
    ) $charset;";

    $sql2 = "CREATE TABLE $answers (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      client_id BIGINT UNSIGNED NOT NULL,
      service VARCHAR(20) NOT NULL,
      answers LONGTEXT NULL,
      sent TINYINT(1) NOT NULL DEFAULT 0,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY client_service (client_id, service),
      KEY client_idx (client_id)
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);

    $col = $wpdb->get_results("SHOW COLUMNS FROM $clients LIKE 'lang'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE $clients ADD COLUMN lang VARCHAR(5) NOT NULL DEFAULT 'es' AFTER services");
    }
  }

  private static function seed_admin_credentials() {
    if (!get_option('ttb_admin_user')) {
      update_option('ttb_admin_user', 'tictac');
    }
    if (!get_option('ttb_admin_pass_hash')) {
      $hash = password_hash('Sipilu2019', PASSWORD_DEFAULT);
      update_option('ttb_admin_pass_hash', $hash);
    }
  }

  private static function seed_forms() {
    if (!get_option('ttb_form_design'))    update_option('ttb_form_design',    wp_json_encode(self::default_form_design(),    JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_social'))    update_option('ttb_form_social',    wp_json_encode(self::default_form_social(),    JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_seo'))       update_option('ttb_form_seo',       wp_json_encode(self::default_form_seo(),       JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_web'))       update_option('ttb_form_web',       wp_json_encode(self::default_form_web(),       JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_reservas'))  update_option('ttb_form_reservas',  wp_json_encode(self::default_form_reservas(),  JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

    if (!get_option('ttb_form_design_en'))   update_option('ttb_form_design_en',   wp_json_encode(self::default_form_design_en(),   JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_social_en'))   update_option('ttb_form_social_en',   wp_json_encode(self::default_form_social_en(),   JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_seo_en'))      update_option('ttb_form_seo_en',      wp_json_encode(self::default_form_seo_en(),      JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_web_en'))      update_option('ttb_form_web_en',      wp_json_encode(self::default_form_web_en(),      JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_reservas_en')) update_option('ttb_form_reservas_en', wp_json_encode(self::default_form_reservas_en(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
  }

  private static function f($id, $label, $type = 'text', $required = false, $options = []) {
    $field = ['id' => $id, 'label' => $label, 'type' => $type, 'required' => (bool)$required];
    if (!empty($options)) $field['options'] = array_values($options);
    return $field;
  }

  /* ── Formularios existentes (sin cambios) ── */

  private static function default_form_design() {
    return [
      self::f('brand_name',    'Nombre de la marca/empresa',          'text',     true),
      self::f('contact_person','Persona de contacto',                 'text',     true),
      self::f('email',         'Email',                               'email',    true),
      self::f('phone',         'Teléfono/WhatsApp',                   'text',     false),
      self::f('brand_desc',    'Describe tu marca (1–2 párrafos)',    'textarea', true),
      self::f('target',        'Cliente ideal (quién es y qué busca)','textarea', true),
      self::f('tone',          'Tono de comunicación',                'textarea', false),
      self::f('colors',        'Colores corporativos (hex si los tienes)','text', false),
      self::f('references',    'Referencias visuales (URLs)',          'textarea', false),
      self::f('deliverables',  'Qué necesitas exactamente',           'textarea', true),
      self::f('deadline',      'Fecha límite / urgencia',             'text',     false),
      self::f('notes',         'Notas adicionales',                   'textarea', false),
    ];
  }

  private static function default_form_social() {
    return [
      self::f('brand_name',     'Nombre de la marca/empresa',             'text',     true),
      self::f('ig_handle',      'Instagram @usuario',                     'text',     false),
      self::f('channels',       'Otros canales (TikTok, LinkedIn...)',     'text',     false),
      self::f('objectives',     'Objetivos',                              'textarea', true),
      self::f('offer',          'Servicios / productos a destacar',       'textarea', true),
      self::f('audience',       'Público objetivo',                       'textarea', true),
      self::f('diff',           'Qué te diferencia (3 puntos)',           'textarea', true),
      self::f('tone',           'Tono deseado',                           'textarea', false),
      self::f('content_types',  'Tipo de contenido preferido',            'select',   false, ['Reels','Carruseles','Imagen única','Stories','Mixto']),
      self::f('freq',           'Frecuencia ideal (posts/semana)',         'text',     false),
      self::f('resources',      'Recursos disponibles (foto/vídeo)',      'textarea', false),
      self::f('dont',           'Qué NO se puede decir/mostrar',          'textarea', false),
      self::f('competitors',    'Competidores/referencias (URLs o @)',     'textarea', false),
      self::f('cta',            'CTA principal',                          'text',     false),
      self::f('notes',          'Notas adicionales',                      'textarea', false),
    ];
  }

  private static function default_form_seo() {
    return [
      self::f('web_url',         'Web actual (URL)',                                'url',      false),
      self::f('business_desc',   'Describe tu negocio y foco principal',            'textarea', true),
      self::f('main_goal',       'Objetivo principal',                              'select',   true,  ['Leads','Llamadas-WhatsApp','Reservas','Ventas online','Otro']),
      self::f('priority_offer',  'Lo que quieres vender primero (prioridad)',       'textarea', true),
      self::f('top_categories',  'Top 3 servicios o categorías',                    'textarea', true),
      self::f('star_products',   'Top 3 productos/servicios estrella',              'textarea', false),
      self::f('service_area',    'Zona de trabajo/venta + ¿local? + ¿GBP?',        'textarea', true),
      self::f('ideal_client',    'Cliente ideal',                                   'textarea', true),
      self::f('why_choose',      'Por qué te eligen (3 puntos)',                    'textarea', true),
      self::f('avg_ticket',      'Ticket medio',                                    'text',     false),
      self::f('how_find_you',    'Cómo te busca el cliente y qué solicita',         'textarea', false),
      self::f('competitors_urls','3 competidores (URLs)',                            'textarea', false),
      self::f('webs_like',       '1–2 webs que te gusten (URLs)',                   'textarea', false),
      self::f('cta',             'CTA principal',                                   'text',     false),
      self::f('contact',         'Teléfono/WhatsApp + email de recepción',          'textarea', false),
      self::f('accesses',        'Accesos (GSC/GA4/CMS)',                           'textarea', false),
    ];
  }

  private static function default_form_web() {
    return [
      self::f('project_type','Tipo de web',                          'select',   true,  ['Corporativa','E-commerce','Landing','Blog','Otro']),
      self::f('web_url',     'Web actual (si existe)',               'url',      false),
      self::f('goal',        'Objetivo principal de la web',         'textarea', true),
      self::f('structure',   'Estructura deseada (secciones/páginas)','textarea',false),
      self::f('services',    'Servicios/productos',                  'textarea', true),
      self::f('diff',        'Diferenciadores (3 puntos)',           'textarea', true),
      self::f('assets',      '¿Tienes logo/branding/fotos? (enlace Drive)','textarea',false),
      self::f('references',  'Referencias de webs (URLs)',           'textarea', false),
      self::f('languages',   'Idiomas',                              'text',     false),
      self::f('features',    'Funcionalidades (formularios, reservas, pagos...)','textarea',false),
      self::f('legal',       '¿Necesitas textos legales?',           'select',   false, ['Sí','No','No lo sé']),
      self::f('deadline',    'Fecha objetivo',                       'text',     false),
      self::f('notes',       'Notas adicionales',                    'textarea', false),
    ];
  }

  /* ── NUEVO: Gestor de reservas restaurante ── */

  private static function default_form_reservas() {
    return [
      self::f('mesas_cantidad',          '¿Cuántas mesas tienes y de cuántos comensales cada una?',                             'textarea', true),
      self::f('mesas_unir',              '¿Se pueden unir mesas?',                                                              'select',   false, ['Sí', 'No', 'En algunos casos']),
      self::f('comensales_min_max',      '¿Número mínimo y máximo de personas por reserva?',                                    'text',     true),
      self::f('zonas',                   '¿Quieres diferenciar zonas? (interior, terraza, privado…)',                           'textarea', false),
      self::f('dias_apertura',           '¿Qué días abres y qué días cierras?',                                                 'textarea', true),
      self::f('horarios_servicio',       '¿Cuáles son tus horarios de comida y cena?',                                          'textarea', true),
      self::f('intervalo_reservas',      '¿Cada cuánto tiempo quieres permitir reservas?',                                      'select',   true,  ['15 minutos', '30 minutos', '60 minutos', 'Otro']),
      self::f('duracion_reserva',        '¿Cuánto dura aproximadamente una reserva? (tiempo que ocupa la mesa)',                'text',     true),
      self::f('antelacion_minima',       '¿Con cuánta antelación mínima se puede hacer una reserva?',                           'text',     true),
      self::f('antelacion_maxima',       '¿Con cuánta antelación máxima se puede reservar?',                                    'text',     false),
      self::f('reservas_mismo_dia',      '¿Aceptas reservas el mismo día?',                                                     'select',   true,  ['Sí, siempre', 'Sí, hasta cierta hora', 'No']),
      self::f('gestion_manual_personas', '¿A partir de cuántas personas prefieres gestionar la reserva manualmente?',           'text',     false),
      self::f('confirmacion_tipo',       '¿Las reservas se confirman automáticamente o prefieres confirmarlas tú manualmente?', 'select',   true,  ['Automáticamente', 'Manualmente por mí', 'Automático para grupos pequeños, manual para grupos grandes']),
      self::f('recordatorios',           '¿Quieres enviar recordatorios automáticos al cliente antes de su reserva?',           'select',   false, ['Sí, por email', 'Sí, por SMS', 'Sí, por email y SMS', 'No']),
      self::f('deposito_activo',         '¿Quieres cobrar un depósito o señal para confirmar la reserva?',                     'select',   true,  ['Sí', 'No', 'Solo en algunos casos']),
      self::f('deposito_importe',        '¿Cuánto sería el depósito? (importe fijo o por persona)',                             'text',     false),
      self::f('deposito_casos',          '¿En qué casos aplicarías el depósito? (grupos grandes, fines de semana…)',            'textarea', false),
      self::f('deposito_no_show',        '¿El depósito se pierde si el cliente no se presenta?',                                'select',   false, ['Sí, se pierde íntegramente', 'Sí, pero se puede devolver si cancela con antelación', 'No se pierde', 'Aún no lo he decidido']),
      self::f('datos_cliente',           '¿Qué datos quieres pedir al cliente al reservar? (nombre, teléfono, email…)',         'textarea', true),
      self::f('campo_observaciones',     '¿Quieres un campo de observaciones para alergias, peticiones especiales, etc.?',      'select',   false, ['Sí', 'No']),
      self::f('cancelacion_online',      '¿El cliente puede cancelar o modificar su reserva online?',                          'select',   true,  ['Sí, cancelar y modificar', 'Solo cancelar', 'No, debe llamar']),
      self::f('cancelacion_plazo',       '¿Hasta cuánto tiempo antes puede cancelar o modificar?',                             'text',     false),
      self::f('no_show_politica',        '¿Qué pasa si el cliente no se presenta sin avisar? ¿Tienes alguna política definida?','textarea', false),
      self::f('gestion_aceptar_rechazar','¿Quieres poder aceptar o rechazar reservas manualmente desde el panel de gestión?',   'select',   true,  ['Sí', 'No, prefiero que sea todo automático']),
      self::f('calendario_interno',      '¿Necesitas un calendario visual con todas las reservas del día/semana?',              'select',   true,  ['Sí', 'No']),
      self::f('bloqueo_dias_horas',      '¿Necesitas poder bloquear días completos u horas concretas?',                        'select',   true,  ['Sí', 'No']),
      self::f('notas_adicionales',       '¿Hay algo más que debamos saber sobre tu restaurante o cómo gestionas las reservas?','textarea', false),
    ];
  }

  /* ── Formularios EN existentes (sin cambios) ── */

  private static function default_form_design_en() {
    return [
      self::f('brand_name',    'Brand / company name',               'text',     true),
      self::f('contact_person','Contact person',                     'text',     true),
      self::f('email',         'Email',                              'email',    true),
      self::f('phone',         'Phone / WhatsApp',                   'text',     false),
      self::f('brand_desc',    'Describe your brand (1–2 paragraphs)','textarea',true),
      self::f('target',        'Ideal customer (who they are and what they look for)','textarea',true),
      self::f('tone',          'Communication tone',                 'textarea', false),
      self::f('colors',        'Brand colours (hex codes if available)','text',  false),
      self::f('references',    'Visual references (URLs)',            'textarea', false),
      self::f('deliverables',  'What exactly do you need?',          'textarea', true),
      self::f('deadline',      'Deadline / urgency',                 'text',     false),
      self::f('notes',         'Additional notes',                   'textarea', false),
    ];
  }

  private static function default_form_social_en() {
    return [
      self::f('brand_name',    'Brand / company name',               'text',     true),
      self::f('ig_handle',     'Instagram @handle',                  'text',     false),
      self::f('channels',      'Other channels (TikTok, LinkedIn…)', 'text',     false),
      self::f('objectives',    'Goals & objectives',                 'textarea', true),
      self::f('offer',         'Services / products to highlight',   'textarea', true),
      self::f('audience',      'Target audience',                    'textarea', true),
      self::f('diff',          'What sets you apart (3 points)',     'textarea', true),
      self::f('tone',          'Desired tone',                       'textarea', false),
      self::f('content_types', 'Preferred content type',             'select',   false, ['Reels','Carousels','Single image','Stories','Mixed']),
      self::f('freq',          'Ideal frequency (posts/week)',        'text',     false),
      self::f('resources',     'Available resources (photos/video)', 'textarea', false),
      self::f('dont',          'What should NOT be said/shown',      'textarea', false),
      self::f('competitors',   'Competitors / references (URLs or @)','textarea',false),
      self::f('cta',           'Main CTA',                           'text',     false),
      self::f('notes',         'Additional notes',                   'textarea', false),
    ];
  }

  private static function default_form_seo_en() {
    return [
      self::f('web_url',         'Current website (URL)',                          'url',      false),
      self::f('business_desc',   'Describe your business and main focus',          'textarea', true),
      self::f('main_goal',       'Primary goal',                                   'select',   true,  ['Leads','Calls/WhatsApp','Bookings','Online sales','Other']),
      self::f('priority_offer',  'What you want to sell first (priority)',         'textarea', true),
      self::f('top_categories',  'Top 3 services or categories',                  'textarea', true),
      self::f('star_products',   'Top 3 star products/services',                  'textarea', false),
      self::f('service_area',    'Work/sales area + local? + Google Business Profile?','textarea',true),
      self::f('ideal_client',    'Ideal client',                                   'textarea', true),
      self::f('why_choose',      'Why clients choose you (3 points)',              'textarea', true),
      self::f('avg_ticket',      'Average order value',                            'text',     false),
      self::f('how_find_you',    'How clients search for you and what they request','textarea',false),
      self::f('competitors_urls','3 competitors (URLs)',                            'textarea', false),
      self::f('webs_like',       '1–2 websites you like (URLs)',                   'textarea', false),
      self::f('cta',             'Main CTA',                                       'text',     false),
      self::f('contact',         'Phone/WhatsApp + reception email',               'textarea', false),
      self::f('accesses',        'Access credentials (GSC/GA4/CMS)',               'textarea', false),
    ];
  }

  private static function default_form_web_en() {
    return [
      self::f('project_type','Type of website',                      'select',   true,  ['Corporate','E-commerce','Landing page','Blog','Other']),
      self::f('web_url',     'Current website (if any)',             'url',      false),
      self::f('goal',        'Main goal of the website',             'textarea', true),
      self::f('structure',   'Desired structure (sections/pages)',   'textarea', false),
      self::f('services',    'Services / products',                  'textarea', true),
      self::f('diff',        'Differentiators (3 points)',           'textarea', true),
      self::f('assets',      'Do you have a logo/branding/photos? (Drive link)','textarea',false),
      self::f('references',  'Website references (URLs)',            'textarea', false),
      self::f('languages',   'Languages',                            'text',     false),
      self::f('features',    'Features needed (forms, bookings, payments…)','textarea',false),
      self::f('legal',       'Do you need legal texts?',             'select',   false, ['Yes','No','Not sure']),
      self::f('deadline',    'Target date',                          'text',     false),
      self::f('notes',       'Additional notes',                     'textarea', false),
    ];
  }

  /* ── NUEVO EN: Reservation manager ── */

  private static function default_form_reservas_en() {
    return [
      self::f('mesas_cantidad',          'How many tables do you have and how many diners per table?',                         'textarea', true),
      self::f('mesas_unir',              'Can tables be joined together?',                                                     'select',   false, ['Yes', 'No', 'In some cases']),
      self::f('comensales_min_max',      'Minimum and maximum number of people per booking?',                                  'text',     true),
      self::f('zonas',                   'Do you want to differentiate areas? (indoor, terrace, private room…)',               'textarea', false),
      self::f('dias_apertura',           'Which days are you open and which days are you closed?',                             'textarea', true),
      self::f('horarios_servicio',       'What are your lunch and dinner service hours?',                                      'textarea', true),
      self::f('intervalo_reservas',      'How often do you want to allow bookings?',                                           'select',   true,  ['Every 15 minutes', 'Every 30 minutes', 'Every 60 minutes', 'Other']),
      self::f('duracion_reserva',        'How long does a booking last approximately? (time a table is occupied)',             'text',     true),
      self::f('antelacion_minima',       'How far in advance (minimum) can a booking be made?',                                'text',     true),
      self::f('antelacion_maxima',       'How far in advance (maximum) can a booking be made?',                                'text',     false),
      self::f('reservas_mismo_dia',      'Do you accept same-day bookings?',                                                   'select',   true,  ['Yes, always', 'Yes, up to a certain time', 'No']),
      self::f('gestion_manual_personas', 'From how many people do you prefer to manage the booking manually?',                 'text',     false),
      self::f('confirmacion_tipo',       'Are bookings confirmed automatically or do you prefer to confirm them manually?',    'select',   true,  ['Automatically', 'Manually by me', 'Automatic for small groups, manual for large groups']),
      self::f('recordatorios',           'Do you want to send automatic reminders to the customer before their booking?',      'select',   false, ['Yes, by email', 'Yes, by SMS', 'Yes, by email and SMS', 'No']),
      self::f('deposito_activo',         'Do you want to charge a deposit to confirm the booking?',                            'select',   true,  ['Yes', 'No', 'Only in some cases']),
      self::f('deposito_importe',        'How much would the deposit be? (fixed amount or per person)',                        'text',     false),
      self::f('deposito_casos',          'In which cases would you apply the deposit? (large groups, weekends…)',              'textarea', false),
      self::f('deposito_no_show',        'Is the deposit lost if the customer does not show up?',                              'select',   false, ['Yes, fully forfeited', 'Yes, but refundable if cancelled in advance', 'Not forfeited', 'Not decided yet']),
      self::f('datos_cliente',           'What customer data do you need when booking? (name, phone, email…)',                 'textarea', true),
      self::f('campo_observaciones',     'Do you want an observations field for allergies, special requests, etc.?',           'select',   false, ['Yes', 'No']),
      self::f('cancelacion_online',      'Can the customer cancel or modify their booking online?',                            'select',   true,  ['Yes, cancel and modify', 'Cancel only', 'No, they must call']),
      self::f('cancelacion_plazo',       'How far in advance can they cancel or modify?',                                      'text',     false),
      self::f('no_show_politica',        'What happens if the customer does not show up without notice? Any policy defined?',  'textarea', false),
      self::f('gestion_aceptar_rechazar','Do you want to be able to accept or reject bookings manually from the panel?',       'select',   true,  ['Yes', 'No, I prefer everything to be automatic']),
      self::f('calendario_interno',      'Do you need a visual calendar showing all bookings for the day/week?',               'select',   true,  ['Yes', 'No']),
      self::f('bloqueo_dias_horas',      'Do you need to be able to block full days or specific hours?',                       'select',   true,  ['Yes', 'No']),
      self::f('notas_adicionales',       'Is there anything else we should know about your restaurant or how you manage bookings?','textarea', false),
    ];
  }
}