<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Models\Provider;
use App\Services\SubscriptionService;

/**
 * Audited Admin API writes for brand-scoped providers (CORE-011 Increment 5).
 */
final class AdminApiProviderWriteService
{
    /** @var list<string> */
    private const PATCH_FIELDS = [
        'business_name',
        'contact_name',
        'email',
        'phone',
        'public_email',
        'public_phone',
        'website',
        'description',
        'service_model',
        'street_address',
        'base_town_id',
        'region_id',
        'display_name',
        'search_visible',
        'listing_status',
    ];

    /** @var list<string> */
    private const SERVICE_MODELS = ['mobile', 'workshop', 'both'];

    private AdminApiProviderService $reader;

    public function __construct(?AdminApiProviderService $reader = null)
    {
        $this->reader = $reader ?? new AdminApiProviderService();
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input, Request $request): array
    {
        $businessName = trim((string) ($input['business_name'] ?? ''));
        if ($businessName === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['business_name' => ['Business name is required.']]
            );
        }

        $requestedStatus = strtolower(trim((string) ($input['status'] ?? '')));
        $providerStatus = $requestedStatus === 'draft' ? 'draft' : 'pending';
        $listingStatus = $providerStatus === 'draft' ? 'draft' : 'pending';
        $now = date('Y-m-d H:i:s');
        $brandId = AdminApiBrandScope::brandId();
        $displayName = trim((string) ($input['display_name'] ?? '')) ?: $businessName;

