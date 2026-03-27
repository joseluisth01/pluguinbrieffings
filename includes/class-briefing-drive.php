<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_Briefing_Drive')) return;

/**
 * TTB_Briefing_Drive — v1.1
 *
 * Estructura en Drive:
 *  📁 CLIENTES (carpeta raíz fija: ROOT_FOLDER_ID)
 *    └── 📁 [Nombre del cliente]          ← se crea si no existe (sin compartir)
 *          └── 📁 00. DOC - COMPARTIDA CON EL CLIENTE  ← se crea y se comparte con emails del cliente
 *
 * Cambios v1.1:
 * - Ya no se necesita que el admin pegue la URL de Drive al crear el briefing.
 * - Se usa la carpeta raíz fija ROOT_FOLDER_ID.
 * - Se crea automáticamente la carpeta del cliente (si no existe) y dentro la compartida.
 */
class TTB_Briefing_Drive {

  // ── Carpeta raíz donde viven todas las carpetas de clientes ──
  // https://drive.google.com/drive/u/1/folders/18UqPFC0xgnLhQ5cFBBIU5ge0kRHOli-E
  const ROOT_FOLDER_ID = '18UqPFC0xgnLhQ5cFBBIU5ge0kRHOli-E';

  // ── Nombre fijo de la subcarpeta compartida con el cliente ───
  const SHARED_FOLDER_NAME = '00. DOC - COMPARTIDA CON EL CLIENTE';

  // ── Prefijo de la subcarpeta de briefings (sin compartir) ────
  const BRIEFINGS_FOLDER_PREFIX = 'BRIEFINGS - ';

  // ── Service Account (mismas credenciales que TTB_Drive) ──────
  const CLIENT_EMAIL = 'briefing-bot@tictac-441710.iam.gserviceaccount.com';
  const OWNER_EMAIL  = 'hola@tictac-comunicacion.es';
  const PRIVATE_KEY  = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDBoimLhqZ6qg06\n9v3lcE/N7IJmxvKE0x7MEDoullt7L63GhaysleAqbyJtpexDpvP8I3I510OlAYxe\nm8Og1QBHphdGVbx4r3PkPrFbRAlz84YF8IPrdSb4PcbF/dKH3OhzpF72g0wnGvQe\nkfrlKYl78ZafFWtye/95ernODOrT7akF++1KDvyIwx2CfnM/+bhVu6Ovcg6f2R/V\nWJBgvxXC6CCQtomSDarfE4bGD6mrIXg59Po6Mbl4Ph0th9wkF5O1A9Zrd3MA6/G4\nruF6V4vh4uFlF26DQuD5L+50wLrhkz0tRzjIYGc09cq5ET/6QtN15nW3zhkGaa9e\n4igJ4uDvAgMBAAECggEAHn7dHynWN1Rn4AT9SLDPCMX6ZZhooo2jeI0HtMWeY8DH\nFBCCeO3jz5sQJ4ettZvqKigk+cIS175uLopGnaJeOGqKmNuw4qrzTBupkA+fk4Dj\ndzUBechKGmeUUiNfEGG0xF27TQSxrij7EIN6KbRIgFo0mBpmATJRMn8nGzICm9y4\njjw6B23e3VouMQ/UOzTvxXfssV19PaPMpDBowUvtHrgplA3+uR0Gh5Ny/ShuYhnQ\nJLqa7eWzrYSMlDcHSbRo3GAQOEwZ3kBb3ylThBgMtpKhs6D2bNekMOS9jpp9Bwun\nNaJnkpy/KSRSc9XNR0eOjXOkX5YOQLD6azhAqRgE0QKBgQDkfrcz5wa15sAc2y8z\nVSlP8cBARv9mL5Y85rlMxNOY9yn0UNCuq6vtloqLH2Sk85VQdpz68LkdDPk0YSMA\n2DJNVcvuxBfp5JxAmOs0br7sX06Bo8Cxv12WvminQGKS6zrLtTpiaH6DJYe3vDCp\nG8Tgzwq5AF7p5AQFRKHPm4U7EQKBgQDY8Sf4reBsWiH3rnCW6VWMdLTbrlZLJxri\nooBvlDbT0xb+95JZqc7En1URzzwTTNRWVDJ3itXK4ZjDuXEu9lWKzsHt0hmgGAb3\ntjZFntXNsIFkrA3ahVErA8FtC3eZrm0F58VG2pkDmXtN0/vhu9Wd5q7ZS75CQEQg\n7UetdU5b/wKBgHtfmBfkNBFfiHeMOY4j+2x5Ae8y5pAMPbigc4jp9b5wJi0Ovb6y\nXuCoGiJITxVpmEOb5+Luu2TeLmiD0lyQX4i2PKitJKRblaqjZswmx9vlEgSZoF/Z\nDfVo1iUIdLETZem77sxX04eIaiFg8X09yy3/XLDLbHQpc6pMhnoMZQGhAoGADgkM\nHPqi2l+6ctvGTP0rm7qxOMU+r/4Hr0H0LUPZiDrP8g7yWPqzdeUZC93sdRMzaaJo\n4XMKAeY2i/Mjb3ZgcmqOAWTmY4UqbjxLppVwH66bsHexLcISTkYf7X4gbsDqLMeh\n68OYwrLbV12vnhsY5u5VwZk05fRic/7l9ELynuECgYEAnOWU5VEI9iK/defyCHYr\nKTtqt6kZbS/Efus1D8glmiTwM5lLy0N7//hzyuu1r4OnpQD+KJ4cIsQIL2es+whh\nTav5+L3ipZoBY6aKp4kcfBg8nPv63opuZhSd6dXzC5lgeCKdizZjenb9oD7MypK+\nukgP/Fh/swmnehoh3G7IW7E=\n-----END PRIVATE KEY-----\n";

