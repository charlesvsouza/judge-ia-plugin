<?php
if (!defined('ABSPATH')) exit;

/**
 * Remove markdown e caracteres problemáticos
 */
function judgeia_clean_response($text) {

    if (!$text) return '';

    // Remove blocos markdown
    $patterns = [
        '/\*\*(.*?)\*\*/s',      // bold
        '/\*(.*?)\*/s',          // italico
        '/\#\#\#(.*?)\n/s',
        '/\#\#(.*?)\n/s',
        '/\#(.*?)\n/s',
        '/^\s*-\s+/m',
        '/^\s*\*\s+/m',
    ];

    foreach ($patterns as $pattern) {
        $text = preg_replace($pattern, '$1', $text);
    }

    // Remove símbolos isolados
    $text = str_replace(
        ['*', '#', '`', '---', '--'],
        '',
        $text
    );

    // Normaliza espaços
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}