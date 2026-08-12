<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RoadDistance\GoogleRoutesReleaseCredentialInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GoogleRoutesReleaseCredentialInputTest extends TestCase
{
    public function testEmptyInputMeansNoReleaseCredentialWasSupplied(): void
    {
        self::assertNull(GoogleRoutesReleaseCredentialInput::parse(" \n"));
    }

    public function testVersionedEnvelopeReturnsOnlyTheCredential(): void
    {
        $credential = 'AIza' . str_repeat('x', 35);

        self::assertSame(
            $credential,
            GoogleRoutesReleaseCredentialInput::parse(
                'ASSIST_GOOGLE_ROUTES_CREDENTIAL_V1:' . $credential . "\n"
            )
        );
    }

    public function testUnexpectedStandardInputFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid envelope');

        GoogleRoutesReleaseCredentialInput::parse('untrusted release input');
    }

    public function testReleasePipeCanBeReadWithoutExposingItsEnvelope(): void
    {
        $credential = 'AIza' . str_repeat('y', 35);
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'ASSIST_GOOGLE_ROUTES_CREDENTIAL_V1:' . $credential . "\n");
        rewind($stream);

        self::assertSame($credential, GoogleRoutesReleaseCredentialInput::read($stream));
        fclose($stream);
    }

    public function testMultipleLinesCannotBeTreatedAsOneCredential(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid credential payload');

        GoogleRoutesReleaseCredentialInput::parse(
            "ASSIST_GOOGLE_ROUTES_CREDENTIAL_V1:credential\nsecond-line"
        );
    }
}
