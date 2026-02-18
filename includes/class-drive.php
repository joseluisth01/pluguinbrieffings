<?php
if (!defined('ABSPATH')) exit;

/**
 * TTB_Drive
 * Crea un Google Doc en Drive con las respuestas del briefing.
 * Usa JWT + Service Account, sin librerías externas.
 */
class TTB_Drive {

  /* ── Configuración ── */
  const FOLDER_ID    = '17HJ0F4PePs9DxnJM8J6zAjCuU90MS6LQ';
  const CLIENT_EMAIL = 'briefing-bot@tictac-441710.iam.gserviceaccount.com';
  const PRIVATE_KEY  = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDBoimLhqZ6qg06\n9v3lcE/N7IJmxvKE0x7MEDoullt7L63GhaysleAqbyJtpexDpvP8I3I510OlAYxe\nm8Og1QBHphdGVbx4r3PkPrFbRAlz84YF8IPrdSb4PcbF/dKH3OhzpF72g0wnGvQe\nkfrlKYl78ZafFWtye/95ernODOrT7akF++1KDvyIwx2CfnM/+bhVu6Ovcg6f2R/V\nWJBgvxXC6CCQtomSDarfE4bGD6mrIXg59Po6Mbl4Ph0th9wkF5O1A9Zrd3MA6/G4\nruF6V4vh4uFlF26DQuD5L+50wLrhkz0tRzjIYGc09cq5ET/6QtN15nW3zhkGaa9e\n4igJ4uDvAgMBAAECggEAHn7dHynWN1Rn4AT9SLDPCMX6ZZhooo2jeI0HtMWeY8DH\nFBCCeO3jz5sQJ4ettZvqKigk+cIS175uLopGnaJeOGqKmNuw4qrzTBupkA+fk4Dj\ndzUBechKGmeUUiNfEGG0xF27TQSxrij7EIN6KbRIgFo0mBpmATJRMn8nGzICm9y4\njjw6B23e3VouMQ/UOzTvxXfssV19PaPMpDBowUvtHrgplA3+uR0Gh5Ny/ShuYhnQ\nJLqa7eWzrYSMlDcHSbRo3GAQOEwZ3kBb3ylThBgMtpKhs6D2bNekMOS9jpp9Bwun\nNaJnkpy/KSRSc9XNR0eOjXOkX5YOQLD6azhAqRgE0QKBgQDkfrcz5wa15sAc2y8z\nVSlP8cBARv9mL5Y85rlMxNOY9yn0UNCuq6vtloqLH2Sk85VQdpz68LkdDPk0YSMA\n2DJNVcvuxBfp5JxAmOs0br7sX06Bo8Cxv12WvminQGKS6zrLtTpiaH6DJYe3vDCp\nG8Tgzwq5AF7p5AQFRKHPm4U7EQKBgQDY8Sf4reBsWiH3rnCW6VWMdLTbrlZLJxri\nooBvlDbT0xb+95JZqc7En1URzzwTTNRWVDJ3itXK4ZjDuXEu9lWKzsHt0hmgGAb3\ntjZFntXNsIFkrA3ahVErA8FtC3eZrm0F58VG2pkDmXtN0/vhu9Wd5q7ZS75CQEQg\n7UetdU5b/wKBgHtfmBfkNBFfiHeMOY4j+2x5Ae8y5pAMPbigc4jp9b5wJi0Ovb6y\nXuCoGiJITxVpmEOb5+Luu2TeLmiD0lyQX4i2PKitJKRblaqjZswmx9vlEgSZoF/Z\nDfVo1iUIdLETZem77sxX04eIaiFg8X09yy3/XLDLbHQpc6pMhnoMZQGhAoGADgkM\nHPqi2l+6ctvGTP0rm7qxOMU+r/4Hr0H0LUPZiDrP8g7yWPqzdeUZC93sdRMzaaJo\n4XMKAeY2i/Mjb3ZgcmqOAWTmY4UqbjxLppVwH66bsHexLcISTkYf7X4gbsDqLMeh\n68OYwrLbV12vnhsY5u5VwZk05fRic/7l9ELynuECgYEAnOWU5VEI9iK/defyCHYr\nKTtqt6kZbS/Efus1D8glmiTwM5lLy0N7//hzyuu1r4OnpQD+KJ4cIsQIL2es+whh\nTav5+L3ipZoBY6aKp4kcfBg8nPv63opuZhSd6dXzC5lgeCKdizZjenb9oD7MypK+\nukgP/Fh/swmnehoh3G7IW7E=\n-----END PRIVATE KEY-----\n";

