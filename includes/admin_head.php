<?php
// Partial: <head> compartido para todas las páginas admin_*.php
// Requiere $pageTitle definido antes del include.
$pageTitle = $pageTitle ?? 'Centrotec Admin';
?>
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="sadmin-csrf" content="<?= htmlspecialchars($_SESSION['sadmin_csrf'] ?? '') ?>">
<meta name="base-path" content="<?= BASE ?>">
<link rel="icon" type="image/x-icon" href="<?= BASE ?>/assets/img/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE ?>/assets/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE ?>/assets/img/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE ?>/assets/img/apple-touch-icon.png">
<link rel="manifest" href="<?= BASE ?>/admin-manifest.php">
<meta name="theme-color" content="#7c3aed">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css?v=<?= filemtime(__DIR__.'/../assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/admin.css?v=<?= filemtime(__DIR__.'/../assets/css/admin.css') ?>">
<?php if (!empty($extra_css)) echo $extra_css; ?>
<script src="<?= BASE ?>/assets/js/sw-register.js?v=<?= filemtime(__DIR__.'/../assets/js/sw-register.js') ?>" defer></script>
</head>
