<?php
if (!defined('ABSPATH')) exit;
if (class_exists('TTB_WebRev_Client')) return;

/**
 * TTB_WebRev_Client
 * v3 — FIXES:
 *   1. Eliminada duplicación de revisiones: handle_submit() ahora usa flag estático
 *      para garantizar ejecución única aunque render() sea llamado varias veces.
 *   2. Editor de anotaciones sobre imágenes: canvas interactivo con lápiz,
 *      subrayador, flechas, rectángulos y texto encima de capturas.
 */
class TTB_WebRev_Client
{
  /** Flag para evitar doble procesamiento del POST en el mismo ciclo de vida */
  private static $submitted = false;

  public static function render($token)
  {
    $project = TTB_WebRev_DB::get_project_by_token($token);

    if (!$project) {
      echo '<div class="ttb-card" style="text-align:center;padding:48px 24px">
        <p style="font-size:40px;margin:0 0 12px">🔗</p>
        <h2>Enlace no válido</h2>
        <p class="ttb-muted">Este enlace no existe o ha caducado. Contacta con TicTac Comunicación.</p>
      </div>';
      TTB_WebRev_DB::log(null, 'invalid_token_access', 'client', ['token_partial' => substr($token, 0, 8) . '…']);
      return;
    }

    // ── FIX DUPLICACIÓN: solo procesar POST una vez por ciclo ──
    if (!self::$submitted && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ttb_webrev_action'])) {
      self::$submitted = true;
      self::handle_submit($project);
      return;
    }

    TTB_WebRev_DB::log($project->id, 'client_view', 'client', ['status' => $project->status]);

    $accepted = ($project->status === 'accepted');

    global $wpdb;
    $revisions   = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM " . TTB_WebRev_DB::revisions_table() . " WHERE project_id=%d ORDER BY created_at DESC",
      $project->id
    ));
    $round_count = count($revisions);
    $next_round  = $round_count + 1;