  /* ─────────────────────────────────────────────
     PUNTO DE ENTRADA PRINCIPAL
     Llama a esto desde class-forms.php al enviar.
  ───────────────────────────────────────────── */
  public function create_briefing_doc($client_name, $service, $schema, $answers) {
    try {
      $token = $this->get_access_token();
      if (!$token) {
        error_log('TTB_Drive: no se pudo obtener token de acceso.');
        return null;
      }

      // Primero creamos el Doc vacío en Drive dentro de la carpeta del cliente
      $folder_id = $this->get_or_create_client_folder($token, $client_name);
      $doc_id    = $this->create_empty_doc($token, $folder_id, $client_name, $service);
      if (!$doc_id) return null;

      // Luego rellenamos el Doc con las respuestas usando Docs API (batchUpdate)
      $this->fill_doc($token, $doc_id, $client_name, $service, $schema, $answers);

      return 'https://docs.google.com/document/d/' . $doc_id;

    } catch (Exception $e) {
      error_log('TTB_Drive exception: ' . $e->getMessage());
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
    if (!$key) return null;

    $signature = '';
    openssl_sign($signing_input, $signature, $key, 'SHA256');
    $jwt = $signing_input . '.' . $this->base64url($signature);

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
      'timeout' => 15,
      'body'    => [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
      ],
    ]);

