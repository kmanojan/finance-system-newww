<?php

namespace App\Services;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;

/** Draw paginated tables directly: HTML layout builds a costly frame tree for thousands of cells. */
class AuditPayablesPdf
{
    private CPDF $canvas;

    private FontMetrics $metrics;

    private string $font;

    private string $bold;

    private float $y = 34;

    private float $width;

    private float $bottom;

    private string $section = '';

    public function render(array $report): string
    {
        $dompdf = new Dompdf(['fontCache' => sys_get_temp_dir()]);
        $this->canvas = new CPDF('a4', 'landscape', $dompdf);
        $this->metrics = $dompdf->getFontMetrics();
        $this->font = $this->metrics->getFont('DejaVu Sans');
        $this->bold = $this->metrics->getFont('DejaVu Sans', 'bold');
        $this->width = $this->canvas->get_width() - 64;
        $this->bottom = $this->canvas->get_height() - 42;
        $this->y = 34;
        $this->section = '';
        $this->canvas->get_cpdf()->addInfo('Title', 'Auditor payables as at '.$report['asOf']);
        $this->text('Auditor payables', 21, true);
        $this->text('All companies · As at '.$report['asOf'].' · Generated '.$report['generatedAt'], 9);
        $this->y += 12;

        $dom = new \DOMDocument;
        $old = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.view('reports.partials.audit-payables-schedules', ['report' => $report, 'pdf' => true])->render());
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//h2|//h3|//p|//table') as $node) {
            if ($node->nodeName === 'h2') {
                if ($this->section !== '') {
                    $this->newPage();
                }
                $this->section = $this->clean($node->textContent);
                $this->text($this->section, 15, true);
            } elseif ($node->nodeName === 'h3') {
                $this->ensureSpace(70);
                $this->y += 10;
                $this->text($this->clean($node->textContent), 11, true);
            } elseif ($node->nodeName === 'p') {
                $this->text($this->clean($node->textContent), 9);
                $this->y += 6;
            } else {
                $this->table($node, $xpath);
                $this->y += 12;
            }
        }
        $this->canvas->page_text(32, $this->bottom + 18, 'Apptimus Finance · Confidential · As at '.$report['asOf'].' · Page {PAGE_NUM} of {PAGE_COUNT}', $this->font, 8, [.35, .35, .35]);

        return $this->canvas->output();
    }

    private function table(\DOMElement $table, \DOMXPath $xpath): void
    {
        $headers = [];
        foreach ($xpath->query('./thead/tr/th', $table) as $cell) {
            $headers[] = ['text' => $this->clean($cell->textContent), 'number' => str_contains($cell->getAttribute('class'), 'num')];
        }
        $count = count($headers);
        if ($count === 0) {
            return;
        }
        // Currency/date columns stay narrow; people, references and evidence have room to wrap.
        $weights = array_map(fn ($h) => $h['text'] === 'Currency' ? .7 : ($h['number'] ? 1.05 : 1.35), $headers);
        $widths = array_map(fn ($weight) => $this->width * $weight / array_sum($weights), $weights);
        $headerLines = array_map(fn ($h, $i) => $this->wrap($h['text'], $widths[$i] - 12, 8, $this->bold), $headers, array_keys($headers));
        $headerHeight = max(array_map('count', $headerLines)) * 11 + 12;
        $this->ensureSpace($headerHeight + 35);
        $drawHeaders = function () use ($headerLines, $widths, $headers, $headerHeight) {
            $this->rowLines($headerLines, $widths, $headers, 8, true, 11);
            $this->y += $headerHeight;
        };
        $drawHeaders();
        foreach ($xpath->query('./tbody/tr', $table) as $tr) {
            $cells = iterator_to_array($xpath->query('./td', $tr));
            if (count($cells) === 1 && (int) $cells[0]->getAttribute('colspan') > 1) {
                $this->text($this->clean($cells[0]->textContent), 9);

                continue;
            }
            $lines = [];
            foreach ($headers as $i => $header) {
                $cell = $cells[$i] ?? null;
                $text = '';
                if ($cell) {
                    foreach ($cell->childNodes as $child) {
                        $text .= ($child->nodeName === 'small' ? "\n" : '').$child->textContent;
                    }
                }
                $lines[] = $this->wrap($text, $widths[$i] - 12, 8.5, $this->font);
            }
            $rowHeight = max(array_map('count', $lines)) * 12 + 12;
            if ($this->y + $rowHeight > $this->bottom && $rowHeight < $this->bottom - 70 - $headerHeight) {
                $this->newPage(true);
                $drawHeaders();
            }
            // Extremely long source notes can continue onto another page without dropping text.
            while (max(array_map('count', $lines)) > 0) {
                $fit = (int) floor(($this->bottom - $this->y - 12) / 12);
                if ($fit < 1) {
                    $this->newPage(true);
                    $drawHeaders();

                    continue;
                }
                $take = min($fit, max(array_map('count', $lines)));
                $part = array_map(fn ($column) => array_slice($column, 0, $take), $lines);
                $this->rowLines($part, $widths, $headers, 8.5, false, 12);
                $this->y += $take * 12 + 12;
                $lines = array_map(fn ($column) => array_slice($column, $take), $lines);
            }
        }
    }

    private function rowLines(array $lines, array $widths, array $headers, float $size, bool $bold, float $pitch): void
    {
        $x = 32;
        $font = $bold ? $this->bold : $this->font;
        $height = max(array_map('count', $lines)) * $pitch + 12;
        foreach ($lines as $i => $column) {
            foreach ($column as $line => $text) {
                $left = $headers[$i]['number'] ? $x + $widths[$i] - 6 - $this->metrics->getTextWidth($text, $font, $size) : $x + 6;
                $this->canvas->text($left, $this->y + 6 + $line * $pitch, $text, $font, $size, $bold ? [.28, .28, .28] : [.12, .12, .12]);
            }
            $x += $widths[$i];
        }
        $this->canvas->line(32, $this->y + $height, 32 + $this->width, $this->y + $height, [.82, .82, .82], .4);
    }

    private function text(string $text, float $size, bool $bold = false): void
    {
        $font = $bold ? $this->bold : $this->font;
        foreach ($this->wrap($text, $this->width, $size, $font) as $line) {
            $this->ensureSpace($size * 1.6);
            $this->canvas->text(32, $this->y, $line, $font, $size);
            $this->y += $size * 1.6;
        }
    }

    private function wrap(string $text, float $width, float $size, string $font): array
    {
        $result = [];
        foreach (explode("\n", trim($text)) as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if ($line !== '' && $this->metrics->getTextWidth($line.' '.$word, $font, $size) > $width) {
                    $result[] = $line;
                    $line = '';
                }
                // Also split unbroken references/URLs instead of painting over the next column.
                while ($this->metrics->getTextWidth($word, $font, $size) > $width) {
                    $low = 1;
                    $high = mb_strlen($word);
                    while ($low < $high) {
                        $middle = (int) ceil(($low + $high) / 2);
                        if ($this->metrics->getTextWidth(mb_substr($word, 0, $middle), $font, $size) <= $width) {
                            $low = $middle;
                        } else {
                            $high = $middle - 1;
                        }
                    }
                    $length = $low;
                    $result[] = mb_substr($word, 0, $length);
                    $word = mb_substr($word, $length);
                }
                if ($word !== '') {
                    $line .= ($line === '' ? '' : ' ').$word;
                }
            }
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result ?: [''];
    }

    private function newPage(bool $continued = false): void
    {
        $this->canvas->new_page();
        $this->y = 34;
        if ($continued) {
            $this->text($this->section.' · continued', 10, true);
            $this->y += 6;
        }
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y + $height > $this->bottom) {
            $this->newPage(true);
        }
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
