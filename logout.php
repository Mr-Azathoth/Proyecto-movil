<?php
require_once __DIR__.'/includes/config.php';
session_unset();
session_destroy();
// Eliminar cookie de sesión del navegador
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
header('Location: /reparo/index.php');
exit;