        Database::beginTransaction();
        try {
            $providerId = Provider::create([
                'business_name' => $businessName,
                'slug' => $this->uniqueProviderSlug($businessName),
                'contact_name' => $this->nullableString($input['contact_name'] ?? null),
                'email' => $this->nullableString($input['email'] ?? null),
                'phone' => $this->nullableString($input['phone'] ?? null),
                'public_email' => $this->nullableString($input['public_email'] ?? null),
                'public_phone' => $this->nullableString($input['public_phone'] ?? null),
                'website' => $this->nullableString($input['website'] ?? null),
                'description' => $this->nullableString($input['description'] ?? null),
                'service_model' => $this->serviceModel($input['service_model'] ?? 'mobile'),
                'street_address' => $this->nullableString($input['street_address'] ?? null),
                'base_town_id' => $this->nullablePositiveInt($input['base_town_id'] ?? null),
                'region_id' => $this->nullablePositiveInt($input['region_id'] ?? null),
                'status' => $providerStatus,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $brandSlug = $this->uniqueBrandSlug($brandId, $displayName, $providerId);
            Database::query(
                'INSERT INTO provider_brand_listings (brand_id, provider_id, slug, display_name, status, search_visible, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
                [$brandId, $providerId, $brandSlug, $displayName, $listingStatus, $now, $now]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        (new SubscriptionService())->provisionProvider($providerId);

        AdminApiAudit::record(
            'provider.created',
            'provider',
            $providerId,
            null,
            ['business_name' => $businessName, 'status' => $providerStatus],
            $request
        );

        return $this->reader->show($providerId);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function patch(int $id, array $input, Request $request): array
    {
        $providerUpdates = [];
        $listingUpdates = [];

        foreach ($input as $key => $value) {
            if (!in_array($key, self::PATCH_FIELDS, true)) {
                continue;
            }

            if (in_array($key, ['display_name', 'search_visible', 'listing_status'], true)) {
                $listingUpdates[$key] = $value;
                continue;
            }

            $providerUpdates[$key] = $value;
        }

        if ($providerUpdates === [] && $listingUpdates === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['body' => ['No recognised writable fields were supplied.']]
            );
        }

        $scoped = $this->findScoped($id);
        $providerId = (int) $scoped['provider_id'];
        $listingId = (int) $scoped['listing_id'];
        $previous = $this->auditSnapshot($scoped);

        $validatedProvider = $this->validateProviderPatch($providerUpdates);
        $validatedListing = $this->validateListingPatch($listingUpdates);

        if ($validatedProvider !== []) {
            $validatedProvider['updated_at'] = date('Y-m-d H:i:s');
            Provider::update($providerId, $validatedProvider);
        }

        if ($validatedListing !== []) {
            $sets = [];
            $params = [];
            foreach ($validatedListing as $column => $value) {
                $sets[] = $column . ' = ?';
                $params[] = $value;
            }
            $sets[] = 'updated_at = NOW()';
            $params[] = $listingId;
            Database::query(
                'UPDATE provider_brand_listings SET ' . implode(', ', $sets) . ' WHERE id = ?',
                $params
            );
        }

        AdminApiAudit::record(
            'provider.updated',
            'provider',
            $providerId,
            $previous,
            array_merge($validatedProvider, $validatedListing),
            $request
        );

        return $this->reader->show($providerId);
    }

    /** @return array<string,mixed> */
    public function publish(int $id, Request $request): array
    {
        $scoped = $this->findScoped($id);
        $previous = $this->auditSnapshot($scoped);
        $providerId = (int) $scoped['provider_id'];
        $listingId = (int) $scoped['listing_id'];

        Database::query(
            'UPDATE providers SET status = ?, approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE id = ?',
            ['active', $providerId]
        );
        Database::query(
            "UPDATE provider_brand_listings SET status = 'active', search_visible = 1, updated_at = NOW() WHERE id = ?",
            [$listingId]
        );

        AdminApiAudit::record(
            'provider.published',
            'provider',
            $providerId,
            $previous,
            AdminApiLifecycle::providerFieldsAfterPublish(),
            $request
        );

        return $this->reader->show($providerId);
    }

    /** @return array<string,mixed> */
    public function unpublish(int $id, Request $request): array
    {
        $scoped = $this->findScoped($id);
        $previous = $this->auditSnapshot($scoped);
        $providerId = (int) $scoped['provider_id'];
        $listingId = (int) $scoped['listing_id'];

        Database::query(
            'UPDATE provider_brand_listings SET search_visible = 0, updated_at = NOW() WHERE id = ?',
            [$listingId]
        );

        AdminApiAudit::record(
            'provider.unpublished',
            'provider',
            $providerId,
            $previous,
            AdminApiLifecycle::providerFieldsAfterUnpublish(),
            $request
        );

        return $this->reader->show($providerId);
    }

    /** @return array<string,mixed> */
    public function archive(int $id, Request $request): array
    {
        $scoped = $this->findScoped($id);
        $previous = $this->auditSnapshot($scoped);
        $providerId = (int) $scoped['provider_id'];
        $listingId = (int) $scoped['listing_id'];

        Database::query(
            "UPDATE providers SET status = 'suspended', updated_at = NOW() WHERE id = ?",
            [$providerId]
        );
        Database::query(
            'UPDATE provider_brand_listings SET search_visible = 0, updated_at = NOW() WHERE id = ?',
            [$listingId]
        );

        AdminApiAudit::record(
            'provider.archived',
            'provider',
            $providerId,
            $previous,
            AdminApiLifecycle::providerFieldsAfterArchive(),
            $request
        );

        return $this->reader->show($providerId);
    }

    /** @return array<string,mixed> */
    public function restore(int $id, Request $request): array
    {
        $scoped = $this->findScoped($id, true);
        if (($scoped['provider_deleted_at'] ?? null) === null && ($scoped['listing_deleted_at'] ?? null) === null) {
            throw new AdminApiException(422, 'validation_failed', 'Provider is not deleted.');
        }

        $previous = $this->auditSnapshot($scoped);
        $providerId = (int) $scoped['provider_id'];
        $listingId = (int) $scoped['listing_id'];

        Database::query(
            "UPDATE providers SET deleted_at = NULL, status = 'pending', updated_at = NOW() WHERE id = ?",
            [$providerId]
        );
        Database::query(
            "UPDATE provider_brand_listings SET deleted_at = NULL, status = 'pending', search_visible = 0, updated_at = NOW() WHERE id = ?",
            [$listingId]
        );

        AdminApiAudit::record(
            'provider.restored',
            'provider',
            $providerId,
            $previous,
            AdminApiLifecycle::providerFieldsAfterRestore(),
            $request
        );

        return $this->reader->show($providerId);
    }

    /** @return array<string,mixed> */
    public function softDelete(int $id, ?string $reason, Request $request): array
    {
        $reason = trim((string) $reason);
        if (strlen($reason) < 3) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['reason' => ['A deletion reason of at least 3 characters is required.']]
            );
        }

        $scoped = $this->findScoped($id);
        $previous = $this->auditSnapshot($scoped);
        $providerId = (int) $scoped['provider_id'];
        $listingId = (int) $scoped['listing_id'];
        $now = date('Y-m-d H:i:s');

        Provider::delete($providerId);
        Database::query(
            'UPDATE provider_brand_listings SET deleted_at = ?, updated_at = ? WHERE id = ?',
            [$now, $now, $listingId]
        );

        AdminApiAudit::record(
            'provider.deleted',
            'provider',
            $providerId,
            $previous,
            ['reason' => $reason],
            $request
        );

        return [
            'id' => (string) $providerId,
            'deleted' => true,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function findScoped(int $id, bool $includeDeleted = false): array
    {
        $brandId = AdminApiBrandScope::brandId();
        $providerClause = $includeDeleted ? '' : ' AND p.deleted_at IS NULL';
        $listingClause = $includeDeleted ? '' : ' AND pbl.deleted_at IS NULL';

        $row = Database::selectOne(
            'SELECT p.id AS provider_id, p.business_name, p.status AS provider_status, p.deleted_at AS provider_deleted_at, '
            . 'pbl.id AS listing_id, pbl.display_name, pbl.status AS listing_status, pbl.search_visible, pbl.deleted_at AS listing_deleted_at '
            . 'FROM providers p '
            . 'INNER JOIN provider_brand_listings pbl ON pbl.provider_id = p.id AND pbl.brand_id = ? '
            . 'WHERE p.id = ?' . $providerClause . $listingClause,
            [$brandId, $id]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $scoped
     * @return array<string,mixed>
     */
    private function auditSnapshot(array $scoped): array
    {
        return [
            'provider_status' => (string) ($scoped['provider_status'] ?? ''),
            'listing_status' => (string) ($scoped['listing_status'] ?? ''),
            'search_visible' => (int) ($scoped['search_visible'] ?? 0),
            'display_name' => (string) ($scoped['display_name'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $updates
     * @return array<string,mixed>
     */
    private function validateProviderPatch(array $updates): array
    {
        $validated = [];
        $errors = [];

        if (array_key_exists('business_name', $updates)) {
            $name = trim((string) $updates['business_name']);
            if ($name === '') {
                $errors['business_name'][] = 'Business name cannot be empty.';
            } else {
                $validated['business_name'] = $name;
            }
        }

        foreach (['contact_name', 'email', 'phone', 'public_email', 'public_phone', 'website', 'description', 'street_address'] as $field) {
            if (array_key_exists($field, $updates)) {
                $validated[$field] = $this->nullableString($updates[$field]);
            }
        }

        if (array_key_exists('service_model', $updates)) {
            $model = strtolower(trim((string) $updates['service_model']));
            if (!in_array($model, self::SERVICE_MODELS, true)) {
                $errors['service_model'][] = 'Service model must be mobile, workshop, or both.';
            } else {
                $validated['service_model'] = $model;
            }
        }

        foreach (['base_town_id', 'region_id'] as $field) {
            if (array_key_exists($field, $updates)) {
                $validated[$field] = $this->nullablePositiveInt($updates[$field]);
            }
        }

        if ($errors !== []) {
            throw new AdminApiException(422, 'validation_failed', 'Validation failed.', $errors);
        }

        return $validated;
    }

    /**
     * @param array<string,mixed> $updates
     * @return array<string,mixed>
     */
    private function validateListingPatch(array $updates): array
    {
        $validated = [];
        $errors = [];

        if (array_key_exists('display_name', $updates)) {
            $name = trim((string) $updates['display_name']);
            if ($name === '') {
                $errors['display_name'][] = 'Display name cannot be empty.';
            } else {
                $validated['display_name'] = $name;
            }
        }

        if (array_key_exists('search_visible', $updates)) {
            $validated['search_visible'] = $this->boolish($updates['search_visible']) ? 1 : 0;
        }

        if (array_key_exists('listing_status', $updates)) {
            $status = strtolower(trim((string) $updates['listing_status']));
            if (!in_array($status, AdminApiLifecycle::PROVIDER_STATUSES, true)) {
                $errors['listing_status'][] = 'Listing status is not recognised.';
            } else {
                $validated['status'] = $status;
            }
        }

        if ($errors !== []) {
            throw new AdminApiException(422, 'validation_failed', 'Validation failed.', $errors);
        }

        return $validated;
    }

    private function uniqueProviderSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-') ?: 'provider';
        $slug = $base;
        $i = 2;
        while ((int) Database::scalar('SELECT COUNT(*) FROM providers WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueBrandSlug(int $brandId, string $name, int $providerId): string
    {
        $existing = Database::selectOne(
            'SELECT slug FROM provider_brand_listings WHERE brand_id = ? AND provider_id = ?',
            [$brandId, $providerId]
        );
        if ($existing) {
            return (string) $existing['slug'];
        }

        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-') ?: 'provider';
        $slug = $base;
        $i = 2;
        while ((int) Database::scalar(
            'SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id = ? AND slug = ?',
            [$brandId, $slug]
        ) > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function serviceModel(mixed $value): string
    {
        $model = strtolower(trim((string) $value));
        if (!in_array($model, self::SERVICE_MODELS, true)) {
            return 'mobile';
        }

        return $model;
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

    private function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
