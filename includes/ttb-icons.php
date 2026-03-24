<?php
if (!defined('ABSPATH')) exit;

/**
 * ttb-icons.php
 * Iconos SVG inline para las pestañas del portal TicTac.
 * Uso: ttb_icon('nombre')
 */

function ttb_icon($name) {
  $icons = [

    'briefings' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="4" y="2" width="12" height="16" rx="2"/><path d="M7 7h6M7 10h6M7 13h4" stroke-linecap="round"/></svg>',

    'revisiones-dis' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="7" cy="7" r="2.5"/><path d="M2.5 17c0-2.5 2-4.5 4.5-4.5S11.5 14.5 11.5 17" stroke-linecap="round"/><path d="M12.5 3.5l4 4-5 5-4-4 5-5z" stroke-linejoin="round"/><path d="M16.5 5.5l.5-.5a1.4 1.4 0 0 0-2-2l-.5.5" stroke-linecap="round"/></svg>',

    'redes-sociales' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="15" cy="5" r="2"/><circle cx="5" cy="10" r="2"/><circle cx="15" cy="15" r="2"/><path d="M7 9l6-3M7 11l6 3" stroke-linecap="round"/></svg>',

    'revisiones-web' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="2" y="3" width="16" height="11" rx="2"/><path d="M8 18h4M10 14v4" stroke-linecap="round"/><path d="M2 10h16" stroke-linecap="round"/></svg>',

    'clients' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="7.5" cy="6" r="2.5"/><path d="M2 18c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke-linecap="round"/><circle cx="14" cy="6" r="2"/><path d="M14 11c2 .3 4 1.8 4 5" stroke-linecap="round"/></svg>',

    'answers' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 4h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6l-3 3V5a1 1 0 0 1 1-1z" stroke-linejoin="round"/><path d="M7 8h6M7 11h4" stroke-linecap="round"/></svg>',

    'forms' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="3" width="14" height="14" rx="2"/><path d="M7 7h6M7 10h6M7 13h3" stroke-linecap="round"/></svg>',

    'revisions' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="3" width="14" height="14" rx="2"/><path d="M7 10l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>',

    'audit' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="M13.5 13.5l3.5 3.5" stroke-linecap="round"/></svg>',

    'settings' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="10" cy="10" r="2.5"/><path d="M10 3v1.5M10 15.5V17M3 10h1.5M15.5 10H17M5.1 5.1l1.06 1.06M13.84 13.84l1.06 1.06M5.1 14.9l1.06-1.06M13.84 6.16l1.06-1.06" stroke-linecap="round"/></svg>',

    'calendar' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M3 9h14M7 2v3M13 2v3" stroke-linecap="round"/></svg>',

    'content' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="9" y="9" width="8" height="8" rx="1.5"/></svg>',

    'projects' => '<svg class="ttb-tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h2l2-2h4a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke-linejoin="round"/></svg>',

  ];

  return $icons[$name] ?? '';
}