<?php

declare(strict_types=1);

namespace Tests\Unit;

use Assist\Tools\Documentation\DocumentationChangeValidator;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/scripts/lib/DocumentationChangeValidator.php';

final class DocumentationChangeValidatorTest extends TestCase
{
    private DocumentationChangeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DocumentationChangeValidator();
    }

    public function testIrrelevantInternalChangesDoNotRequireDocumentation(): void
    {
        self::assertSame([], $this->validator->validate([
            'app/Services/QueueWorker.php',
            'tests/Unit/QueueWorkerTest.php',
        ]));
    }

    public function testAdministratorChangeRequiresGuideAndReleaseNotes(): void
    {
        $errors = $this->validator->validate(['app/Views/admin/dashboard.php']);

        self::assertCount(2, $errors);
        self::assertStringContainsString('docs/ADMINISTRATOR_GUIDE.md', $errors[0]);
        self::assertStringContainsString('docs/RELEASE_NOTES.md', $errors[1]);
    }

    public function testAdministratorChangePassesWithBothRequiredDocuments(): void
    {
        self::assertSame([], $this->validator->validate([
            'app/Controllers/Admin/ReportsController.php',
            'docs/ADMINISTRATOR_GUIDE.md',
            'docs/RELEASE_NOTES.md',
        ]));
    }

    public function testAdministratorLayoutIsNotMisclassifiedAsPublicInterface(): void
    {
        self::assertSame(['administrator'], $this->validator->affectedScopes([
            'app/Views/layouts/admin.php',
        ]));
    }

    public function testAdministratorScriptIsNotMisclassifiedAsPublicInterface(): void
    {
        self::assertSame(['administrator'], $this->validator->affectedScopes([
            'public/assets/js/admin-platform.js',
        ]));
    }

    public function testReleaseNotesAloneDoNotReplaceTheMatchingGuide(): void
    {
        $errors = $this->validator->validate([
            'routes/provider.php',
            'docs/RELEASE_NOTES.md',
        ]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('docs/PROVIDER_GUIDE.md', $errors[0]);
    }

    public function testGuideAloneDoesNotReplaceReleaseNotes(): void
    {
        $errors = $this->validator->validate([
            'routes/admin.php',
            'docs/ADMINISTRATOR_GUIDE.md',
        ]);

        self::assertSame(
            ['Relevant interface/API/admin behaviour changed without updating docs/RELEASE_NOTES.md.'],
            $errors
        );
    }

    public function testPublicChangeRejectsAnUnrelatedAdministratorGuide(): void
    {
        $errors = $this->validator->validate([
            'app/Views/public/home.php',
            'docs/ADMINISTRATOR_GUIDE.md',
            'docs/RELEASE_NOTES.md',
        ]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('public or customer interface', $errors[0]);
    }

    public function testApiChangeAcceptsTheApiContractAndReleaseNotes(): void
    {
        self::assertSame([], $this->validator->validate([
            'app/Controllers/Api/ProviderController.php',
            'docs/API.md',
            'docs/RELEASE_NOTES.md',
        ]));
    }

    public function testLivingGuideArticleSatisfiesItsMatchingScope(): void
    {
        self::assertSame([], $this->validator->validate([
            'app/Views/admin/providers/index.php',
            'docs/user-guide/administrator-guide/providers-and-directory.md',
            'docs/RELEASE_NOTES.md',
        ]));
    }

    public function testAuthenticationAndPwaFilesArePublicInterfaceChanges(): void
    {
        self::assertSame(['public'], $this->validator->affectedScopes([
            'routes/auth.php',
            'public/service-worker.js',
        ]));
    }

    public function testWindowsPathsAreNormalisedBeforeMatching(): void
    {
        self::assertSame([], $this->validator->validate([
            '.\\app\\Views\\admin\\dashboard.php',
            'docs\\ADMINISTRATOR_GUIDE.md',
            'docs\\RELEASE_NOTES.md',
        ]));
    }

    public function testOnePullRequestCanSatisfyMultipleAffectedScopes(): void
    {
        self::assertSame([], $this->validator->validate([
            'routes/admin.php',
            'routes/api.php',
            'docs/ADMINISTRATOR_GUIDE.md',
            'docs/API.md',
            'docs/RELEASE_NOTES.md',
        ]));
    }
}
