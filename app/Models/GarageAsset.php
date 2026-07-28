<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class GarageAsset
{
    /** @var array<string,string> */
    public const TYPES = [
        'car' => 'Car or ute',
        'motorcycle' => 'Motorcycle',
        'light_truck' => 'Light truck',
        'heavy_vehicle' => 'Heavy vehicle',
        'street_rod' => 'Street rod',
        'trailer' => 'Trailer',
        'caravan' => 'Caravan',
        'camper_trailer' => 'Camper trailer',
        'motorhome' => 'Motorhome',
        'boat_trailer' => 'Boat trailer',
        'horse_float' => 'Horse float',
        'other' => 'Other vehicle or towable',
    ];

    /** @var array<string,string> */
    public const JURISDICTIONS = [
        'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales',
        'NT' => 'Northern Territory',
        'QLD' => 'Queensland',
        'SA' => 'South Australia',
        'TAS' => 'Tasmania',
        'VIC' => 'Victoria',
        'WA' => 'Western Australia',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function forOwner(int $userId): array
    {
        return Database::select(
            'SELECT a.*, b.brand_key AS created_in_brand_key, b.name AS created_in_brand_name, '
            . '(SELECT COUNT(*) FROM garage_documents d WHERE d.garage_asset_id=a.id) AS document_count, '
            . '(SELECT MIN(d.expires_at) FROM garage_documents d WHERE d.garage_asset_id=a.id AND d.expires_at>=CURRENT_DATE) AS next_expiry '
            . 'FROM garage_assets a INNER JOIN brands b ON b.id=a.created_in_brand_id '
            . 'WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.created_at DESC',
            [$userId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function owned(int $assetId, int $userId): ?array
    {
        return Database::selectOne(
            'SELECT a.*, b.brand_key AS created_in_brand_key, b.name AS created_in_brand_name '
            . 'FROM garage_assets a INNER JOIN brands b ON b.id=a.created_in_brand_id '
            . 'WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL LIMIT 1',
            [$assetId, $userId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function documents(int $assetId): array
    {
        return Database::select(
            'SELECT * FROM garage_documents WHERE garage_asset_id=? '
            . 'ORDER BY CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END, expires_at, created_at DESC',
            [$assetId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function ownedDocument(int $documentId, int $userId): ?array
    {
        return Database::selectOne(
            'SELECT d.* FROM garage_documents d INNER JOIN garage_assets a ON a.id=d.garage_asset_id '
            . 'WHERE d.id=? AND a.user_id=? AND a.deleted_at IS NULL LIMIT 1',
            [$documentId, $userId]
        );
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? 'Vehicle or towable';
    }

    public static function rulesVehicle(string $type): string
    {
        return match ($type) {
            'motorcycle' => 'motorcycle',
            'heavy_vehicle' => 'heavy-vehicle',
            'trailer', 'boat_trailer', 'horse_float', 'caravan', 'camper_trailer' => 'trailer',
            default => 'car',
        };
    }
}
