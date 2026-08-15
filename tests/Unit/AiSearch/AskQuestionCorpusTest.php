<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the committed Ask question corpus (1,000 entries).
 * Intent routing only — no database or paid AI.
 */
final class AskQuestionCorpusTest extends TestCase
{
    private IntentRuleEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new IntentRuleEngine();
    }

    /** @return array{version:string,engine_version:string,count:int,entries:list<array<string,mixed>>} */
    private static function corpus(): array
    {
        static $corpus = null;
        if ($corpus !== null) {
            return $corpus;
        }

        $path = dirname(__DIR__, 2) . '/fixtures/ask-question-corpus.json';
        if (!is_file($path)) {
            throw new \RuntimeException('Missing ask-question-corpus.json — run php tools/generate-ask-question-corpus.php');
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid ask-question-corpus.json');
        }

        /** @var array{version:string,engine_version:string,count:int,entries:list<array<string,mixed>>} $decoded */
        $corpus = $decoded;

        return $corpus;
    }

    public function testCorpusSizeAndEngineVersion(): void
    {
        $corpus = self::corpus();
        self::assertSame(1000, $corpus['count']);
        self::assertCount(1000, $corpus['entries']);
        self::assertSame(IntentRuleEngine::VERSION, $corpus['engine_version']);
    }

    /** @return iterable<string,array{string,array<string,mixed>}> */
    public static function corpusEntries(): iterable
    {
        foreach (self::corpus()['entries'] as $entry) {
            yield (string) $entry['id'] => [(string) $entry['query'], (array) $entry['expect']];
        }
    }

    /** @dataProvider corpusEntries */
    public function testCorpusEntryRoutesDeterministically(string $query, array $expect): void
    {
        $intent = $this->engine->interpret($query);

        self::assertSame((string) $expect['intent_type'], $intent->intentType, $query);
        self::assertSame((string) $expect['location_text'], $intent->locationText, $query);
        if (($expect['allows_clarification'] ?? false) === false) {
            self::assertFalse($intent->clarificationRequired, $query);
        }
        foreach ($expect['adapters'] as $adapter) {
            self::assertContains((string) $adapter, $intent->adapterKeys, $query);
        }
        self::assertNotSame(Intent::TYPE_UNKNOWN, $intent->intentType, $query);
    }
}