?>
    <div class="ttb-container">

      <div class="ttb-card ttb-card--header">
        <h2>Revisión de diseño web</h2>
        <p class="ttb-muted">Hola, <strong><?php echo esc_html($project->name); ?></strong>. Revisa el diseño y danos tu feedback.</p>
      </div>

      <?php if ($accepted): ?>
        <div class="ttb-card" style="text-align:center;padding:40px 24px">
          <span style="font-size:54px;display:block;margin-bottom:12px">✅</span>
          <h3 style="margin:0 0 8px;color:#065f46">¡Diseño aceptado!</h3>
          <p class="ttb-muted">Ya nos has dado el visto bueno. Nuestro equipo está trabajando en ello. ¡Gracias!</p>
        </div>

      <?php else: ?>

        <?php $is_figma = strpos($project->figma_url, 'figma.com') !== false; ?>
        <div class="ttb-card">
          <h3 style="margin:0 0 14px">🎨 Tu diseño</h3>
          <div style="border-radius:14px;border:2px dashed var(--ttb-border);background:linear-gradient(135deg,#fdf2f7 0%,#fff 100%);padding:48px 32px;text-align:center">
            <?php if ($is_figma): ?>
              <svg width="48" height="48" viewBox="0 0 38 57" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin:0 auto 16px;display:block">
                <path d="M19 28.5C19 25.9804 20.0009 23.5641 21.7825 21.7825C23.5641 20.0009 25.9804 19 28.5 19C31.0196 19 33.4359 20.0009 35.2175 21.7825C36.9991 23.5641 38 25.9804 38 28.5C38 31.0196 36.9991 33.4359 35.2175 35.2175C33.4359 36.9991 31.0196 38 28.5 38H19V28.5Z" fill="#1ABCFE"/>
                <path d="M0 47.5C0 44.9804 1.00089 42.5641 2.78249 40.7825C4.56408 39.0009 6.98044 38 9.5 38H19V47.5C19 50.0196 17.9991 52.4359 16.2175 54.2175C14.4359 55.9991 12.0196 57 9.5 57C6.98044 57 4.56408 55.9991 2.78249 54.2175C1.00089 52.4359 0 50.0196 0 47.5Z" fill="#0ACF83"/>
                <path d="M19 0V19H28.5C31.0196 19 33.4359 17.9991 35.2175 16.2175C36.9991 14.4359 38 12.0196 38 9.5C38 6.98044 36.9991 4.56408 35.2175 2.78249C33.4359 1.00089 31.0196 0 28.5 0H19Z" fill="#FF7262"/>
                <path d="M0 9.5C0 12.0196 1.00089 14.4359 2.78249 16.2175C4.56408 17.9991 6.98044 19 9.5 19H19V0H9.5C6.98044 0 4.56408 1.00089 2.78249 2.78249C1.00089 4.56408 0 6.98044 0 9.5Z" fill="#F24E1E"/>
                <path d="M0 28.5C0 31.0196 1.00089 33.4359 2.78249 35.2175C4.56408 36.9991 6.98044 38 9.5 38H19V19H9.5C6.98044 19 4.56408 20.0009 2.78249 21.7825C1.00089 23.5641 0 25.9804 0 28.5Z" fill="#A259FF"/>
              </svg>
              <p style="margin:0 0 8px;font-size:20px;font-weight:900;color:var(--ttb-text)">Tu diseño está en Figma</p>
              <p style="margin:0 0 28px;font-size:15px;color:var(--ttb-muted);line-height:1.6">Haz clic para verlo. Después vuelve aquí para aceptar o pedir cambios.</p>
            <?php else: ?>
              <p style="font-size:40px;margin:0 0 12px">🎨</p>
              <p style="margin:0 0 8px;font-size:20px;font-weight:900;color:var(--ttb-text)">Tu diseño está listo</p>
              <p style="margin:0 0 28px;font-size:15px;color:var(--ttb-muted)">Haz clic para abrirlo. Después vuelve aquí para aceptar o pedir cambios.</p>
            <?php endif; ?>

            <div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center;align-items:center">
              <a href="<?php echo esc_url($project->figma_url); ?>" target="_blank" rel="noopener"
                style="display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,#D72173 0%,#a8005a 100%);color:#fff;text-decoration:none;font-weight:900;font-size:17px;padding:18px 40px;border-radius:14px;box-shadow:0 8px 24px rgba(215,33,115,.30)">
                🖥️ <?php echo $is_figma ? 'Abrir diseño desktop' : 'Ver diseño'; ?>
              </a>
              <?php if (!empty($project->figma_url_mobile)): ?>
                <a href="<?php echo esc_url($project->figma_url_mobile); ?>" target="_blank" rel="noopener"
                  style="display:inline-flex;align-items:center;gap:10px;background:#fff;border:2px solid rgba(215,33,115,.35);color:#D72173;text-decoration:none;font-weight:900;font-size:17px;padding:18px 40px;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.08)">
                  📱 Abrir diseño mobile
                </a>
              <?php endif; ?>
            </div>
            <p style="margin:18px 0 0;font-size:12px;color:var(--ttb-muted)">Se abrirá en una nueva pestaña</p>
          </div>
        </div>

        <!-- Acciones -->
        <div class="ttb-card">
          <h3 style="margin:0 0 6px">¿Qué quieres hacer?</h3>
          <p class="ttb-muted" style="margin:0 0 20px">Ronda actual: <strong>#<?php echo $next_round; ?></strong></p>

          <div class="ttbwr-action-tabs" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
            <button class="ttb-btn ttb-btn--ghost ttbwr-tab-btn ttbwr-tab-btn--active" data-tab="accept" type="button">✅ Aceptar diseño</button>
            <button class="ttb-btn ttb-btn--ghost ttbwr-tab-btn" data-tab="changes" type="button">✏️ Necesito cambios</button>
          </div>

          <!-- Panel: Aceptar -->
          <div class="ttbwr-panel" id="ttbwr-panel-accept">
            <div style="background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:14px;padding:20px 24px;margin-bottom:16px">
              <p style="margin:0;font-size:15px;color:#065f46;line-height:1.6">Al aceptar, confirmas que el diseño es correcto y autorizas a TicTac a continuar con el desarrollo. Recibirás una confirmación por email.</p>
            </div>
            <form method="post" action="">
              <?php wp_nonce_field('ttb_webrev_accept_' . $token); ?>
              <input type="hidden" name="ttb_webrev_action" value="accept">
              <input type="hidden" name="ttb_webrev_token" value="<?php echo esc_attr($token); ?>">
              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" style="background:linear-gradient(135deg,#10b981,#059669)">✅ Confirmar aceptación del diseño</button>
              </div>
            </form>
          </div>

          <!-- Panel: Cambios -->
          <div class="ttbwr-panel" id="ttbwr-panel-changes" style="display:none">

            <!-- Guía de uso -->
            <div style="background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:14px;padding:16px 20px;margin-bottom:20px">
              <p style="margin:0 0 10px;font-size:14px;font-weight:900;color:#0369a1">💡 Cómo explicar bien los cambios</p>
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
                <div style="font-size:13px;color:#0369a1;line-height:1.5">
                  <strong>✏️ Bloque de texto</strong><br>Úsalo solo como apoyo. Cada envío debe llevar al menos una captura.
                </div>
                <div style="font-size:13px;color:#0369a1;line-height:1.5">
                  <strong>🖼️ Captura anotada</strong><br>Sube una imagen y dibuja encima con flechas, subrayado o texto.
                </div>
                <div style="font-size:13px;color:#0369a1;line-height:1.5">
                  <strong>📋 Imprescindible</strong><br>Sin captura o imagen de referencia no se podrá enviar la solicitud.
                </div>
              </div>
            </div>

            <form method="post" action="" enctype="multipart/form-data" id="ttbwr-changes-form">
              <?php wp_nonce_field('ttb_webrev_changes_' . $token); ?>
              <input type="hidden" name="ttb_webrev_action" value="changes">
              <input type="hidden" name="ttb_webrev_token" value="<?php echo esc_attr($token); ?>">
              <input type="hidden" name="ttbwr_blocks_json" id="ttbwr_blocks_json" value="">

              <div id="ttbwr-blocks-container"></div>

              <div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 24px">
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwr-add-text">✏️ Añadir bloque de texto</button>
                <button type="button" class="ttb-btn ttb-btn--ghost" id="ttbwr-add-image">🖼️ Añadir captura con anotaciones</button>
              </div>

              <div class="ttb-actions">
                <button class="ttb-btn" type="submit" id="ttbwr-submit-btn">📨 Enviar cambios</button>
              </div>
            </form>
          </div>
        </div>

      <?php endif; ?>

      <!-- Historial -->
      <?php if ($revisions): ?>
        <div class="ttb-card">
          <h3 style="margin:0 0 16px">📋 Historial de revisiones</h3>
          <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($revisions as $rev): ?>
              <?php
              $is_accepted = $rev->type === 'accept';
              $bg  = $is_accepted ? '#ecfdf5' : '#fffbeb';
              $bc  = $is_accepted ? '#6ee7b7' : '#fcd34d';
              $ico = $is_accepted ? '✅' : '✏️';
              $lbl = $is_accepted ? 'Diseño aceptado' : 'Cambios solicitados — Ronda #' . $rev->round;
              ?>
              <div style="background:<?php echo $bg; ?>;border:1.5px solid <?php echo $bc; ?>;border-radius:14px;padding:16px 20px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                  <p style="margin:0;font-weight:900;color:var(--ttb-text)"><?php echo $ico . ' ' . esc_html($lbl); ?></p>
                  <span style="font-size:13px;color:var(--ttb-muted)"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($rev->created_at))); ?></span>
                </div>
                <?php if ($rev->message || $rev->images): ?>
                  <?php
                  $blocks    = json_decode((string)$rev->images, true);
                  $is_blocks = is_array($blocks) && !empty($blocks) && isset($blocks[0]['type']);
                  ?>
                  <?php if ($is_blocks): ?>
                    <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px">
                      <?php foreach ($blocks as $bl): ?>
                        <?php if (($bl['type'] ?? '') === 'text' && !empty($bl['html'])): ?>
                          <div style="font-size:14px;color:var(--ttb-text);line-height:1.7;background:#fff;border-radius:10px;padding:12px 14px;border:1px solid rgba(0,0,0,.07)"><?php echo wp_kses_post($bl['html']); ?></div>
                        <?php elseif (($bl['type'] ?? '') === 'image'): ?>
                          <div style="background:#fff;border-radius:10px;border:1px solid rgba(0,0,0,.07);overflow:hidden">
                            <?php if (!empty($bl['image_url'])): ?>
                              <a href="<?php echo esc_url($bl['image_url']); ?>" target="_blank">
                                <img src="<?php echo esc_url($bl['image_url']); ?>" style="width:100%;max-height:400px;object-fit:contain;display:block;background:#f4f4f4" alt="Adjunto">
                              </a>
                            <?php endif; ?>
                            <?php if (!empty($bl['caption'])): ?>
                              <div style="padding:10px 14px;font-size:14px;color:var(--ttb-text);line-height:1.6;border-top:1px solid rgba(0,0,0,.06)"><?php echo nl2br(esc_html($bl['caption'])); ?></div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <?php if ($rev->message): ?><p style="margin:10px 0 0;font-size:14px;color:var(--ttb-text);line-height:1.6;white-space:pre-line"><?php echo nl2br(esc_html($rev->message)); ?></p><?php endif; ?>
                    <?php $old_images = json_decode((string)$rev->images, true); if (is_array($old_images) && $old_images): ?>
                      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                        <?php foreach ($old_images as $img_url): ?>
                          <a href="<?php echo esc_url($img_url); ?>" target="_blank"><img src="<?php echo esc_url($img_url); ?>" style="height:72px;width:auto;border-radius:8px;border:1px solid rgba(0,0,0,.1);object-fit:cover" alt=""></a>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php self::render_project_chat($project); ?>

    </div>

