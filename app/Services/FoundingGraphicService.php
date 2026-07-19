<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * Free founding ad graphic for providers in launch towns who claim and verify.
 */
final class FoundingGraphicService
{
    public const TYPE = 'founding_graphic';

    public static function schemaReady(): bool
    {
        return Database::tableExists('provider_promotions');
    }

    /** @return array<string,mixed>|null */
    public static function forProvider(int $providerId): ?array
    {
        if (!self::schemaReady()) {
            return null;
        }

        return Database::selectOne(
            'SELECT * FROM provider_promotions WHERE provider_id = ? AND promotion_type = ?',
            [$providerId, self::TYPE]
        ) ?: null;
    }

    /**
     * Whether the provider's base town is a launch town.
     *
     * @param array<string,mixed>|null $provider
     */
    public static function isLaunchTownProvider(?array $provider): bool
    {
        if ($provider === null) {
            return false;
        }
        if (!empty($provider['is_launch_town'])) {
            return true;
        }
        $townId = (int) ($provider['base_town_id'] ?? 0);
        if ($townId <= 0) {
            return false;
        }

        return (int) Database::scalar('SELECT is_launch_town FROM towns WHERE id = ? AND is_active = 1', [$townId]) === 1;
    }

    /**
     * Record eligibility when a founding provider claims a listing in a launch town.
     */
    public static function grantEligibilityIfQualifies(int $providerId): bool
    {
        if (!self::schemaReady()) {
            return false;
        }

        $provider = Database::selectOne(
            'SELECT p.id, p.is_founding_provider, p.base_town_id, t.is_launch_town, t.name AS town_name '
            . 'FROM providers p LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'WHERE p.id = ? AND p.deleted_at IS NULL',
            [$providerId]
        );
        if ($provider === null || empty($provider['is_founding_provider']) || !self::isLaunchTownProvider($provider)) {
            return false;
        }

        if (self::forProvider($providerId) !== null) {
            return false;
        }

        Database::query(
            'INSERT INTO provider_promotions (provider_id, promotion_type, status, eligible_at, created_at) '
            . 'VALUES (?, ?, ?, NOW(), NOW())',
            [$providerId, self::TYPE, 'eligible']
        );

        return true;
    }

