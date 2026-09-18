<?php

declare(strict_types=1);

/**
 * CLI equivalent of Admin → Maintenance → Populate Pages & Blocks.
 * Safe for production after a release that includes updated legal_pages.php.
 *
 * Usage (inside the application container):
 *   php scripts/seed-cms-content.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Services\AuditLog;
use Throwable;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$seedPath = BASE_PATH . '/database/seeds/content.php';
$legalPath = BASE_PATH . '/database/seeds/legal_pages.php';
if (!is_file($seedPath)) {
    fwrite(STDERR, "Missing content seed: {$seedPath}\n");
    exit(1);
}

/** @var array<string,mixed> $content */
$content = require $seedPath;
if (!is_array($content)) {
    fwrite(STDERR, "Content seed did not return an array.\n");
    exit(1);
}

if (is_file($legalPath)) {
    /** @var array<string,mixed> $legalPages */
    $legalPages = require $legalPath;
    if (is_array($legalPages) && isset($content['pages']) && is_array($content['pages'])) {
        foreach ($content['pages'] as $index => $page) {
            $key = (string) ($page['page_key'] ?? '');
            if ($key !== '' && isset($legalPages[$key]) && is_array($legalPages[$key])) {
                $content['pages'][$index] = $legalPages[$key];
            }
        }
        $existingKeys = [];
        foreach ($content['pages'] as $page) {
            $existingKeys[(string) ($page['page_key'] ?? '')] = true;
        }
        foreach ($legalPages as $key => $page) {
            if (!isset($existingKeys[(string) $key]) && is_array($page)) {
                $content['pages'][] = $page;
            }
        }
    }
}

$pagesWritten = 0;
$pagesVerified = 0;
$errors = [];

try {
    foreach (($content['pages'] ?? []) as $p) {
        $pageKey = (string) ($p['page_key'] ?? '');
        $slug = (string) ($p['slug'] ?? '');
        $title = (string) ($p['title'] ?? '');
        $body = (string) ($p['body'] ?? '');
        if ($pageKey === '' || $slug === '' || $title === '' || trim($body) === '') {
            $errors[] = "skip invalid page {$pageKey}";
            continue;
        }
        $seedLen = strlen($body);
        Database::query('DELETE FROM content_pages WHERE page_key = ? OR slug = ?', [$pageKey, $slug]);
        Database::query(
            'INSERT INTO content_pages (page_key, title, slug, body, is_published, is_system, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())',
            [$pageKey, $title, $slug, $body]
        );
        $pagesWritten++;
        $dbLen = (int) Database::scalar(
            'SELECT CHAR_LENGTH(COALESCE(body, "")) FROM content_pages WHERE page_key = ? LIMIT 1',
            [$pageKey]
        );
        if ($dbLen >= (int) max(50, $seedLen * 0.8)) {
            $pagesVerified++;
            $marker = (str_contains($body, 'Effective 18 September 2026') ? ' [legal-2026-09-18]' : '');
            echo "OK  {$pageKey} /{$slug} ({$dbLen} chars){$marker}\n";
        } else {
            $errors[] = "{$pageKey}: body length mismatch seed={$seedLen} db={$dbLen}";
        }
    }

    AuditLog::record(
        'maintenance.seed_content_cli',
        'content_pages',
        null,
        null,
        json_encode(['pages_written' => $pagesWritten, 'pages_verified' => $pagesVerified], JSON_UNESCAPED_SLASHES) ?: null
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Done. pages_written={$pagesWritten} pages_verified={$pagesVerified}\n";
if ($errors !== []) {
    fwrite(STDERR, "Warnings:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
exit(0);
