<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\RoadDistance\GoogleRoutesCredentialProvisioner;
use InvalidArgumentException;
use RuntimeException;

final class ReleaseBootstrapController extends Controller
{
    public function googleRoutes(Request $request): Response
    {
        $path = BASE_PATH . '/config/google-routes-release-bootstrap.php';
        $bootstrap = require $path;
        if (!is_array($bootstrap)) {
            return $this->denied();
        }

        $release = (string) Config::get('app.release', '');
        $expectedRelease = (string) ($bootstrap['release'] ?? '');
        $expectedHash = (string) ($bootstrap['nonce_sha256'] ?? '');
        $nonce = trim((string) $request->header('X-Assist-Release-Nonce', ''));
        if ($release === '' || !hash_equals($expectedRelease, $release)
            || !preg_match('/\A[a-f0-9]{64}\z/', $expectedHash)
            || $nonce === '' || !hash_equals($expectedHash, hash('sha256', $nonce))) {
            return $this->denied();
        }

        try {
            (new GoogleRoutesCredentialProvisioner())->provisionForRelease(
                (string) $request->input('api_key', ''),
                $release,
                $expectedHash
            );
        } catch (InvalidArgumentException) {
            return Response::json(['status' => 'invalid_credential'], 422)
                ->withHeader('Cache-Control', 'no-store');
        } catch (RuntimeException $error) {
            if (str_contains($error->getMessage(), 'already been consumed')) {
                return Response::json(['status' => 'already_consumed'], 409)
                    ->withHeader('Cache-Control', 'no-store');
            }
            throw $error;
        }

        return Response::json(['status' => 'configured', 'release' => $release])
            ->withHeader('Cache-Control', 'no-store');
    }

    private function denied(): Response
    {
        return Response::json(['status' => 'not_found'], 404)
            ->withHeader('Cache-Control', 'no-store');
    }
}
