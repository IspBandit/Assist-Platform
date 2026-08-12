<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Knowledge;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentRuleEngine;

/** Builds the bundled, deterministic Ask question set from the live rule taxonomy. */
final class AskQuestionCatalog
{
    /** @return list<array{question:string,normalized_question:string,intent:Intent,popularity_rank:int}> */
    public function entries(): array
    {
        $templates = [
            '%s near me',
            'find %s near me',
            'please find %s near me',
            'nearest %s',
            'closest %s',
        ];
        $engine = new IntentRuleEngine();
        $entries = [];
        $rank = 0;

        foreach (IntentRuleEngine::questionPatterns() as $pattern) {
            $term = $this->cleanTerm($pattern);
            if ($term === '') {
                continue;
            }
            foreach ($templates as $template) {
                $question = sprintf($template, $term);
                $normalized = self::normalize($question);
                if ($normalized === '' || isset($entries[$normalized])) {
                    continue;
                }
                $intent = $engine->interpret($question);
                if ($intent->intentType === Intent::TYPE_UNKNOWN || $intent->adapterKeys === []) {
                    continue;
                }
                $entries[$normalized] = [
                    'question' => $question,
                    'normalized_question' => $normalized,
                    'intent' => $intent,
                    'popularity_rank' => ++$rank,
                ];
            }
        }

        return array_values($entries);
    }

    public static function normalize(string $question): string
    {
        $text = mb_strtolower(trim($question));
        $text = preg_replace('/[^\pL\pN]+/u', ' ', $text) ?? $text;
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function cleanTerm(string $pattern): string
    {
        $term = mb_strtolower(trim($pattern));
        $term = (string) preg_replace('/\s+(?:near me|nearby|around me|closest|nearest)$/u', '', $term);
        return trim($term);
    }
}
