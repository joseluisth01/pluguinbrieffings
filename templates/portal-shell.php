<?php
if (!defined('ABSPATH')) exit;

$auth  = new TTB_Auth();
$flash = $auth->consume_flash();

$lang = 'es';
if ($auth->is_client()) {
  $lang = TTB_Forms::get_client_lang($auth->client_id());
}

$logout_label = $lang === 'en' ? 'Log out' : 'Cerrar sesión';

nocache_headers();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Prebriefing — TicTac Comunicación</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

<meta name="robots" content="noindex, nofollow, noarchive">

<?php wp_head(); ?>

<style>

.ttb-header{
  background:#D72173;
  width:100%;
  position:sticky;
  top:0;
  z-index:999999;
}

.ttb-header-inner{
  max-width:1200px;
  margin:auto;
  height:70px;
  position:relative;
  display:flex;
  align-items:center;
  justify-content:center;
}

.ttb-logo{
  position:absolute;
  left:50%;
  transform:translateX(-50%);
}

.ttb-logo img{
  height:40px;
  display:block;
}

.ttb-logout{
  position:absolute;
  right:20px;
  background:white;
  color:#D72173;
  padding:8px 14px;
  border-radius:8px;
  text-decoration:none;
  font-weight:600;
  font-family:Montserrat, Arial;
}

.ttb-logout:hover{
  background:#f4f4f4;
}

.ttb-main{
  max-width:1200px;
  margin:auto;
  padding:30px 20px;
}

</style>

</head>

<body <?php body_class('ttb-body'); ?>>

<header class="ttb-header">

<div class="ttb-header-inner">

<a class="ttb-logo" href="<?php echo esc_url(home_url('/briefing')); ?>">
<img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="TicTac Comunicación">
</a>

<?php if ($auth->current()): ?>

<a class="ttb-logout" href="<?php echo esc_url(add_query_arg(['ttb_logout'=>1],home_url('/briefing'))); ?>">
<?php echo esc_html($logout_label); ?>
</a>

<?php endif; ?>

</div>

</header>

<div class="ttb-main">

<?php if ($flash): ?>
<div class="ttb-flash ttb-flash--<?php echo esc_attr($flash['type']); ?>">
<?php echo esc_html($flash['text']); ?>
</div>
<?php endif; ?>

<?php

if (!$auth->current()){

include TTB_PATH.'templates/login.php';

}elseif ($auth->is_admin()){

include TTB_PATH.'templates/admin.php';

}else{

include TTB_PATH.'templates/client.php';

}

?>

</div>

<?php
// ── Modal de envío exitoso ──────────────────────────────────
if ($auth->is_client()) {
  $modal_svc = TTB_Forms::consume_modal($auth->client_id());
  if ($modal_svc) {
    $modal_titles = [
      'es' => [
        'design' => 'Prebriefing de Diseño enviado',
        'social' => 'Prebriefing de Redes enviado',
        'seo'    => 'Prebriefing SEO enviado',
        'web'    => 'Prebriefing Web enviado',
      ],
      'en' => [
        'design' => 'Design Pre-briefing submitted',
        'social' => 'Social Media Pre-briefing submitted',
        'seo'    => 'SEO Pre-briefing submitted',
        'web'    => 'Web Pre-briefing submitted',
      ],
    ];
    $modal_subs = [
      'es' => [
        'design' => 'Diseño',
        'social' => 'Redes Sociales',
        'seo'    => 'SEO',
        'web'    => 'Web',
      ],
      'en' => [
        'design' => 'Design',
        'social' => 'Social Media',
        'seo'    => 'SEO',
        'web'    => 'Web',
      ],
    ];
    $modal_emojis = ['design' => '🎨', 'social' => '📣', 'seo' => '🚀', 'web' => '🌐'];
    $modal_msgs = [
      'es' => 'Nuestro equipo lo revisará y se pondrá en contacto contigo muy pronto. ¡Gracias por confiar en TicTac!',
      'en' => 'Our team will review it and get in touch with you very soon. Thank you for trusting TicTac!',
    ];
    $modal_btn = ['es' => 'Perfecto, ¡gracias! 🎉', 'en' => 'Great, thanks! 🎉'];

    $t     = $modal_titles[$lang][$modal_svc]  ?? 'Prebriefing enviado';
    $sub   = $modal_subs[$lang][$modal_svc]    ?? strtoupper($modal_svc);
    $emoji = $modal_emojis[$modal_svc]         ?? '✅';
    $msg   = $modal_msgs[$lang]                ?? $modal_msgs['es'];
    $btn   = $modal_btn[$lang]                 ?? $modal_btn['es'];
    ?>
    <div class="ttb-modal-overlay" id="ttbSuccessModal" role="dialog" aria-modal="true" aria-labelledby="ttbSuccessTitle">
      <div class="ttb-modal">
        <div class="ttb-modal__confetti">
          <?php for ($i = 1; $i <= 18; $i++) echo '<div class="ttb-confetti-dot"></div>'; ?>
        </div>
        <span class="ttb-modal__emoji" aria-hidden="true"><?php echo $emoji; ?></span>
        <h2 class="ttb-modal__title" id="ttbSuccessTitle"><?php echo esc_html($t); ?></h2>
        <p class="ttb-modal__sub"><?php echo esc_html($sub); ?></p>
        <p class="ttb-modal__msg"><?php echo esc_html($msg); ?></p>
        <button class="ttb-btn ttb-modal__close" id="ttbSuccessClose"><?php echo esc_html($btn); ?></button>
      </div>
    </div>
    <script>
    (function(){
      var overlay = document.getElementById('ttbSuccessModal');
      var closeBtn = document.getElementById('ttbSuccessClose');
      if (!overlay) return;

      var spinner = document.getElementById('ttbSendingOverlay');
      if (spinner) spinner.classList.remove('active');

      function closeModal() {
        overlay.classList.add('ttb-modal-overlay--out');
        overlay.addEventListener('animationend', function() {
          overlay.remove();
        }, { once: true });
      }
      closeBtn.addEventListener('click', closeModal);
      overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
      });
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
      });
    })();
    </script>
    <?php
  }
}
?>

<?php wp_footer(); ?>
</body>
</html>