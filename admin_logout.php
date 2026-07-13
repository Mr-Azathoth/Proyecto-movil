<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_config.php';

session_unset();
session_destroy();
header('Location: '.BASE.'/admin_login.php');
exit;
