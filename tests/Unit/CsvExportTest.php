<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CsvExport;
use PHPUnit\Framework\TestCase;

final class CsvExportTest extends TestCase
{
    public function testSpreadsheetFormulaValuesAreNeutralised(): void
    {
        $response = CsvExport::download('test.csv', ['Business', 'Value'], [
            ['Example', '=HYPERLINK("https://example.invalid")'],
            ['Another', '+1+1'],
        ]);

        self::assertSame(200, $response->status());
        self::assertStringContainsString("'=HYPERLINK", $response->content());
        self::assertStringContainsString("'+1+1", $response->content());
        self::assertSame('attachment; filename="test.csv"', $response->headers()['Content-Disposition']);
    }
}
