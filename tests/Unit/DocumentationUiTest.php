<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Documentation\DocumentationCatalog;
use App\Services\Documentation\DocumentationLinkResolver;
use App\Services\Documentation\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class DocumentationUiTest extends TestCase
{
    public function testPublicCatalogueDoesNotExposeOperationalGuides(): void
    {
        $catalog = new DocumentationCatalog();
        $slugs = array_column($catalog->guides(false), 'slug');

        self::assertContains('customer-guide', $slugs);
        self::assertContains('provider-guide', $slugs);
        self::assertNotContains('administrator-guide', $slugs);
        self::assertNotContains('developer-guide', $slugs);
        self::assertNull($catalog->article('administrator-guide', 'overview-and-workspaces', false));
        self::assertNotNull($catalog->article('administrator-guide', 'overview-and-workspaces', true));
    }

    public function testSearchSupportsAudienceBrandModuleAndVersionTogether(): void
    {
        $catalog = new DocumentationCatalog();
        $results = $catalog->search([
            'q' => 'providers',
            'audience' => 'administrator',
            'brand' => 'vanassist',
            'module' => 'directory',
            'version' => 'Current repository baseline',
        ], true);

        self::assertNotEmpty($results);
        self::assertSame('administrator-guide', $results[0]['guide']);
    }

    public function testContextHelpUsesLongestMatchingDashboardRoute(): void
    {
        self::assertSame(
            ['guide' => 'administrator-guide', 'slug' => 'providers-and-directory'],
            DocumentationLinkResolver::forRoute('/admin/providers/123/edit', 'administrator')
        );
        self::assertSame(
            ['guide' => 'customer-guide', 'slug' => 'account-and-garage'],
            DocumentationLinkResolver::forRoute('/account/garage/15', 'customer')
        );
    }

    public function testMarkdownRendererEscapesHtmlAndRejectsUnsafeLinks(): void
    {
        $html = MarkdownRenderer::render("## Purpose\n<script>alert(1)</script> [bad](javascript:alert(1)) [safe](/help)");

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('href="/help"', $html);
    }

    public function testRoutesAndDashboardLayoutsWireDocumentationEntryPoints(): void
    {
        $web = $this->source('routes/web.php');
        $admin = $this->source('routes/admin.php');
        $adminLayout = $this->source('app/Views/layouts/admin.php');
        $publicLayout = $this->source('app/Views/layouts/public.php');

        self::assertStringContainsString("'/help/{guide}/{article}'", $web);
        self::assertStringContainsString("'/help/{guide}/{article}'", $admin);
        self::assertStringContainsString('admin-context-help', $adminLayout);
        self::assertStringContainsString("DocumentationLinkResolver::forRoute", $adminLayout);
        self::assertStringContainsString('context-help-bar', $publicLayout);
        self::assertStringContainsString('$layoutPath === \'/provider\'', $publicLayout);
        self::assertStringContainsString("str_starts_with(\$layoutPath, '/provider/')", $publicLayout);
    }

    private function source(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($contents);
        return $contents;
    }
}
