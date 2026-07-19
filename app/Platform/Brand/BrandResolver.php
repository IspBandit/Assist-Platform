<?php

declare(strict_types=1);

namespace App\Platform\Brand;

use App\Core\Request;
use RuntimeException;

final class BrandResolver
{
    public function __construct(
        private readonly BrandRegistry $registry,
        private readonly string $defaultBrand,
        private readonly ?string $explicitBrand = null,
        private readonly string $environment = 'production',
        private readonly bool $allowDevelopmentFallback = false,
        private readonly bool $strictHosts = false,
    ) {
        $this->registry->get($this->defaultBrand);
        if ($this->explicitBrand !== null && $this->explicitBrand !== '') {
            $this->registry->get($this->explicitBrand);
        }
    }

    public function resolve(Request $request): Brand
    {
        if ($this->explicitBrand !== null && $this->explicitBrand !== '') {
            $brand = $this->registry->get($this->explicitBrand);
            if ($this->strictHosts && $this->environment === 'production') {
                $hostBrand = $this->registry->forHost((string) $request->header('Host', ''));
                if ($hostBrand === null || $hostBrand->id() !== $brand->id()) {
                    throw new RuntimeException('The request host does not match the configured deployment brand');
                }
            }
            return $brand;
        }

        $host = (string) $request->header('Host', '');
        $byHost = $this->registry->forHost($host);
        if ($byHost !== null) {
            return $byHost;
        }

        if ($this->allowDevelopmentFallback && $this->environment !== 'production') {
            $requested = trim((string) $request->query('_brand', ''));
            if ($requested !== '') {
                $brand = $this->registry->find($requested);
                if ($brand === null) {
                    throw new RuntimeException("Unknown development brand: {$requested}");
                }
                return $brand;
            }
        }

        if ($this->strictHosts && $this->environment === 'production' && BrandRegistry::normalizeHost($host) !== '') {
            throw new RuntimeException('The request host is not registered to an Assist Platform brand');
        }

        return $this->registry->get($this->defaultBrand);
    }
}
