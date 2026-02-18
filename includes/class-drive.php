<?php
if (!defined('ABSPATH')) exit;

/**
 * TTB_Drive
 * Crea un Google Doc en Drive con las respuestas del briefing.
 * Usa JWT + Service Account, sin librerías externas.
 */
class TTB_Drive {

  const FOLDER_ID    = '17HJ0F4PePs9DxnJM8J6zAjCuU90MS6LQ';
  const CLIENT_EMAIL = 'briefing-bot@tictac-441710.iam.gserviceaccount.com';
  const PRIVATE_KEY  = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDBoimLhqZ6qg06\n9v3lcE/N7IJmxvKE0x7MEDoullt7L63GhaysleAqbyJtpexDpvP8I3I510OlAYxe\nm8Og1QBHphdGVbx4r3PkPrFbRAlz84YF8IPrdSb4PcbF/dKH3OhzpF72g0wnGvQe\nkfrlKYl78ZafFWtye/95ernODOrT7akF++1KDvyIwx2CfnM/+bhVu6Ovcg6f2R/V\nWJBgvxXC6CCQtomSDarfE4bGD6mrIXg59Po6Mbl4Ph0th9wkF5O1A9Zrd3MA6/G4\nruF6V4vh4uFlF26DQuD5L+50wLrhkz0tRzjIYGc09cq5ET/6QtN15nW3zhkGaa9e\n4igJ4uDvAgMBAAECggEAHn7dHynWN1Rn4AT9SLDPCMX6ZZhooo2jeI0HtMWeY8DH\nFBCCeO3jz5sQJ4ettZvqKigk+cIS175uLopGnaJeOGqKmNuw4qrzTBupkA+fk4Dj\ndzUBechKGmeUUiNfEGG0xF27TQSxrij7EIN6KbRIgFo0mBpmATJRMn8nGzICm9y4\njjw6B23e3VouMQ/UOzTvxXfssV19PaPMpDBowUvtHrgplA3+uR0Gh5Ny/ShuYhnQ\nJLqa7eWzrYSMlDcHSbRo3GAQOEwZ3kBb3ylThBgMtpKhs6D2bNekMOS9jpp9Bwun\nNaJnkpy/KSRSc9XNR0eOjXOkX5YOQLD6azhAqRgE0QKBgQDkfrcz5wa15sAc2y8z\nVSlP8cBARv9mL5Y85rlMxNOY9yn0UNCuq6vtloqLH2Sk85VQdpz68LkdDPk0YSMA\n2DJNVcvuxBfp5JxAmOs0br7sX06Bo8Cxv12WvminQGKS6zrLtTpiaH6DJYe3vDCp\nG8Tgzwq5AF7p5AQFRKHPm4U7EQKBgQDY8Sf4reBsWiH3rnCW6VWMdLTbrlZLJxri\nooBvlDbT0xb+95JZqc7En1URzzwTTNRWVDJ3itXK4ZjDuXEu9lWKzsHt0hmgGAb3\ntjZFntXNsIFkrA3ahVErA8FtC3eZrm0F58VG2pkDmXtN0/vhu9Wd5q7ZS75CQEQg\n7UetdU5b/wKBgHtfmBfkNBFfiHeMOY4j+2x5Ae8y5pAMPbigc4jp9b5wJi0Ovb6y\nXuCoGiJITxVpmEOb5+Luu2TeLmiD0lyQX4i2PKitJKRblaqjZswmx9vlEgSZoF/Z\nDfVo1iUIdLETZem77sxX04eIaiFg8X09yy3/XLDLbHQpc6pMhnoMZQGhAoGADgkM\nHPqi2l+6ctvGTP0rm7qxOMU+r/4Hr0H0LUPZiDrP8g7yWPqzdeUZC93sdRMzaaJo\n4XMKAeY2i/Mjb3ZgcmqOAWTmY4UqbjxLppVwH66bsHexLcISTkYf7X4gbsDqLMeh\n68OYwrLbV12vnhsY5u5VwZk05fRic/7l9ELynuECgYEAnOWU5VEI9iK/defyCHYr\nKTtqt6kZbS/Efus1D8glmiTwM5lLy0N7//hzyuu1r4OnpQD+KJ4cIsQIL2es+whh\nTav5+L3ipZoBY6aKp4kcfBg8nPv63opuZhSd6dXzC5lgeCKdizZjenb9oD7MypK+\nukgP/Fh/swmnehoh3G7IW7E=\n-----END PRIVATE KEY-----\n";

  /* ─────────────────────────────────────────────
     PUNTO DE ENTRADA PRINCIPAL
  ───────────────────────────────────────────── */
  public function create_briefing_doc($client_name, $service, $schema, $answers) {
    try {
      $token = $this->get_access_token();
      if (!$token) {
        error_log('TTB_Drive ERROR: no se pudo obtener token de acceso.');
        return null;
      }

      $folder_id = $this->get_or_create_client_folder($token, $client_name);
      error_log('TTB_Drive: carpeta cliente = ' . $folder_id);

      $doc_id = $this->create_doc_with_content($token, $folder_id, $client_name, $service, $schema, $answers);
      if (!$doc_id) {
        error_log('TTB_Drive ERROR: no se pudo crear el documento.');
        return null;
      }

      error_log('TTB_Drive OK: doc creado = ' . $doc_id);
      return 'https://docs.google.com/document/d/' . $doc_id;

    } catch (Exception $e) {
      error_log('TTB_Drive EXCEPTION: ' . $e->getMessage());
      return null;
    }
  }

