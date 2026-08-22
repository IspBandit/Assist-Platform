<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Knowledge;

use App\Core\Database;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Intent\IntentSchemaValidator;
use Throwable;

/** Exact-match lookup for common questions. It contains no user or GPS data. */
final class AskQuestionLibrary
{
    public function find(string $rawQuestion): ?Intent
    {
        try {
            $row = Database::selectOne(
                'SELECT id, intent_json FROM ask_question_library
                 WHERE normalized_question = ? AND rules_version = ? AND is_active = 1 LIMIT 1',
                [AskQuestionCatalog::normalize($rawQuestion), IntentRuleEngine::VERSION]
            );
        } catch (Throwable) {
            return null;
        }
        if ($row === null) {
            return null;
        }

        $decoded = json_decode((string) ($row['intent_json'] ?? ''), true);
        if (!is_array($decoded)) {
            return null;
        }
        $validated = IntentSchemaValidator::validate(Intent::fromArray($decoded, 'question_library'));
        $intent = $validated['intent'];
        if ($intent->intentType === Intent::TYPE_UNKNOWN || $intent->adapterKeys === []) {
            return null;
        }

        try {
            Database::query(
                'UPDATE ask_question_library SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE id = ?',
                [(int) $row['id']]
            );
        } catch (Throwable) {
            // Analytics must never break Ask.
        }
        return $intent;
    }
}
