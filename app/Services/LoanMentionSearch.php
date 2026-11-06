<?php

namespace App\Services;

class LoanMentionSearch
{
    public static function plainDescription(?string $html): string
    {
        $text = preg_replace('~<(?:br\s*/?|/?(?:p|div|li|tr|h[1-6]))\b[^>]*>~i', ' ', $html ?? '');

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function normalize(string $text): string
    {
        // Accept ordinary grouping commas and the non-breaking spaces used in formatted amounts.
        $text = preg_replace('/(?<=\d)[,\x{00a0}\x{202f}](?=\d)/u', '', $text);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)), 'UTF-8');
    }

    public static function matches(array $loan, string $query): bool
    {
        $amount = number_format($loan['principal_amount'], 2, '.', '');
        $fields = [$loan['loan_code'], $loan['lender_name'], $loan['party_name'], $loan['purpose'], $loan['currency'], $loan['status'], (string) $loan['id'], $amount, $loan['currency'].$amount];
        $haystack = implode(' | ', array_map(fn ($field) => self::normalize((string) $field), $fields));
        foreach (preg_split('/\s+/u', self::normalize($query), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            if (! str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }
}
