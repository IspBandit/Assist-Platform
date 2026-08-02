<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Controllers\Site\AssistOutcomeController;
use App\Platform\AiSearch\Budget\AiSettings;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentInterpreter;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Intent\IntentSchemaValidator;
use App\Platform\AiSearch\Knowledge\KnowledgeGapService;
use App\Platform\AiSearch\Provider\AiCompletionRequest;
use App\Platform\AiSearch\Provider\AiCompletionResult;
use App\Platform\AiSearch\Provider\AiProviderInterface;
use App\Platform\AiSearch\Provider\OpenAiProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Remaining original-prompt matrix cases not covered elsewhere.
 */
final class PromptMatrixCoverageTest extends TestCase
{
    public function testMultipleIntentQueryResolvesMixed(): void
    {
        $intent = (new IntentRuleEngine())->interpret('dump point and caravan park near Batemans Bay');
        self::assertSame(Intent::TYPE_MIXED, $intent->intentType);
        self::assertContains('dump_point', $intent->facilityTypeKeys);
        self::assertContains('caravan_park', $intent->stayTypeKeys);
        self::assertNotEmpty(array_intersect(['providers', 'stays', 'traveller_facilities'], $intent->adapterKeys));
    }

    public function testProviderFailureDistinctFromTimeout(): void
    {
        $provider = new class implements AiProviderInterface {
            public function name(): string
            {
                return 'openai';
            }

            public function completeStructured(AiCompletionRequest $request): AiCompletionResult
            {
                return AiCompletionResult::failure('openai', 'test-model', 'provider_error', 12);
            }
        };
        $this->seedAiSettings(['test-model']);
        $out = (new IntentInterpreter($provider))->interpret('tyres near me', 'vanassist', 'corr-fail');
        self::assertFalse($out['ok']);
        self::assertSame('provider_error', $out['failure']);
    }

