<?php
// Copia este archivo como config.php y completa los valores reales
// NUNCA subas config.php al repositorio (está en .gitignore)

// ── Base de datos ─────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'reparo_db');
define('DB_USER',    'root');          // En prod: usuario con permisos mínimos
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── App ───────────────────────────────────────────────────────
define('APP_URL',   'http://localhost/reparo');
define('EMPRESA_ID', 1);              // ID de la empresa para instalaciones single-tenant

define('VALID_STATUS', ['Ingresado', 'En Reparacion', 'Reparado', 'Entregado', 'Garantia']);

// ── Sesión segura (config.php completo tiene la lógica de sesión) ─
// No modificar este bloque — se activa automáticamente
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
// En producción con HTTPS descomentar:
// ini_set('session.cookie_secure', '1');

session_start();

// ── Headers de seguridad HTTP ─────────────────────────────────
// (se declaran aquí para que apliquen a todas las páginas)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");

// ── Mercado Pago (pasarela de pago CL) ────────────────────────
// Obtener en: https://www.mercadopago.cl/developers/panel
define('MP_ACCESS_TOKEN', 'TEST-xxxx-xxxx-xxxx');   // Access token (privado)
define('MP_PUBLIC_KEY',   'TEST-xxxx-xxxx-xxxx');   // Public key
define('MP_ENV',          'sandbox');               // 'sandbox' | 'production'

// Planes de suscripción (IDs de preapproval_plan creados en MP)
define('MP_PLANES', [
    '1mes'    => ['id' => 'PLAN_ID_AQUI', 'nombre' => '1 mes',    'meses' => 1,  'precio' => 4990],
    '3meses'  => ['id' => 'PLAN_ID_AQUI', 'nombre' => '3 meses',  'meses' => 3,  'precio' => 13990],
    '6meses'  => ['id' => 'PLAN_ID_AQUI', 'nombre' => '6 meses',  'meses' => 6,  'precio' => 25990],
    '12meses' => ['id' => 'PLAN_ID_AQUI', 'nombre' => '12 meses', 'meses' => 12, 'precio' => 49990],
]);

// ── Webpay Plus (Transbank) ───────────────────────────────────
// Credenciales de integración públicas para pruebas (no cambiar para sandbox)
// Para producción: https://www.transbankdevelopers.cl/
define('WP_COMMERCE_CODE', '597055555532');
define('WP_API_KEY',       'API_KEY_DE_PRODUCCION_AQUI');
define('WP_ENV',           'integration');          // 'integration' | 'production'
