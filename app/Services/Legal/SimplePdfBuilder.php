<?php

declare(strict_types=1);

namespace App\Services\Legal;

/**
 * Minimal A4 PDF builder using built-in Helvetica fonts.
 * Produces professional multi-page legal packs without Composer PDF libraries.
 */
final class SimplePdfBuilder
{
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;
    private const MARGIN_L = 54.0;
    private const MARGIN_R = 54.0;
    private const MARGIN_T = 64.0;
    private const MARGIN_B = 64.0;

    /** @var list<string> */
    private array $pages = [];

    /** @var list<string> */
    private array $ops = [];

    private float $y = 0.0;
    private string $headerLeft = '';
    private string $headerRight = '';
    private string $footerCenter = '';
    private bool $pageStarted = false;

    public function setRunningHeader(string $left, string $right = ''): void
    {
        $this->headerLeft = $left;
        $this->headerRight = $right;
    }

    public function setFooterCenter(string $text): void
    {
        $this->footerCenter = $text;
    }

    public function addTitlePage(
        string $eyebrow,
        string $title,
        string $subtitle,
        string $metaBlock,
        string $disclaimer
    ): void {
        $this->startPage(false);
        $this->y = self::PAGE_H - 150.0;
        $this->drawRule(self::MARGIN_L, $this->y + 28, self::contentRight(), $this->y + 28, 1.4);
        $this->drawText(self::MARGIN_L, $this->y, 10.0, $eyebrow, false, 0.30);
        $this->y -= 34.0;
        $this->wrapped(self::MARGIN_L, $title, 22.0, true, 28.0);
        $this->y -= 6.0;
        $this->wrapped(self::MARGIN_L, $subtitle, 12.0, false, 17.0);
        $this->y -= 16.0;
        $this->drawRule(self::MARGIN_L, $this->y, self::contentRight(), $this->y, 0.6);
        $this->y -= 26.0;
        $this->wrapped(self::MARGIN_L, $metaBlock, 10.0, false, 14.0);
        $this->y -= 28.0;
        $this->wrapped(self::MARGIN_L, $disclaimer, 9.0, false, 13.0);
        $this->endPage();
    }

    public function heading(string $text, int $level = 1): void
    {
        if ($level <= 1) {
            $this->ensureSpace(42.0);
            $this->y -= 8.0;
            $this->wrapped(self::MARGIN_L, $text, 14.0, true, 18.0);
            $this->y -= 2.0;
            $this->drawRule(self::MARGIN_L, $this->y, self::contentRight(), $this->y, 0.5);
            $this->y -= 12.0;
            return;
        }
        if ($level === 2) {
            $this->ensureSpace(30.0);
            $this->y -= 6.0;
            $this->wrapped(self::MARGIN_L, $text, 12.0, true, 16.0);
            $this->y -= 6.0;
            return;
        }
        $this->ensureSpace(24.0);
        $this->y -= 4.0;
        $this->wrapped(self::MARGIN_L, $text, 11.0, true, 14.0);
        $this->y -= 4.0;
    }

    public function paragraph(string $text, float $size = 10.0, float $leading = 14.0): void
    {
        $this->ensureSpace($leading * 2);
        $this->wrapped(self::MARGIN_L, $text, $size, false, $leading);
        $this->y -= 8.0;
    }

    public function bullet(string $text): void
    {
        $this->ensureSpace(28.0);
        $this->drawText(self::MARGIN_L, $this->y, 10.0, '-', false);
        $this->wrapped(self::MARGIN_L + 14.0, $text, 10.0, false, 14.0, self::contentWidth() - 14.0);
        $this->y -= 4.0;
    }

    /** @param list<list<string>> $rows */
    public function table(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $colCount = max(1, count($rows[0]));
        $usable = self::contentWidth();
        $colW = $usable / $colCount;

        foreach ($rows as $rIndex => $row) {
            $cells = [];
            $maxLines = 1;
            for ($i = 0; $i < $colCount; $i++) {
                $lines = $this->wrap((string) ($row[$i] ?? ''), 8.5, $colW - 8.0);
                $cells[$i] = $lines;
                $maxLines = max($maxLines, count($lines));
            }
            $rowH = max(16.0, ($maxLines * 11.0) + 8.0);
            $this->ensureSpace($rowH + 4.0);

            $fill = $rIndex === 0 ? 0.90 : ($rIndex % 2 === 1 ? 0.96 : 1.0);
            if ($fill < 1.0) {
                $this->fillRect(self::MARGIN_L, $this->y - $rowH + 10.0, $usable, $rowH, $fill);
            }

            $x = self::MARGIN_L;
            foreach ($cells as $lines) {
                $lineY = $this->y;
                foreach ($lines as $line) {
                    $this->drawText($x + 4.0, $lineY, 8.5, $line, $rIndex === 0);
                    $lineY -= 11.0;
                }
                $x += $colW;
            }
            $this->y -= $rowH;
        }
        $this->y -= 10.0;
    }

    public function spacer(float $points = 12.0): void
    {
        $this->ensureSpace($points);
        $this->y -= $points;
    }

