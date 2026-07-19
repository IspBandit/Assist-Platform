<?php

declare(strict_types=1);

namespace App\Platform\Brand;

use InvalidArgumentException;

/**
 * Immutable, validated brand configuration exposed to application code.
 */
final class Brand
{
    /**
     * @param array<string,string> $domains
     * @param array<string,string> $assets
     * @param array<string,string> $theme
     * @param array<string,string> $metadata
     * @param array<string,string> $contact
     * @param array<string,string> $legal
     * @param array<int,array<string,string>> $navigation
     * @param array<int,array<string,string>> $footer
     * @param array<string,bool> $features
     * @param array<string,bool> $modules
     * @param array<string,mixed> $analytics
     * @param array<string,mixed> $search
     */
    private function __construct(
        private readonly string $id,
        private readonly int $databaseId,
        private readonly string $name,
        private readonly string $legalName,
        private readonly string $shortName,
        private readonly string $status,
        private readonly string $url,
        private readonly array $domains,
        private readonly array $assets,
        private readonly array $theme,
        private readonly array $metadata,
        private readonly array $contact,
        private readonly array $legal,
        private readonly array $navigation,
        private readonly array $footer,
        private readonly array $features,
        private readonly array $modules,
        private readonly array $analytics,
        private readonly array $search,
        private readonly string $storageNamespace,
    ) {
    }

    /** @param array<string,mixed> $config */
    public static function fromArray(string $id, array $config): self
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException("Invalid brand ID: {$id}");
        }

        foreach (['database_id', 'name', 'legal_name', 'short_name', 'status', 'url', 'domains', 'assets', 'theme', 'metadata', 'contact', 'legal', 'storage_namespace'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new InvalidArgumentException("Brand {$id} is missing required configuration: {$key}");
            }
        }

        $status = (string) $config['status'];
        if (!in_array($status, ['active', 'private', 'coming_soon', 'disabled'], true)) {
            throw new InvalidArgumentException("Brand {$id} has invalid status: {$status}");
        }
        $databaseId = (int) $config['database_id'];
        if ($databaseId < 1) {
            throw new InvalidArgumentException("Brand {$id} requires a positive database_id");
        }

        $url = rtrim((string) $config['url'], '/');
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Brand {$id} has an invalid URL");
        }

        $domains = self::stringMap($config['domains'], "{$id}.domains");
        if (($domains['primary'] ?? '') === '') {
            throw new InvalidArgumentException("Brand {$id} requires a primary domain");
        }

        $storageNamespace = (string) $config['storage_namespace'];
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $storageNamespace)) {
            throw new InvalidArgumentException("Brand {$id} has an invalid storage namespace");
        }

        return new self(
            $id,
            $databaseId,
            self::requiredString($config, 'name', $id),
            self::requiredString($config, 'legal_name', $id),
            self::requiredString($config, 'short_name', $id),
            $status,
            $url,
            $domains,
            self::stringMap($config['assets'], "{$id}.assets"),
            self::stringMap($config['theme'], "{$id}.theme"),
            self::stringMap($config['metadata'], "{$id}.metadata"),
            self::stringMap($config['contact'], "{$id}.contact"),
            self::stringMap($config['legal'], "{$id}.legal"),
            self::linkList($config['navigation'] ?? [], "{$id}.navigation"),
            self::linkList($config['footer'] ?? [], "{$id}.footer"),
            self::boolMap($config['features'] ?? [], "{$id}.features"),
            self::boolMap($config['modules'] ?? [], "{$id}.modules"),
            is_array($config['analytics'] ?? null) ? $config['analytics'] : [],
            is_array($config['search'] ?? null) ? $config['search'] : [],
            $storageNamespace,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function databaseId(): int
    {
        return $this->databaseId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function shortName(): string
    {
        return $this->shortName;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function primaryDomain(): string
    {
        return $this->domains['primary'];
    }

    /** @return array<int,string> */
    public function domains(): array
    {
        return array_values(array_unique(array_filter($this->domains)));
    }

    /** @return array<string,string> */
    public function assets(): array
    {
        return $this->assets;
    }

    /** @return array<string,string> */
    public function theme(): array
    {
        return $this->theme;
    }

    /** @return array<string,string> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /** @return array<string,string> */
    public function contact(): array
    {
        return $this->contact;
    }

    /** @return array<string,string> */
    public function legal(): array
    {
        return $this->legal;
    }

    /** @return array<int,array<string,string>> */
    public function navigation(): array
    {
        return $this->navigation;
    }

    /** @return array<int,array<string,string>> */
    public function footer(): array
    {
        return $this->footer;
    }

    public function featureEnabled(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    public function moduleEnabled(string $module): bool
    {
        return $this->modules[$module] ?? false;
    }

    /** @return array<string,mixed> */
    public function analytics(): array
    {
        return $this->analytics;
    }

    /** @return array<string,mixed> */
    public function search(): array
    {
        return $this->search;
    }

    public function storageNamespace(): string
    {
        return $this->storageNamespace;
    }

    /** @param array<string,mixed> $config */
    private static function requiredString(array $config, string $key, string $id): string
    {
        $value = trim((string) ($config[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException("Brand {$id} requires a non-empty {$key}");
        }
        return $value;
    }

    /** @return array<string,string> */
    private static function stringMap(mixed $value, string $path): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Brand configuration {$path} must be an array");
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || (!is_string($item) && !is_numeric($item))) {
                throw new InvalidArgumentException("Brand configuration {$path} must contain string values");
            }
            $result[$key] = trim((string) $item);
        }
        return $result;
    }

    /** @return array<string,bool> */
    private static function boolMap(mixed $value, string $path): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Brand configuration {$path} must be an array");
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_bool($item)) {
                throw new InvalidArgumentException("Brand configuration {$path} must contain boolean values");
            }
            $result[$key] = $item;
        }
        return $result;
    }

    /** @return array<int,array<string,string>> */
    private static function linkList(mixed $value, string $path): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Brand configuration {$path} must be an array");
        }

        $result = [];
        foreach ($value as $link) {
            if (!is_array($link) || trim((string) ($link['label'] ?? '')) === '' || trim((string) ($link['path'] ?? '')) === '') {
                throw new InvalidArgumentException("Brand configuration {$path} contains an invalid link");
            }
            $result[] = [
                'label' => trim((string) $link['label']),
                'path' => trim((string) $link['path']),
            ];
        }
        return $result;
    }
}
