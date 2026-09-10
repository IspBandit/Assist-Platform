<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Knowledge\AskQuestionCatalog;
use PHPUnit\Framework\TestCase;

final class AskQuestionCatalogTest extends TestCase
{
    public function testBundledCatalogueContainsAtLeastOneThousandUniqueUsefulQuestions(): void
    {
        $entries = (new AskQuestionCatalog())->entries();
        self::assertGreaterThanOrEqual(1000, count($entries));
        self::assertCount(count($entries), array_unique(array_column($entries, 'normalized_question')));
        foreach ($entries as $entry) {
            self::assertNotSame(Intent::TYPE_UNKNOWN, $entry['intent']->intentType, $entry['question']);
            self::assertTrue($entry['intent']->useCurrentLocation, $entry['question']);
            self::assertNull($entry['intent']->locationText, $entry['question']);
            self::assertNotSame([], $entry['intent']->adapterKeys, $entry['question']);
        }
    }

    public function testQuestionKeyPreservesGpsWording(): void
    {
        self::assertNotSame(
            AskQuestionCatalog::normalize('dump point'),
            AskQuestionCatalog::normalize('dump point near me')
        );
    }
}
