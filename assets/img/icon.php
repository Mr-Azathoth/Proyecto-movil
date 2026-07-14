<?php
// Genera el ícono de la PWA en memoria con GD — sin archivos PNG estáticos necesarios
$size = in_array((int)($_GET['s'] ?? 192), [192, 512]) ? (int)$_GET['s'] : 192;

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$img = imagecreatetruecolor($size, $size);

// Fondo redondeado — fondo morado oscuro de Centrotec
$bg   = imagecolorallocate($img, 13,  11,  23);   // #0d0b17
$fill = imagecolorallocate($img, 124, 58, 237);   // #7c3aed

// Fondo liso
imagefill($img, 0, 0, $bg);

// Círculo de fondo
$pad = (int)($size * 0.08);
imagefilledellipse($img, $size / 2, $size / 2, $size - $pad * 2, $size - $pad * 2, $fill);

// Letra "C" centrada usando fuente built-in (proporcional al tamaño)
$white = imagecolorallocate($img, 230, 237, 243); // #e6edf3
$font  = 5; // fuente interna más grande (1-5)
$cw    = imagefontwidth($font);
$ch    = imagefontheight($font);
$scale = (int)($size * 0.30 / $ch);
$scale = max(1, $scale);

// Texto escalado con imagestring (sin TTF)
// Para tamaños grandes usamos múltiples caracteres como bloque
$letter = 'C';
$tx = (int)(($size - $cw * $scale) / 2);
$ty = (int)(($size - $ch * $scale) / 2);

// Escalar manualmente píxel a píxel si scale > 1
if ($scale <= 1) {
    imagestring($img, $font, $tx, $ty, $letter, $white);
} else {
    $tmp = imagecreatetruecolor($cw, $ch);
    $tbg = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
    imagefill($tmp, 0, 0, $tbg);
    $tw = imagecolorallocate($tmp, 230, 237, 243);
    imagestring($tmp, $font, 0, 0, $letter, $tw);
    imagecopyresized($img, $tmp, $tx, $ty, 0, 0, $cw * $scale, $ch * $scale, $cw, $ch);
    imagedestroy($tmp);
}

imagepng($img);
imagedestroy($img);
