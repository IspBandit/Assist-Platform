<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Offline dry-run: shape QLD publishable-minus-regulated candidates for the
 * data-source import review queue. Default mode never writes to the database.
 */
final class QldCoverageImportDryRunService
{
    public const DEFAULT_BATCH = 'brisbane-moreton-bay';

    /** @var array<string,array{name:string,bbox:?array{0:float,1:float,2:float,3:float},regions:array<int,string>}> */
    private const BATCHES = [
        'brisbane-moreton-bay' => [
            'name' => 'Brisbane and Moreton Bay',
            'bbox' => [152.4, -27.85, 153.5, -26.95],
            'regions' => ['seq'],
        ],
        'gold-coast-scenic-rim' => [
            'name' => 'Gold Coast and Scenic Rim',
            'bbox' => [152.6, -28.4, 153.6, -27.75],
            'regions' => ['seq'],
        ],
        'sunshine-coast-noosa' => [
            'name' => 'Sunshine Coast and Noosa',
            'bbox' => [152.6, -26.95, 153.3, -26.2],
            'regions' => ['seq'],
        ],
        'darling-downs-south-west' => [
            'name' => 'Darling Downs and South West',
            'bbox' => null,
            'regions' => ['downs'],
        ],
        'wide-bay-burnett' => [
            'name' => 'Wide Bay–Burnett',
            'bbox' => null,
            'regions' => ['widebay'],
        ],
        'central-queensland' => [
            'name' => 'Central Queensland',
            'bbox' => null,
            'regions' => ['cq', 'fitzroy'],
        ],
        'mackay-whitsunday' => [
            'name' => 'Mackay–Whitsunday',
            'bbox' => null,
            'regions' => ['mackay'],
        ],
        'townsville-north-queensland' => [
            'name' => 'Townsville and North Queensland',
            'bbox' => null,
            'regions' => ['nq'],
        ],
        'cairns-far-north' => [
            'name' => 'Cairns and Far North Queensland',
            'bbox' => null,
            'regions' => ['fnq'],
        ],
        'gulf-cape-remote' => [
            'name' => 'Gulf, Cape York and remote Queensland',
            'bbox' => null,
            'regions' => ['outback'],
        ],
    ];

    private string $seedRoot;

    public function __construct(?string $seedRoot = null)
    {
        $this->seedRoot = $seedRoot ?? (BASE_PATH . '/database/seeds/qld-coverage');
    }

    /** @return array<int,string> */
    public function batchIds(): array
    {
        return array_keys(self::BATCHES);
    }

