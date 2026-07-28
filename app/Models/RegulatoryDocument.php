<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RegulatoryDocument extends Model
{
    protected static string $table = 'regulatory_documents';

    /**
     * @param array{jurisdiction?:string,vehicle?:string,kind?:string,q?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function publicLibrary(int $brandId, array $filters): array
    {
        $where = [
            "d.is_public = 1",
            "d.official_document = 1",
            "d.publication_status IN ('current','upcoming')",
            'EXISTS (SELECT 1 FROM regulatory_document_brands db WHERE db.document_id=d.id AND db.brand_id=?)',
        ];
        $params = [$brandId];

        if (($filters['jurisdiction'] ?? '') !== '') {
            $where[] = 'd.jurisdiction_code = ?';
            $params[] = $filters['jurisdiction'];
        }
        if (($filters['vehicle'] ?? '') !== '') {
            $where[] = 'JSON_CONTAINS(d.vehicle_classes_json, JSON_QUOTE(?))';
            $params[] = $filters['vehicle'];
            if ($filters['vehicle'] === 'street-rod') {
                $where[] = "d.document_kind IN ('street_rods','registration')";
            } else {
                $where[] = "d.document_kind <> 'street_rods'";
            }
        }
        if (($filters['kind'] ?? '') !== '') {
            $where[] = 'd.document_kind = ?';
            $params[] = $filters['kind'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(d.title LIKE ? OR d.summary LIKE ? OR a.name LIKE ?)';
            $needle = '%' . $filters['q'] . '%';
            array_push($params, $needle, $needle, $needle);
        }

        return Database::select(
            'SELECT d.*, a.name AS authority_name, a.official_url AS authority_url '
            . 'FROM regulatory_documents d INNER JOIN regulatory_authorities a ON a.id = d.authority_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . "ORDER BY FIELD(d.jurisdiction_code,'AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'), "
            . "FIELD(d.publication_status,'current','upcoming'), d.title",
            $params
        );
    }

    /** @return array<string,int> */
    public static function publicCoverage(int $brandId): array
    {
        $rows = Database::select(
            "SELECT d.jurisdiction_code, COUNT(*) AS document_count FROM regulatory_documents d "
            . 'INNER JOIN regulatory_document_brands db ON db.document_id=d.id AND db.brand_id=? '
            . "WHERE d.is_public = 1 AND d.official_document = 1 AND d.publication_status IN ('current','upcoming') "
            . 'GROUP BY d.jurisdiction_code',
            [$brandId]
        );
        $coverage = [];
        foreach ($rows as $row) {
            $coverage[(string) $row['jurisdiction_code']] = (int) $row['document_count'];
        }

        return $coverage;
    }
}
