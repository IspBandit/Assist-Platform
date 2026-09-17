<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DataSources\GooglePlacesCredentialProvisioner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GooglePlacesCredentialProvisionerTest extends TestCase
{
    public function testProvisionerRejectsAnythingThatIsNotAGoogleApiKeyBeforeDatabaseAccess(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GooglePlacesCredentialProvisioner())->provision('not-a-google-key');
    }

    public function testProvisionerRejectsEmptyKeyBeforeDatabaseAccess(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GooglePlacesCredentialProvisioner())->provision('');
    }
}
