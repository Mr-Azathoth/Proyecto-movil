<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
$base = rtrim(BASE, '/');
echo json_encode([
    'name'             => 'Centrotec Admin',
    'short_name'       => 'CT Admin',
    'description'      => 'Panel de administración Centrotec',
    'start_url'        => $base . '/admin.php',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#0d1117',
    'theme_color'      => '#7c3aed',
    'lang'             => 'es',
    'icons'            => [
        ['src' => $base . '/assets/img/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => $base . '/assets/img/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
