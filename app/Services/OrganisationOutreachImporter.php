<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class OrganisationOutreachImporter
{
    /** @return array{imported:int,updated:int,held:int} */
    public static function importFile(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('The organisation outreach CSV could not be read.');
        }
        $header = fgetcsv($handle, null, ',', '"', '');
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('The organisation outreach CSV is empty.');
        }
        $header = array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        $required = ['organisation_name','organisation_type','website_url','contact_role','email','source_url','source_checked_at','publication_context','relevance_reason'];
        if (array_diff($required, $header) !== []) {
            fclose($handle);
            throw new RuntimeException('The organisation outreach CSV is missing required evidence columns.');
        }

        $result = ['imported' => 0, 'updated' => 0, 'held' => 0];
        $width = count($header);
        while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $record = array_combine($header, array_slice(array_pad($line, $width, ''), 0, $width)) ?: [];
            $email = strtolower(trim((string) ($record['email'] ?? '')));
            $type = (string) ($record['organisation_type'] ?? '');
            $checked = trim((string) ($record['source_checked_at'] ?? ''));
            $valid = trim((string) ($record['organisation_name'] ?? '')) !== ''
                && isset(OrganisationOutreach::TYPES[$type])
                && filter_var($email, FILTER_VALIDATE_EMAIL)
                && filter_var((string) ($record['website_url'] ?? ''), FILTER_VALIDATE_URL)
                && filter_var((string) ($record['source_url'] ?? ''), FILTER_VALIDATE_URL)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $checked) === 1;
            if (!$valid) {
                $result['held']++;
                continue;
            }
            $personal = self::truthy($record['personal_or_ambiguous'] ?? '');
            $warning = self::truthy($record['no_unsolicited_warning'] ?? '');
            $status = ($personal || $warning) ? 'held' : 'research';
            $params = [
                trim((string) $record['organisation_name']), $type,
                trim((string) ($record['coverage'] ?? '')) ?: null,
                strtoupper(trim((string) ($record['state_code'] ?? ''))) ?: null,
                trim((string) $record['website_url']), trim((string) $record['contact_role']), $email,
                trim((string) $record['source_url']), $checked,
                mb_substr(trim((string) $record['publication_context']), 0, 500),
                mb_substr(trim((string) $record['relevance_reason']), 0, 500),
                $warning ? 1 : 0, $personal ? 1 : 0, $status,
                trim((string) ($record['notes'] ?? '')) ?: null,
            ];
            $exists = (int) Database::scalar('SELECT COUNT(*) FROM organisation_outreach_contacts WHERE email=?', [$email]) > 0;
            Database::query(
                'INSERT INTO organisation_outreach_contacts '
                . '(organisation_name,organisation_type,coverage,state_code,website_url,contact_role,email,source_url,source_checked_at,publication_context,relevance_reason,no_unsolicited_warning,personal_or_ambiguous,review_status,notes,created_at,updated_at) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE '
                . 'organisation_name=VALUES(organisation_name),organisation_type=VALUES(organisation_type),coverage=VALUES(coverage),state_code=VALUES(state_code),website_url=VALUES(website_url),contact_role=VALUES(contact_role),source_url=VALUES(source_url),source_checked_at=VALUES(source_checked_at),publication_context=VALUES(publication_context),relevance_reason=VALUES(relevance_reason),no_unsolicited_warning=VALUES(no_unsolicited_warning),personal_or_ambiguous=VALUES(personal_or_ambiguous),notes=VALUES(notes),review_status=IF(review_status=\'research\',VALUES(review_status),review_status),updated_at=NOW()',
                $params
            );
            $exists ? $result['updated']++ : $result['imported']++;
        }
        fclose($handle);
        return $result;
    }

    /** @return array{imported:int,updated:int,held:int} */
    public static function afterMigrations(): array
    {
        $path = base_path('database/seeds/outreach/vanassist-organisations.csv');
        return is_file($path) ? self::importFile($path) : ['imported' => 0, 'updated' => 0, 'held' => 0];
    }

    private static function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }
}
