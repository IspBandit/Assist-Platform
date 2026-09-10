<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Ensures the authoritative provider pack is activated after a production
 * migration even when the server's root-owned release wrapper predates the
 * separate seed command. Idempotent fingerprints make normal runs a no-op.
 */
final class ProviderPackActivation
{
    /** @return array<string,mixed> */
    public static function afterMigrations(): array
    {
        if (!Database::tableExists('towns') || !Database::tableExists('site_settings')) {
            return ['skipped' => true, 'note' => 'provider prerequisites are not installed'];
        }

        $townCount = (int) Database::scalar('SELECT COUNT(*) FROM towns WHERE is_active=1');
        $runner = new ProviderImportRunner();
        $fingerprint = $runner->providerPackFingerprint();
        $savedFingerprint = (string) Settings::get(ProviderImportRunner::SETTING_PROVIDER_PACK_FP, '');
        $savedOffset = (string) Settings::get(ProviderImportRunner::SETTING_PROVIDER_PACK_OFFSET, '0');

        if (!self::shouldRun($townCount, $fingerprint, $savedFingerprint, $savedOffset)) {
            return ['skipped' => true, 'note' => $townCount < 1000 ? 'national towns are not seeded' : 'provider pack is current'];
        }

        $result = $runner->runProviderPackToCompletion();
        if (isset($result['error']) || empty($result['complete'])) {
            throw new \RuntimeException('Authoritative provider-pack activation did not complete: ' . (string) ($result['error'] ?? 'unknown error'));
        }
        return $result;
    }

    public static function shouldRun(int $townCount, string $fingerprint, string $savedFingerprint, string $savedOffset): bool
    {
        return $townCount >= 1000
            && $fingerprint !== ''
            && ($fingerprint !== $savedFingerprint || $savedOffset !== 'done');
    }
}
