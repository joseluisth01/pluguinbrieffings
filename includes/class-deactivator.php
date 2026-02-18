<?php
if (!defined('ABSPATH')) exit;

class TTB_Deactivator {
  public static function deactivate() {
    flush_rewrite_rules();
  }
}
