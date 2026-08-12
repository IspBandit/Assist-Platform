<?php

declare(strict_types=1);

namespace App\Services\RoadDistance;

use App\Core\Database;
use App\Services\AuditLog;
use App\Services\SecretCipher;
use InvalidArgumentException;
use Throwable;

final class GoogleRoutesCredentialProvisioner
{
    public function provision(string $apiKey): void
    {
        $this->store($apiKey, null, null);
    }

    public function provisionForRelease(string $apiKey, string $release, string $nonceHash): void
    {
        if (!preg_match('/\A[a-f0-9]{40}\z/', $release)
            || !preg_match('/\A[a-f0-9]{64}\z/', $nonceHash)) {
            throw new InvalidArgumentException('Release credential metadata is invalid.');
        }

        $this->store($apiKey, $release, $nonceHash);
    }

    private function store(string $apiKey, ?string $release, ?string $nonceHash): void
    {
        $apiKey = trim($apiKey);
        if (!preg_match('/\AAIza[0-9A-Za-z_-]{30,}\z/', $apiKey) || strlen($apiKey) > 255) {
            throw new InvalidArgumentException('Google Routes credential has an invalid format.');
        }

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO data_source_connectors
                    (connector_key, name, connector_class, status, daily_request_limit,
                     daily_budget_aud, estimated_request_cost_aud, settings_json, created_at, updated_at)
                 VALUES
                    ('google_routes', 'Google Routes API', ?, 'configured', 1000,
                     0, 0, JSON_OBJECT('purpose', 'road_distance'), NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name), status = 'configured', updated_at = NOW()",
                [GoogleRoutesMatrixClient::class]
            );
            $connectorId = (int) Database::scalar(
                "SELECT id FROM data_source_connectors WHERE connector_key = 'google_routes' LIMIT 1"
            );
            if ($connectorId < 1) {
                throw new \RuntimeException('Google Routes connector could not be resolved.');
            }

            if ($release !== null && $nonceHash !== null) {
                $settingsRaw = Database::scalar(
                    'SELECT settings_json FROM data_source_connectors WHERE id = ? FOR UPDATE',
                    [$connectorId]
                );
                $settings = is_string($settingsRaw) ? json_decode($settingsRaw, true) : [];
                $settings = is_array($settings) ? $settings : [];
                if (hash_equals((string) ($settings['bootstrap_nonce_hash'] ?? ''), $nonceHash)) {
                    throw new \RuntimeException('This release credential envelope has already been consumed.');
                }
                $settings['purpose'] = 'road_distance';
                $settings['bootstrap_release'] = $release;
                $settings['bootstrap_nonce_hash'] = $nonceHash;
                $settings['bootstrap_consumed_at'] = gmdate('c');
                Database::query(
                    'UPDATE data_source_connectors SET settings_json = ?, updated_at = NOW() WHERE id = ?',
                    [json_encode($settings, JSON_UNESCAPED_SLASHES), $connectorId]
                );
            }

            $encrypted = SecretCipher::encrypt($apiKey);
            $hint = '••••' . substr($apiKey, -4);
            Database::query(
                "INSERT INTO data_source_credentials
                    (connector_id, credential_key, encrypted_value, value_hint, updated_by, created_at, updated_at)
                 VALUES (?, 'api_key', ?, ?, NULL, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    encrypted_value = VALUES(encrypted_value), value_hint = VALUES(value_hint),
                    updated_by = NULL, updated_at = NOW()",
                [$connectorId, $encrypted, $hint]
            );
            Database::commit();
        } catch (Throwable $error) {
            Database::rollBack();
            throw $error;
        }

        AuditLog::record(
            'road_distance.credential_provisioned',
            'data_source_connector',
            (string) $connectorId,
            null,
            json_encode([
                'connector_key' => 'google_routes',
                'source' => $release === null ? 'protected_deployment' : 'protected_release_bootstrap',
                'release' => $release,
            ])
        );
    }
}
