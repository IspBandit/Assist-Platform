<?php

declare(strict_types=1);

namespace App\Services\Legal;

use RuntimeException;

/**
 * Renders catalogued legal markdown into a downloadable PDF binary.
 */
final class LegalPdfRenderer
{
    public function __construct(
        private readonly LegalDocumentCatalog $catalog = new LegalDocumentCatalog()
    ) {
    }

    public function render(string $id): string
    {
        $doc = $this->catalog->find($id);
        if ($doc === null) {
            throw new RuntimeException('Unknown legal document: ' . $id);
        }

        $markdown = $this->catalog->markdownFor($id);
        $pdf = new SimplePdfBuilder();
        $pdf->setRunningHeader('Assist Platform Enterprise', (string) $doc['title']);
        $pdf->setFooterCenter('Assist Platform Enterprise · COM-005 · ABN 76 553 821 887');

        $pdf->addTitlePage(
            'ASSIST PLATFORM ENTERPRISE  ·  LEGAL PACK',
            (string) $doc['title'],
            (string) $doc['summary'],
            "Category: {$doc['category']}\nAudience: {$doc['audience']}\nStatus: {$doc['effective_label']}\nOperator: Glen Condren (sole trader), ABN 76 553 821 887\nBrands: VanAssist · TowSmart · TrailerWise\nGoverning law: Queensland, Australia\nGenerated: " . gmdate('Y-m-d H:i') . ' UTC',
            'Published for platform use from 18 September 2026. Formal solicitor review may refine wording later. This document is general information and not legal advice.'
        );

        foreach ($this->parseBlocks($markdown) as $block) {
            match ($block['type']) {
                'h1' => $pdf->heading($block['text'], 1),
                'h2' => $pdf->heading($block['text'], 2),
                'h3' => $pdf->heading($block['text'], 3),
                'p' => $pdf->paragraph($block['text']),
                'li' => $pdf->bullet($block['text']),
                'table' => $pdf->table($block['rows']),
                'hr' => $pdf->spacer(10),
                default => null,
            };
        }

        return $pdf->output();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseBlocks(string $markdown): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown) ?: [];
        $blocks = [];
        $i = 0;
        $n = count($lines);
        $skipTitle = true;

        while ($i < $n) {
            $line = rtrim($lines[$i]);
            $trim = trim($line);

            if ($trim === '') {
                $i++;
                continue;
            }

            if (str_starts_with($trim, '#')) {
                if (preg_match('/^(#{1,3})\s+(.*)$/', $trim, $m) === 1) {
                    $level = strlen($m[1]);
                    $text = $this->inline($m[2]);
                    if ($skipTitle && $level === 1) {
                        $skipTitle = false;
                        $i++;
                        continue;
                    }
                    $skipTitle = false;
                    $blocks[] = ['type' => 'h' . $level, 'text' => $text];
                    $i++;
                    continue;
                }
            }

            if (preg_match('/^---+$/', $trim) === 1) {
                $blocks[] = ['type' => 'hr'];
                $i++;
                continue;
            }

            if (str_starts_with($trim, '|')) {
                $tableLines = [];
                while ($i < $n && str_starts_with(trim($lines[$i]), '|')) {
                    $tableLines[] = trim($lines[$i]);
                    $i++;
                }
                $rows = $this->parseTable($tableLines);
                if ($rows !== []) {
                    $blocks[] = ['type' => 'table', 'rows' => $rows];
                }
                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/', $trim, $m) === 1) {
                $blocks[] = ['type' => 'li', 'text' => $this->inline($m[1])];
                $i++;
                continue;
            }

            // Numbered list as bullet-equivalent.
            if (preg_match('/^\d+\.\s+(.*)$/', $trim, $m) === 1) {
                $blocks[] = ['type' => 'li', 'text' => $this->inline($m[1])];
                $i++;
                continue;
            }

            // Fenced code / checklist-ish lines as paragraphs.
            if (str_starts_with($trim, '```')) {
                $i++;
                $code = [];
                while ($i < $n && !str_starts_with(trim($lines[$i]), '```')) {
                    $code[] = $lines[$i];
                    $i++;
                }
                if ($i < $n) {
                    $i++;
                }
                $blocks[] = ['type' => 'p', 'text' => $this->inline(implode(' ', $code))];
                continue;
            }

            $para = [$trim];
            $i++;
            while ($i < $n) {
                $next = trim($lines[$i]);
                if ($next === '' || str_starts_with($next, '#') || str_starts_with($next, '|')
                    || preg_match('/^[-*]\s+/', $next) === 1 || preg_match('/^\d+\.\s+/', $next) === 1
                    || preg_match('/^---+$/', $next) === 1 || str_starts_with($next, '```')) {
                    break;
                }
                $para[] = $next;
                $i++;
            }
            $blocks[] = ['type' => 'p', 'text' => $this->inline(implode(' ', $para))];
        }

        return $blocks;
    }

    /**
     * @param list<string> $tableLines
     * @return list<list<string>>
     */
    private function parseTable(array $tableLines): array
    {
        $rows = [];
        foreach ($tableLines as $line) {
            if (preg_match('/^\|\s*:?-{3,}/', $line) === 1) {
                continue;
            }
            $parts = array_map(
                static fn (string $c): string => trim($c),
                explode('|', trim($line, " \t|"))
            );
            $cells = [];
            foreach ($parts as $part) {
                $cells[] = $this->inline($part);
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }
        return $rows;
    }

    private function inline(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text) ?? $text;
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '$1', $text) ?? $text;
        $text = preg_replace('/<\/?[^>]+>/', '', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