    if (is_wp_error($response)) return null;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['access_token'] ?? null;
  }

  /* ─────────────────────────────────────────────
     CARPETA POR CLIENTE dentro de la carpeta raíz
  ───────────────────────────────────────────── */
  private function get_or_create_client_folder($token, $client_name) {
    $safe_name = sanitize_text_field($client_name);

    // Buscar si ya existe carpeta con ese nombre dentro de FOLDER_ID
    $query = urlencode(
      "mimeType='application/vnd.google-apps.folder'"
      . " and name='" . addslashes($safe_name) . "'"
      . " and '" . self::FOLDER_ID . "' in parents"
      . " and trashed=false"
    );

    $resp = wp_remote_get(
      'https://www.googleapis.com/drive/v3/files?q=' . $query . '&fields=files(id,name)',
      ['headers' => ['Authorization' => 'Bearer ' . $token], 'timeout' => 15]
    );

    if (!is_wp_error($resp)) {
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      if (!empty($data['files'][0]['id'])) {
        return $data['files'][0]['id'];
      }
    }

    // Si no existe, crearla
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

    $data2 = json_decode(wp_remote_retrieve_body($resp2), true);
    return $data2['id'] ?? self::FOLDER_ID; // fallback a carpeta raíz
  }

  /* ─────────────────────────────────────────────
     CREAR DOC VACÍO
  ───────────────────────────────────────────── */
  private function create_empty_doc($token, $folder_id, $client_name, $service) {
    $service_names = [
      'web'    => 'Web',
      'seo'    => 'SEO',
      'social' => 'Redes Sociales',
      'design' => 'Diseño',
    ];
    $svc_label = $service_names[$service] ?? strtoupper($service);
    $date      = date_i18n('d/m/Y');
    $doc_title = 'Briefing ' . $svc_label . ' — ' . $client_name . ' — ' . $date;

    $resp = wp_remote_post('https://www.googleapis.com/drive/v3/files', [
      'timeout' => 15,
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
      ],
      'body' => wp_json_encode([
        'name'     => $doc_title,
        'mimeType' => 'application/vnd.google-apps.document',
        'parents'  => [$folder_id],
      ]),
    ]);

    if (is_wp_error($resp)) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    return $data['id'] ?? null;
  }

  /* ─────────────────────────────────────────────
     RELLENAR DOC con batchUpdate de Docs API
  ───────────────────────────────────────────── */
  private function fill_doc($token, $doc_id, $client_name, $service, $schema, $answers) {
    $service_names = [
      'web'    => 'Web',
      'seo'    => 'SEO',
      'social' => 'Redes Sociales',
      'design' => 'Diseño',
    ];
    $svc_label = $service_names[$service] ?? strtoupper($service);
    $date      = date_i18n('d/m/Y \a \l\a\s H:i');

    /* Construimos el texto completo del documento de una vez.
       Insertamos al índice 1 (después del comienzo del doc) en orden inverso
       para que los índices no se desplacen, pero es más limpio hacerlo
       como un único bloque de texto y luego aplicar estilos. */

    // Primero construimos los requests en orden:
    // 1. Insertar todo el texto plano de una vez
    // 2. Aplicar estilos (HEADING_1, HEADING_2, NORMAL_TEXT)

    $lines   = []; // ['text' => '...', 'style' => 'HEADING_1|HEADING_2|NORMAL_TEXT']

    // Título principal
    $lines[] = ['text' => 'Briefing de ' . $svc_label . ' — ' . $client_name, 'style' => 'HEADING_1'];
    $lines[] = ['text' => 'Fecha de envío: ' . $date, 'style' => 'NORMAL_TEXT'];
    $lines[] = ['text' => '', 'style' => 'NORMAL_TEXT']; // línea en blanco

    foreach ($schema as $f) {
      $id    = $f['id']    ?? '';
      $label = $f['label'] ?? $id;
      if (!$id) continue;

      $val = $answers[$id] ?? '';
      if (is_array($val)) $val = implode(', ', $val);
      $val = trim((string)$val);
      if ($val === '') $val = '—';

      $lines[] = ['text' => $label, 'style' => 'HEADING_2'];
      $lines[] = ['text' => $val,   'style' => 'NORMAL_TEXT'];
      $lines[] = ['text' => '',     'style' => 'NORMAL_TEXT'];
    }

    // Ahora construimos el texto completo y calculamos índices para estilos
    $full_text  = '';
    $style_reqs = []; // requests de estilo a aplicar
    $index      = 1;  // el doc empieza en índice 1

    foreach ($lines as $line) {
      $text   = $line['text'] . "\n";
      $start  = $index;
      $end    = $index + mb_strlen($text, 'UTF-8');

      if ($line['style'] !== 'NORMAL_TEXT' && $line['text'] !== '') {
        $style_reqs[] = [
          'updateParagraphStyle' => [
            'range' => ['startIndex' => $start, 'endIndex' => $end - 1],
            'paragraphStyle' => ['namedStyleType' => $line['style']],
            'fields' => 'namedStyleType',
          ],
        ];
      }

      $full_text .= $text;
      $index      = $end;
    }

    // Request 1: insertar todo el texto de una vez
    $insert_req = [
      'insertText' => [
        'location' => ['index' => 1],
        'text'     => $full_text,
      ],
    ];

    // Primero insertamos el texto
    $this->docs_batch_update($token, $doc_id, [$insert_req]);

    // Luego aplicamos estilos (ahora los índices son correctos)
    if ($style_reqs) {
      $this->docs_batch_update($token, $doc_id, $style_reqs);
    }
  }

  /* ─────────────────────────────────────────────
     HELPER: Docs API batchUpdate
  ───────────────────────────────────────────── */
  private function docs_batch_update($token, $doc_id, $requests) {
    $url = 'https://docs.googleapis.com/v1/documents/' . $doc_id . ':batchUpdate';
    $resp = wp_remote_post($url, [
      'timeout' => 20,
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
      ],
      'body' => wp_json_encode(['requests' => $requests]),
    ]);

    if (is_wp_error($resp)) {
      error_log('TTB_Drive batchUpdate error: ' . $resp->get_error_message());
    }
  }

  /* ─────────────────────────────────────────────
     HELPER: base64url encoding (RFC 4648)
  ───────────────────────────────────────────── */
  private function base64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
}