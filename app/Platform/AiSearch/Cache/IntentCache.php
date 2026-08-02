<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Cache;

use App\Core\Database;
use App\Platform\AiSearch\Budget\AiSettings;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Intent\IntentSchemaValidator;
use App\Platform\AiSearch\Intent\TaxonomyRegistry;
use Throwable;

/**
 * MariaDB-backed intent interpretation cache (ADR 0021).
 * Does not cache precise GPS; key uses brand + normalised query + versions.
 */
final class IntentCache
{
    public function buildKey(
        string $brandKey,
        string $normalisedQuery,
        string $locale = 'en-AU',
        ?string $modelVersion = null,
    ): string {
        $payload = implode('|', [
            mb_strtolower(trim($brandKey)),
            mb_strtolower(trim($locale)),
            mb_strtolower(trim($normalisedQuery)),
            TaxonomyRegistry::VERSION,
            IntentRuleEngine::VERSION,
            (string) config('ai_search.intent_schema_version', 'intent_schema_v1'),
            $modelVersion ?? '',
        ]);

        return hash('sha256', $payload);
    }

    public function get(string $cacheKey): ?Intent
    {
        try {
            $row = Database::selectOne(
                'SELECT intent_json, intent_source, id FROM ai_intent_cache
                 WHERE cache_key = ? AND expires_at > NOW() LIMIT 1',
                [$cacheKey]
            );
        } catch (Throwable) {
            return null;
        }

        if ($row === null || !isset($row['intent_json'])) {
            return null;
        }

        $decoded = json_decode((string) $row['intent_json'], true);
        if (!is_array($decoded)) {
            return null;
        }

        $intent = Intent::fromArray($decoded, (string) ($row['intent_source'] ?? 'cache'));
        $validated = IntentSchemaValidator::validate($intent);
        $intent = $validated['intent'];

        // Re-tag as cache hit for analytics even if underlying source was rules/ai.
        $intent = new Intent(
            intentType: $intent->intentType,
            providerCategoryKeys: $intent->providerCategoryKeys,
            stayTypeKeys: $intent->stayTypeKeys,
            facilityTypeKeys: $intent->facilityTypeKeys,
            locationText: $intent->locationText,
            useCurrentLocation: $intent->useCurrentLocation,
            radiusKm: $intent->radiusKm,
            urgency: $intent->urgency,
            adapterKeys: $intent->adapterKeys,
            confidence: $intent->confidence,
            clarificationRequired: $intent->clarificationRequired,
            clarificationReason: $intent->clarificationReason,
            source: 'cache',
        );

        try {
            Database::query(
                'UPDATE ai_intent_cache SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE id = ?',
                [(int) $row['id']]
            );
        } catch (Throwable) {
            // ignore
        }

        return $intent;
    }

    public function put(
        string $cacheKey,
        string $brandKey,
        string $normalisedQuery,
        Intent $intent,
        string $locale = 'en-AU',
        ?string $modelVersion = null,
    ): void {
        if ($intent->intentType === Intent::TYPE_UNKNOWN) {
            return;
        }
        if ($intent->clarificationRequired && $intent->confidence < 0.4) {
            return;
        }

        $ttl = AiSettings::get()['intent_cache_ttl_hours'];
        $source = $intent->source === 'cache' ? 'rules' : $intent->source;

        try {
            Database::query(
                'INSERT INTO ai_intent_cache (
                    cache_key, brand_key, normalised_query, locale,
                    taxonomy_version, rules_version, model_version,
                    intent_json, intent_source, confidence, hit_count,
                    expires_at, created_at, last_hit_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW(), NULL)
                ON DUPLICATE KEY UPDATE
                    intent_json = VALUES(intent_json),
                    intent_source = VALUES(intent_source),
                    confidence = VALUES(confidence),
                    model_version = VALUES(model_version),
                    taxonomy_version = VALUES(taxonomy_version),
                    rules_version = VALUES(rules_version),
                    expires_at = VALUES(expires_at)',
                [
                    $cacheKey,
                    mb_substr($brandKey, 0, 40),
                    mb_substr($normalisedQuery, 0, 500),
                    mb_substr($locale, 0, 16),
                    TaxonomyRegistry::VERSION,
                    IntentRuleEngine::VERSION,
                    $modelVersion,
                    json_encode($intent->toArray(), JSON_THROW_ON_ERROR),
                    mb_substr($source, 0, 20),
                    $intent->confidence,
                    $ttl,
                ]
            );
        } catch (Throwable) {
            // Cache must never break search.
        }
    }
}
