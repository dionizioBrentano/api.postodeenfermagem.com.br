<?php

namespace App\Services;

/**
 * Sanitiza o HTML editorial dos Procedimentos de Enfermagem antes de
 * persistir no banco.
 *
 * O conteúdo é escrito por administradores no painel e depois servido em
 * rotas PÚBLICAS (sem autenticação), então tratamos a entrada como não
 * confiável: só sobrevivem as tags da allowlist abaixo, sem handlers de
 * evento (onclick, onerror, ...) e sem URIs executáveis (javascript:,
 * data:, vbscript:).
 *
 * Markdown puro atravessa intacto — a sanitização só remove marcação HTML
 * que não esteja na allowlist.
 */
class HtmlSanitizer
{
    /**
     * Tags permitidas no conteúdo rico dos procedimentos.
     *
     * @var array<int, string>
     */
    public const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sub', 'sup',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        'a', 'img', 'figure', 'figcaption', 'span', 'div',
    ];

    /**
     * Remove de um bloco de HTML tudo que não estiver na allowlist.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        // 1. Remove blocos executáveis inteiros (conteúdo incluído), antes
        //    do strip_tags — senão o corpo do <script> viraria texto visível.
        $html = preg_replace(
            '#<\s*(script|style|iframe|object|embed|applet|form|noscript|template)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html
        ) ?? '';

        // 2. Remove tags fora da allowlist, preservando o texto interno.
        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $html = strip_tags($html, $allowed);

        // 3. Remove handlers de evento inline (onclick="...", onerror='...', onload=...).
        $html = preg_replace('#\son[a-z-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';

        // 4. Neutraliza URIs executáveis em href/src/xlink:href.
        $html = preg_replace(
            '#\s(href|src|xlink:href)\s*=\s*(?:"\s*(?:javascript|vbscript|data)\s*:[^"]*"|\'\s*(?:javascript|vbscript|data)\s*:[^\']*\'|\s*(?:javascript|vbscript|data)\s*:[^\s>]*)#i',
            '',
            $html
        ) ?? '';

        // 5. Remove atributos style (vetor de CSS expression / url(javascript:)).
        $html = preg_replace('#\sstyle\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';

        return trim($html);
    }

    /**
     * Versão em texto puro (para resumos e meta tags): remove toda a marcação
     * e normaliza espaços em branco.
     */
    public static function toPlainText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = strip_tags(self::clean($html) ?? '');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
