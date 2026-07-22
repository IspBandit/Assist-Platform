<?php

declare(strict_types=1);

namespace App\Platform\Brand;

use InvalidArgumentException;

final class BrandRegistry
{
    /** @var array<string,string> retired brand ID => current brand ID */
    private const LEGACY_IDS = ['towwise' => 'towsmart'];

    /** @var array<string,Brand> */
    private array $brands = [];

    /** @var array<string,string> normalized host => brand ID */
    private array $hosts = [];

    /** @var array<int,string> database ID => brand ID */
    private array $databaseIds = [];

    /** @param array<string,array<string,mixed>> $config */
    public static function fromArray(array $config): self
    {
        $registry = new self();
        foreach ($config as $id => $brandConfig) {
            if (!is_string($id) || !is_array($brandConfig)) {
                throw new InvalidArgumentException('Brand registry entries must be keyed configuration arrays');
            }
            $registry->register(Brand::fromArray($id, $brandConfig));
        }

        if ($registry->brands === []) {
            throw new InvalidArgumentException('At least one brand must be configured');
        }

        return $registry;
    }

    public function register(Brand $brand): void
    {
        if (isset($this->brands[$brand->id()])) {
            throw new InvalidArgumentException("Duplicate brand ID: {$brand->id()}");
        }
        if (isset($this->databaseIds[$brand->databaseId()])) {
            throw new InvalidArgumentException(
                "Database brand ID {$brand->databaseId()} is assigned to both "
                . "{$this->databaseIds[$brand->databaseId()]} and {$brand->id()}"
            );
        }

        foreach ($brand->domains() as $host) {
            $normalized = self::normalizeHost($host);
            if ($normalized === '') {
                continue;
            }
            if (isset($this->hosts[$normalized])) {
                throw new InvalidArgumentException(
                    "Domain {$normalized} is assigned to both {$this->hosts[$normalized]} and {$brand->id()}"
                );
            }
            $this->hosts[$normalized] = $brand->id();
        }

        $this->brands[$brand->id()] = $brand;
        $this->databaseIds[$brand->databaseId()] = $brand->id();
    }

    public function get(string $id): Brand
    {
        $id = self::LEGACY_IDS[$id] ?? $id;
        if (!isset($this->brands[$id])) {
            throw new InvalidArgumentException("Unknown brand: {$id}");
        }
        return $this->brands[$id];
    }

    public function find(string $id): ?Brand
    {
        $id = self::LEGACY_IDS[$id] ?? $id;
        return $this->brands[$id] ?? null;
    }

    public function forHost(string $host): ?Brand
    {
        $id = $this->hosts[self::normalizeHost($host)] ?? null;
        return $id !== null ? $this->brands[$id] : null;
    }

    /** @return array<string,Brand> */
    public function all(): array
    {
        return $this->brands;
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        // HTTP_HOST may contain a port; bracketed IPv6 is not a configured
        // production brand hostname and is retained without unsafe guessing.
        if (!str_starts_with($host, '[')) {
            $host = explode(':', $host, 2)[0];
        }

        return rtrim($host, '.');
    }
}
