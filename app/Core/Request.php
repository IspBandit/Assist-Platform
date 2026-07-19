<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish wrapper around the current HTTP request.
 */
final class Request
{
    private array $query;
    private array $body;
    private array $server;
    private array $files;
    private array $routeParams = [];

    public function __construct(array $query, array $body, array $server, array $files)
    {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->files = $files;
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES);
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        // Support method spoofing for PUT/PATCH/DELETE from HTML forms.
        if ($method === 'POST' && isset($this->body['_method'])) {
            $spoofed = strtoupper((string) $this->body['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }
        return $method;
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$normalized] ?? $default;
    }

    public function ip(): string
    {
        $remote = $this->remoteIp();
        $trusted = (array) Config::get('security.trusted_proxies', []);
        if (in_array($remote, $trusted, true)) {
            $forwarded = explode(',', (string) $this->header('X-Forwarded-For', ''));
            $client = trim($forwarded[0] ?? '');
            if (filter_var($client, FILTER_VALIDATE_IP) !== false) {
                return $client;
            }
        }

        return $remote;
    }

    public function remoteIp(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public function wantsJson(): bool
    {
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function csrfToken(): ?string
    {
        $token = $this->body['_csrf'] ?? $this->header('X-CSRF-Token');
        return is_string($token) ? $token : null;
    }
}
