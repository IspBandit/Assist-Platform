<?php

declare(strict_types=1);

namespace App\Services\Documentation;

final class DocumentationCatalog
{
    /** @var list<string> */
    private const PUBLIC_GUIDES = ['customer-guide', 'provider-guide', 'release-notes', 'changelog'];

    public function __construct(private readonly DocumentationRegistry $registry = new DocumentationRegistry())
    {
    }

    /** @return list<array<string,mixed>> */
    public function guides(bool $includeOperational): array
    {
        return array_values(array_filter(
            $this->registry->guides(),
            static fn (array $guide): bool => $includeOperational || in_array((string) $guide['slug'], self::PUBLIC_GUIDES, true)
        ));
    }

    /** @return array<string,mixed>|null */
    public function guide(string $slug, bool $includeOperational): ?array
    {
        $guide = $this->registry->guide($slug);
        if ($guide === null || (!$includeOperational && !in_array($slug, self::PUBLIC_GUIDES, true))) {
            return null;
        }
        return $guide + ['articles' => $this->registry->articles($slug)];
    }

    /** @return array<string,mixed>|null */
    public function article(string $guide, string $slug, bool $includeOperational): ?array
    {
        if (!$includeOperational && !in_array($guide, self::PUBLIC_GUIDES, true)) {
            return null;
        }
        $article = $this->registry->article($guide, $slug);
        return $article === null ? null : $article + ['html' => MarkdownRenderer::render((string) $article['markdown'])];
    }

    /** @param array<string,string> $filters @return list<array<string,mixed>> */
    public function search(array $filters, bool $includeOperational): array
    {
        $results = $this->registry->search(
            $filters['q'] ?? '',
            ($filters['audience'] ?? '') !== '' ? $filters['audience'] : null,
            ($filters['brand'] ?? '') !== '' ? $filters['brand'] : null,
            ($filters['module'] ?? '') !== '' ? $filters['module'] : null,
            null,
            ($filters['version'] ?? '') !== '' ? $filters['version'] : null,
        );
        if (($filters['q'] ?? '') === '') {
            $results = array_map(static function (array $article): array {
                $article['excerpt'] = (string) $article['summary'];
                return $article;
            }, $results);
        }
        if (!$includeOperational) {
            $results = array_values(array_filter(
                $results,
                static fn (array $article): bool => in_array((string) $article['guide'], self::PUBLIC_GUIDES, true)
            ));
        }
        return $results;
    }

    /** @return array{audiences:list<string>,brands:list<string>,modules:list<string>,versions:list<string>} */
    public function filterOptions(bool $includeOperational): array
    {
        $options = ['audiences' => [], 'brands' => [], 'modules' => [], 'versions' => []];
        $allowed = array_column($this->guides($includeOperational), 'slug');
        foreach ($this->registry->articles() as $article) {
            if (!in_array((string) $article['guide'], $allowed, true)) {
                continue;
            }
            foreach ((array) $article['audiences'] as $value) {
                $options['audiences'][(string) $value] = true;
            }
            foreach ((array) $article['brands'] as $value) {
                $options['brands'][(string) $value] = true;
            }
            $options['modules'][(string) $article['module']] = true;
            $options['versions'][(string) $article['version_introduced']] = true;
        }
        foreach ($options as $key => $values) {
            $items = array_keys($values);
            natcasesort($items);
            $options[$key] = array_values($items);
        }
        rsort($options['versions'], SORT_NATURAL);
        return $options;
    }

    /** @return list<array<string,mixed>> */
    public function whatsNew(bool $includeOperational): array
    {
        $allowed = array_column($this->guides($includeOperational), 'slug');
        $articles = array_values(array_filter(
            $this->registry->articles(),
            static fn (array $article): bool => in_array((string) $article['guide'], ['release-notes', 'changelog'], true)
                && in_array((string) $article['guide'], $allowed, true)
        ));
        usort($articles, static fn (array $a, array $b): int => [
            (string) $b['version_introduced'], (string) $b['last_updated'],
        ] <=> [
            (string) $a['version_introduced'], (string) $a['last_updated'],
        ]);
        return $articles;
    }
}
