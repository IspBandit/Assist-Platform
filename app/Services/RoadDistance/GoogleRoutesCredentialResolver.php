<?php

declare(strict_types=1);

namespace App\Services\RoadDistance;

use App\Core\Database;
use App\Services\SecretCipher;

final class GoogleRoutesCredentialResolver
{
    /** @return array{key:string,source:string} */
    public function resolve(): array
    {
        $environmentKey = trim((string) env('GOOGLE_ROUTES_API_KEY', ''));
        if ($environmentKey !== '') {
            return ['key' => $environmentKey, 'source' => 'environment'];
        }

        try {
            if (!Database::tableExists('data_source_credentials')) {
                return ['key' => '', 'source' => 'missing'];
            }
            $row = Database::selectOne(
                'SELECT cr.encrypted_value, c.connector_key '
                . 'FROM data_source_credentials cr '
                . 'JOIN data_source_connectors c ON c.id = cr.connector_id '
                . "WHERE cr.credential_key = 'api_key' AND c.connector_key IN ('google_routes','google_places') "
                . "ORDER BY c.connector_key = 'google_routes' DESC LIMIT 1"
            );
            $key = trim(SecretCipher::decrypt((string) ($row['encrypted_value'] ?? '')));
            if ($key !== '') {
                return [
                    'key' => $key,
                    'source' => (string) ($row['connector_key'] ?? '') === 'google_routes'
                        ? 'encrypted_google_routes_connector'
                        : 'encrypted_google_places_connector',
                ];
            }
        } catch (\Throwable) {
            // Routing remains fail-soft; health reports the missing state
            // without exposing decryption or database details publicly.
        }

        return ['key' => '', 'source' => 'missing'];
    }

    /** @return array{configured:bool,source:string} */
    public function status(): array
    {
        $credential = $this->resolve();

        return ['configured' => $credential['key'] !== '', 'source' => $credential['source']];
    }
}
