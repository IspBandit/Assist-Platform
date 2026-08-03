<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use App\Services\AuditLog;
use RuntimeException;

/**
 * Draft-first CSV/JSON catalogue import. Never auto-publishes models.
 */
final class ImportService
{
    public const EXTRACTOR_VERSION = 'polaris-import-2';

    /**
     * @return array{job_id:int,draft_count:int,errors:list<string>}
     */
    public function importCsv(int $brandId, string $csvContents, ?int $userId, ?string $filename = null): array
    {
        return $this->importRows($brandId, $this->parseCsv($csvContents), 'csv', $userId, $filename);
    }

    /**
     * Accepts a JSON array of objects or `{ "rows": [ ... ] }`.
     *
     * @return array{job_id:int,draft_count:int,errors:list<string>}
     */
    public function importJson(int $brandId, string $jsonContents, ?int $userId, ?string $filename = null): array
    {
        $decoded = json_decode($jsonContents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON payload is invalid.');
        }
        $rows = isset($decoded['rows']) && is_array($decoded['rows']) ? $decoded['rows'] : $decoded;
        if ($rows === [] || !array_is_list($rows)) {
            throw new RuntimeException('JSON must be a list of row objects (or { "rows": [...] }).');
        }
        $normalised = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $flat = [];
            foreach ($row as $key => $value) {
                $flat[strtolower((string) $key)] = is_scalar($value) || $value === null ? (string) ($value ?? '') : '';
            }
            $normalised[] = $flat;
        }
        return $this->importRows($brandId, $normalised, 'json', $userId, $filename);
    }

    /**
     * Deterministic brochure/text extraction → single draft (flag-gated by caller).
     *
     * @return array{job_id:int,draft_count:int,errors:list<string>,cost:array<string,mixed>,hints:list<string>}
     */
    public function importBrochureText(
        int $brandId,
        string $text,
        ?int $userId,
        ?string $filename = null,
        ?string $manufacturerHint = null
    ): array {
        $extracted = BrochureTextExtractor::extract($text, $manufacturerHint);
        $cost = ExtractionCostEstimator::forMode('brochure_text', 1);
        $row = [
            'manufacturer' => (string) ($extracted['payload']['manufacturer_name'] ?? ''),
            'model' => (string) ($extracted['payload']['model_name'] ?? ''),
            'variant' => (string) ($extracted['payload']['variant_name'] ?? 'Standard'),
            'category' => (string) ($extracted['payload']['category'] ?? 'caravan'),
            'sleeps' => isset($extracted['payload']['sleeps']) ? (string) $extracted['payload']['sleeps'] : '',
            'tare_kg' => isset($extracted['payload']['tare_kg']) ? (string) $extracted['payload']['tare_kg'] : '',
            'atm_kg' => isset($extracted['payload']['atm_kg']) ? (string) $extracted['payload']['atm_kg'] : '',
            'body_length_m' => isset($extracted['payload']['body_length_m']) ? (string) $extracted['payload']['body_length_m'] : '',
            'fresh_water_l' => isset($extracted['payload']['fresh_water_l']) ? (string) $extracted['payload']['fresh_water_l'] : '',
            'solar_w' => isset($extracted['payload']['solar_w']) ? (string) $extracted['payload']['solar_w'] : '',
            'bathroom_type' => (string) ($extracted['payload']['bathroom_type'] ?? ''),
            'price_aud' => isset($extracted['payload']['price_aud']) ? (string) $extracted['payload']['price_aud'] : '',
            'price_status' => (string) ($extracted['payload']['price_status'] ?? 'from'),
            'description' => (string) ($extracted['payload']['description'] ?? ''),
        ];
        $result = $this->importRows($brandId, [$row], 'brochure', $userId, $filename ?? 'brochure.txt');
        // Override extractor version for brochure jobs
        Database::affecting(
            'UPDATE polaris_import_jobs SET extractor_version = ?, confidence_avg = ? WHERE id = ?',
            [BrochureTextExtractor::VERSION, $extracted['confidence'], $result['job_id']]
        );
        if ($result['draft_count'] > 0) {
            $draft = Database::selectOne(
                'SELECT id, payload_json FROM polaris_import_drafts WHERE job_id = ? ORDER BY id DESC LIMIT 1',
                [$result['job_id']]
            );
            if ($draft !== null) {
                $payload = json_decode((string) $draft['payload_json'], true);
                if (is_array($payload)) {
                    $payload['extraction_hints'] = $extracted['hints'];
                    $payload['extraction_errors'] = $extracted['errors'];
                    Database::affecting(
                        'UPDATE polaris_import_drafts SET payload_json = ?, confidence = ? WHERE id = ?',
                        [json_encode($payload, JSON_THROW_ON_ERROR), $extracted['confidence'], (int) $draft['id']]
                    );
                }
            }
        }
        return $result + ['cost' => $cost, 'hints' => $extracted['hints']];
    }

    /**
     * @return array{job_id:int,draft_count:int,errors:list<string>}
     */
    public function importXlsx(int $brandId, string $absolutePath, ?int $userId, ?string $filename = null): array
    {
        $rows = XlsxSheetReader::rowsFromFile($absolutePath);
        return $this->importRows($brandId, $rows, 'xlsx', $userId, $filename ?? 'upload.xlsx');
    }

    /**
     * @param list<array<string,string>> $rows
     * @return array{job_id:int,draft_count:int,errors:list<string>}
     */
    private function importRows(int $brandId, array $rows, string $jobType, ?int $userId, ?string $filename): array
    {
        if ($rows === []) {
            throw new RuntimeException('Import contained no data rows.');
        }
        if (!in_array($jobType, ['csv', 'xlsx', 'json', 'manual', 'brochure', 'webpage'], true)) {
            $jobType = 'csv';
        }

        $jobId = Database::insert(
            'INSERT INTO polaris_import_jobs
                (brand_id, job_type, status, progress_pct, original_filename, extractor_version, row_count, created_by_user_id, started_at, created_at)
             VALUES (?, ?, \'running\', 10, ?, ?, ?, ?, NOW(), NOW())',
            [$brandId, $jobType, $filename, self::EXTRACTOR_VERSION, count($rows), $userId]
        );

        $errors = [];
        $draftCount = 0;
        $confidences = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $validation = $this->validateRow($row);
            if ($validation['errors'] !== []) {
                $errors[] = 'Row ' . $line . ': ' . implode('; ', $validation['errors']);
                continue;
            }
            Database::insert(
                'INSERT INTO polaris_import_drafts
                    (job_id, draft_type, payload_json, confidence, review_status, created_at)
                 VALUES (?, \'variant\', ?, ?, \'pending\', NOW())',
                [$jobId, json_encode($validation['payload'], JSON_THROW_ON_ERROR), $validation['confidence']]
            );
            $draftCount++;
            $confidences[] = $validation['confidence'];
        }

        $avg = $confidences === [] ? null : round(array_sum($confidences) / count($confidences), 2);
        Database::affecting(
            'UPDATE polaris_import_jobs SET status = ?, progress_pct = 100, error_count = ?, confidence_avg = ?,
                validation_errors_json = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?',
            [
                $draftCount > 0 ? 'awaiting_review' : 'failed',
                count($errors),
                $avg,
                $errors === [] ? null : json_encode($errors, JSON_THROW_ON_ERROR),
                $jobId,
            ]
        );

        AuditLog::record(
            'polaris.import.created',
            'polaris_import_job',
            (string) $jobId,
            null,
            json_encode(['drafts' => $draftCount, 'errors' => count($errors), 'type' => $jobType], JSON_THROW_ON_ERROR)
        );

        return ['job_id' => $jobId, 'draft_count' => $draftCount, 'errors' => $errors];
    }

    /**
     * Publish an approved draft as a published model+variant under an existing or new manufacturer.
     *
     * @return array{manufacturer_id:int,model_id:int,variant_id:int}
     */
    public function approveDraft(int $brandId, int $draftId, int $reviewerId, ?string $notes = null): array
    {
        $draft = Database::selectOne(
            'SELECT d.*, j.brand_id FROM polaris_import_drafts d
             INNER JOIN polaris_import_jobs j ON j.id = d.job_id
             WHERE d.id = ? AND j.brand_id = ? AND d.review_status = \'pending\' LIMIT 1',
            [$draftId, $brandId]
        );
        if ($draft === null) {
            throw new RuntimeException('Draft not found or already reviewed.');
        }

        /** @var array<string,mixed> $payload */
        $payload = json_decode((string) $draft['payload_json'], true, 512, JSON_THROW_ON_ERROR);

        $manufacturer = Database::selectOne(
            'SELECT * FROM polaris_manufacturers WHERE brand_id = ? AND slug = ? AND deleted_at IS NULL LIMIT 1',
            [$brandId, (string) $payload['manufacturer_slug']]
        );
        if ($manufacturer === null) {
            $mfrId = Database::insert(
                'INSERT INTO polaris_manufacturers
                    (brand_id, legal_name, trading_name, slug, claim_status, verification_status, publication_status, lifecycle_status, is_demo, created_at)
                 VALUES (?, ?, ?, ?, \'unclaimed\', \'unverified\', \'published\', \'active\', 0, NOW())',
                [
                    $brandId,
                    (string) $payload['manufacturer_name'],
                    (string) $payload['manufacturer_name'],
                    (string) $payload['manufacturer_slug'],
                ]
            );
        } else {
            $mfrId = (int) $manufacturer['id'];
        }

        $model = Database::selectOne(
            'SELECT * FROM polaris_rv_models WHERE manufacturer_id = ? AND slug = ? AND deleted_at IS NULL LIMIT 1',
            [$mfrId, (string) $payload['model_slug']]
        );
        if ($model === null) {
            $modelId = Database::insert(
                'INSERT INTO polaris_rv_models
                    (brand_id, manufacturer_id, name, slug, category, description, production_status, verification_status, publication_status, lifecycle_status, is_demo, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, \'current\', \'pending\', \'published\', \'active\', 0, NOW())',
                [
                    $brandId,
                    $mfrId,
                    (string) $payload['model_name'],
                    (string) $payload['model_slug'],
                    (string) $payload['category'],
                    (string) ($payload['description'] ?? 'Imported draft — review recommended.'),
                ]
            );
        } else {
            $modelId = (int) $model['id'];
        }

        $variantId = Database::insert(
            'INSERT INTO polaris_rv_variants
                (model_id, name, slug, sleeps, body_length_m, tare_kg, atm_kg, fresh_water_l, solar_w,
                 bathroom_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), \'published\', \'active\', NOW())',
            [
                $modelId,
                (string) $payload['variant_name'],
                (string) $payload['variant_slug'],
                $payload['sleeps'] ?? null,
                $payload['body_length_m'] ?? null,
                $payload['tare_kg'] ?? null,
                $payload['atm_kg'] ?? null,
                $payload['fresh_water_l'] ?? null,
                $payload['solar_w'] ?? null,
                $payload['bathroom_type'] ?? null,
                (string) ($payload['price_status'] ?? 'unknown'),
                $payload['price_aud_cents'] ?? null,
            ]
        );

        Database::affecting(
            'UPDATE polaris_import_drafts SET review_status = \'approved\', published_entity_type = \'variant\',
                published_entity_id = ?, reviewer_user_id = ?, reviewed_at = NOW(), notes = ?, updated_at = NOW()
             WHERE id = ?',
            [$variantId, $reviewerId, $notes, $draftId]
        );

        AuditLog::record(
            'polaris.import.draft_approved',
            'polaris_import_draft',
            (string) $draftId,
            null,
            json_encode(['variant_id' => $variantId, 'model_id' => $modelId], JSON_THROW_ON_ERROR)
        );

        return ['manufacturer_id' => $mfrId, 'model_id' => $modelId, 'variant_id' => $variantId];
    }

    public function rejectDraft(int $brandId, int $draftId, int $reviewerId, ?string $notes = null): void
    {
        $affected = Database::affecting(
            'UPDATE polaris_import_drafts d
             INNER JOIN polaris_import_jobs j ON j.id = d.job_id
             SET d.review_status = \'rejected\', d.reviewer_user_id = ?, d.reviewed_at = NOW(), d.notes = ?, d.updated_at = NOW()
             WHERE d.id = ? AND j.brand_id = ? AND d.review_status = \'pending\'',
            [$reviewerId, $notes, $draftId, $brandId]
        );
        if ($affected < 1) {
            throw new RuntimeException('Draft not found or already reviewed.');
        }
        AuditLog::record('polaris.import.draft_rejected', 'polaris_import_draft', (string) $draftId, null, $notes);
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingDrafts(int $brandId, int $limit = 100): array
    {
        return Database::select(
            'SELECT d.*, j.original_filename, j.extractor_version
             FROM polaris_import_drafts d
             INNER JOIN polaris_import_jobs j ON j.id = d.job_id
             WHERE j.brand_id = ? AND d.review_status = \'pending\'
             ORDER BY d.created_at ASC LIMIT ' . max(1, min(200, $limit)),
            [$brandId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function jobs(int $brandId, int $limit = 50): array
    {
        return Database::select(
            'SELECT * FROM polaris_import_jobs WHERE brand_id = ? ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit)),
            [$brandId]
        );
    }

    /**
     * @return list<array<string,string>>
     */
    private function parseCsv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('Unable to open temporary CSV buffer.');
        }
        fwrite($fh, $contents);
        rewind($fh);
        $header = fgetcsv($fh, 0, ',', '"', '\\');
        if ($header === false || $header === [null] || $header === []) {
            fclose($fh);
            return [];
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        $rows = [];
        while (($data = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if ($data === [null] || $data === []) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string) ($data[$i] ?? ''));
            }
            if (implode('', $row) === '') {
                continue;
            }
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    /**
     * @param array<string,string> $row
     * @return array{errors:list<string>,payload:array<string,mixed>,confidence:int}
     */
    private function validateRow(array $row): array
    {
        $errors = [];
        $manufacturer = $row['manufacturer'] ?? $row['manufacturer_name'] ?? '';
        $model = $row['model'] ?? $row['model_name'] ?? '';
        $variant = $row['variant'] ?? $row['variant_name'] ?? 'Base';
        $category = strtolower(str_replace([' ', '-'], '_', $row['category'] ?? 'caravan'));
        if ($manufacturer === '') {
            $errors[] = 'manufacturer is required';
        }
        if ($model === '') {
            $errors[] = 'model is required';
        }
        if (!isset(CatalogueService::categoryLabels()[$category])) {
            $errors[] = 'category is invalid';
        }

        $payload = [
            'manufacturer_name' => $manufacturer,
            'manufacturer_slug' => $this->slugify($manufacturer),
            'model_name' => $model,
            'model_slug' => $this->slugify($model),
            'variant_name' => $variant,
            'variant_slug' => $this->slugify($variant),
            'category' => $category,
            'description' => $row['description'] ?? '',
            'sleeps' => $this->optionalInt($row['sleeps'] ?? null),
            'body_length_m' => $this->optionalFloat($row['body_length_m'] ?? $row['length_m'] ?? null),
            'tare_kg' => $this->optionalInt($row['tare_kg'] ?? null),
            'atm_kg' => $this->optionalInt($row['atm_kg'] ?? null),
            'fresh_water_l' => $this->optionalInt($row['fresh_water_l'] ?? null),
            'solar_w' => $this->optionalInt($row['solar_w'] ?? null),
            'bathroom_type' => ($row['bathroom_type'] ?? '') !== '' ? $row['bathroom_type'] : null,
            'price_status' => ($row['price_status'] ?? '') !== '' ? $row['price_status'] : 'unknown',
            'price_aud_cents' => isset($row['price_aud']) && is_numeric($row['price_aud'])
                ? (int) round(((float) $row['price_aud']) * 100)
                : null,
        ];

        $confidence = 55;
        foreach (['tare_kg', 'atm_kg', 'sleeps'] as $key) {
            if ($payload[$key] !== null) {
                $confidence += 10;
            }
        }
        if ($payload['price_aud_cents'] !== null) {
            $confidence += 10;
        }

        return ['errors' => $errors, 'payload' => $payload, 'confidence' => min(95, $confidence)];
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-') ?: 'item';
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (int) $value : null;
    }

    private function optionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }
}