    /**
     * Notify the provider they can request their free graphic after verification.
     */
    public static function onVerified(int $providerId): void
    {
        if (!self::schemaReady()) {
            return;
        }

        $promo = self::forProvider($providerId);
        if ($promo === null || (string) $promo['status'] !== 'eligible') {
            return;
        }

        $provider = Database::selectOne(
            'SELECT p.business_name, p.email, p.public_email, t.name AS town_name, s.abbreviation AS state_abbr '
            . 'FROM providers p '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'WHERE p.id = ? AND p.is_verified = 1',
            [$providerId]
        );
        if ($provider === null) {
            return;
        }

        $email = strtolower(trim((string) ($provider['public_email'] ?: $provider['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $townLabel = trim((string) ($provider['town_name'] ?? ''));
        if ($townLabel !== '' && !empty($provider['state_abbr'])) {
            $townLabel .= ', ' . $provider['state_abbr'];
        }

        EmailQueue::queueTemplate('provider_founding_graphic_unlocked', $email, (string) $provider['business_name'], [
            'provider_name' => (string) $provider['business_name'],
            'town_name'     => $townLabel !== '' ? $townLabel : 'your area',
            'action_url'    => url('provider/promotion'),
        ]);
    }

    public static function canRequest(int $providerId): bool
    {
        $provider = Database::selectOne(
            'SELECT is_verified, is_founding_provider FROM providers WHERE id = ? AND deleted_at IS NULL',
            [$providerId]
        );
        if ($provider === null || empty($provider['is_verified']) || empty($provider['is_founding_provider'])) {
            return false;
        }

        $promo = self::forProvider($providerId);

        return $promo !== null && (string) $promo['status'] === 'eligible';
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function submitRequest(int $providerId, array $input, ?array $logoFile = null): void
    {
        if (!self::canRequest($providerId)) {
            throw new RuntimeException('Your free founding graphic is not available to request yet.');
        }

        $headline = trim((string) ($input['headline'] ?? ''));
        $tagline = trim((string) ($input['tagline'] ?? ''));
        if ($headline === '' || $tagline === '') {
            throw new RuntimeException('Please provide a headline and tagline for your ad.');
        }
        if (strlen($headline) > 120 || strlen($tagline) > 200) {
            throw new RuntimeException('Headline or tagline is too long.');
        }

        $logoPath = null;
        if ($logoFile !== null && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $meta = FileStorage::storeUpload(
                $logoFile,
                'provider_promotions',
                (array) config('uploads.allowed_image_mimes'),
                (int) config('uploads.max_image_mb', 8) * 1024 * 1024
            );
            $logoPath = $meta['stored_name'];
        }

        Database::query(
            'UPDATE provider_promotions SET status = ?, headline = ?, tagline = ?, brief_notes = ?, '
            . 'logo_path = COALESCE(?, logo_path), requested_at = NOW(), updated_at = NOW() '
            . 'WHERE provider_id = ? AND promotion_type = ? AND status = ?',
            [
                'requested',
                $headline,
                $tagline,
                trim((string) ($input['brief_notes'] ?? '')) ?: null,
                $logoPath,
                $providerId,
                self::TYPE,
                'eligible',
            ]
        );
    }

    public static function markInProgress(int $providerId): void
    {
        Database::query(
            "UPDATE provider_promotions SET status = 'in_progress', updated_at = NOW() "
            . 'WHERE provider_id = ? AND promotion_type = ? AND status = ?',
            [$providerId, self::TYPE, 'requested']
        );
    }

    /**
     * @return array{desktop:?string,mobile:?string}
     */
    public static function imagePaths(array $promo): array
    {
        $desktop = trim((string) ($promo['image_path_desktop'] ?? ''));
        $mobile = trim((string) ($promo['image_path_mobile'] ?? ''));
        // Legacy single-file column if present on older databases.
        if ($desktop === '' && !empty($promo['image_path'])) {
            $desktop = (string) $promo['image_path'];
        }
        if ($mobile === '' && $desktop !== '') {
            $mobile = $desktop;
        }

        return [
            'desktop' => $desktop !== '' ? $desktop : null,
            'mobile'  => $mobile !== '' ? $mobile : null,
        ];
    }

    /**
     * @return array{desktop:?string,mobile:?string}
     */
    public static function imageUrls(array $promo): array
    {
        $paths = self::imagePaths($promo);

        return [
            'desktop' => self::imageUrl($paths['desktop']),
            'mobile'  => self::imageUrl($paths['mobile']),
        ];
    }

    /** @return array<string,mixed>|null Delivered promotion suitable for public display. */
    public static function deliveredAd(int $providerId): ?array
    {
        $promo = self::forProvider($providerId);
        if ($promo === null || (string) $promo['status'] !== 'delivered') {
            return null;
        }
        $paths = self::imagePaths($promo);
        if ($paths['desktop'] === null && $paths['mobile'] === null) {
            return null;
        }

        return $promo;
    }

    /**
     * Responsive HTML for a delivered promotion (mobile vs desktop source).
     */
    public static function responsivePicture(array $promo, string $alt, string $class = 'provider-promo-ad'): string
    {
        $urls = self::imageUrls($promo);
        if ($urls['desktop'] === null && $urls['mobile'] === null) {
            return '';
        }

        $desktop = $urls['desktop'] ?? $urls['mobile'];
        $mobile = $urls['mobile'] ?? $urls['desktop'];

        $bp = (int) config('promotions.mobile_max_width_px', 719);
        $classAttr = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $altAttr = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        $mobileAttr = htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8');
        $desktopAttr = htmlspecialchars($desktop, ENT_QUOTES, 'UTF-8');
        $desktopW = (int) config('promotions.desktop.width', 1200);
        $desktopH = (int) config('promotions.desktop.height', 400);

        return '<picture class="' . $classAttr . '">'
            . '<source media="(max-width: ' . $bp . 'px)" srcset="' . $mobileAttr . '">'
            . '<img src="' . $desktopAttr . '" alt="' . $altAttr . '" loading="lazy" decoding="async" '
            . 'width="' . $desktopW . '" height="' . $desktopH . '">'
            . '</picture>';
    }

    public static function deliver(
        int $providerId,
        array $desktopFile,
        array $mobileFile,
        ?int $adminId = null,
        bool $featureProvider = true,
    ): void {
        if (!self::schemaReady()) {
            throw new RuntimeException('Promotion storage is not available.');
        }

        $promo = self::forProvider($providerId);
        if ($promo === null || !in_array((string) $promo['status'], ['requested', 'in_progress'], true)) {
            throw new RuntimeException('No pending graphic request for this provider.');
        }

        $maxBytes = (int) config('uploads.max_promotion_image_mb', 2) * 1024 * 1024;
        $mimes = (array) config('uploads.allowed_image_mimes');

        $desktopMeta = FileStorage::storeUpload($desktopFile, 'provider_promotions', $mimes, $maxBytes);
        $mobileMeta = FileStorage::storeUpload($mobileFile, 'provider_promotions', $mimes, $maxBytes);

        $paths = self::imagePaths($promo);
        if ($paths['desktop'] !== null) {
            FileStorage::delete('provider_promotions', $paths['desktop']);
        }
        if ($paths['mobile'] !== null && $paths['mobile'] !== $paths['desktop']) {
            FileStorage::delete('provider_promotions', $paths['mobile']);
        }

        Database::query(
            "UPDATE provider_promotions SET status = 'delivered', image_path_desktop = ?, image_path_mobile = ?, "
            . 'delivered_at = NOW(), delivered_by = ?, updated_at = NOW() WHERE provider_id = ? AND promotion_type = ?',
            [$desktopMeta['stored_name'], $mobileMeta['stored_name'], $adminId, $providerId, self::TYPE]
        );

        if ($featureProvider) {
            Database::query('UPDATE providers SET is_featured = 1, updated_at = NOW() WHERE id = ?', [$providerId]);
        }

        $provider = Database::selectOne(
            'SELECT business_name, email, public_email FROM providers WHERE id = ?',
            [$providerId]
        );
        if ($provider !== null) {
            $email = strtolower(trim((string) ($provider['public_email'] ?: $provider['email'] ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                EmailQueue::queueTemplate('provider_founding_graphic_delivered', $email, (string) $provider['business_name'], [
                    'provider_name' => (string) $provider['business_name'],
                    'action_url'    => url('provider/promotion'),
                    'image_url'     => self::imageUrl($desktopMeta['stored_name']) ?? '',
                    'image_url_mobile' => self::imageUrl($mobileMeta['stored_name']) ?? '',
                ]);
            }
        }
    }

    public static function imageUrl(?string $storedName): ?string
    {
        if ($storedName === null || $storedName === '') {
            return null;
        }

        return url('uploads-public/provider-promotions/' . rawurlencode($storedName));
    }

    /** HTML block for claim invite emails when the listing is in a launch town. */
    public static function claimInviteOfferLine(?string $townName): string
    {
        if ($townName === null || trim($townName) === '') {
            return '';
        }

        $label = htmlspecialchars(trim($townName), ENT_QUOTES, 'UTF-8');

        return '<p style="background:#eef9f7;border-left:4px solid #0f6e6e;padding:12px 16px;margin:16px 0">'
            . '<strong>Launch offer:</strong> Claim and verify your profile to receive '
            . '<strong>free local ad graphics</strong> (desktop + mobile, worth $99) shown to travellers searching near '
            . $label . '.</p>';
    }

    public static function claimInviteOfferText(?string $townName): string
    {
        if ($townName === null || trim($townName) === '') {
            return '';
        }

        return "\n\nLaunch offer: Claim and verify your profile to receive free local ad graphics "
            . '(desktop + mobile, worth $99) for travellers searching near ' . trim($townName) . '.';
    }

    /**
     * Dashboard summary for the provider UI.
     *
     * @return array<string,mixed>|null
     */
    public static function dashboardCard(int $providerId, ?array $provider): ?array
    {
        $promo = self::forProvider($providerId);
        if ($promo === null) {
            return null;
        }

        $status = (string) $promo['status'];
        $verified = !empty($provider['is_verified']);

        return [
            'status'      => $status,
            'verified'    => $verified,
            'can_request' => self::canRequest($providerId),
            'image_urls'  => self::imageUrls($promo),
            'headline'    => (string) ($promo['headline'] ?? ''),
            'tagline'     => (string) ($promo['tagline'] ?? ''),
        ];
    }

    /** @return array<string,int> */
    public static function statusCounts(): array
    {
        if (!self::schemaReady()) {
            return [];
        }

        $rows = Database::select(
            'SELECT status, COUNT(*) AS total FROM provider_promotions WHERE promotion_type = ? GROUP BY status',
            [self::TYPE]
        );
        $out = [
            'eligible'     => 0,
            'requested'    => 0,
            'in_progress'  => 0,
            'delivered'    => 0,
            'cancelled'    => 0,
        ];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }

        return $out;
    }

    public static function queueCount(): int
    {
        $counts = self::statusCounts();

        return ($counts['requested'] ?? 0) + ($counts['in_progress'] ?? 0);
    }

    /**
     * @param array{status?:string,q?:string} $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public static function listForAdmin(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        if (!self::schemaReady()) {
            return ['rows' => [], 'total' => 0];
        }

        $where = ['pr.promotion_type = ?'];
        $params = [self::TYPE];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'actionable') {
            $where[] = "pr.status IN ('requested', 'in_progress')";
        } elseif ($status !== '' && in_array($status, ['eligible', 'requested', 'in_progress', 'delivered', 'cancelled'], true)) {
            $where[] = 'pr.status = ?';
            $params[] = $status;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.business_name LIKE ? OR p.email LIKE ? OR pr.headline LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            'SELECT COUNT(*) FROM provider_promotions pr '
            . 'INNER JOIN providers p ON p.id = pr.provider_id AND p.deleted_at IS NULL '
            . 'WHERE ' . $whereSql,
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::select(
            'SELECT pr.*, p.business_name, p.slug, p.status AS provider_status, p.is_verified, p.is_featured, '
            . 'p.email AS provider_email, t.name AS town_name, s.abbreviation AS state_abbr '
            . 'FROM provider_promotions pr '
            . 'INNER JOIN providers p ON p.id = pr.provider_id AND p.deleted_at IS NULL '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'WHERE ' . $whereSql . ' '
            . 'ORDER BY FIELD(pr.status, \'requested\', \'in_progress\', \'eligible\', \'delivered\', \'cancelled\'), '
            . 'COALESCE(pr.requested_at, pr.eligible_at) DESC '
            . 'LIMIT ' . max(1, min(100, $perPage)) . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function findForAdmin(int $promotionId): ?array
    {
        if (!self::schemaReady()) {
            return null;
        }

        return Database::selectOne(
            'SELECT pr.*, p.business_name, p.slug, p.status AS provider_status, p.is_verified, p.is_featured, '
            . 'p.email AS provider_email, p.public_email, p.phone, p.id AS provider_id, '
            . 't.name AS town_name, s.abbreviation AS state_abbr, u.name AS delivered_by_name '
            . 'FROM provider_promotions pr '
            . 'INNER JOIN providers p ON p.id = pr.provider_id AND p.deleted_at IS NULL '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'LEFT JOIN users u ON u.id = pr.delivered_by '
            . 'WHERE pr.id = ? AND pr.promotion_type = ?',
            [$promotionId, self::TYPE]
        ) ?: null;
    }
}
