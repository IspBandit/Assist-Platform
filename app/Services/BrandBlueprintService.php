<?php

declare(strict_types=1);

namespace App\Services;

use App\Platform\Brand\BrandRegistry;
use InvalidArgumentException;

/**
 * Builds a non-persistent, private brand blueprint for reviewed promotion into
 * configuration and migrations. This service deliberately cannot launch a host.
 */
final class BrandBlueprintService
{
    /** @var array<string,string> */
    public const MODULES = [
        'providers' => 'Provider directory',
        'parks' => 'Stays and parks',
        'towing_tools' => 'Towing tools and calculators',
        'trailer_marketplace' => 'Trailer services and ownership',
        'automotive_directory' => 'Automotive directory',
        'cms' => 'Content management',
        'reviews' => 'Reviews',
        'memberships' => 'Provider memberships',
        'social_studio' => 'Social Studio',
    ];

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function build(array $input, BrandRegistry $registry): array
    {
        $key = strtolower(trim((string) ($input['brand_key'] ?? '')));
        if (!preg_match('/^[a-z][a-z0-9-]{2,39}$/', $key)) {
            throw new InvalidArgumentException('Brand key must be 3–40 lowercase letters, numbers or hyphens and start with a letter.');
        }
        if ($registry->find($key) !== null) {
            throw new InvalidArgumentException('That brand key is already configured.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Brand name is required and must be 120 characters or fewer.');
        }

        $domain = BrandRegistry::normalizeHost((string) ($input['domain'] ?? ''));
        if (!$this->validDomain($domain)) {
            throw new InvalidArgumentException('Enter a valid hostname without a path, for example example.com.au.');
        }
        if ($registry->forHost($domain) !== null) {
            throw new InvalidArgumentException('That hostname is already assigned to a configured brand.');
        }

        $primary = $this->colour($input, 'primary_colour', '#0f6e6e');
        $accent = $this->colour($input, 'accent_colour', '#e56b2f');
        $selected = is_array($input['modules'] ?? null) ? $input['modules'] : [];
        $modules = [];
        foreach (self::MODULES as $module => $label) {
            $modules[$module] = in_array($module, $selected, true);
        }
        if (!in_array(true, $modules, true)) {
            throw new InvalidArgumentException('Select at least one platform module.');
        }

        return [
            'brand_key' => $key,
            'name' => $name,
            'status' => 'private',
            'url' => 'https://' . $domain,
            'domains' => ['primary' => $domain],
            'theme' => ['brand' => $primary, 'accent' => $accent],
            'modules' => $modules,
            'launch_prerequisites' => [
                'Allocate a stable database ID in a forward migration.',
                'Add reviewed typed registry configuration and brand assets.',
                'Configure and verify domain, DNS, TLS and canonical redirects.',
                'Complete legal pages, sender identity and analytics settings.',
                'Pass Architecture, UX, Engineering and Business quality gates.',
            ],
        ];
    }

    private function validDomain(string $domain): bool
    {
        return $domain !== ''
            && strlen($domain) <= 190
            && str_contains($domain, '.')
            && preg_match('/^(?=.{1,190}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domain) === 1;
    }

    /** @param array<string,mixed> $input */
    private function colour(array $input, string $key, string $fallback): string
    {
        $value = strtolower(trim((string) ($input[$key] ?? $fallback)));
        if (preg_match('/^#[0-9a-f]{6}$/', $value) !== 1) {
            throw new InvalidArgumentException('Brand colours must use six-digit hexadecimal values.');
        }
        return $value;
    }
}

