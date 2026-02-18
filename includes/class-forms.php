<?php
if (!defined('ABSPATH')) exit;

class TTB_Forms {

  public function init() {
    add_action('init', [$this, 'handle_form_submit']);
  }

  public static function get_schema($service) {
    $map = [
      'design' => 'ttb_form_design',
      'social' => 'ttb_form_social',
      'seo'    => 'ttb_form_seo',
      'web'    => 'ttb_form_web',
    ];
    $opt = $map[$service] ?? '';
    $raw = $opt ? (string)get_option($opt, '') : '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }

  public function handle_form_submit() {
    $auth = new TTB_Auth();
    if (!$auth->is_client()) return;
    if (!isset($_POST['ttb_save_form'])) return;

    $client_id = $auth->client_id();
    $service   = sanitize_text_field($_POST['service'] ?? '');
    if (!in_array($service, ['design','social','seo','web'], true)) return;

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_form_'.$service)) {
      $auth->flash('error', 'Sesión inválida. Recarga y prueba otra vez.');
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }

    $schema  = self::get_schema($service);
    $answers = [];
    $errors  = [];

    foreach ($schema as $f) {
      $id = $f['id'] ?? '';
      if (!$id) continue;

      $required = !empty($f['required']);
      $type     = $f['type'] ?? 'text';

      $val = $_POST['f'][$id] ?? '';
      if (is_array($val)) {
        $val = array_map('sanitize_text_field', $val);
      } else {
        $val = sanitize_textarea_field((string)$val);
      }

      if ($type === 'email') {
        $val = sanitize_email((string)$val);
        if ($required && !is_email($val)) {
          $errors[$id] = 'Introduce un email válido.';
        }
      } elseif ($type === 'url') {
        $val = esc_url_raw((string)$val);
        if ($required && empty(trim($val))) {
          $errors[$id] = 'Este campo es obligatorio.';
        }
      } else {
        $empty = (is_array($val) && empty($val)) || (!is_array($val) && trim((string)$val) === '');
        if ($required && $empty) {
          $errors[$id] = 'Este campo es obligatorio.';
        }
      }

      $answers[$id] = $val;
    }

    // Errores → transients y redirect
    if ($errors) {
      set_transient('ttb_form_errors_' . $client_id . '_' . $service, $errors, 120);
      set_transient('ttb_form_values_' . $client_id . '_' . $service, $answers, 120);
      $auth->flash('error', 'Por favor, corrige los errores marcados en el formulario.');
      wp_safe_redirect(home_url('/briefing'));
      exit;
    }

    global $wpdb;
    $table = TTB_DB::answers_table();
    $mode  = sanitize_text_field($_POST['submit_mode'] ?? 'save');
    $sent  = ($mode === 'send') ? 1 : 0;

    $wpdb->query($wpdb->prepare(
      "INSERT INTO $table (client_id, service, answers, sent, updated_at)
       VALUES (%d, %s, %s, %d, %s)
       ON DUPLICATE KEY UPDATE answers=VALUES(answers), sent=GREATEST(sent, VALUES(sent)), updated_at=VALUES(updated_at)",
      $client_id, $service, wp_json_encode($answers, JSON_UNESCAPED_UNICODE), $sent, TTB_DB::now()
    ));

    $this->update_client_status($client_id);

    delete_transient('ttb_form_errors_' . $client_id . '_' . $service);
    delete_transient('ttb_form_values_' . $client_id . '_' . $service);

    if ($sent) {
      // Datos del cliente
      $clients_table = TTB_DB::clients_table();
      $client = $wpdb->get_row($wpdb->prepare("SELECT name, email FROM $clients_table WHERE id=%d", $client_id));

      if ($client) {
        $client_name = (string)$client->name;
        $client_email = (string)$client->email;

        // 1. Email al departamento
        (new TTB_Mailer())->send_department_alert($client_name, $client_email, $service);

        // 2. Subir a Google Drive (en background — si falla no bloquea al usuario)
        try {
          $drive    = new TTB_Drive();
          $doc_url  = $drive->create_briefing_doc($client_name, $service, $schema, $answers);

          // Guardar la URL del doc en la respuesta para tenerla en el admin
          if ($doc_url) {
            $wpdb->query($wpdb->prepare(
              "UPDATE $table SET answers = JSON_SET(answers, '$.ttb_drive_url', %s)
               WHERE client_id=%d AND service=%s",
              $doc_url, $client_id, $service
            ));
          }
        } catch (Exception $e) {
          error_log('TTB Drive upload failed: ' . $e->getMessage());
        }
      }

      // Modal de confirmación
      set_transient('ttb_show_modal_' . $client_id, $service, 120);
      wp_safe_redirect(home_url('/briefing') . '#modal-sent');
      exit;
    }

    $auth->flash('success', 'Guardado correctamente.');
    wp_safe_redirect(home_url('/briefing'));
    exit;
  }

  private function update_client_status($client_id) {
    global $wpdb;
    $clients = TTB_DB::clients_table();
    $answers = TTB_DB::answers_table();

    $client = $wpdb->get_row($wpdb->prepare("SELECT services FROM $clients WHERE id=%d", $client_id));
    if (!$client) return;

    $services = json_decode((string)$client->services, true);
    if (!is_array($services)) $services = [];

    if (!$services) {
      $wpdb->update($clients, ['status'=>'pendiente','updated_at'=>TTB_DB::now()], ['id'=>$client_id]);
      return;
    }

    $all_sent = true;
    foreach ($services as $svc) {
      $row = $wpdb->get_row($wpdb->prepare("SELECT sent FROM $answers WHERE client_id=%d AND service=%s", $client_id, $svc));
      if (!$row || (int)$row->sent !== 1) { $all_sent = false; break; }
    }

    $status = $all_sent ? 'enviado' : 'en_progreso';
    $wpdb->update($clients, ['status'=>$status,'updated_at'=>TTB_DB::now()], ['id'=>$client_id]);
  }

  public static function get_client_answers($client_id, $service) {
    global $wpdb;
    $table = TTB_DB::answers_table();
    $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE client_id=%d AND service=%s", $client_id, $service));
    if (!$row) return ['answers'=>[], 'sent'=>0];
    $a = json_decode((string)$row->answers, true);
    return ['answers'=> is_array($a) ? $a : [], 'sent'=>(int)$row->sent];
  }

  public static function consume_form_state($client_id, $service) {
    $key_e  = 'ttb_form_errors_' . $client_id . '_' . $service;
    $key_v  = 'ttb_form_values_' . $client_id . '_' . $service;
    $errors = get_transient($key_e) ?: [];
    $values = get_transient($key_v) ?: [];
    delete_transient($key_e);
    delete_transient($key_v);
    return ['errors' => $errors, 'values' => $values];
  }

  public static function consume_modal($client_id) {
    $key = 'ttb_show_modal_' . $client_id;
    $svc = get_transient($key);
    if ($svc) delete_transient($key);
    return $svc ?: null;
  }
}