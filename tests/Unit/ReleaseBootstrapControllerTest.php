<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\ReleaseBootstrapController;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class ReleaseBootstrapControllerTest extends TestCase
{
    public function testRepositoryPlaceholderCannotProvisionACredential(): void
    {
        $request = new Request(
            [],
            ['api_key' => 'AIza' . str_repeat('x', 35)],
            ['HTTP_X_ASSIST_RELEASE_NONCE' => str_repeat('n', 64)],
            []
        );

        $response = (new ReleaseBootstrapController())->googleRoutes($request);

        self::assertSame(404, $response->status());
        self::assertSame('no-store', $response->headers()['Cache-Control'] ?? null);
        self::assertStringNotContainsString('api_key', $response->content());
    }
}
