<?php
function sanitize_ticket_html(string $html): string {
    $html = strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><img>');
    $html = preg_replace_callback('/<img[^>]*>/i', function ($m) {
        if (!preg_match('/src=["\']([^"\']+)["\']/', $m[0], $s)) return '';
        $src = $s[1];
        if (strpos($src, '/assets/uploads/tickets/') === false) return '';
        $basename = basename(parse_url($src, PHP_URL_PATH));
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|gif|webp)$/i', $basename)) return '';
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '">';
    }, $html);
    return trim($html);
}
