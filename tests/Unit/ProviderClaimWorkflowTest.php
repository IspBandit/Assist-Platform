<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProviderClaimWorkflowTest extends TestCase
{
    public function testInboundClaimsAreStructuredAndRemainSeparateFromVerification(): void
    {
        $migration = (string) file_get_contents(base_path('database/migrations/135_provider_claim_review_and_verification_evidence.sql'));
        $public = (string) file_get_contents(base_path('app/Controllers/Site/PageController.php'));
        $admin = (string) file_get_contents(base_path('app/Controllers/Admin/ProvidersController.php'));

        self::assertStringContainsString("request_type ENUM('interest','claim','correction')", $migration);
        self::assertStringContainsString('authority_evidence', $public);
        self::assertStringContainsString("\$listingProvider !== null ? 'claim' : 'interest'", $public);
        self::assertStringContainsString('sendApprovedClaimInvite', $admin);
        self::assertStringContainsString("'approved_claim_request'", $admin);
        self::assertStringContainsString('provider_brand_listings SET is_verified=1', $admin);
        self::assertStringContainsString('Only an active, claimed provider can be verified.', $admin);
    }

    public function testApprovedInboundClaimUsesTransactionalTemplate(): void
    {
        $service = (string) file_get_contents(base_path('app/Services/ProviderClaimService.php'));
        $templates = require base_path('database/seeds/email_templates.php');
        $byKey = [];
        foreach ($templates as $template) {
            $byKey[(string) $template['template_key']] = $template;
        }

        self::assertStringContainsString("\$approvedInboundRequest ? 'transactional' : 'marketing'", $service);
        self::assertArrayHasKey('provider_claim_request_approved', $byKey);
        self::assertArrayHasKey('provider_claim_evidence_requested', $byKey);
        self::assertArrayHasKey('provider_claim_request_rejected', $byKey);
        self::assertStringNotContainsString('unsubscribe', strtolower((string) $byKey['provider_claim_request_approved']['html_body']));
        self::assertStringContainsString('does not mark', strtolower((string) $byKey['provider_claim_request_approved']['html_body']));
    }
}
