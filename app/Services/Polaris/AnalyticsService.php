<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use Throwable;

/**
 * Privacy-conscious Polaris first-party analytics. Does not store free-text prompts.
 */
final class AnalyticsService
{
    /**
     * @param array<string,scalar|null> $properties
     */
    public static function track(
        string $eventName,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $properties = [],
        string $privacyClass = 'anonymous'
    ): void {
        try {
            if (current_brand()->id() !== 'polaris') {
                return;
            }
            // Strip free-text fields that could contain sensitive content.
            unset($properties['prompt'], $properties['q'], $properties['notes'], $properties['evidence']);
            Database::insert(
                'INSERT INTO polaris_analytics_events
                    (brand_id, event_name, user_id, session_key, entity_type, entity_id, properties_json, privacy_class, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    current_brand()->databaseId(),
                    substr($eventName, 0, 80),
                    $userId,
                    substr(session_id() ?: '', 0, 64) ?: null,
                    $entityType,
                    $entityId,
                    $properties === [] ? null : json_encode($properties, JSON_THROW_ON_ERROR),
                    in_array($privacyClass, ['anonymous', 'authenticated', 'sensitive'], true) ? $privacyClass : 'anonymous',
                ]
            );
        } catch (Throwable) {
            // Analytics must never break product flows.
        }
    }
}
