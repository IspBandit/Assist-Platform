<?php

declare(strict_types=1);

namespace App\Services\Documentation;

use RuntimeException;

/**
 * Read-only, repository-backed documentation catalogue.
 *
 * Metadata is kept separately from Markdown so controllers can filter and
 * build navigation without parsing prose. Only files declared in the trusted
 * registry can be read.
 */
final class DocumentationRegistry
{
    private const REQUIRED_METADATA = [
        'audiences', 'brands', 'module', 'version_introduced', 'last_updated',
        'owner', 'permissions', 'routes', 'related', 'source_files',
    ];

    private const REQUIRED_SECTIONS = [
        'Purpose', 'Intended users', 'Permissions', 'Fields', 'Actions',
        'Workflows', 'Examples', 'Common mistakes', 'Related pages', 'FAQ',
        'Version introduced', 'Last updated', 'Owner',
    ];

    private string $root;

    /** @var array{guides:array<int,array<string,mixed>>,articles:array<int,array<string,mixed>>}|null */
    private ?array $registry = null;

    public function __construct(?string $repositoryRoot = null)
    {
        $this->root = rtrim($repositoryRoot ?? dirname(__DIR__, 3), '/\\');
    }

    /** @return array<int,array<string,mixed>> */
    public function guides(): array
    {
        $guides = $this->load()['guides'];
        usort($guides, static fn (array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));
        foreach ($guides as &$guide) {
            $guide['article_count'] = count($this->articles((string) $guide['slug']));
        }
        unset($guide);
        return $guides;
    }

