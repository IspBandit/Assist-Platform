<?php

declare(strict_types=1);

namespace App\Services;

use App\Billing\MembershipCatalogue;

final class MembershipPresentationService
{
    /**
     * @param array<string,mixed> $provider
     * @return array{slug:string,name:string,charging_enabled:bool,summary:string}
     */
    public function forProvider(array $provider, bool $billingEnabled, ?string $planSlug = null): array
    {
        if (!$billingEnabled) {
            return [
                'slug' => MembershipCatalogue::LAUNCH_ACCESS,
                'name' => 'Launch Access',
                'charging_enabled' => false,
                'summary' => 'Full launch access is active at no charge. No payment method is required and no automatic billing can occur.',
            ];
        }

        $slug = $planSlug ?: MembershipCatalogue::FREE_LISTING;
        $definition = MembershipCatalogue::find($slug);

        return [
            'slug' => $definition === null ? MembershipCatalogue::FREE_LISTING : $slug,
            'name' => $definition['public_name'] ?? 'Free Listing',
            'charging_enabled' => true,
            'summary' => 'Membership capabilities are controlled by your current entitlements.',
        ];
    }
}
