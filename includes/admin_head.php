<?php
// Partial: <head> compartido para todas las páginas admin_*.php
// Requiere $pageTitle definido antes del include.
$pageTitle = $pageTitle ?? 'Reparo Admin';
?>
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="sadmin-csrf" content="<?= htmlspecialchars($_SESSION['sadmin_csrf'] ?? '') ?>">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<link rel="stylesheet" href="/reparo/assets/css/style.css">
<link rel="stylesheet" href="/reparo/assets/css/admin.css">
</head>
