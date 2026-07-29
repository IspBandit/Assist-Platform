<?php

declare(strict_types=1);

namespace App\Services\Documentation;

final class DocumentationLinkResolver
{
    /** @return array{guide:string,slug:string}|null */
    public static function forRoute(string $path, string $audience): ?array
    {
        $path = '/' . trim((string) parse_url($path, PHP_URL_PATH), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $matches = [];
        foreach ((new DocumentationRegistry())->articles() as $article) {
            if (!in_array($audience, (array) $article['audiences'], true)) {
                continue;
            }
            foreach ((array) $article['routes'] as $route) {
                $route = '/' . trim((string) parse_url((string) $route, PHP_URL_PATH), '/');
                $route = $route === '/' ? '/' : rtrim($route, '/');
                $pattern = preg_replace('/\\\{[^}]+\\\}/', '[^/]+', preg_quote($route, '#')) ?? preg_quote($route, '#');
                if (preg_match('#^' . $pattern . '$#', $path) === 1 || ($route !== '/' && $route !== '/admin' && str_starts_with($path, $route . '/'))) {
                    $matches[] = ['length' => strlen($route), 'guide' => (string) $article['guide'], 'slug' => (string) $article['slug']];
                }
            }
        }
        usort($matches, static fn (array $a, array $b): int => $b['length'] <=> $a['length']);
        if ($matches !== []) {
            return ['guide' => $matches[0]['guide'], 'slug' => $matches[0]['slug']];
        }
        return match ($audience) {
            'administrator' => ['guide' => 'administrator-guide', 'slug' => 'overview-and-workspaces'],
            'provider' => ['guide' => 'provider-guide', 'slug' => 'profile-services-and-evidence'],
            'customer' => ['guide' => 'customer-guide', 'slug' => 'account-and-garage'],
            default => null,
        };
    }
}
