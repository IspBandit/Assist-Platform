<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Knowledge\AskQuestionCatalog;
use RuntimeException;

final class AskQuestionLibrarySeeder
{
    public const MINIMUM_QUESTIONS = 1000;

    public function seed(): int
    {
        $entries = (new AskQuestionCatalog())->entries();
        if (count($entries) < self::MINIMUM_QUESTIONS) {
            throw new RuntimeException('Ask question catalogue generated fewer than 1,000 valid questions.');
        }

        foreach (array_chunk($entries, 100) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $entry) {
                $values[] = '(?, ?, ?, ?, ?, \'bundled\', ?, 1, NOW(), NOW())';
                array_push(
                    $params,
                    $entry['question'],
                    $entry['normalized_question'],
                    json_encode($entry['intent']->toArray(), JSON_THROW_ON_ERROR),
                    $entry['intent']->intentType,
                    IntentRuleEngine::VERSION,
                    $entry['popularity_rank']
                );
            }
            Database::query(
                'INSERT INTO ask_question_library
                 (question, normalized_question, intent_json, intent_type, rules_version, source,
                  popularity_rank, is_active, created_at, updated_at) VALUES ' . implode(', ', $values) . '
                 ON DUPLICATE KEY UPDATE question = VALUES(question), intent_json = VALUES(intent_json),
                  intent_type = VALUES(intent_type), rules_version = VALUES(rules_version),
                  source = VALUES(source), popularity_rank = VALUES(popularity_rank), is_active = 1,
                  updated_at = NOW()',
                $params
            );
        }
        Database::query(
            "UPDATE ask_question_library SET is_active = 0
             WHERE source = 'bundled' AND rules_version <> ?",
            [IntentRuleEngine::VERSION]
        );
        return count($entries);
    }
}