    public function output(): string
    {
        if ($this->pageStarted) {
            $this->endPage();
        }
        if ($this->pages === []) {
            $this->startPage(true);
            $this->endPage();
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $nextId = 5;
        $kids = [];
        foreach ($this->pages as $content) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Contents %d 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>',
                self::PAGE_W,
                self::PAGE_H,
                $contentId
            );
            $kids[] = $pageId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[(int) $id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $maxId = (int) max(array_keys($objects));
        $pdf .= 'xref' . "\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }

    private function startPage(bool $withHeader): void
    {
        if ($this->pageStarted) {
            $this->endPage();
        }
        $this->ops = [];
        $this->pageStarted = true;
        $this->y = self::PAGE_H - self::MARGIN_T;
        if ($withHeader) {
            $this->drawText(self::MARGIN_L, self::PAGE_H - 36.0, 8.0, $this->headerLeft, false, 0.35);
            if ($this->headerRight !== '') {
                $w = $this->textWidth($this->headerRight, 8.0);
                $this->drawText(self::PAGE_W - self::MARGIN_R - $w, self::PAGE_H - 36.0, 8.0, $this->headerRight, false, 0.35);
            }
            $this->drawRule(self::MARGIN_L, self::PAGE_H - 44.0, self::contentRight(), self::PAGE_H - 44.0, 0.4);
        }
    }

    private function endPage(): void
    {
        $pageNo = count($this->pages) + 1;
        $footer = trim($this->footerCenter . '  |  Page ' . $pageNo);
        $this->drawRule(self::MARGIN_L, self::MARGIN_B - 18.0, self::contentRight(), self::MARGIN_B - 18.0, 0.4);
        $w = $this->textWidth($footer, 8.0);
        $this->drawText((self::PAGE_W - $w) / 2.0, self::MARGIN_B - 32.0, 8.0, $footer, false, 0.40);
        $this->pages[] = implode("\n", $this->ops) . "\n";
        $this->ops = [];
        $this->pageStarted = false;
    }

    private function ensureSpace(float $needed): void
    {
        if (!$this->pageStarted) {
            $this->startPage(true);
        }
        if (($this->y - $needed) < self::MARGIN_B) {
            $this->endPage();
            $this->startPage(true);
        }
    }

    private function wrapped(
        float $left,
        string $text,
        float $size,
        bool $bold,
        float $leading,
        ?float $maxWidth = null
    ): void {
        $width = $maxWidth ?? (self::contentRight() - $left);
        foreach ($this->wrap($text, $size, $width) as $line) {
            $this->ensureSpace($leading);
            $this->drawText($left, $this->y, $size, $line, $bold);
            $this->y -= $leading;
        }
    }

    /** @return list<string> */
    private function wrap(string $text, float $size, float $maxWidth): array
    {
        $text = trim(preg_replace("/[ \t]+/u", ' ', str_replace(["\r\n", "\r"], "\n", $text)) ?? $text);
        if ($text === '') {
            return [''];
        }
        $out = [];
        foreach (explode("\n", $text) as $para) {
            $para = trim($para);
            if ($para === '') {
                $out[] = '';
                continue;
            }
            $words = preg_split('/\s+/u', $para) ?: [];
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->textWidth($candidate, $size) <= $maxWidth) {
                    $line = $candidate;
                    continue;
                }
                if ($line !== '') {
                    $out[] = $line;
                }
                if ($this->textWidth($word, $size) > $maxWidth) {
                    $chunk = '';
                    foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
                        $try = $chunk . $ch;
                        if ($chunk !== '' && $this->textWidth($try, $size) > $maxWidth) {
                            $out[] = $chunk;
                            $chunk = $ch;
                        } else {
                            $chunk = $try;
                        }
                    }
                    $line = $chunk;
                } else {
                    $line = $word;
                }
            }
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out === [] ? [''] : $out;
    }

    private function drawText(float $x, float $y, float $size, string $text, bool $bold = false, float $gray = 0.0): void
    {
        if (!$this->pageStarted) {
            $this->startPage(true);
        }
        $font = $bold ? 'F2' : 'F1';
        $this->ops[] = sprintf(
            "q %.3F g BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET Q",
            $gray,
            $font,
            $size,
            $x,
            $y,
            $this->escape($text)
        );
    }

    private function drawRule(float $x1, float $y1, float $x2, float $y2, float $width): void
    {
        if (!$this->pageStarted) {
            $this->startPage(true);
        }
        $this->ops[] = sprintf('q %.2F w %.2F %.2F m %.2F %.2F l S Q', $width, $x1, $y1, $x2, $y2);
    }

    private function fillRect(float $x, float $y, float $w, float $h, float $gray): void
    {
        if (!$this->pageStarted) {
            $this->startPage(true);
        }
        $this->ops[] = sprintf('q %.3F g %.2F %.2F %.2F %.2F re f Q', $gray, $x, $y, $w, $h);
    }

    private function escape(string $text): string
    {
        $map = [
            '•' => '-', '–' => '-', '—' => '-', '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
            '…' => '...', '×' => 'x', '→' => '->', '·' => '|', '™' => '(TM)', '®' => '(R)', '©' => '(C)',
            'é' => 'e', 'á' => 'a', 'ö' => 'o', 'ü' => 'u', 'ñ' => 'n', '‑' => '-', ' ' => ' ',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $text) ?? $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function textWidth(string $text, float $size): float
    {
        return strlen($this->escape($text)) * $size * 0.50;
    }

    private static function contentRight(): float
    {
        return self::PAGE_W - self::MARGIN_R;
    }

    private static function contentWidth(): float
    {
        return self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
    }
}
