<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode([
    'id'               => 'cl.centrotec.app',
    'name'             => 'Centrotec',
    'short_name'       => 'Centrotec',
    'description'      => 'Gestión de reparaciones para tu local técnico. Órdenes de trabajo, clientes, repuestos y estadísticas en un solo lugar.',
    'start_url'        => '/app.php',
    'scope'            => '/',
    'display'          => 'standalone',
    'display_override' => ['standalone', 'minimal-ui', 'browser'],
    'orientation'      => 'portrait',
    'background_color' => '#0d1117',
    'theme_color'      => '#7c3aed',
    'lang'             => 'es',
    'dir'              => 'ltr',
    'categories'       => ['business', 'productivity', 'utilities'],
    'prefer_related_applications' => false,
    'icons'            => [
        ['src' => '/assets/img/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/assets/img/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
    'shortcuts'        => [
        [
            'name'        => 'Nueva orden',
            'short_name'  => 'Nueva orden',
            'description' => 'Crear una nueva orden de trabajo',
            'url'         => '/app.php',
            'icons'       => [['src' => '/assets/img/android-chrome-192x192.png', 'sizes' => '192x192']],
        ],
        [
            'name'        => 'Seguimiento',
            'short_name'  => 'Seguimiento',
            'description' => 'Ver estado de una reparación',
            'url'         => '/seguimiento',
            'icons'       => [['src' => '/assets/img/android-chrome-192x192.png', 'sizes' => '192x192']],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
