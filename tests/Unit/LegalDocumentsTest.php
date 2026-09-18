<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Legal\LegalDocumentCatalog;
use App\Services\Legal\LegalPdfRenderer;
use App\Services\Legal\SimplePdfBuilder;
use PHPUnit\Framework\TestCase;

final class LegalDocumentsTest extends TestCase
{
    public function testCatalogListsSixDocumentsWhenDraftsExist(): void
    {
        $catalog = new LegalDocumentCatalog();
        $all = $catalog->all();
        self::assertCount(6, $all);
        self::assertSame('terms-of-use', $all[0]['id']);
        self::assertSame('ip-assignment', $all[5]['id']);
        self::assertNotNull($catalog->find('privacy-policy'));
        self::assertNull($catalog->find('missing-doc'));
    }

    public function testMarkdownStripsDraftHonestyNotes(): void
    {
        $markdown = (new LegalDocumentCatalog())->markdownFor('terms-of-use');
        self::assertStringNotContainsString('Draft honesty notes', $markdown);
        self::assertStringContainsString('Who we are and what the Platform is', $markdown);
        self::assertStringContainsString('Effective 18 September 2026', $markdown);
    }

    public function testSimplePdfBuilderProducesValidPdfHeaderAndEof(): void
    {
        $pdf = new SimplePdfBuilder();
        $pdf->setRunningHeader('Assist Platform', 'Test');
        $pdf->setFooterCenter('Test footer');
        $pdf->addTitlePage('EYEBROW', 'Title', 'Subtitle', "Meta line 1\nMeta line 2", 'Disclaimer text.');
        $pdf->heading('Section one', 1);
        $pdf->paragraph('Body paragraph with enough text to wrap across multiple lines in the A4 content column for layout coverage.');
        $pdf->bullet('First bullet');
        $pdf->table([
            ['Column A', 'Column B'],
            ['Value 1', 'Value 2'],
        ]);
        $binary = $pdf->output();
        self::assertStringStartsWith('%PDF-1.4', $binary);
        self::assertStringContainsString('%%EOF', $binary);
        self::assertGreaterThan(500, strlen($binary));
    }

    public function testRendererBuildsPdfForEachCatalogDocument(): void
    {
        $catalog = new LegalDocumentCatalog();
        $renderer = new LegalPdfRenderer($catalog);
        foreach ($catalog->all() as $doc) {
            $binary = $renderer->render((string) $doc['id']);
            self::assertStringStartsWith('%PDF-1.4', $binary, $doc['id']);
            self::assertStringContainsString('%%EOF', $binary, $doc['id']);
            self::assertGreaterThan(2000, strlen($binary), $doc['id']);
        }
    }
}