<?php
    // Inyectar estilos y JS del editor de anotaciones
    self::render_annotation_styles();
    self::render_annotation_js(
      (int)get_option('ttb_webrev_max_filesize', 5),
      (int)get_option('ttb_webrev_max_files', 10)
    );
  }

  private static function render_project_chat($project) {
    $messages = TTB_WebRev_DB::get_messages((int)$project->id);
    ?>
      <div class="ttb-card">
        <h3 style="margin:0 0 8px">💬 Chat con TicTac Comunicación</h3>
        <p class="ttb-muted" style="margin:0 0 16px">Aquí puedes responder al equipo, resolver dudas o confirmar que los cambios están correctos.</p>

        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px">
          <?php if (!$messages): ?>
            <p class="ttb-muted" style="margin:0">Todavía no hay mensajes en el chat.</p>
          <?php else: ?>
            <?php foreach ($messages as $m): ?>
              <?php
              $is_admin = $m->actor === 'admin';
              $bg = $is_admin ? '#eff6ff' : '#fdf4ff';
              $bc = $is_admin ? '#bfdbfe' : '#f9a8d4';
              $align = $is_admin ? 'margin-right:auto' : 'margin-left:auto';
              $label = $is_admin ? 'TicTac Comunicación' : 'Tú';
              ?>
              <div style="max-width:760px;<?php echo esc_attr($align); ?>;background:<?php echo esc_attr($bg); ?>;border:1px solid <?php echo esc_attr($bc); ?>;border-radius:14px;padding:12px 14px">
                <div style="display:flex;justify-content:space-between;gap:14px;margin-bottom:6px"><strong><?php echo esc_html($label); ?></strong><span class="ttb-muted" style="font-size:12px"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($m->created_at))); ?></span></div>
                <div style="font-size:14px;line-height:1.65;color:var(--ttb-text);white-space:pre-line"><?php echo nl2br(esc_html($m->message)); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form method="post" action="" style="display:flex;flex-direction:column;gap:10px">
          <?php wp_nonce_field('ttb_webrev_message_' . $project->token); ?>
          <input type="hidden" name="ttb_webrev_action" value="message">
          <input type="hidden" name="ttb_webrev_token" value="<?php echo esc_attr($project->token); ?>">
          <textarea class="ttb-textarea" name="ttb_webrev_message" required style="min-height:96px" placeholder="Escribe tu mensaje para el equipo..."></textarea>
          <div class="ttb-actions" style="margin:0">
            <button class="ttb-btn" type="submit">💬 Enviar mensaje</button>
          </div>
        </form>
      </div>
    <?php
  }

  // ═══════════════════════════════════════════════════════════
  // ESTILOS DEL EDITOR DE ANOTACIONES
  // Método público para que TTB_WebProg_Client pueda reutilizarlo
  // ═══════════════════════════════════════════════════════════
  public static function render_annotation_styles_public() {
    self::render_annotation_styles();
  }

  private static function render_annotation_styles() { ?>
<style>
/* ── Tabs ── */
.ttbwr-tab-btn--active{background:rgba(215,33,115,.10)!important;border-color:rgba(215,33,115,.35)!important;color:var(--ttb-pink)!important}

/* ── Bloques ── */
.ttbwr-block{border:1.5px solid var(--ttb-border);border-radius:14px;background:#fff;margin-bottom:14px;overflow:hidden;transition:box-shadow .2s}
.ttbwr-block:focus-within{box-shadow:0 0 0 3px rgba(215,33,115,.12);border-color:rgba(215,33,115,.4)}
.ttbwr-block-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f9fafb;border-bottom:1px solid var(--ttb-border);gap:8px}
.ttbwr-block-label{font-size:12px;font-weight:900;color:var(--ttb-muted);text-transform:uppercase;letter-spacing:.06em}
.ttbwr-block-actions{display:flex;gap:6px}
.ttbwr-block-btn{background:none;border:1px solid var(--ttb-border);border-radius:8px;padding:4px 8px;font-size:13px;cursor:pointer;color:var(--ttb-muted);transition:background .15s,color .15s}
.ttbwr-block-btn:hover{background:#f0f0f0;color:var(--ttb-text)}
.ttbwr-block-btn--delete:hover{background:#fff1f2;color:#e11d48;border-color:#fecdd3}

/* ── Wysiwyg ── */
.ttbwr-wysiwyg-bar{display:flex;flex-wrap:wrap;gap:2px;padding:8px 10px;background:#f9fafb;border-bottom:1px solid var(--ttb-border)}
.ttbwr-wysiwyg-bar button{background:none;border:1px solid transparent;border-radius:6px;padding:4px 8px;font-size:13px;font-weight:700;cursor:pointer;color:var(--ttb-text);line-height:1.4;min-width:28px;transition:background .12s,border-color .12s}
.ttbwr-wysiwyg-bar button:hover{background:#e5e7eb;border-color:#d1d5db}
.ttbwr-wysiwyg-bar button.active{background:rgba(215,33,115,.12);border-color:rgba(215,33,115,.3);color:var(--ttb-pink)}
.ttbwr-wysiwyg-bar .ttbwr-sep{width:1px;background:var(--ttb-border);margin:2px 4px;align-self:stretch}
.ttbwr-editor{min-height:120px;padding:14px 16px;outline:none;font-size:15px;line-height:1.7;color:var(--ttb-text)}
.ttbwr-editor:empty::before{content:attr(data-placeholder);color:#9ca3af;pointer-events:none}
.ttbwr-editor ul,.ttbwr-editor ol{padding-left:22px;margin:6px 0}
.ttbwr-editor blockquote{border-left:3px solid var(--ttb-pink);margin:8px 0;padding:4px 12px;color:var(--ttb-muted);font-style:italic}

/* ── Bloque imagen / anotación ── */
.ttbwr-img-block{padding:14px 16px}
.ttbwr-img-dropzone{border:2px dashed var(--ttb-border);border-radius:12px;padding:28px 20px;text-align:center;cursor:pointer;background:#fafafa;margin-bottom:12px;transition:border-color .2s,background .2s}
.ttbwr-img-dropzone:hover,.ttbwr-img-dropzone.dragover{border-color:var(--ttb-pink);background:rgba(215,33,115,.03)}
.ttbwr-img-caption{width:100%;border:1px solid var(--ttb-border);border-radius:10px;padding:10px 12px;font-size:14px;line-height:1.5;resize:vertical;min-height:72px;font-family:inherit;color:var(--ttb-text);outline:none;transition:border-color .2s,box-shadow .2s}
.ttbwr-img-caption:focus{border-color:var(--ttb-pink);box-shadow:0 0 0 3px rgba(215,33,115,.10)}

/* ── Anotador ── */
.ttbwr-annotator{display:none;flex-direction:column;gap:0;border:1.5px solid var(--ttb-pink);border-radius:14px;overflow:hidden;margin-bottom:12px;background:#1a1a2e}
.ttbwr-annotator.visible{display:flex}

/* Barra de herramientas del anotador */
.ttbwr-anno-toolbar{display:flex;align-items:center;gap:4px;padding:10px 14px;background:#1a1a2e;flex-wrap:wrap}
.ttbwr-anno-tool-btn{background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.15);border-radius:8px;padding:6px 10px;font-size:13px;cursor:pointer;color:#fff;font-weight:700;transition:background .15s,border-color .15s;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;line-height:1}
.ttbwr-anno-tool-btn:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.3)}
.ttbwr-anno-tool-btn.active{background:rgba(215,33,115,.5);border-color:var(--ttb-pink)}
.ttbwr-anno-sep{width:1px;height:24px;background:rgba(255,255,255,.15);margin:0 4px;flex-shrink:0}
.ttbwr-anno-color-btn{width:26px;height:26px;border-radius:50%;border:2px solid rgba(255,255,255,.4);cursor:pointer;transition:border-color .15s,transform .15s;flex-shrink:0}
.ttbwr-anno-color-btn.active{border-color:#fff;transform:scale(1.2)}
.ttbwr-anno-size-btn{background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.15);border-radius:8px;padding:4px 10px;font-size:12px;cursor:pointer;color:#fff;font-weight:700}
.ttbwr-anno-size-btn.active{background:rgba(215,33,115,.5);border-color:var(--ttb-pink)}

/* Canvas wrapper */
.ttbwr-canvas-wrap{position:relative;background:#111;cursor:crosshair;user-select:none;line-height:0}
.ttbwr-canvas-wrap canvas{display:block;max-width:100%;touch-action:none}
canvas.ttbwr-canvas-base{position:relative}
canvas.ttbwr-canvas-draw{position:absolute;top:0;left:0;pointer-events:all}

/* Barra inferior del anotador */
.ttbwr-anno-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 14px;background:#111;flex-wrap:wrap}
.ttbwr-anno-undo-btn,.ttbwr-anno-clear-btn,.ttbwr-anno-done-btn{border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:800;cursor:pointer;transition:filter .15s}
.ttbwr-anno-undo-btn{background:rgba(255,255,255,.12);color:#fff}
.ttbwr-anno-clear-btn{background:#fff1f2;color:#e11d48}
.ttbwr-anno-done-btn{background:linear-gradient(135deg,var(--ttb-pink),#a8005a);color:#fff;box-shadow:0 4px 12px rgba(215,33,115,.35)}
.ttbwr-anno-undo-btn:hover,.ttbwr-anno-clear-btn:hover,.ttbwr-anno-done-btn:hover{filter:brightness(1.1)}

/* Texto en canvas: input flotante */
.ttbwr-text-input{position:absolute;background:rgba(255,255,0,.2);border:2px dashed #ff0;outline:none;color:#ff0;font-size:16px;font-weight:700;font-family:sans-serif;padding:2px 6px;z-index:10;min-width:80px;text-shadow:0 1px 3px rgba(0,0,0,.8);resize:both}

/* Preview imagen anotada */
.ttbwr-img-preview{position:relative;display:block;margin-bottom:12px}
.ttbwr-img-preview img{max-width:100%;max-height:300px;border-radius:10px;border:1px solid var(--ttb-border);display:block}
.ttbwr-img-preview-remove{position:absolute;top:-8px;right:-8px;background:#e11d48;color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;font-weight:900;cursor:pointer;line-height:22px;text-align:center}
.ttbwr-annotate-again-btn{display:inline-block;margin-top:6px;background:none;border:1.5px solid rgba(215,33,115,.4);color:var(--ttb-pink);border-radius:8px;padding:5px 12px;font-size:12px;font-weight:700;cursor:pointer;transition:background .15s}
.ttbwr-annotate-again-btn:hover{background:rgba(215,33,115,.08)}
</style>
<?php }

  // ═══════════════════════════════════════════════════════════
  // JS DEL EDITOR DE ANOTACIONES
  // ═══════════════════════════════════════════════════════════
  private static function render_annotation_js($max_mb, $max_files) { ?>
<script>
(function(){
  /* ─── Configuración ─── */
  var MAX_MB    = <?php echo (int)$max_mb; ?>;
  var MAX_FILES = <?php echo (int)$max_files; ?>;
  var blockCount = 0;
  var imageBlockCount = 0; // total de bloques imagen creados (para ID únicos)

  /* ─── Tabs ─── */
  document.querySelectorAll('.ttbwr-tab-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      document.querySelectorAll('.ttbwr-tab-btn').forEach(function(b){b.classList.remove('ttbwr-tab-btn--active');});
      btn.classList.add('ttbwr-tab-btn--active');
      var t=btn.getAttribute('data-tab');
      document.getElementById('ttbwr-panel-accept').style.display =(t==='accept') ?'block':'none';
      document.getElementById('ttbwr-panel-changes').style.display=(t==='changes')?'block':'none';
    });
  });

  /* ─── Botones añadir bloque ─── */
  document.getElementById('ttbwr-add-text').addEventListener('click',function(){addTextBlock();});
  document.getElementById('ttbwr-add-image').addEventListener('click',function(){addAnnotatedImageBlock();});

  function getContainer(){return document.getElementById('ttbwr-blocks-container');}

  /* ════════════════════════════════════
     BLOQUE DE TEXTO (wysiwyg)
  ════════════════════════════════════ */
  function addTextBlock(initialHtml){
    blockCount++;
    var id='ttbwr-block-'+blockCount;
    var div=document.createElement('div');
    div.className='ttbwr-block';
    div.setAttribute('data-type','text');
    div.setAttribute('data-id',id);
    div.innerHTML=
      '<div class="ttbwr-block-header">'+
        '<span class="ttbwr-block-label">✏️ Bloque de texto</span>'+
        '<div class="ttbwr-block-actions">'+
          '<button type="button" class="ttbwr-block-btn" data-move="up">↑</button>'+
          '<button type="button" class="ttbwr-block-btn" data-move="down">↓</button>'+
          '<button type="button" class="ttbwr-block-btn ttbwr-block-btn--delete" data-delete="1">✕</button>'+
        '</div>'+
      '</div>'+
      '<div class="ttbwr-wysiwyg-bar">'+
        '<button type="button" data-cmd="bold"><b>N</b></button>'+
        '<button type="button" data-cmd="italic"><i>C</i></button>'+
        '<button type="button" data-cmd="underline"><u>S</u></button>'+
        '<span class="ttbwr-sep"></span>'+
        '<button type="button" data-cmd="insertUnorderedList">• Lista</button>'+
        '<button type="button" data-cmd="insertOrderedList">1. Lista</button>'+
        '<span class="ttbwr-sep"></span>'+
        '<button type="button" data-cmd="formatBlock|<blockquote>">❝ Cita</button>'+
        '<button type="button" data-cmd="formatBlock|<p>">¶ Normal</button>'+
        '<span class="ttbwr-sep"></span>'+
        '<button type="button" data-cmd="removeFormat">✕ Formato</button>'+
      '</div>'+
      '<div class="ttbwr-editor" contenteditable="true" data-placeholder="Escribe aquí los cambios: indica la página, sección y qué quieres modificar con todo el detalle que necesites…"></div>';

    getContainer().appendChild(div);
    var editor=div.querySelector('.ttbwr-editor');
    if(initialHtml) editor.innerHTML=initialHtml;

    div.querySelectorAll('.ttbwr-wysiwyg-bar button').forEach(function(btn){
      btn.addEventListener('mousedown',function(e){
        e.preventDefault();
        var cmd=btn.getAttribute('data-cmd');
        if(cmd.indexOf('|')!==-1){var p=cmd.split('|');document.execCommand(p[0],false,p[1]);}
        else document.execCommand(cmd,false,null);
        editor.focus();
      });
    });
    editor.addEventListener('keyup',function(){updateToolbar(div);});
    editor.addEventListener('mouseup',function(){updateToolbar(div);});
    bindBlockActions(div);
    editor.focus();
  }

  function updateToolbar(blockEl){
    blockEl.querySelectorAll('.ttbwr-wysiwyg-bar button[data-cmd]').forEach(function(b){
      var cmd=b.getAttribute('data-cmd').split('|')[0];
      try{b.classList.toggle('active',document.queryCommandState(cmd));}catch(e){}
    });
  }

  /* ════════════════════════════════════
     BLOQUE DE IMAGEN CON ANOTACIONES
  ════════════════════════════════════ */
  function addAnnotatedImageBlock(){
    blockCount++;
    imageBlockCount++;
    var blockId='ttbwr-block-'+blockCount;
    var annoId='ttbwr-anno-'+imageBlockCount;
    var div=document.createElement('div');
    div.className='ttbwr-block';
    div.setAttribute('data-type','image');
    div.setAttribute('data-id',blockId);

    div.innerHTML=
      '<div class="ttbwr-block-header">'+
        '<span class="ttbwr-block-label">🖼️ Captura con anotaciones</span>'+
        '<div class="ttbwr-block-actions">'+
          '<button type="button" class="ttbwr-block-btn" data-move="up">↑</button>'+
          '<button type="button" class="ttbwr-block-btn" data-move="down">↓</button>'+
          '<button type="button" class="ttbwr-block-btn ttbwr-block-btn--delete" data-delete="1">✕</button>'+
        '</div>'+
      '</div>'+
      '<div class="ttbwr-img-block" id="'+annoId+'-wrap">'+
        '<div class="ttbwr-img-dropzone" id="'+annoId+'-dz" tabindex="0">'+
          '<p style="margin:0 0 6px;font-size:28px">📎</p>'+
          '<p style="margin:0 0 4px;font-weight:700;color:var(--ttb-text);font-size:14px">Arrastra una captura o haz clic para seleccionarla</p>'+
          '<p style="margin:0 0 6px;font-size:12px;color:var(--ttb-muted)">PNG, JPG, GIF, WEBP · Máx. '+MAX_MB+' MB</p>'+
          '<p style="margin:0;font-size:12px;color:var(--ttb-pink);font-weight:700">Podrás dibujar encima para señalar exactamente qué cambiar ✍️</p>'+
          '<input type="file" accept="image/*" style="display:none" id="'+annoId+'-input">'+
        '</div>'+
        '<div class="ttbwr-annotator" id="'+annoId+'-editor">'+
          '<div class="ttbwr-anno-toolbar" id="'+annoId+'-toolbar">'+
            '<button type="button" class="ttbwr-anno-tool-btn active" data-tool="pencil" title="Lápiz libre">✏️ Lápiz</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="highlighter" title="Subrayador">🖌️ Subrayado</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="arrow" title="Flecha">➡️ Flecha</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="rect" title="Rectángulo">⬜ Rect.</button>'+
            '<button type="button" class="ttbwr-anno-tool-btn" data-tool="text" title="Texto">🔤 Texto</button>'+
            '<div class="ttbwr-anno-sep"></div>'+
            '<button type="button" class="ttbwr-anno-color-btn active" data-color="#ff3b3b" style="background:#ff3b3b" title="Rojo"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#ff9500" style="background:#ff9500" title="Naranja"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#ffcc00" style="background:#ffcc00" title="Amarillo"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#34c759" style="background:#34c759" title="Verde"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#007aff" style="background:#007aff" title="Azul"></button>'+
            '<button type="button" class="ttbwr-anno-color-btn" data-color="#ffffff" style="background:#fff;border-color:#666" title="Blanco"></button>'+
            '<div class="ttbwr-anno-sep"></div>'+
            '<button type="button" class="ttbwr-anno-size-btn" data-size="3" title="Fino">S</button>'+
            '<button type="button" class="ttbwr-anno-size-btn active" data-size="5" title="Medio">M</button>'+
            '<button type="button" class="ttbwr-anno-size-btn" data-size="10" title="Grueso">L</button>'+
            '<button type="button" class="ttbwr-anno-size-btn" data-size="18" title="Muy grueso">XL</button>'+
          '</div>'+
          '<div class="ttbwr-canvas-wrap" id="'+annoId+'-canvaswrap">'+
            '<canvas id="'+annoId+'-base" class="ttbwr-canvas-base"></canvas>'+
            '<canvas id="'+annoId+'-draw" class="ttbwr-canvas-draw"></canvas>'+
          '</div>'+
          '<div class="ttbwr-anno-bottom">'+
            '<div style="display:flex;gap:8px">'+
              '<button type="button" class="ttbwr-anno-undo-btn" id="'+annoId+'-undo">↩ Deshacer</button>'+
              '<button type="button" class="ttbwr-anno-clear-btn" id="'+annoId+'-clear">🗑 Borrar todo</button>'+
            '</div>'+
            '<button type="button" class="ttbwr-anno-done-btn" id="'+annoId+'-done">✅ Guardar anotaciones</button>'+
          '</div>'+
        '</div>'+
        '<div id="'+annoId+'-preview" style="display:none"></div>'+
        '<textarea class="ttbwr-img-caption" id="'+annoId+'-caption" placeholder="Describe el cambio: ¿qué elemento hay que modificar? ¿cómo debería quedar?"></textarea>'+
      '</div>';

    getContainer().appendChild(div);
    bindBlockActions(div);
    initAnnotator(annoId, div);
  }

  /* ════════════════════════════════════
     MOTOR DEL ANOTADOR
  ════════════════════════════════════ */
  function initAnnotator(annoId, blockEl){
    var dz      = document.getElementById(annoId+'-dz');
    var fileInp = document.getElementById(annoId+'-input');
    var editor  = document.getElementById(annoId+'-editor');
    var preview = document.getElementById(annoId+'-preview');
    var cWrap   = document.getElementById(annoId+'-canvaswrap');
    var baseC   = document.getElementById(annoId+'-base');
    var drawC   = document.getElementById(annoId+'-draw');
    var bCtx    = baseC.getContext('2d');
    var dCtx    = drawC.getContext('2d');

    var state = {
      tool      : 'pencil',
      color     : '#ff3b3b',
      size      : 5,
      drawing   : false,
      startX    : 0, startY : 0,
      history   : [],
      finalDataUrl: null,
      scale     : 1,
    };

    dz.addEventListener('click',function(){fileInp.click();});
    dz.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' ')fileInp.click();});
    dz.addEventListener('dragover',function(e){e.preventDefault();dz.classList.add('dragover');});
    dz.addEventListener('dragleave',function(){dz.classList.remove('dragover');});
    dz.addEventListener('drop',function(e){e.preventDefault();dz.classList.remove('dragover');if(e.dataTransfer.files[0])loadImage(e.dataTransfer.files[0]);});
    fileInp.addEventListener('change',function(){if(fileInp.files[0])loadImage(fileInp.files[0]);});

    function loadImage(file){
      if(!file.type.startsWith('image/')){alert('Solo se admiten imágenes.');return;}
      if(file.size>MAX_MB*1024*1024){alert('La imagen supera el límite de '+MAX_MB+' MB.');return;}
      var reader=new FileReader();
      reader.onload=function(e){
        var img=new Image();
        img.onload=function(){
          var maxW=cWrap.offsetWidth||700;
          var ratio=Math.min(1, maxW/img.width);
          var W=Math.round(img.width*ratio);
          var H=Math.round(img.height*ratio);
          state.scale=img.width/W;

          baseC.width=img.width; baseC.height=img.height;
          drawC.width=img.width; drawC.height=img.height;
          baseC.style.width=W+'px'; baseC.style.height=H+'px';
          drawC.style.width=W+'px'; drawC.style.height=H+'px';
          cWrap.style.height=H+'px';

          bCtx.drawImage(img,0,0);
          clearDraw();
          pushHistory();

          dz.style.display='none';
          editor.classList.add('visible');
          state.finalDataUrl=null;
        };
        img.src=e.target.result;
      };
      reader.readAsDataURL(file);
    }

    document.getElementById(annoId+'-toolbar').querySelectorAll('[data-tool]').forEach(function(btn){
      btn.addEventListener('click',function(){
        state.tool=btn.getAttribute('data-tool');
        document.getElementById(annoId+'-toolbar').querySelectorAll('[data-tool]').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        drawC.style.cursor = state.tool==='text' ? 'text' : 'crosshair';
      });
    });
    document.getElementById(annoId+'-toolbar').querySelectorAll('[data-color]').forEach(function(btn){
      btn.addEventListener('click',function(){
        state.color=btn.getAttribute('data-color');
        document.getElementById(annoId+'-toolbar').querySelectorAll('[data-color]').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
      });
    });
    document.getElementById(annoId+'-toolbar').querySelectorAll('[data-size]').forEach(function(btn){
      btn.addEventListener('click',function(){
        state.size=parseInt(btn.getAttribute('data-size'));
        document.getElementById(annoId+'-toolbar').querySelectorAll('[data-size]').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
      });
    });

    document.getElementById(annoId+'-undo').addEventListener('click',function(){
      if(state.history.length>1){
        state.history.pop();
        var img=new Image();
        img.onload=function(){dCtx.clearRect(0,0,drawC.width,drawC.height);dCtx.drawImage(img,0,0);};
        img.src=state.history[state.history.length-1];
      } else {
        clearDraw(); pushHistory();
      }
    });
    document.getElementById(annoId+'-clear').addEventListener('click',function(){
      if(!confirm('¿Borrar todas las anotaciones?'))return;
      clearDraw(); state.history=[]; pushHistory();
    });

    document.getElementById(annoId+'-done').addEventListener('click',function(){
      var merged=document.createElement('canvas');
      merged.width=baseC.width; merged.height=baseC.height;
      var mCtx=merged.getContext('2d');
      mCtx.drawImage(baseC,0,0);
      mCtx.drawImage(drawC,0,0);
      state.finalDataUrl=merged.toDataURL('image/jpeg', 0.88);
      blockEl.setAttribute('data-annotated-url',state.finalDataUrl);

      editor.classList.remove('visible');
      preview.style.display='block';
      preview.innerHTML='';
      var wrap=document.createElement('div'); wrap.className='ttbwr-img-preview';
      var pImg=document.createElement('img'); pImg.src=state.finalDataUrl; pImg.style.maxWidth='100%';
      var rm=document.createElement('button'); rm.type='button'; rm.className='ttbwr-img-preview-remove'; rm.textContent='✕';
      rm.title='Eliminar imagen';
      rm.addEventListener('click',function(){
        preview.style.display='none'; preview.innerHTML='';
        blockEl.removeAttribute('data-annotated-url');
        state.finalDataUrl=null;
        clearDraw(); state.history=[];
        dz.style.display=''; editor.classList.remove('visible');
      });
      wrap.appendChild(pImg); wrap.appendChild(rm);

      var reEdit=document.createElement('button'); reEdit.type='button'; reEdit.className='ttbwr-annotate-again-btn';
      reEdit.textContent='✏️ Editar anotaciones';
      reEdit.addEventListener('click',function(){
        preview.style.display='none';
        editor.classList.add('visible');
      });
      preview.appendChild(wrap); preview.appendChild(reEdit);
    });

    var snapshot = null;

    function getPos(e){
      var r=drawC.getBoundingClientRect();
      var clientX,clientY;
      if(e.touches){clientX=e.touches[0].clientX;clientY=e.touches[0].clientY;}
      else{clientX=e.clientX;clientY=e.clientY;}
      return {
        x:Math.round((clientX-r.left)*state.scale),
        y:Math.round((clientY-r.top)*state.scale)
      };
    }

    function setupCtx(ctx, alpha){
      ctx.strokeStyle = state.color;
      ctx.fillStyle   = state.color;
      ctx.lineWidth   = state.size * state.scale;
      ctx.lineCap     = 'round';
      ctx.lineJoin    = 'round';
      ctx.globalAlpha = alpha||1;
    }

    drawC.addEventListener('mousedown', onDown);
    drawC.addEventListener('mousemove', onMove);
    drawC.addEventListener('mouseup',   onUp);
    drawC.addEventListener('mouseleave',onUp);
    drawC.addEventListener('touchstart', function(e){e.preventDefault();onDown(e);}, {passive:false});
    drawC.addEventListener('touchmove',  function(e){e.preventDefault();onMove(e);}, {passive:false});
    drawC.addEventListener('touchend',   function(e){e.preventDefault();onUp(e);},   {passive:false});

    function onDown(e){
      var pos=getPos(e);
      state.startX=pos.x; state.startY=pos.y;

      if(state.tool==='text'){
        spawnTextInput(pos.x/state.scale+drawC.getBoundingClientRect().left-cWrap.getBoundingClientRect().left,
                       pos.y/state.scale+drawC.getBoundingClientRect().top -cWrap.getBoundingClientRect().top);
        return;
      }
      state.drawing=true;
      if(state.tool==='arrow'||state.tool==='rect'){
        snapshot=dCtx.getImageData(0,0,drawC.width,drawC.height);
      }
      if(state.tool==='pencil'||state.tool==='highlighter'){
        setupCtx(dCtx, state.tool==='highlighter'?0.38:1);
        dCtx.beginPath();
        dCtx.moveTo(state.startX, state.startY);
      }
    }

    function onMove(e){
      if(!state.drawing)return;
      var pos=getPos(e);
      if(state.tool==='pencil'||state.tool==='highlighter'){
        setupCtx(dCtx, state.tool==='highlighter'?0.38:1);
        dCtx.lineTo(pos.x,pos.y);
        dCtx.stroke();
      } else if(state.tool==='arrow'||state.tool==='rect'){
        dCtx.putImageData(snapshot,0,0);
        setupCtx(dCtx,1);
        if(state.tool==='arrow') drawArrow(dCtx,state.startX,state.startY,pos.x,pos.y);
        if(state.tool==='rect')  drawRect(dCtx,state.startX,state.startY,pos.x,pos.y);
      }
    }

    function onUp(e){
      if(!state.drawing)return;
      state.drawing=false;
      var pos=getPos(e);
      if(state.tool==='arrow'){dCtx.putImageData(snapshot,0,0);setupCtx(dCtx,1);drawArrow(dCtx,state.startX,state.startY,pos.x,pos.y);}
      if(state.tool==='rect') {dCtx.putImageData(snapshot,0,0);setupCtx(dCtx,1);drawRect(dCtx,state.startX,state.startY,pos.x,pos.y);}
      if(state.tool==='pencil'||state.tool==='highlighter'){dCtx.globalAlpha=1;}
      pushHistory();
    }

    function spawnTextInput(cssX, cssY){
      var inp=document.createElement('input');
      inp.type='text';
      inp.className='ttbwr-text-input';
      inp.style.left=(cssX)+'px';
      inp.style.top =(cssY-16)+'px';
      inp.placeholder='Escribe aquí...';
      inp.style.fontSize=Math.max(12, state.size*3)+'px';
      inp.style.color=state.color;
      cWrap.style.position='relative';
      cWrap.appendChild(inp);
      inp.focus();
      function commit(){
        if(!inp.value.trim()){inp.remove();return;}
        setupCtx(dCtx,1);
        dCtx.font='bold '+(Math.max(14,state.size*state.scale*2.5))+'px sans-serif';
        dCtx.fillStyle=state.color;
        dCtx.shadowColor='rgba(0,0,0,0.7)';
        dCtx.shadowBlur=4;
        dCtx.fillText(inp.value, cssX*state.scale, (cssY-4)*state.scale);
        dCtx.shadowBlur=0;
        inp.remove();
        pushHistory();
      }
      inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();commit();}if(e.key==='Escape')inp.remove();});
      inp.addEventListener('blur',commit);
    }

    function drawArrow(ctx,x1,y1,x2,y2){
      var headLen=Math.max(16, state.size*state.scale*3);
      var angle=Math.atan2(y2-y1,x2-x1);
      ctx.beginPath();
      ctx.moveTo(x1,y1);
      ctx.lineTo(x2,y2);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(x2,y2);
      ctx.lineTo(x2-headLen*Math.cos(angle-Math.PI/7),y2-headLen*Math.sin(angle-Math.PI/7));
      ctx.lineTo(x2-headLen*Math.cos(angle+Math.PI/7),y2-headLen*Math.sin(angle+Math.PI/7));
      ctx.closePath();
      ctx.fill();
    }
    function drawRect(ctx,x1,y1,x2,y2){
      ctx.beginPath();
      ctx.rect(x1,y1,x2-x1,y2-y1);
      ctx.stroke();
    }

    function pushHistory(){
      if(state.history.length>30)state.history.shift();
      state.history.push(drawC.toDataURL());
    }
    function clearDraw(){
      dCtx.clearRect(0,0,drawC.width,drawC.height);
    }
  }

  /* ════════════════════════════════════
     ACCIONES COMUNES DE BLOQUE
  ════════════════════════════════════ */
  function bindBlockActions(block){
    block.querySelector('[data-delete]').addEventListener('click',function(){
      if(getContainer().querySelectorAll('.ttbwr-block').length<=1){alert('Debe haber al menos un bloque.');return;}
      block.remove();
    });
    block.querySelector('[data-move="up"]').addEventListener('click',function(){var prev=block.previousElementSibling;if(prev)getContainer().insertBefore(block,prev);});
    block.querySelector('[data-move="down"]').addEventListener('click',function(){var next=block.nextElementSibling;if(next)getContainer().insertBefore(next,block);});
  }

  /* Añadir primer bloque de captura al inicio: la referencia gráfica es obligatoria */
  addAnnotatedImageBlock();
  addTextBlock();

  /* ════════════════════════════════════
     ENVÍO DEL FORMULARIO
  ════════════════════════════════════ */
  document.getElementById('ttbwr-changes-form').addEventListener('submit',function(e){
    e.preventDefault();
    var blocks=[];
    var imageFiles=[];
    var container=getContainer();
    var blockEls=container.querySelectorAll('.ttbwr-block');
    var hasContent=false;

    blockEls.forEach(function(bl,idx){
      var type=bl.getAttribute('data-type');
      if(type==='text'){
        var html=bl.querySelector('.ttbwr-editor').innerHTML.trim();
        var plain=bl.querySelector('.ttbwr-editor').innerText.trim();
        if(plain) hasContent=true;
        blocks.push({type:'text',html:html,idx:idx});
      } else if(type==='image'){
  var caption=bl.querySelector('[id$="-caption"]').value.trim();
  var annotatedUrl=bl.getAttribute('data-annotated-url')||'';

  /*
   * FIX:
   * Si el cliente ha subido una captura pero no ha pulsado "Guardar anotaciones",
   * la imagen existe en el canvas, pero todavía no está guardada como dataURL.
   * La generamos automáticamente antes de validar/enviar.
   */
  if(!annotatedUrl){
    var baseCanvas = bl.querySelector('canvas.ttbwr-canvas-base');
    var drawCanvas = bl.querySelector('canvas.ttbwr-canvas-draw');

    if(baseCanvas && drawCanvas && baseCanvas.width > 0 && baseCanvas.height > 0){
      var merged = document.createElement('canvas');
      merged.width = baseCanvas.width;
      merged.height = baseCanvas.height;

      var mergedCtx = merged.getContext('2d');
      mergedCtx.drawImage(baseCanvas, 0, 0);
      mergedCtx.drawImage(drawCanvas, 0, 0);

      annotatedUrl = merged.toDataURL('image/jpeg', 0.88);
      bl.setAttribute('data-annotated-url', annotatedUrl);
    }
  }

  if(annotatedUrl || caption) hasContent=true;

  var fileIndex=-1;

  if(annotatedUrl){
    fileIndex=imageFiles.length;
    imageFiles.push({
      dataUrl: annotatedUrl,
      name: 'anotacion-'+(imageFiles.length+1)+'.jpg',
      mimeType: 'image/jpeg'
    });
  }

  blocks.push({
    type: 'image',
    caption: caption,
    fileIndex: fileIndex,
    idx: idx
  });
}
    });

    if(!hasContent){alert('Añade al menos un comentario o imagen antes de enviar.');return;}
    if(imageFiles.length<1){alert('Para enviar cambios es obligatorio adjuntar al menos una captura o imagen de referencia.');return;}

    document.getElementById('ttbwr_blocks_json').value=JSON.stringify(blocks);

    var fd=new FormData(document.getElementById('ttbwr-changes-form'));
    imageFiles.forEach(function(f,i){
      var arr=f.dataUrl.split(','),mime=arr[0].match(/:(.*?);/)[1];
      var bstr=atob(arr[1]),n=bstr.length,u8=new Uint8Array(n);
      for(var j=0;j<n;j++) u8[j]=bstr.charCodeAt(j);
      var blob=new Blob([u8],{type:mime});
      fd.append('ttbwr_img_file_'+i,blob,f.name||('anotacion-'+(i+1)+'.jpg'));
    });
    fd.set('ttbwr_img_count',imageFiles.length);

    var btn=document.getElementById('ttbwr-submit-btn');
    btn.disabled=true; btn.textContent='⏳ Enviando...';

    fetch(window.location.href,{method:'POST',body:fd})
      .then(function(r){
        if(!r.ok){ return r.text().then(function(t){ throw new Error('HTTP '+r.status+': '+t.substring(0,300)); }); }
        return r.text();
      })
      .then(function(html){
        var match=html.match(/window\.location\.replace\((.+?)\)/);
        if(match) window.location.replace(JSON.parse(match[1]));
        else {
          var phpErr=html.match(/(Fatal error|Warning|Parse error)[^<]*/i);
          if(phpErr) alert('Error del servidor: '+phpErr[0]);
          else window.location.reload();
        }
      })
      .catch(function(err){btn.disabled=false;btn.textContent='📨 Enviar cambios';alert('Error al enviar: '+err.message);});
  });
})();
</script>
<?php }

  // ═══════════════════════════════════════════════════════════
  // HELPERS
  // ═══════════════════════════════════════════════════════════
  private static function js_redirect($url) {
    echo '<script>window.location.replace(' . wp_json_encode(esc_url_raw($url)) . ');</script>';
    exit;
  }

  private static function handle_submit($project) {
    $action = sanitize_text_field($_POST['ttb_webrev_action'] ?? '');
    $token  = sanitize_text_field($_POST['ttb_webrev_token']  ?? '');

    global $wpdb;
    $projects_table  = TTB_WebRev_DB::projects_table();
    $revisions_table = TTB_WebRev_DB::revisions_table();

    if ($action === 'accept') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_webrev_accept_' . $token)) {
        TTB_WebRev_DB::log($project->id, 'nonce_failed', 'client', ['action' => 'accept']);
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      $wpdb->update($projects_table, [
        'status'     => 'accepted',
        'updated_at' => TTB_WebRev_DB::now(),
      ], ['id' => $project->id]);

      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'accept',
        'message'    => null,
        'images'     => null,
        'created_at' => TTB_WebRev_DB::now(),
      ]);

      (new TTB_WebRev_Mailer())->send_accepted_alert($project);
      TTB_WebRev_DB::log($project->id, 'design_accepted', 'client', ['round' => $round]);
      TTB_WebRev_DB::log($project->id, 'email_accepted_sent', 'system', ['recipients' => 'hola + creativo']);

      self::js_redirect(TTB_WebRev_DB::client_url($token));

    } elseif ($action === 'message') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_webrev_message_' . $token)) {
        TTB_WebRev_DB::log($project->id, 'nonce_failed', 'client', ['action' => 'message']);
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      $message = sanitize_textarea_field($_POST['ttb_webrev_message'] ?? '');
      if (trim($message) === '') self::js_redirect(TTB_WebRev_DB::client_url($token));

      TTB_WebRev_DB::add_message($project->id, 'client', $message);
      (new TTB_WebRev_Mailer())->send_client_message_alert($project, $message);
      TTB_WebRev_DB::log($project->id, 'client_chat_message', 'client', ['message' => mb_substr($message, 0, 180)]);

      self::js_redirect(TTB_WebRev_DB::client_url($token));

    } elseif ($action === 'changes') {
      if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ttb_webrev_changes_' . $token)) {
        TTB_WebRev_DB::log($project->id, 'nonce_failed', 'client', ['action' => 'changes']);
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      $blocks_raw = sanitize_text_field($_POST['ttbwr_blocks_json'] ?? '');
      $blocks     = json_decode(stripslashes($blocks_raw), true);

      if (!is_array($blocks) || empty($blocks)) {
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      $has_content = false;
      foreach ($blocks as $b) {
        if (!empty($b['html']) || !empty($b['caption'])) { $has_content = true; break; }
        if (isset($b['fileIndex']) && $b['fileIndex'] >= 0) { $has_content = true; break; }
      }
      if (!$has_content) self::js_redirect(TTB_WebRev_DB::client_url($token));

      $round = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $revisions_table WHERE project_id=%d", $project->id
      )) + 1;

      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';

      $max_mb         = (int)get_option('ttb_webrev_max_filesize', 5);
      $max_files      = (int)get_option('ttb_webrev_max_files', 10);
      $img_count      = min((int)($_POST['ttbwr_img_count'] ?? 0), $max_files);
      $uploaded       = [];
      $uploaded_count = 0;

      for ($i = 0; $i < $img_count; $i++) {
        $key = 'ttbwr_img_file_' . $i;
        if (empty($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) continue;
        if ($_FILES[$key]['size'] > $max_mb * 1024 * 1024) continue;
        $type = $_FILES[$key]['type'];
        if (!in_array($type, ['image/jpeg','image/png','image/gif','image/webp'], true)) continue;
        $att_id = media_handle_sideload([
          'name'     => $_FILES[$key]['name'],
          'type'     => $type,
          'tmp_name' => $_FILES[$key]['tmp_name'],
          'error'    => $_FILES[$key]['error'],
          'size'     => $_FILES[$key]['size'],
        ], 0, null, [
          'post_title'  => 'WebRev anotacion - ' . $project->name . ' #' . $round . ' img' . ($i + 1),
          'post_status' => 'private',
        ]);
        if (!is_wp_error($att_id)) {
          $uploaded[$i] = wp_get_attachment_url($att_id);
          $uploaded_count++;
        }
      }

      if ($uploaded_count < 1) {
        self::js_redirect(TTB_WebRev_DB::client_url($token));
      }

      $sanitized_blocks = [];
      foreach ($blocks as $b) {
        $type = $b['type'] ?? '';
        if ($type === 'text') {
          $html = wp_kses_post($b['html'] ?? '');
          if (trim(wp_strip_all_tags($html)) === '') continue;
          $sanitized_blocks[] = ['type' => 'text', 'html' => $html];
        } elseif ($type === 'image') {
          $caption   = sanitize_textarea_field($b['caption'] ?? '');
          $file_idx  = (int)($b['fileIndex'] ?? -1);
          $image_url = ($file_idx >= 0 && isset($uploaded[$file_idx])) ? $uploaded[$file_idx] : '';
          if (!$image_url && !$caption) continue;
          $sanitized_blocks[] = ['type' => 'image', 'caption' => $caption, 'image_url' => $image_url];
        }
      }
      if (empty($sanitized_blocks)) self::js_redirect(TTB_WebRev_DB::client_url($token));

      $message_plain = '';
      foreach ($sanitized_blocks as $b) {
        if ($b['type'] === 'text') $message_plain .= wp_strip_all_tags($b['html']) . "\n\n";
        if ($b['type'] === 'image' && $b['caption']) $message_plain .= '[Imagen anotada] ' . $b['caption'] . "\n\n";
      }

      $wpdb->update($projects_table, [
        'status'        => 'changes_requested',
        'last_notified' => TTB_WebRev_DB::now(),
        'updated_at'    => TTB_WebRev_DB::now(),
      ], ['id' => $project->id]);

      $wpdb->insert($revisions_table, [
        'project_id' => $project->id,
        'round'      => $round,
        'type'       => 'change',
        'message'    => trim($message_plain),
        'images'     => wp_json_encode($sanitized_blocks, JSON_UNESCAPED_UNICODE),
        'created_at' => TTB_WebRev_DB::now(),
      ]);

      $revision = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $revisions_table WHERE id=%d", $wpdb->insert_id
      ));
      (new TTB_WebRev_Mailer())->send_changes_alert($project, $revision);

      TTB_WebRev_DB::log($project->id, 'changes_requested', 'client', [
        'round'        => $round,
        'text_blocks'  => count(array_filter($sanitized_blocks, fn($b) => $b['type'] === 'text')),
        'image_blocks' => $uploaded_count,
      ]);
      TTB_WebRev_DB::log($project->id, 'email_changes_sent', 'system', ['round' => $round, 'recipients' => 'hola + creativo']);

      self::js_redirect(TTB_WebRev_DB::client_url($token));
    }
  }
}