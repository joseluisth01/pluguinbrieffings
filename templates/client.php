<?php
if (!defined('ABSPATH')) exit;
$auth = new TTB_Auth();
TTB_Client_UI::render($auth->client_id());
