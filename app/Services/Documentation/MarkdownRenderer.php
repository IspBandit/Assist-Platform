<?php

declare(strict_types=1);

namespace App\Services\Documentation;

/**
 * Small, safe renderer for the repository-owned guide subset of Markdown.
 * Raw HTML is always escaped; only headings, paragraphs, lists, links, code
 * and emphasis used by the guide sources are promoted to markup.
 */
final class MarkdownRenderer
{
    public static function render(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $html = [];
        $paragraph = [];
        $list = null;
        $inCode = false;
        $code = [];

        $flushParagraph = static function () use (&$html, &$paragraph): void {
            if ($paragraph === []) {
                return;
            }
            $html[] = '<p>' . self::inline(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        };
        $closeList = static function () use (&$html, &$list): void {
            if ($list === null) {
                return;
            }
            $html[] = '</' . $list . '>';
            $list = null;
        };

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                $flushParagraph();
                $closeList();
                if ($inCode) {
                    $html[] = '<pre><code>' . self::escape(implode("\n", $code)) . '</code></pre>';
                    $code = [];
                    $inCode = false;
                } else {
                    $inCode = true;
                }
                continue;
            }
            if ($inCode) {
                $code[] = $line;
                continue;
            }
            if (trim($line) === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (preg_match('/^(#{1,4})\s+(.+)$/', $line, $match) === 1) {
                $flushParagraph();
                $closeList();
                $level = strlen($match[1]);
                $text = trim($match[2]);
                $id = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '', '-');
                $html[] = sprintf('<h%d id="%s">%s</h%d>', $level, self::escape($id), self::inline($text), $level);
                continue;
            }
            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $match) === 1) {
                $flushParagraph();
                if ($list !== 'ul') {
                    $closeList();
                    $html[] = '<ul>';
                    $list = 'ul';
                }
                $html[] = '<li>' . self::inline(trim($match[1])) . '</li>';
                continue;
            }
            if (preg_match('/^\s*\d+[.)]\s+(.+)$/', $line, $match) === 1) {
                $flushParagraph();
                if ($list !== 'ol') {
                    $closeList();
                    $html[] = '<ol>';
                    $list = 'ol';
                }
                $html[] = '<li>' . self::inline(trim($match[1])) . '</li>';
                continue;
            }
            $paragraph[] = trim($line);
        }

        if ($inCode) {
            $html[] = '<pre><code>' . self::escape(implode("\n", $code)) . '</code></pre>';
        }
        $flushParagraph();
        $closeList();
        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $escaped = self::escape($text);
        $escaped = preg_replace_callback(
            '/\[([^]]+)]\(([^)]+)\)/',
            static function (array $match): string {
                $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!str_starts_with($href, '/') && preg_match('#^https?://#i', $href) !== 1) {
                    return $match[1];
                }
                $external = preg_match('#^https?://#i', $href) === 1 ? ' target="_blank" rel="noopener"' : '';
                return '<a href="' . self::escape($href) . '"' . $external . '>' . $match[1] . '</a>';
            },
            $escaped
        ) ?? $escaped;
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        return preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped) ?? $escaped;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
