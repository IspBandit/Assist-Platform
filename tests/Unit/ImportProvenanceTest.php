<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ImportProvenance;
use PHPUnit\Framework\TestCase;

final class ImportProvenanceTest extends TestCase
{
    public function testBuildsOpenStreetMapSourceLinks(): void
    {
        self::assertSame('https://www.openstreetmap.org/node/123', ImportProvenance::sourceUrl(['id' => 'osm-n123']));
        self::assertSame('https://www.openstreetmap.org/way/456', ImportProvenance::sourceUrl(['id' => 'osm-w456']));
        self::assertSame('https://www.openstreetmap.org/relation/789', ImportProvenance::sourceUrl(['id' => 'osm-r789']));
    }

    public function testPreservesExplicitEvidenceAndFallsBackToWebsite(): void
    {
        self::assertSame('https://source.example/evidence', ImportProvenance::sourceUrl([
            'id' => 'osm-n123',
            'source_url' => 'https://source.example/evidence',
            'website' => 'https://business.example',
        ]));
        self::assertSame('https://business.example', ImportProvenance::sourceUrl(['website' => 'https://business.example']));
        self::assertNull(ImportProvenance::sourceUrl([]));
    }
}
