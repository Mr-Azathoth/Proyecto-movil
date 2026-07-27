<?php
function sanitize_ticket_html(string $html): string {
    $html = strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><img>');
    // Eliminar todos los atributos de las etiquetas permitidas (excepto img, que se reconstruye)
    $html = preg_replace('/<(p|b|strong|i|em|ul|ol|li|br)(?:\s[^>]*)?\s*\/?>/i', '<$1>', $html);
    // Reconstruir <img> desde cero — solo permitir src apuntando a nuestro directorio de uploads
    $html = preg_replace_callback('/<img[^>]*>/i', function ($m) {
        if (!preg_match('/src=["\']([^"\']+)["\']/', $m[0], $s)) return '';
        $src  = $s[1];
        $host = parse_url($src, PHP_URL_HOST) ?: '';
        $path = parse_url($src, PHP_URL_PATH) ?: $src;
        // Si la URL es absoluta, el host debe coincidir con el servidor
        if ($host !== '' && $host !== ($_SERVER['HTTP_HOST'] ?? '')) return '';
        // La ruta debe apuntar a nuestro directorio de uploads
        if (strpos($path, '/assets/uploads/tickets/') !== 0) return '';
        $basename = basename($path);
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|gif|webp)$/i', $basename)) return '';
        // Normalizar a ruta relativa para evitar URLs absolutas en la BD
        return '<img src="' . htmlspecialchars($path, ENT_QUOTES) . '">';
    }, $html);
    return trim($html);
}