  /**
   * Punto de entrada principal.
   *
   * Estructura creada:
   *  📁 CLIENTES (ROOT_FOLDER_ID)
   *    └── 📁 [Nombre cliente]                         ← sin compartir
   *          ├── 📁 00. DOC - COMPARTIDA CON EL CLIENTE  ← compartida con emails del cliente
   *          └── 📁 BRIEFINGS - [Nombre cliente]          ← sin compartir, aquí van los docs del briefing
   *
   * @param string $client_name   Nombre del cliente.
   * @param array  $client_emails Emails del cliente para compartir la subcarpeta.
   * @return array|null [
   *   'folder_id'         => ID carpeta compartida (para shared_folder_url),
   *   'folder_url'        => URL carpeta compartida,
   *   'client_folder_id'  => ID carpeta raíz del cliente,
   *   'briefings_folder_id' => ID carpeta BRIEFINGS (para subir los docs),
   * ] o null si falla.
   */
  public function setup_client_folder($client_name, $client_emails) {
    try {
      $token = $this->get_access_token();
      if (!$token) {
        error_log('TTB_Briefing_Drive: no se pudo obtener token de acceso.');
        return null;
      }

      // 1. Carpeta del cliente (sin compartir) dentro de ROOT
      $client_folder_id = $this->find_or_create_folder(
        $token,
        self::ROOT_FOLDER_ID,
        sanitize_text_field($client_name)
      );
      if (!$client_folder_id) {
        error_log('TTB_Briefing_Drive: no se pudo crear/localizar carpeta del cliente.');
        return null;
      }
      error_log('TTB_Briefing_Drive: carpeta cliente = ' . $client_folder_id);

      // 2. Subcarpeta compartida con el cliente
      $shared_folder_id = $this->find_or_create_folder(
        $token,
        $client_folder_id,
        self::SHARED_FOLDER_NAME
      );
      if (!$shared_folder_id) {
        error_log('TTB_Briefing_Drive: no se pudo crear subcarpeta compartida.');
        return null;
      }
      error_log('TTB_Briefing_Drive: subcarpeta compartida = ' . $shared_folder_id);

      // 3. Subcarpeta BRIEFINGS (sin compartir) para los docs del briefing
      $briefings_folder_name = self::BRIEFINGS_FOLDER_PREFIX . $client_name;
      $briefings_folder_id   = $this->find_or_create_folder(
        $token,
        $client_folder_id,
        $briefings_folder_name
      );
      if (!$briefings_folder_id) {
        error_log('TTB_Briefing_Drive: no se pudo crear carpeta BRIEFINGS.');
        // No es fatal — continuamos sin ella
      }
      error_log('TTB_Briefing_Drive: carpeta briefings = ' . ($briefings_folder_id ?: 'N/A'));

      // 4. Compartir la subcarpeta con cada email del cliente (writer)
      $shared_count = 0;
      foreach ($client_emails as $email) {
        if (!is_email($email)) continue;
        if ($this->share_with_user($token, $shared_folder_id, $email)) {
          $shared_count++;
        }
      }
      error_log('TTB_Briefing_Drive: carpeta compartida con ' . $shared_count . ' email(s).');

      return [
        'folder_id'          => $shared_folder_id,
        'folder_url'         => 'https://drive.google.com/drive/folders/' . $shared_folder_id,
        'client_folder_id'   => $client_folder_id,
        'briefings_folder_id'=> $briefings_folder_id,
      ];

    } catch (Exception $e) {
      error_log('TTB_Briefing_Drive EXCEPTION: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Compartir una carpeta ya existente con nuevos emails (p.ej. al actualizar emails del cliente).
   */
  public function share_folder_with_emails($folder_id, $client_emails) {
    try {
      $token = $this->get_access_token();
      if (!$token) return false;
      $ok = 0;
      foreach ($client_emails as $email) {
        if (!is_email($email)) continue;
        if ($this->share_with_user($token, $folder_id, $email)) $ok++;
      }
      return $ok > 0;
    } catch (Exception $e) {
      error_log('TTB_Briefing_Drive share EXCEPTION: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Sube un archivo local a una carpeta de Drive.
   *
   * @param string $folder_id   ID de la carpeta destino en Drive.
   * @param string $file_path   Ruta absoluta al archivo en el servidor.
   * @param string $file_name   Nombre con el que aparecerá en Drive.
   * @param string $mime_type   MIME type del archivo.
   * @return string|null  ID del archivo en Drive, o null si falla.
   */
  public function upload_file_to_folder($folder_id, $file_path, $file_name, $mime_type) {
    try {
      $token = $this->get_access_token();
      if (!$token) return null;

      if (!file_exists($file_path) || !is_readable($file_path)) {
        error_log('TTB_Briefing_Drive upload: archivo no encontrado o no legible: ' . $file_path);
        return null;
      }

      $file_content = file_get_contents($file_path);
      if ($file_content === false) {
        error_log('TTB_Briefing_Drive upload: no se pudo leer el archivo.');
        return null;
      }

      // Multipart upload a Drive API v3
      $boundary = '---TTBBriefingUpload' . uniqid();
      $metadata = wp_json_encode([
        'name'    => $file_name,
        'parents' => [$folder_id],
      ]);

      $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mime_type}\r\n\r\n"
            . $file_content . "\r\n"
            . "--{$boundary}--";

      $resp = wp_remote_post(
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true',
        [
          'timeout' => 60,
          'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'multipart/related; boundary=' . $boundary,
            'Content-Length'=> strlen($body),
          ],
          'body' => $body,
        ]
      );

      if (is_wp_error($resp)) {
        error_log('TTB_Briefing_Drive upload error: ' . $resp->get_error_message());
        return null;
      }

      $code = wp_remote_retrieve_response_code($resp);
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      error_log('TTB_Briefing_Drive upload [' . $code . '] ' . $file_name . ' → ' . ($data['id'] ?? 'sin ID'));
      return $data['id'] ?? null;

    } catch (Exception $e) {
      error_log('TTB_Briefing_Drive upload EXCEPTION: ' . $e->getMessage());
      return null;
    }
  }

  // ─────────────────────────────────────────────
  // PRIVADOS
  // ─────────────────────────────────────────────

  /**
   * Busca una carpeta por nombre dentro de un parent.
   * Si no existe, la crea. Devuelve el ID.
   */
  private function find_or_create_folder($token, $parent_id, $name) {
    $existing = $this->find_folder($token, $parent_id, $name);
    if ($existing) return $existing;
    return $this->create_folder($token, $parent_id, $name);
  }

  private function find_folder($token, $parent_id, $name) {
    $escaped = str_replace("'", "\\'", $name);
    $query   = "mimeType='application/vnd.google-apps.folder'"
             . " and name='" . $escaped . "'"
             . " and '" . $parent_id . "' in parents"
             . " and trashed=false";

    $resp = wp_remote_get(
      'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q'                         => $query,
        'fields'                    => 'files(id,name)',
        'supportsAllDrives'         => 'true',
        'includeItemsFromAllDrives' => 'true',
      ]),
      ['headers' => ['Authorization' => 'Bearer ' . $token], 'timeout' => 15]
    );

    if (is_wp_error($resp)) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    return $data['files'][0]['id'] ?? null;
  }

  private function create_folder($token, $parent_id, $name) {
    $resp = wp_remote_post('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true', [
      'timeout' => 15,
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
      ],
      'body' => wp_json_encode([
        'name'     => $name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => [$parent_id],
      ]),
    ]);

    if (is_wp_error($resp)) {
      error_log('TTB_Briefing_Drive create_folder error: ' . $resp->get_error_message());
      return null;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    error_log('TTB_Briefing_Drive create_folder [' . $code . ']: ' . $body);

    $data = json_decode($body, true);
    return $data['id'] ?? null;
  }

  private function share_with_user($token, $file_id, $email) {
    $resp = wp_remote_post(
      'https://www.googleapis.com/drive/v3/files/' . $file_id . '/permissions?supportsAllDrives=true&sendNotificationEmail=false',
      [
        'timeout' => 15,
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
          'role'         => 'writer',
          'type'         => 'user',
          'emailAddress' => $email,
        ]),
      ]
    );

    $code = wp_remote_retrieve_response_code($resp);
    error_log('TTB_Briefing_Drive share [' . $code . '] ' . $email);
    return !is_wp_error($resp) && (int)$code === 200;
  }

  private function get_access_token() {
    $now    = time();
    $header = $this->base64url(wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = $this->base64url(wp_json_encode([
      'iss'   => self::CLIENT_EMAIL,
      'sub'   => self::OWNER_EMAIL,
      'scope' => 'https://www.googleapis.com/auth/drive',
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
      'timeout' => 20,
      'body'    => [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt,
      ],
    ]);

    if (is_wp_error($response)) return null;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['access_token'] ?? null;
  }

  private function base64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
}