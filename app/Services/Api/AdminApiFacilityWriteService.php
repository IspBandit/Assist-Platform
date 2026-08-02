<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Audited Admin API writes for traveller facilities (Option B Increment G).
 */
final class AdminApiFacilityWriteService
{
    /** @var list<string> */
    private const PATCH_FIELDS = [
        'name',
        'facility_type',
        'formatted_address',
        'locality',
        'town_id',
        'state_id',
        'latitude',
        'longitude',
        'operating_status',
        'opening_hours',
        'accessibility_notes',
        'verification_status',
        'status',
        'confidence',
        'linked_provider_id',
    ];

    private AdminApiFacilityService $reader;

    public function __construct(?AdminApiFacilityService $reader = null)
    {
        $this->reader = $reader ?? new AdminApiFacilityService();
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input, Request $request): array
    {
        $this->assertTable();

        $name = trim((string) ($input['name'] ?? ''));
        $facilityType = trim((string) ($input['facility_type'] ?? ''));
        if ($name === '' || $facilityType === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                [
                    'name' => $name === '' ? ['Name is required.'] : [],
                    'facility_type' => $facilityType === '' ? ['Facility type is required.'] : [],
                ]
            );
        }

        $slug = $this->uniqueSlug($name);
        $now = date('Y-m-d H:i:s');
        $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
        if (!in_array($status, ['draft', 'pending'], true)) {
            $status = 'draft';
        }

        $id = Database::insert(
            'INSERT INTO traveller_facilities (facility_type, name, slug, formatted_address, locality, town_id, state_id, latitude, longitude, '
            . 'operating_status, opening_hours, accessibility_notes, verification_status, status, confidence, brand_id, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $facilityType,
                $name,
                $slug,
                $input['formatted_address'] ?? null,
                $input['locality'] ?? null,
                isset($input['town_id']) ? (int) $input['town_id'] : null,
                isset($input['state_id']) ? (int) $input['state_id'] : null,
                isset($input['latitude']) ? (float) $input['latitude'] : null,
                isset($input['longitude']) ? (float) $input['longitude'] : null,
                (string) ($input['operating_status'] ?? 'unknown'),
                $input['opening_hours'] ?? null,
                $input['accessibility_notes'] ?? null,
                (string) ($input['verification_status'] ?? 'unverified'),
                $status,
                isset($input['confidence']) ? (int) $input['confidence'] : 0,
                AdminApiBrandScope::brandId(),
                $now,
                $now,
            ]
        );

        AdminApiAudit::record('facility.created', 'traveller_facility', $id, null, ['name' => $name], $request);

        return $this->reader->show($id);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function patch(int $id, array $input, Request $request): array
    {
        $this->assertTable();
        $before = $this->reader->show($id);
        $updates = [];
        $params = [];

        foreach ($input as $key => $value) {
            if (!in_array($key, self::PATCH_FIELDS, true)) {
                continue;
            }
            $updates[] = $key . ' = ?';
            $params[] = $value;
        }

        if ($updates === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['body' => ['No recognised fields to update.']]
            );
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;

        Database::query(
            'UPDATE traveller_facilities SET ' . implode(', ', $updates) . ' WHERE id = ? AND deleted_at IS NULL',
            $params
        );

        AdminApiAudit::record('facility.updated', 'traveller_facility', $id, $before, ['fields' => array_keys($input)], $request);

        return $this->reader->show($id);
    }

    /** @return array<string,mixed> */
    public function publish(int $id, Request $request): array
    {
        return $this->transition($id, ['status' => 'active'], 'facility.published', $request);
    }

    /** @return array<string,mixed> */
    public function unpublish(int $id, Request $request): array
    {
        return $this->transition($id, ['status' => 'pending'], 'facility.unpublished', $request);
    }

    /** @return array<string,mixed> */
    public function archive(int $id, Request $request): array
    {
        return $this->transition($id, ['status' => 'archived', 'archived_at' => date('Y-m-d H:i:s')], 'facility.archived', $request);
    }

    /** @return array<string,mixed> */
    public function restore(int $id, Request $request): array
    {
        return $this->transition($id, ['status' => 'pending', 'archived_at' => null, 'deleted_at' => null], 'facility.restored', $request);
    }

    /** @return array<string,mixed> */
    public function softDelete(int $id, string $reason, Request $request): array
    {
        if (strlen(trim($reason)) < 3) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['reason' => ['A delete reason of at least 3 characters is required.']]
            );
        }

        $before = $this->reader->show($id);
        $now = date('Y-m-d H:i:s');

        Database::query(
            'UPDATE traveller_facilities SET deleted_at = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            [$now, $id]
        );

        AdminApiAudit::record('facility.deleted', 'traveller_facility', $id, $before, ['reason' => $reason], $request);

        return [
            'id' => (string) $id,
            'deleted' => true,
            'reason' => $reason,
            'deleted_at' => $now,
        ];
    }

    /**
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    private function transition(int $id, array $fields, string $action, Request $request): array
    {
        $before = $this->reader->show($id);
        $sets = [];
        $params = [];

        foreach ($fields as $column => $value) {
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }

        $sets[] = 'updated_at = NOW()';
        $params[] = $id;

        Database::query(
            'UPDATE traveller_facilities SET ' . implode(', ', $sets) . ' WHERE id = ? AND deleted_at IS NULL',
            $params
        );

        AdminApiAudit::record($action, 'traveller_facility', $id, $before, $fields, $request);

        return $this->reader->show($id);
    }

    private function uniqueSlug(string $name): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
        if ($base === '') {
            $base = 'facility';
        }

        $slug = $base;
        $suffix = 1;
        while (Database::selectOne('SELECT id FROM traveller_facilities WHERE slug = ?', [$slug]) !== null) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function assertTable(): void
    {
        if (!Database::tableExists('traveller_facilities')) {
            throw new AdminApiException(503, 'unavailable', 'Traveller facilities are not available.');
        }
    }
}
