<?php
require_once __DIR__ . '/includes/config.php';
remember_check();
if (logueado()) { header('Location: '.BASE.'/app.php'); exit; }
header('Location: '.BASE.'/landing.php'); exit;
