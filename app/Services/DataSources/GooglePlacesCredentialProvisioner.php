<?php

declare(strict_types=1);

namespace App\Services\DataSources;

use App\Core\Database;
use App\Helpers\Env;
use App\Platform\DataSources\Connectors\GooglePlacesConnector;
use App\Services\AuditLog;
use App\Services\SecretCipher;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Stores GOOGLE_PLACES_API_KEY in the encrypted data-source vault and activates
 * the Google Places connector for ADR 0042 rescue / bootstrap.
 */
final class GooglePlacesCredentialProvisioner
{
    public function provisionFromEnv(bool $activate = true, float $dailyBudgetAud = 50.0, int $dailyLimit = 100): void
    {
        $apiKey = trim((string) Env::get('GOOGLE_PLACES_API_KEY', ''));
        if ($apiKey === '') {
            throw new InvalidArgumentException('GOOGLE_PLACES_API_KEY is not set.');
        }
        $this->provision($apiKey, $activate, $dailyBudgetAud, $dailyLimit);
    }

    public function provision(
        string $apiKey,
        bool $activate = true,
        float $dailyBudgetAud = 50.0,
        int $dailyLimit = 100,
    ): void {
        $apiKey = trim($apiKey);
        if (!preg_match('/\AAIza[0-9A-Za-z_-]{30,}\z/', $apiKey) || strlen($apiKey) > 255) {
            throw new InvalidArgumentException('Google Places credential has an invalid format.');
        }

        $dailyLimit = max(1, min(100000, $dailyLimit));
        $dailyBudgetAud = max(0, min(100000, $dailyBudgetAud));
        $status = $activate ? 'active' : 'configured';

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO data_source_connectors
                    (connector_key, name, connector_class, status, daily_request_limit,
                     daily_budget_aud, estimated_request_cost_aud, settings_json, created_at, updated_at)
                 VALUES
                    ('google_places', 'Google Places', ?, ?, ?,
                     ?, 0.05, JSON_OBJECT('region_code','AU','language_code','en'), NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    connector_class = VALUES(connector_class),
                    status = VALUES(status),
                    daily_request_limit = VALUES(daily_request_limit),
                    daily_budget_aud = VALUES(daily_budget_aud),
                    updated_at = NOW()",
                [GooglePlacesConnector::class, $status, $dailyLimit, $dailyBudgetAud]
            );
            $connectorId = (int) Database::scalar(
                "SELECT id FROM data_source_connectors WHERE connector_key = 'google_places' LIMIT 1"
            );
            if ($connectorId < 1) {
                throw new RuntimeException('Google Places connector could not be resolved.');
            }

            $encrypted = SecretCipher::encrypt($apiKey);
            $hint = '••••' . substr($apiKey, -4);
            Database::query(
                "INSERT INTO data_source_credentials
                    (connector_id, credential_key, encrypted_value, value_hint, updated_by, created_at, updated_at)
                 VALUES (?, 'api_key', ?, ?, NULL, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    encrypted_value = VALUES(encrypted_value),
                    value_hint = VALUES(value_hint),
                    updated_by = NULL,
                    updated_at = NOW()",
                [$connectorId, $encrypted, $hint]
            );
            Database::commit();
        } catch (Throwable $error) {
            Database::rollBack();
            throw $error;
        }

        AuditLog::record(
            'data_source.places_credential_provisioned',
            'data_source_connector',
            (string) $connectorId,
            null,
            json_encode([
                'connector_key' => 'google_places',
                'source' => 'env_or_cli',
                'active' => $activate,
                'daily_budget_aud' => $dailyBudgetAud,
                'daily_limit' => $dailyLimit,
            ], JSON_THROW_ON_ERROR)
        );
    }
}
