<?php
// Script de uso único: genera icon-192.png e icon-512.png estáticos
// Ejecutar desde browser: http://localhost/reparo/_gen_icons.php
foreach ([192, 512] as $size) {
    $img  = imagecreatetruecolor($size, $size);
    $bg   = imagecolorallocate($img, 13,  11,  23);
    $fill = imagecolorallocate($img, 124, 58, 237);
    imagefill($img, 0, 0, $bg);
    $pad = (int)($size * 0.08);
    imagefilledellipse($img, $size/2, $size/2, $size - $pad*2, $size - $pad*2, $fill);
    $white = imagecolorallocate($img, 230, 237, 243);
    $font  = 5;
    $cw    = imagefontwidth($font);
    $ch    = imagefontheight($font);
    $scale = max(1, (int)($size * 0.30 / $ch));
    $tx    = (int)(($size - $cw * $scale) / 2);
    $ty    = (int)(($size - $ch * $scale) / 2);
    if ($scale <= 1) {
        imagestring($img, $font, $tx, $ty, 'C', $white);
    } else {
        $tmp = imagecreatetruecolor($cw, $ch);
        imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
        imagestring($tmp, $font, 0, 0, 'C', imagecolorallocate($tmp, 230, 237, 243));
        imagecopyresized($img, $tmp, $tx, $ty, 0, 0, $cw*$scale, $ch*$scale, $cw, $ch);
        imagedestroy($tmp);
    }
    $path = __DIR__ . "/assets/img/icon-{$size}.png";
    imagepng($img, $path);
    imagedestroy($img);
    echo "Generado: $path<br>";
}
echo "Listo. Elimina este archivo.";
