<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Models\CaravanPark;

/**
 * Audited Admin API writes for stays (`caravan_parks`, CORE-011 Increment 5).
 */
final class AdminApiStayWriteService
{
    /** @var list<string> */
    private const PATCH_FIELDS = [
        'name',
        'address',
        'phone',
        'email',
        'website',
        'description',
        'town_id',
        'state_id',
        'region_id',
        'stay_type',
        'price_type',
        'number_of_sites',
        'latitude',
        'longitude',
    ];

    /** @var list<string> */
    private const STAY_TYPES = [
        'caravan_park',
        'campground',
        'free_camp',
        'showground',
        'rest_area',
        'farm_stay',
        'other',
    ];

    /** @var list<string> */
    private const PRICE_TYPES = ['free', 'donation', 'low_cost', 'paid', 'unknown'];

    private AdminApiStayService $reader;

    public function __construct(?AdminApiStayService $reader = null)
    {
        $this->reader = $reader ?? new AdminApiStayService();
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input, Request $request): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['name' => ['Name is required.']]
            );
        }

        $requestedStatus = strtolower(trim((string) ($input['status'] ?? '')));
        $status = $requestedStatus === 'draft' ? 'draft' : 'pending';
        $now = date('Y-m-d H:i:s');
        $validated = $this->validateStayFields($input, true);

        $stayId = CaravanPark::create(array_merge($validated, [
            'name' => $name,
            'slug' => CaravanPark::uniqueSlug($name),
            'status' => $status,
            'public_page_enabled' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        AdminApiAudit::record(
            'stay.created',
            'caravan_park',
            $stayId,
            null,
            ['name' => $name, 'status' => $status],
            $request
        );

        return $this->reader->show($stayId);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function patch(int $id, array $input, Request $request): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, self::PATCH_FIELDS, true)) {
                $updates[$key] = $value;
            }
        }

        if ($updates === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['body' => ['No recognised writable fields were supplied.']]
            );
        }

        $row = $this->findScoped($id);
        $previous = $this->auditSnapshot($row);

        $validated = $this->validateStayFields($updates, false);
        $validated['updated_at'] = date('Y-m-d H:i:s');
        CaravanPark::update($id, $validated);

        AdminApiAudit::record(
            'stay.updated',
            'caravan_park',
            $id,
            $previous,
            $validated,
            $request
        );

        return $this->reader->show($id);
    }

    /** @return array<string,mixed> */
    public function publish(int $id, Request $request): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $row = $this->findScoped($id);
        $previous = $this->auditSnapshot($row);

        Database::query(
            "UPDATE caravan_parks SET status = 'active', public_page_enabled = 1, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        AdminApiAudit::record(
            'stay.published',
            'caravan_park',
            $id,
            $previous,
            AdminApiLifecycle::stayFieldsAfterPublish(),
            $request
        );

        return $this->reader->show($id);
    }

    /** @return array<string,mixed> */
    public function unpublish(int $id, Request $request): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $row = $this->findScoped($id);
        $previous = $this->auditSnapshot($row);

        Database::query(
            'UPDATE caravan_parks SET public_page_enabled = 0, updated_at = NOW() WHERE id = ?',
            [$id]
        );

        AdminApiAudit::record(
            'stay.unpublished',
            'caravan_park',
            $id,
            $previous,
            AdminApiLifecycle::stayFieldsAfterUnpublish(),
            $request
        );

        return $this->reader->show($id);
    }

    /** @return array<string,mixed> */
    public function archive(int $id, Request $request): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $row = $this->findScoped($id);
        $previous = $this->auditSnapshot($row);

        Database::query(
            "UPDATE caravan_parks SET status = 'suspended', public_page_enabled = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        AdminApiAudit::record(
            'stay.archived',
            'caravan_park',
            $id,
            $previous,
            AdminApiLifecycle::stayFieldsAfterArchive(),
            $request
        );

        return $this->reader->show($id);
    }

    /** @return array<string,mixed> */
    public function restore(int $id, Request $request): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $row = $this->findScoped($id, true);
        if (($row['deleted_at'] ?? null) === null) {
            throw new AdminApiException(422, 'validation_failed', 'Stay is not deleted.');
        }

        $previous = $this->auditSnapshot($row);

        Database::query(
            "UPDATE caravan_parks SET deleted_at = NULL, status = 'pending', public_page_enabled = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        AdminApiAudit::record(
            'stay.restored',
            'caravan_park',
            $id,
            $previous,
            AdminApiLifecycle::stayFieldsAfterRestore(),
            $request
        );

        return $this->reader->show($id);
    }

    /** @return array<string,mixed> */
    public function softDelete(int $id, ?string $reason, Request $request): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $reason = trim((string) $reason);
        if (strlen($reason) < 3) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['reason' => ['A deletion reason of at least 3 characters is required.']]
            );
        }

        $row = $this->findScoped($id);
        $previous = $this->auditSnapshot($row);

        CaravanPark::delete($id);

        AdminApiAudit::record(
            'stay.deleted',
            'caravan_park',
            $id,
            $previous,
            ['reason' => $reason],
            $request
        );

        return [
            'id' => (string) $id,
            'deleted' => true,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function findScoped(int $id, bool $includeDeleted = false): array
    {
        $clause = $includeDeleted ? '' : ' AND deleted_at IS NULL';
        $row = Database::selectOne(
            'SELECT id, name, status, public_page_enabled, deleted_at FROM caravan_parks WHERE id = ?' . $clause,
            [$id]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return [
            'status' => (string) ($row['status'] ?? ''),
            'public_page_enabled' => (int) ($row['public_page_enabled'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function validateStayFields(array $input, bool $forCreate): array
    {
        $validated = [];
        $errors = [];

        if (!$forCreate && array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                $errors['name'][] = 'Name cannot be empty.';
            } else {
                $validated['name'] = $name;
            }
        }

        foreach (['address', 'phone', 'email', 'website', 'description'] as $field) {
            if (array_key_exists($field, $input)) {
                $validated[$field] = $this->nullableString($input[$field]);
            }
        }

        foreach (['town_id', 'state_id', 'region_id', 'number_of_sites'] as $field) {
            if (array_key_exists($field, $input)) {
                $validated[$field] = $this->nullablePositiveInt($input[$field]);
            }
        }

        if (array_key_exists('latitude', $input)) {
            $validated['latitude'] = $this->nullableFloat($input['latitude']);
        }
        if (array_key_exists('longitude', $input)) {
            $validated['longitude'] = $this->nullableFloat($input['longitude']);
        }

        if (array_key_exists('stay_type', $input)) {
            $stayType = strtolower(trim((string) $input['stay_type']));
            if (!in_array($stayType, self::STAY_TYPES, true)) {
                $errors['stay_type'][] = 'Stay type is not recognised.';
            } else {
                $validated['stay_type'] = $stayType;
            }
        } elseif ($forCreate) {
            $validated['stay_type'] = 'caravan_park';
        }

        if (array_key_exists('price_type', $input)) {
            $priceType = strtolower(trim((string) $input['price_type']));
            if (!in_array($priceType, self::PRICE_TYPES, true)) {
                $errors['price_type'][] = 'Price type is not recognised.';
            } else {
                $validated['price_type'] = $priceType;
            }
        } elseif ($forCreate) {
            $validated['price_type'] = 'unknown';
        }

        if ($errors !== []) {
            throw new AdminApiException(422, 'validation_failed', 'Validation failed.', $errors);
        }

        return $validated;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
