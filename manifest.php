<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$base = BASE;
echo json_encode([
    'name'             => 'Centrotec',
    'short_name'       => 'Centrotec',
    'description'      => 'Gestión de reparaciones para tu local técnico',
    'start_url'        => $base . '/app.php',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#0d1117',
    'theme_color'      => '#7c3aed',
    'lang'             => 'es',
    'icons'            => [
        ['src' => $base . '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => $base . '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
