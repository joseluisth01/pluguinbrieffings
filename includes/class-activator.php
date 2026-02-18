<?php
if (!defined('ABSPATH')) exit;

class TTB_Activator {

  public static function activate() {
    self::create_tables();
    self::seed_admin_credentials();
    self::seed_forms();
    (new TTB_Router())->add_rewrite();
    flush_rewrite_rules();
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
  }

  private static function seed_admin_credentials() {
    // Guarda hash del admin en options (no depende de wp_users)
    if (!get_option('ttb_admin_user')) {
      update_option('ttb_admin_user', 'tictac');
    }
    if (!get_option('ttb_admin_pass_hash')) {
      $hash = password_hash('Sipilu2019', PASSWORD_DEFAULT);
      update_option('ttb_admin_pass_hash', $hash);
    }
  }

  private static function seed_forms() {
    if (!get_option('ttb_form_design')) update_option('ttb_form_design', wp_json_encode(self::default_form_design(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_social')) update_option('ttb_form_social', wp_json_encode(self::default_form_social(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_seo'))    update_option('ttb_form_seo',    wp_json_encode(self::default_form_seo(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if (!get_option('ttb_form_web'))    update_option('ttb_form_web',    wp_json_encode(self::default_form_web(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
  }

  private static function f($id,$label,$type='text',$required=false,$options=[]) {
    $field = ['id'=>$id,'label'=>$label,'type'=>$type,'required'=>(bool)$required];
    if (!empty($options)) $field['options'] = array_values($options);
    return $field;
  }

  private static function default_form_design() {
    return [
      self::f('brand_name','Nombre de la marca/empresa','text',true),
      self::f('contact_person','Persona de contacto','text',true),
      self::f('email','Email','email',true),
      self::f('phone','Teléfono/WhatsApp','text',false),
      self::f('brand_desc','Describe tu marca (1–2 párrafos)','textarea',true),
      self::f('target','Cliente ideal (quién es y qué busca)','textarea',true),
      self::f('tone','Tono de comunicación','textarea',false),
      self::f('colors','Colores corporativos (hex si los tienes)','text',false),
      self::f('references','Referencias visuales (URLs)','textarea',false),
      self::f('deliverables','Qué necesitas exactamente','textarea',true),
      self::f('deadline','Fecha límite / urgencia','text',false),
      self::f('notes','Notas adicionales','textarea',false),
    ];
  }

  private static function default_form_social() {
    return [
      self::f('brand_name','Nombre de la marca/empresa','text',true),
      self::f('ig_handle','Instagram @usuario','text',false),
      self::f('channels','Otros canales (TikTok, LinkedIn...)','text',false),
      self::f('objectives','Objetivos','textarea',true),
      self::f('offer','Servicios / productos a destacar','textarea',true),
      self::f('audience','Público objetivo','textarea',true),
      self::f('diff','Qué te diferencia (3 puntos)','textarea',true),
      self::f('tone','Tono deseado','textarea',false),
      self::f('content_types','Tipo de contenido preferido','select',false,['Reels','Carruseles','Imagen única','Stories','Mixto']),
      self::f('freq','Frecuencia ideal (posts/semana)','text',false),
      self::f('resources','Recursos disponibles (foto/vídeo)','textarea',false),
      self::f('dont','Qué NO se puede decir/mostrar','textarea',false),
      self::f('competitors','Competidores/referencias (URLs o @)','textarea',false),
      self::f('cta','CTA principal','text',false),
      self::f('notes','Notas adicionales','textarea',false),
    ];
  }

  private static function default_form_seo() {
    return [
      self::f('web_url','Web actual (URL)','url',false),
      self::f('business_desc','Describe tu negocio y foco principal','textarea',true),
      self::f('main_goal','Objetivo principal','select',true,['Leads','Llamadas-WhatsApp','Reservas','Ventas online','Otro']),
      self::f('priority_offer','Lo que quieres vender primero (prioridad)','textarea',true),
      self::f('top_categories','Top 3 servicios o categorías','textarea',true),
      self::f('star_products','Top 3 productos/servicios estrella','textarea',false),
      self::f('service_area','Zona de trabajo/venta + ¿local? + ¿GBP?','textarea',true),
      self::f('ideal_client','Cliente ideal','textarea',true),
      self::f('why_choose','Por qué te eligen (3 puntos)','textarea',true),
      self::f('avg_ticket','Ticket medio','text',false),
      self::f('how_find_you','Cómo te busca el cliente y qué solicita','textarea',false),
      self::f('competitors_urls','3 competidores (URLs)','textarea',false),
      self::f('webs_like','1–2 webs que te gusten (URLs)','textarea',false),
      self::f('cta','CTA principal','text',false),
      self::f('contact','Teléfono/WhatsApp + email de recepción','textarea',false),
      self::f('accesses','Accesos (GSC/GA4/CMS)','textarea',false),
    ];
  }

  private static function default_form_web() {
    return [
      self::f('project_type','Tipo de web','select',true,['Corporativa','E-commerce','Landing','Blog','Otro']),
      self::f('web_url','Web actual (si existe)','url',false),
      self::f('goal','Objetivo principal de la web','textarea',true),
      self::f('structure','Estructura deseada (secciones/páginas)','textarea',false),
      self::f('services','Servicios/productos','textarea',true),
      self::f('diff','Diferenciadores (3 puntos)','textarea',true),
      self::f('assets','¿Tienes logo/branding/fotos? (enlace Drive)','textarea',false),
      self::f('references','Referencias de webs (URLs)','textarea',false),
      self::f('languages','Idiomas','text',false),
      self::f('features','Funcionalidades (formularios, reservas, pagos...)','textarea',false),
      self::f('legal','¿Necesitas textos legales?','select',false,['Sí','No','No lo sé']),
      self::f('deadline','Fecha objetivo','text',false),
      self::f('notes','Notas adicionales','textarea',false),
    ];
  }
}
