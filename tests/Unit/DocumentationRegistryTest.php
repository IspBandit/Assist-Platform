<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Documentation\DocumentationRegistry;
use PHPUnit\Framework\TestCase;

final class DocumentationRegistryTest extends TestCase
{
    public function testSeedDocumentationIsCompleteAndGrounded(): void
    {
        $registry = new DocumentationRegistry(dirname(__DIR__, 2));

        self::assertCount(7, $registry->guides());
        self::assertSame([], $registry->validate());
        self::assertGreaterThanOrEqual(10, count($registry->articles()));
    }

    public function testArticleProvidesSafeSearchContentAndFilters(): void
    {
        $registry = new DocumentationRegistry(dirname(__DIR__, 2));
        $article = $registry->article('administrator-guide', 'providers-and-directory');

        self::assertNotNull($article);
        self::assertStringContainsString('## Permissions', $article['markdown']);
        self::assertStringNotContainsString('#', $article['plain_text']);
        self::assertNotSame('', $article['excerpt']);

        $results = $registry->search('send claim invite', 'administrator', 'vanassist', 'directory', 'providers.manage', 'Current repository baseline');
        self::assertNotEmpty($results);
        self::assertSame('administrator-guide.providers-and-directory', $results[0]['id']);
    }

    public function testUnknownGuideAndArticleReturnNull(): void
    {
        $registry = new DocumentationRegistry(dirname(__DIR__, 2));

        self::assertNull($registry->guide('missing'));
        self::assertNull($registry->article('administrator-guide', 'missing'));
    }
}
