<?php
if (!defined('ABSPATH')) exit;

/**
 * portal-shell-social.php
 * Shell para la vista pública del cliente de Redes Sociales (magic link).
 * No requiere login — usa ?social=TOKEN
 */

$token = sanitize_text_field($_GET['social'] ?? '');

nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redes Sociales — TicTac Comunicación</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<meta name="robots" content="noindex, nofollow, noarchive">
<?php wp_head(); ?>
<style>
.ttb-header {
  background: #D72173;
  width: 100%;
  position: sticky;
  top: 0;
  z-index: 999999;
}
.ttb-header-inner {
  max-width: 1200px;
  margin: auto;
  height: 70px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ttb-logo { position: absolute; left: 50%; transform: translateX(-50%); }
.ttb-logo img { height: 40px; display: block; }
.ttb-main { max-width: 960px; margin: auto; padding: 30px 20px 60px; }
.ttb-flash {
  border-radius: 14px; padding: 12px 16px; margin-bottom: 16px;
  font-weight: 700; font-size: 14px;
}
.ttb-flash--success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.ttb-flash--error   { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; }

/* Aprobación inline via fetch */
.ttb-approval-toggle { cursor: pointer; }
</style>
</head>
<body <?php body_class('ttb-body'); ?>>

<header class="ttb-header">
  <div class="ttb-header-inner">
    <a class="ttb-logo" href="<?php echo esc_url(home_url('/')); ?>">
      <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="TicTac Comunicación">
    </a>
  </div>
</header>

<div class="ttb-main">
<?php
$flash = get_transient('ttb_social_flash_' . $token);
if ($flash) {
  delete_transient('ttb_social_flash_' . $token);
  $cls = $flash['type'] === 'success' ? 'ttb-flash--success' : 'ttb-flash--error';
  echo '<div class="ttb-flash ' . $cls . '">' . esc_html($flash['text']) . '</div>';
}

TTB_Social_Client::render($token);
?>
</div>

<script>
// Botón approve via fetch (para no recargar)
function ttbSocialApprove(postId, action, token) {
  var nonce = document.querySelector('[name="_wpnonce"][data-post="' + postId + '"]');
  // Fallback: buscar el primer nonce del formulario de aprobación de este post
  var panel = document.getElementById('approval-panel-' + postId);
  if (!panel) return;

  var nonceInput = panel.querySelector('input[name="_wpnonce"]');
  if (!nonceInput) return;

  // Construir form data
  var fd = new FormData();
  fd.append('ttb_social_action', 'approve');
  fd.append('ttb_social_token', token);
  fd.append('post_id', postId);
  fd.append('_wpnonce', nonceInput.value);

  // Deshabilitar botones
  panel.querySelectorAll('button').forEach(function(b){ b.disabled = true; });

  fetch(window.location.href, { method: 'POST', body: fd })
    .then(function(r){ return r.text(); })
    .then(function(html){
      var match = html.match(/window\.location\.replace\((.+?)\)/);
      if (match) window.location.replace(JSON.parse(match[1]));
      else window.location.reload();
    })
    .catch(function(){
      panel.querySelectorAll('button').forEach(function(b){ b.disabled = false; });
      alert('Error al enviar. Inténtalo de nuevo.');
    });
}
</script>

<?php wp_footer(); ?>
</body>
</html>