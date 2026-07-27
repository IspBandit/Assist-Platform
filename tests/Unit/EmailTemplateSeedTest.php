<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EmailTemplateSeedTest extends TestCase
{
    public function testSharedAccountTemplatesUseRuntimeBrandIdentity(): void
    {
        /** @var array<int,array<string,mixed>> $templates */
        $templates = require dirname(__DIR__, 2) . '/database/seeds/email_templates.php';
        $sharedKeys = [
            'email_verification',
            'password_reset',
            'provider_invitation',
            'provider_application_received',
            'provider_approved',
            'provider_rejected',
        ];

        foreach ($sharedKeys as $key) {
            $template = array_values(array_filter(
                $templates,
                static fn (array $candidate): bool => ($candidate['template_key'] ?? null) === $key
            ))[0] ?? null;

            self::assertNotNull($template, "Missing shared email template {$key}");
            $copy = implode("\n", [
                (string) ($template['subject'] ?? ''),
                (string) ($template['html_body'] ?? ''),
                (string) ($template['text_body'] ?? ''),
            ]);
            self::assertStringContainsString('{{brand_name}}', $copy, "Template {$key} does not inject brand identity");
            self::assertStringNotContainsString('VanAssist', $copy, "Template {$key} contains stale VanAssist copy");
        }
    }

    public function testProviderOutreachTemplatesContainOneClickUnsubscribe(): void
    {
        /** @var array<int,array<string,mixed>> $templates */
        $templates = require dirname(__DIR__, 2) . '/database/seeds/email_templates.php';
        $byKey = [];
        foreach ($templates as $template) {
            $byKey[(string) $template['template_key']] = $template;
        }

        foreach (['provider_claim_invite', 'provider_invitation'] as $key) {
            self::assertArrayHasKey($key, $byKey);
            self::assertStringContainsString('{{unsubscribe_url}}', (string) $byKey[$key]['html_body']);
            self::assertStringContainsString('{{unsubscribe_url}}', (string) $byKey[$key]['text_body']);
        }
    }
}