    /** @return array<string,mixed>|null */
    public function guide(string $slug): ?array
    {
        foreach ($this->guides() as $guide) {
            if ($guide['slug'] === $slug) {
                return $guide;
            }
        }
        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public function articles(?string $guideSlug = null): array
    {
        $articles = array_values(array_filter(
            $this->load()['articles'],
            static fn (array $article): bool => $guideSlug === null || $article['guide'] === $guideSlug
        ));
        usort($articles, static fn (array $a, array $b): int => [
            (int) $a['order'], (string) $a['title'],
        ] <=> [
            (int) $b['order'], (string) $b['title'],
        ]);
        return $articles;
    }

    /** @return array<string,mixed>|null */
    public function article(string $guideSlug, string $articleSlug): ?array
    {
        foreach ($this->articles($guideSlug) as $metadata) {
            if ($metadata['slug'] !== $articleSlug) {
                continue;
            }
            $markdown = $this->readDeclaredFile((string) $metadata['file']);
            return $metadata + [
                'markdown' => $markdown,
                'plain_text' => self::plainText($markdown),
                'excerpt' => self::excerpt($markdown),
            ];
        }
        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search(
        string $query = '',
        ?string $audience = null,
        ?string $brand = null,
        ?string $module = null,
        ?string $permission = null,
        ?string $version = null,
    ): array {
        $needle = mb_strtolower(trim($query));
        $results = [];
        foreach ($this->articles() as $metadata) {
            if ($audience !== null && !in_array($audience, $metadata['audiences'], true)) {
                continue;
            }
            if ($brand !== null && !in_array('all', $metadata['brands'], true) && !in_array($brand, $metadata['brands'], true)) {
                continue;
            }
            if ($module !== null && $metadata['module'] !== $module) {
                continue;
            }
            if ($permission !== null && !in_array($permission, $metadata['permissions'], true)) {
                continue;
            }
            if ($version !== null && $metadata['version_introduced'] !== $version) {
                continue;
            }
            $markdown = $this->readDeclaredFile((string) $metadata['file']);
            $plainText = self::plainText($markdown);
            $haystack = mb_strtolower(implode(' ', [
                (string) $metadata['title'], (string) $metadata['summary'], $plainText,
            ]));
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }
            $results[] = $metadata + [
                'plain_text' => $plainText,
                'excerpt' => self::excerpt($markdown, $needle),
            ];
        }
        return $results;
    }

    /** @return array<int,string> */
    public function validate(): array
    {
        $errors = [];
        $registry = $this->load();
        $guideSlugs = [];
        foreach ($registry['guides'] as $guide) {
            $slug = (string) ($guide['slug'] ?? '');
            if ($slug === '' || isset($guideSlugs[$slug])) {
                $errors[] = "Guide slug is missing or duplicated: {$slug}";
            }
            $guideSlugs[$slug] = true;
        }

        $articleIds = [];
        foreach ($registry['articles'] as $article) {
            $id = (string) ($article['id'] ?? '');
            if ($id === '' || isset($articleIds[$id])) {
                $errors[] = "Article id is missing or duplicated: {$id}";
            }
            $articleIds[$id] = true;
            if (!isset($guideSlugs[(string) ($article['guide'] ?? '')])) {
                $errors[] = "Article {$id} references an unknown guide.";
            }
            foreach (self::REQUIRED_METADATA as $field) {
                if (!array_key_exists($field, $article)) {
                    $errors[] = "Article {$id} is missing metadata: {$field}.";
                }
            }
            foreach (['audiences', 'brands', 'permissions', 'routes', 'related', 'source_files'] as $field) {
                if (isset($article[$field]) && !is_array($article[$field])) {
                    $errors[] = "Article {$id} metadata {$field} must be a list.";
                }
            }
            try {
                $markdown = $this->readDeclaredFile((string) ($article['file'] ?? ''));
                foreach (self::REQUIRED_SECTIONS as $section) {
                    if (preg_match('/^## ' . preg_quote($section, '/') . '\s*$/mi', $markdown) !== 1) {
                        $errors[] = "Article {$id} is missing section: {$section}.";
                    }
                }
            } catch (RuntimeException $error) {
                $errors[] = $error->getMessage();
            }
            foreach ((array) ($article['source_files'] ?? []) as $source) {
                if (!$this->isReadableRepositoryFile((string) $source)) {
                    $errors[] = "Article {$id} source file does not exist: {$source}.";
                }
            }
            foreach ((array) ($article['routes'] ?? []) as $route) {
                if (!$this->routeExists((string) $route)) {
                    $errors[] = "Article {$id} route is not declared: {$route}.";
                }
            }
        }
        foreach ($registry['articles'] as $article) {
            foreach ((array) ($article['related'] ?? []) as $related) {
                if (!isset($articleIds[(string) $related])) {
                    $errors[] = "Article {$article['id']} has unknown related article: {$related}.";
                }
            }
        }
        return $errors;
    }

    public static function plainText(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/!\[[^]]*]\([^)]*\)/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^]]+)]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
        $text = preg_replace('/[>*_`~|#-]+/', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    public static function excerpt(string $markdown, string $needle = '', int $length = 220): string
    {
        $plain = self::plainText($markdown);
        $start = 0;
        if ($needle !== '') {
            $position = mb_stripos($plain, $needle);
            if ($position !== false) {
                $start = max(0, $position - 70);
            }
        }
        $excerpt = mb_substr($plain, $start, max(80, min(500, $length)));
        return ($start > 0 ? '…' : '') . $excerpt . (mb_strlen($plain) > $start + mb_strlen($excerpt) ? '…' : '');
    }

    /** @return array{guides:array<int,array<string,mixed>>,articles:array<int,array<string,mixed>>} */
    private function load(): array
    {
        if ($this->registry !== null) {
            return $this->registry;
        }
        $file = $this->root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'user-guide' . DIRECTORY_SEPARATOR . 'registry.php';
        if (!is_file($file)) {
            throw new RuntimeException('Documentation registry is missing.');
        }
        $registry = require $file;
        if (!is_array($registry) || !isset($registry['guides'], $registry['articles']) || !is_array($registry['guides']) || !is_array($registry['articles'])) {
            throw new RuntimeException('Documentation registry has an invalid structure.');
        }
        return $this->registry = $registry;
    }

    private function readDeclaredFile(string $relative): string
    {
        $prefix = 'docs/user-guide/';
        if (!str_starts_with(str_replace('\\', '/', $relative), $prefix) || str_contains($relative, '..')) {
            throw new RuntimeException("Documentation file is outside the trusted directory: {$relative}.");
        }
        $file = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException("Documentation file does not exist: {$relative}.");
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Documentation file cannot be read: {$relative}.");
        }
        return $contents;
    }

    private function isReadableRepositoryFile(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, '..') || preg_match('#^[A-Za-z]:|^[/\\\\]#', $relative) === 1) {
            return false;
        }
        return is_file($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    }

    private function routeExists(string $route): bool
    {
        $path = parse_url($route, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }
        $routeFile = 'routes/web.php';
        $relative = $path;
        foreach (['/admin' => 'routes/admin.php', '/account' => 'routes/account.php', '/provider' => 'routes/provider.php'] as $prefix => $file) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $routeFile = $file;
                $relative = substr($path, strlen($prefix));
                break;
            }
        }
        $source = file_get_contents($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $routeFile));
        return is_string($source)
            && (str_contains($source, "->get('{$relative}'") || str_contains($source, "->post('{$relative}'"));
    }
}