  /* ─────────────────────────────────────────────
     JWT + ACCESS TOKEN
  ───────────────────────────────────────────── */
  private function get_access_token() {
    $now = time();
    $header  = $this->base64url(wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = $this->base64url(wp_json_encode([
      'iss'   => self::CLIENT_EMAIL,
      'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/documents',
      'aud'   => 'https://oauth2.googleapis.com/token',
      'iat'   => $now,
      'exp'   => $now + 3600,
    ]));

    $signing_input = $header . '.' . $payload;
    $key = openssl_pkey_get_private(self::PRIVATE_KEY);
    if (!$key) {
      error_log('TTB_Drive ERROR: openssl no pudo cargar la clave privada.');
      return null;
    }

    $signature = '';
    openssl_sign($signing_input, $signature, $key, 'SHA256');
    $jwt = $signing_input . '.' . $this->base64url($signature);

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
      'timeout' => 20,
      'body'    => [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
      ],
    ]);

    if (is_wp_error($response)) {
      error_log('TTB_Drive ERROR token request: ' . $response->get_error_message());
      return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['access_token'])) {
      error_log('TTB_Drive ERROR token response: ' . wp_remote_retrieve_body($response));
    }
    return $body['access_token'] ?? null;
  }

  /* ─────────────────────────────────────────────
     CARPETA POR CLIENTE
  ───────────────────────────────────────────── */
  private function get_or_create_client_folder($token, $client_name) {
    $safe_name = sanitize_text_field($client_name);
    // Escapar comilla simple para la query
    $escaped = str_replace("'", "\\'", $safe_name);

    $query = "mimeType='application/vnd.google-apps.folder'"
           . " and name='" . $escaped . "'"
           . " and '" . self::FOLDER_ID . "' in parents"
           . " and trashed=false";

    $resp = wp_remote_get(
      'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name)',
      ['headers' => ['Authorization' => 'Bearer ' . $token], 'timeout' => 15]
    );

    if (!is_wp_error($resp)) {
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      if (!empty($data['files'][0]['id'])) {
        return $data['files'][0]['id'];
      }
    }

    // Crear carpeta
    $resp2 = wp_remote_post('https://www.googleapis.com/drive/v3/files', [
      'timeout' => 15,
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
      ],
      'body' => wp_json_encode([
        'name'     => $safe_name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => [self::FOLDER_ID],
      ]),
    ]);

    if (is_wp_error($resp2)) {
      error_log('TTB_Drive ERROR creando carpeta: ' . $resp2->get_error_message());
      return self::FOLDER_ID;
    }

    $data2 = json_decode(wp_remote_retrieve_body($resp2), true);
    error_log('TTB_Drive carpeta creada: ' . wp_remote_retrieve_body($resp2));
    return $data2['id'] ?? self::FOLDER_ID;
  }

  /* ─────────────────────────────────────────────
     CREAR DOC CON CONTENIDO
     Estrategia: subir como texto plano multipart
     y convertir a Google Doc. Así evitamos los
     problemas de índices del batchUpdate.
  ───────────────────────────────────────────── */
  private function create_doc_with_content($token, $folder_id, $client_name, $service, $schema, $answers) {
    $service_names = [
      'web'    => 'Web',
      'seo'    => 'SEO',
      'social' => 'Redes Sociales',
      'design' => 'Diseño',
    ];
    $svc_label = $service_names[$service] ?? strtoupper($service);
    $date      = date_i18n('d/m/Y \a \l\a\s H:i');
    $doc_title = 'Briefing ' . $svc_label . ' — ' . $client_name . ' — ' . date_i18n('d/m/Y');

    // Construir contenido HTML — Google Drive lo convierte a Doc limpio
    $html  = '<html><head><meta charset="UTF-8"></head><body>';
    $html .= '<h1>Briefing de ' . esc_html($svc_label) . ' — ' . esc_html($client_name) . '</h1>';
    $html .= '<p><em>Fecha de envío: ' . esc_html($date) . '</em></p>';
    $html .= '<hr>';

    foreach ($schema as $f) {
      $id    = $f['id']    ?? '';
      $label = $f['label'] ?? $id;
      if (!$id || $id === 'ttb_drive_url') continue;

      $val = $answers[$id] ?? '';
      if (is_array($val)) $val = implode(', ', $val);
      $val = trim((string)$val);
      if ($val === '') $val = '—';

      $html .= '<h2>' . esc_html($label) . '</h2>';
      $html .= '<p>' . nl2br(esc_html($val)) . '</p>';
    }

    $html .= '</body></html>';

    // Metadata del fichero
    $metadata = wp_json_encode([
      'name'     => $doc_title,
      'mimeType' => 'application/vnd.google-apps.document',
      'parents'  => [$folder_id],
    ]);

    // Multipart upload: convierte HTML → Google Doc automáticamente
    $boundary = '-------TTBBoundary' . uniqid();
    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $body .= $metadata . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--{$boundary}--";

    $resp = wp_remote_post(
      'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&convert=true',
      [
        'timeout' => 30,
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Content-Type'  => 'multipart/related; boundary="' . $boundary . '"',
          'Content-Length'=> strlen($body),
        ],
        'body' => $body,
      ]
    );

    if (is_wp_error($resp)) {
      error_log('TTB_Drive ERROR upload multipart: ' . $resp->get_error_message());
      return null;
    }

    $raw  = wp_remote_retrieve_body($resp);
    $code = wp_remote_retrieve_response_code($resp);
    error_log('TTB_Drive upload response [' . $code . ']: ' . $raw);

    $data = json_decode($raw, true);
    return $data['id'] ?? null;
  }

  /* ─────────────────────────────────────────────
     HELPER: base64url encoding (RFC 4648)
  ───────────────────────────────────────────── */
  private function base64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
}