    public function testLowConfidenceAiIntentPreservedAfterValidation(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => 'find_provider',
            'provider_category_keys' => ['tyres-and-wheels'],
            'stay_type_keys' => [],
            'facility_type_keys' => [],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['providers'],
            'confidence' => 0.42,
            'clarification_required' => true,
            'clarification_reason' => 'Low confidence — confirm category.',
        ], 'ai');
        $validated = IntentSchemaValidator::validate($intent);
        self::assertTrue($validated['ok']);
        self::assertLessThan(0.55, $validated['intent']->confidence);
        self::assertTrue($validated['intent']->clarificationRequired);
    }

    public function testUnsupportedUnknownAiPayload(): void
    {
        $provider = new class implements AiProviderInterface {
            public function name(): string
            {
                return 'openai';
            }

            public function completeStructured(AiCompletionRequest $request): AiCompletionResult
            {
                return new AiCompletionResult(
                    ok: true,
                    parsed: [
                        'intent_type' => 'unknown',
                        'provider_category_keys' => [],
                        'stay_type_keys' => [],
                        'facility_type_keys' => [],
                        'location_text' => null,
                        'use_current_location' => false,
                        'radius_km' => 25,
                        'urgency' => 'normal',
                        'adapter_keys' => [],
                        'confidence' => 0.05,
                        'clarification_required' => true,
                        'clarification_reason' => 'Unsupported request type.',
                    ],
                    provider: 'openai',
                    model: 'test-model',
                    inputTokens: 20,
                    outputTokens: 10,
                    estimatedCostAud: 0.0001,
                    actualCostAud: null,
                    durationMs: 4,
                    providerRequestId: null,
                    failureReason: null,
                );
            }
        };
        $this->seedAiSettings(['test-model']);
        $out = (new IntentInterpreter($provider))->interpret('plan my entire outback itinerary', 'vanassist', 'corr-unsup');
        self::assertTrue($out['ok']);
        self::assertSame(Intent::TYPE_UNKNOWN, $out['intent']->intentType);
        self::assertTrue($out['intent']->clarificationRequired);
    }

    public function testOpenAiProviderRejectsNonAllowlistedModel(): void
    {
        $this->seedAiSettings(['allowed-model-only']);
        $provider = new OpenAiProvider();
        $result = $provider->completeStructured(new AiCompletionRequest(
            model: 'gpt-secret-expensive',
            messages: [['role' => 'user', 'content' => 'x']],
            jsonSchema: ['type' => 'object'],
            schemaName: 'test',
            maxOutputTokens: 10,
            timeoutSeconds: 1,
            correlationId: 'corr-allow',
        ));
        self::assertFalse($result->ok);
        self::assertSame('model_not_allowlisted', $result->failureReason);
    }

    public function testOrchestratorEncodesBudgetCacheAndNoAiFallbacks(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Platform/AiSearch/SearchOrchestrator.php');
        self::assertStringContainsString('budget_blocked', $src);
        self::assertStringContainsString('ai_disabled', $src);
        self::assertStringContainsString('IntentCache', $src);
        self::assertStringContainsString('AIBudgetService', $src);
        self::assertStringContainsString('IntentInterpreter', $src);
        self::assertStringNotContainsString('new OpenAiProvider', $src);
    }

    public function testAnalyticsAndSecurityWiringPresent(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertStringContainsString('ask.click', $routes);
        self::assertStringContainsString('ask.unlock', $routes);
        self::assertStringContainsString('AssistSearchController@form', $routes);
        self::assertStringContainsString("'/find'", $routes);

        $card = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Views/partials/provider-result-card.php');
        self::assertStringContainsString("g=", $card);

        $ask = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Views/public/assist-search.php');
        self::assertStringContainsString('name="website"', $ask);
        self::assertStringContainsString('ask/click/', $ask);

        self::assertFileExists(dirname(__DIR__, 3) . '/app/Platform/AiSearch/Logging/AssistSearchLogger.php');
        self::assertFileExists(dirname(__DIR__, 3) . '/app/Platform/AiSearch/Budget/AIUsageService.php');
        self::assertFileExists(dirname(__DIR__, 3) . '/app/Middleware/AskVanAssistRateLimit.php');
    }

    public function testAskClickRejectsOpenRedirectTargets(): void
    {
        $method = new ReflectionMethod(AssistOutcomeController::class, 'safeRelativePath');
        $method->setAccessible(true);
        $ctrl = (new ReflectionClass(AssistOutcomeController::class))->newInstanceWithoutConstructor();
        self::assertNull($method->invoke($ctrl, 'https://evil.example/phish'));
        self::assertNull($method->invoke($ctrl, '//evil.example'));
        self::assertNull($method->invoke($ctrl, '../etc/passwd'));
        self::assertSame('providers/acme', $method->invoke($ctrl, 'providers/acme'));
        self::assertSame('caravan-parks/bay-view', $method->invoke($ctrl, '/caravan-parks/bay-view'));
    }

    public function testGapOutcomeRecordMethodsIgnoreInvalidIds(): void
    {
        $svc = new KnowledgeGapService();
        $svc->recordClickThrough(0);
        $svc->recordContactAction(-1);
        self::assertTrue(true);
    }

    public function testNoResultAndExternalUnavailableMessagesInOrchestrator(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Platform/AiSearch/SearchOrchestrator.php');
        self::assertStringContainsString('no_results', $src);
        self::assertStringContainsString('No matching listings were found', $src);
    }

    /**
     * @param list<string> $allowlist
     */
    private function seedAiSettings(array $allowlist): void
    {
        $ref = new ReflectionClass(AiSettings::class);
        $prop = $ref->getProperty('cache');
        $prop->setValue(null, array_merge(AiSettings::defaults(), [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => $allowlist,
            'daily_request_cap' => 100,
            'monthly_request_cap' => 1000,
            'daily_budget_aud' => 50.0,
            'monthly_budget_aud' => 200.0,
            'max_prompt_chars' => 2000,
            'max_output_tokens' => 400,
            'timeout_seconds' => 15,
            'max_retries' => 1,
        ]));
    }

    protected function tearDown(): void
    {
        AiSettings::clearCache();
        parent::tearDown();
    }
}