    /**
     * @return array{
     *   mode:string,
     *   batch_id:string,
     *   batch_name:string,
     *   generated_at:string,
     *   publishable_total:int,
     *   regulated_held_total:int,
     *   regulated_excluded_from_publishable:int,
     *   batch_matched:int,
     *   places_provenance_flagged:int,
     *   eligible_for_apply_estimate:int,
     *   missing_phone:int,
     *   missing_email:int,
     *   candidates:array<int,array<string,mixed>>,
     *   notes:array<int,string>
     * }
     */
    public function build(string $batchId = self::DEFAULT_BATCH, int $limit = 0): array
    {
        if (!isset(self::BATCHES[$batchId])) {
            throw new \InvalidArgumentException('Unknown batch: ' . $batchId);
        }
        $batch = self::BATCHES[$batchId];

        $publishable = $this->loadJsonList($this->seedRoot . '/providers-publishable.json');
        $regulated = $this->loadJsonList($this->seedRoot . '/regulated-missing-licence.json');
        $regulatedIds = [];
        foreach ($regulated as $row) {
            if (is_array($row) && isset($row['id'])) {
                $regulatedIds[(string) $row['id']] = true;
            }
        }

        $candidates = [];
        $regulatedExcluded = 0;
        $placesFlagged = 0;
        $missingPhone = 0;
        $missingEmail = 0;

        foreach ($publishable as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (isset($regulatedIds[$id])) {
                $regulatedExcluded++;
                continue;
            }
            if (!$this->inBatch($row, $batchId, $batch)) {
                continue;
            }

            $hasPlaces = $this->hasGooglePlacesProvenance($row);
            if ($hasPlaces) {
                $placesFlagged++;
            }
            if (empty($row['phone'])) {
                $missingPhone++;
            }
            if (empty($row['public_email'])) {
                $missingEmail++;
            }

            $candidates[] = $this->toReviewCandidate($row, $batchId, $hasPlaces);
            if ($limit > 0 && count($candidates) >= $limit) {
                break;
            }
        }

        return [
            'mode' => 'dry-run',
            'batch_id' => $batchId,
            'batch_name' => $batch['name'],
            'generated_at' => gmdate('c'),
            'publishable_total' => count($publishable),
            'regulated_held_total' => count($regulatedIds),
            'regulated_excluded_from_publishable' => $regulatedExcluded,
            'batch_matched' => count($candidates),
            'places_provenance_flagged' => $placesFlagged,
            'eligible_for_apply_estimate' => max(0, count($candidates) - $placesFlagged),
            'missing_phone' => $missingPhone,
            'missing_email' => $missingEmail,
            'candidates' => $candidates,
            'notes' => [
                'Default dry-run does not write providers or import candidates to the database.',
                'Regulated providers missing licence evidence are excluded (usually already absent from publishable).',
                'Google Places provenance rows are flagged; do not treat Places content as unrestricted permanent directory content.',
                'Marketing consent is never inferred from a public email.',
                'Approve/merge/reject remains a manual admin action in the import review queue after an explicit --apply on local/test.',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $provider
     * @param array{name:string,bbox:?array{0:float,1:float,2:float,3:float},regions:array<int,string>} $batch
     */
    private function inBatch(array $provider, string $batchId, array $batch): bool
    {
        $region = strtolower((string) ($provider['region'] ?? ''));
        $lat = isset($provider['latitude']) && is_numeric($provider['latitude']) ? (float) $provider['latitude'] : null;
        $lng = isset($provider['longitude']) && is_numeric($provider['longitude']) ? (float) $provider['longitude'] : null;

        if ($batch['bbox'] !== null) {
            if ($lat === null || $lng === null) {
                // Town-name fallback for SEQ providers without coords when region matches.
                return $region === 'seq' && $this->seqTownLikelyInBatch($provider, $batchId);
            }
            return $this->inBbox($lat, $lng, $batch['bbox']);
        }

        if ($batchId === 'gulf-cape-remote') {
            if ($region === 'outback') {
                return true;
            }
            if ($lat !== null && $lat > -14.5) {
                return true;
            }
            if ($lng !== null && $lng < 142.0) {
                return true;
            }
            return false;
        }

        if ($batchId === 'cairns-far-north' && $lat !== null && $lat > -14.5) {
            return false;
        }
        if (in_array($region, $batch['regions'], true)) {
            if (in_array($region, ['fnq', 'nq', 'cq'], true) && $this->isRemoteCoords($lat, $lng)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * @param array{0:float,1:float,2:float,3:float} $bbox
     */
    private function inBbox(float $lat, float $lng, array $bbox): bool
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        return $lng >= $minLng && $lng <= $maxLng && $lat >= $minLat && $lat <= $maxLat;
    }

    private function isRemoteCoords(?float $lat, ?float $lng): bool
    {
        if ($lat !== null && $lat > -14.5) {
            return true;
        }
        if ($lng !== null && $lng < 142.0) {
            return true;
        }
        return false;
    }

    /**
     * Heuristic for SEQ rows without coordinates: keep names that are not clearly
     * Gold Coast / Sunshine Coast suburb labels.
     *
     * @param array<string,mixed> $provider
     */
    private function seqTownLikelyInBatch(array $provider, string $batchId): bool
    {
        if ($batchId !== 'brisbane-moreton-bay') {
            return false;
        }
        $town = strtolower((string) ($provider['town'] ?? $provider['suburb'] ?? ''));
        if ($town === '') {
            return false;
        }
        $gcHints = ['gold coast', 'southport', 'surfers', 'broadbeach', 'nerang', 'robina', 'burleigh', 'coolangatta', 'tweed'];
        $scHints = ['noosa', 'maroochydore', 'caloundra', 'nambour', 'buderim', 'mooloolaba', 'sunshine coast'];
        foreach ([...$gcHints, ...$scHints] as $hint) {
            if (str_contains($town, $hint)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $provider */
    private function hasGooglePlacesProvenance(array $provider): bool
    {
        foreach ($provider['source_records'] ?? [] as $src) {
            if (!is_array($src)) {
                continue;
            }
            $name = strtolower((string) ($src['source_name'] ?? ''));
            if (str_contains($name, 'google') || str_contains($name, 'places')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Shape matching data_source_import_candidates + DataSourceService::storeCandidate.
     *
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    private function toReviewCandidate(array $provider, string $batchId, bool $placesFlagged): array
    {
        $address = trim(implode(', ', array_filter([
            (string) ($provider['street_address'] ?? ''),
            (string) ($provider['suburb'] ?? $provider['town'] ?? ''),
            trim((string) ($provider['state'] ?? 'QLD') . ' ' . (string) ($provider['postcode'] ?? '')),
        ], static fn (string $v): bool => $v !== '')));

        $emailEvidence = $provider['field_evidence']['email'] ?? null;
        if (is_array($emailEvidence)) {
            $emailEvidence['marketing_consent'] = false;
        }

        return [
            'external_id' => 'qld-coverage:' . (string) $provider['id'],
            'business_name' => (string) ($provider['business_name'] ?? ''),
            'formatted_address' => $address !== '' ? $address : null,
            'phone' => $provider['phone'] ?? null,
            'website' => $provider['website'] ?? null,
            'latitude' => $provider['latitude'] ?? null,
            'longitude' => $provider['longitude'] ?? null,
            'confidence' => min(100, max(0, (int) ($provider['confidence'] ?? 0))),
            'brand_keys' => array_values(array_map('strval', $provider['brand_visibility'] ?? ['localtorque'])),
            'category_slugs' => array_values(array_map('strval', $provider['category_slugs'] ?? [])),
            'batch_id' => $batchId,
            'claimed_status' => (string) ($provider['claimed_status'] ?? 'unclaimed'),
            'google_places_provenance' => $placesFlagged,
            'marketing_consent' => false,
            'source_records' => $provider['source_records'] ?? [],
            'field_evidence' => [
                'categories' => $provider['field_evidence']['categories'] ?? [],
                'email' => $emailEvidence,
            ],
            'review_reasons' => $provider['review_reasons'] ?? [],
            'raw' => [
                'qld_coverage_id' => $provider['id'] ?? null,
                'service_model' => $provider['service_model'] ?? null,
                'operational_status' => $provider['operational_status'] ?? null,
                'last_checked_at' => $provider['last_checked_at'] ?? null,
            ],
        ];
    }

    /** @return array<int,mixed> */
    private function loadJsonList(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? array_values($json) : [];
    }
}
