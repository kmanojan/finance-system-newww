<?php

namespace App\Services;

/** Allow only formatting and safe links when displaying user-authored settlement notes. */
class LoanSettlementNoteService
{
    public static function clean(?string $value): string
    {
        $value = trim($value ?? '');
        if ($value === '' || ! preg_match('/<\/?[a-z][^>]*>/i', $value)) {
            return $value;
        }
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="utf-8" ?><body>'.$value.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $render = function ($node) use (&$render): string {
                if ($node instanceof \DOMText) {
                    return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                if (! $node instanceof \DOMElement) {
                    return '';
                }
                $tag = strtolower($node->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'])) {
                    return '';
                }
                $content = '';
                foreach ($node->childNodes as $child) {
                    $content .= $render($child);
                }
                if ($tag === 'a') {
                    $href = trim($node->getAttribute('href'));
                    if (preg_match('~^(?:https?://|/(?!/))[^\x00-\x20]*$~i', $href)) {
                        return '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" rel="noopener noreferrer">'.$content.'</a>';
                    }

                    return $content;
                }
                if ($tag === 'br') {
                    return '<br>';
                }

                return in_array($tag, ['p', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'figure']) ? '<'.$tag.'>'.$content.'</'.$tag.'>' : $content;
            };
            $body = $dom->getElementsByTagName('body')->item(0);

            return $body ? $render($body) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function display(?string $value): string
    {
        $clean = self::clean($value);

        return preg_match('/<\/?[a-z][^>]*>/i', $clean) ? $clean : nl2br(e($clean));
    }
}
