<?php
if (!defined('ABSPATH')) exit;

$auth  = new TTB_Auth();
$flash = $auth->consume_flash();

// ¿Hay modal pendiente?
$modal_svc = null;
$lang      = 'es';
if ($auth->is_client()) {
  $modal_svc = TTB_Forms::consume_modal($auth->client_id());
  $lang      = TTB_Forms::get_client_lang($auth->client_id());
}

// Datos del modal por idioma
$modal_data = [
  'es' => [
    'web'    => ['emoji' => '🌐', 'title' => '¡Web en marcha!',         'sub' => 'Briefing recibido y en marcha ⏱️',       'msg' => 'Tu briefing de Web ha llegado a producción. El equipo ya tiene todo lo que necesita para empezar a construir algo increíble.',   'btn' => '¡Perfecto, gracias!'],
    'seo'    => ['emoji' => '🚀', 'title' => '¡Despegamos!',            'sub' => 'Briefing recibido y en marcha ⏱️',       'msg' => 'Tu briefing de SEO ha aterrizado en el equipo. Pronto estarás conquistando las primeras posiciones de Google.',                   'btn' => '¡Perfecto, gracias!'],
    'social' => ['emoji' => '📣', 'title' => '¡Mensaje recibido!',      'sub' => 'Briefing recibido y en marcha ⏱️',       'msg' => 'Tu briefing de Redes ya está en manos del equipo de comunicación. Tus redes sociales van a flipar.',                             'btn' => '¡Perfecto, gracias!'],
    'design' => ['emoji' => '🎨', 'title' => '¡A diseñar se ha dicho!', 'sub' => 'Briefing recibido y en marcha ⏱️',       'msg' => 'Tu briefing de Diseño ha llegado al equipo creativo. Prepárate para enamorarte del resultado.',                                   'btn' => '¡Perfecto, gracias!'],
  ],
  'en' => [
    'web'    => ['emoji' => '🌐', 'title' => 'Website project started!','sub' => 'Briefing received — we\'re on it! ⏱️', 'msg' => 'Your Web briefing has reached the production team. They have everything they need to start building something incredible.',         'btn' => 'Perfect, thank you!'],
    'seo'    => ['emoji' => '🚀', 'title' => 'We have lift-off!',        'sub' => 'Briefing received — we\'re on it! ⏱️', 'msg' => 'Your SEO briefing has landed with the team. You\'ll be climbing to the top of Google search results in no time.',                  'btn' => 'Perfect, thank you!'],
    'social' => ['emoji' => '📣', 'title' => 'Message received!',        'sub' => 'Briefing received — we\'re on it! ⏱️', 'msg' => 'Your Social Media briefing is now with our communications team. Your social channels are about to level up.',                       'btn' => 'Perfect, thank you!'],
    'design' => ['emoji' => '🎨', 'title' => 'Let\'s create!',           'sub' => 'Briefing received — we\'re on it! ⏱️', 'msg' => 'Your Design briefing has reached the creative team. Get ready to fall in love with the result.',                                    'btn' => 'Perfect, thank you!'],
  ],
];

$modal_set = $modal_data[$lang] ?? $modal_data['es'];

// Login page language
$login_title   = $lang === 'en' ? 'Briefing Access' : 'Acceso Briefing';
$login_sub     = $lang === 'en' ? 'Enter your credentials to continue.' : 'Introduce tus credenciales para continuar.';
$logout_label  = $lang === 'en' ? 'Log out' : 'Cerrar sesión';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Briefing — TicTac Comunicación</title>
  <meta name="robots" content="noindex, nofollow, noarchive">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <?php wp_head(); ?>
</head>
<body <?php body_class('ttb-body'); ?>>

<!-- ── MODAL DE CONFIRMACIÓN ── -->
<?php if ($modal_svc && isset($modal_set[$modal_svc])): ?>
<?php $md = $modal_set[$modal_svc]; ?>
<div class="ttb-modal-overlay" id="ttbModal" role="dialog" aria-modal="true" aria-labelledby="ttbModalTitle">
  <div class="ttb-modal">

    <div class="ttb-modal__confetti" aria-hidden="true">
      <?php for ($i = 0; $i < 18; $i++): ?>
        <span class="ttb-confetti-dot"></span>
      <?php endfor; ?>
    </div>

    <div class="ttb-modal__emoji"><?php echo $md['emoji']; ?></div>
    <h2 class="ttb-modal__title" id="ttbModalTitle"><?php echo esc_html($md['title']); ?></h2>
    <p class="ttb-modal__sub"><?php echo esc_html($md['sub']); ?></p>
    <p class="ttb-modal__msg"><?php echo esc_html($md['msg']); ?></p>

    <button class="ttb-btn ttb-modal__close" id="ttbModalClose" autofocus>
      <?php echo esc_html($md['btn']); ?>
    </button>
  </div>
</div>
<?php endif; ?>

<div class="ttb-portal">

  <div class="ttb-top">
    <div class="ttb-top__inner">
      <a class="ttb-brand" href="<?php echo esc_url(home_url('/')); ?>">
        <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="TicTac">
      </a>
      <?php if ($auth->current()): ?>
        <a class="ttb-logout" href="<?php echo esc_url(add_query_arg(['ttb_logout' => 1], home_url('/briefing'))); ?>">
          <?php echo esc_html($logout_label); ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="ttb-main">

    <?php if ($flash && is_array($flash)):
      $cls = ($flash['type'] ?? '') === 'error' ? 'ttb-alert ttb-alert--error' : 'ttb-alert ttb-alert--success';
    ?>
      <div class="<?php echo $cls; ?>"><?php echo esc_html($flash['text'] ?? ''); ?></div>
    <?php endif; ?>

    <?php
    if (!$auth->current()):
      // Detectar idioma desde cookie provisional o mostrar bilingüe en login
      include TTB_PATH . 'templates/login.php';
    elseif ($auth->is_admin()):
      include TTB_PATH . 'templates/admin.php';
    else:
      include TTB_PATH . 'templates/client.php';
    endif;
    ?>

  </div><!-- .ttb-main -->

</div><!-- .ttb-portal -->

<?php if ($modal_svc): ?>
<script>
(function(){
  var overlay = document.getElementById('ttbModal');
  var btn     = document.getElementById('ttbModalClose');
  function closeModal(){
    overlay.classList.add('ttb-modal-overlay--out');
    setTimeout(function(){ overlay.style.display = 'none'; }, 400);
  }
  btn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function(e){
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeModal();
  });
  window.scrollTo({top: 0, behavior: 'smooth'});
})();
</script>
<?php endif; ?>

<?php get_footer(); ?>