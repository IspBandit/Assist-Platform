<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class MotorsportDocument extends Model
{
    protected static string $table = 'motorsport_documents';

    /**
     * @param array{jurisdiction?:string,family?:string,rule_type?:string,q?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function publicLibrary(array $filters): array
    {
        $where = ["d.is_public=1", "d.publication_status='current'"];
        $params = [];

        if (($filters['jurisdiction'] ?? '') !== '') {
            $where[] = 'JSON_CONTAINS(d.jurisdictions_json, JSON_QUOTE(?))';
            $params[] = $filters['jurisdiction'];
        }
        if (($filters['family'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM motorsport_document_families df WHERE df.document_id=d.id AND df.family_key=?)';
            $params[] = $filters['family'];
        }
        if (($filters['rule_type'] ?? '') !== '') {
            $where[] = 'JSON_CONTAINS(d.rule_types_json, JSON_QUOTE(?))';
            $params[] = $filters['rule_type'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(d.title LIKE ? OR d.summary LIKE ? OR a.name LIKE ?)';
            $needle = '%' . $filters['q'] . '%';
            array_push($params, $needle, $needle, $needle);
        }

        return Database::select(
            'SELECT d.*,a.name AS authority_name,a.official_url AS authority_url,'
            . '(SELECT GROUP_CONCAT(df.family_key ORDER BY df.family_key SEPARATOR ",") FROM motorsport_document_families df WHERE df.document_id=d.id) AS family_keys '
            . 'FROM motorsport_documents d INNER JOIN motorsport_authorities a ON a.id=d.authority_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . "ORDER BY FIELD(d.document_level,'national','state','category','event'),a.name,d.title",
            $params
        );
    }

    /** @return array<string,int> */
    public static function coverage(): array
    {
        $rows = Database::select(
            "SELECT j.code,COUNT(DISTINCT d.id) AS total FROM motorsport_documents d "
            . "JOIN (SELECT 'AUS' code UNION ALL SELECT 'ACT' UNION ALL SELECT 'NSW' UNION ALL SELECT 'VIC' UNION ALL SELECT 'QLD' UNION ALL SELECT 'SA' UNION ALL SELECT 'WA' UNION ALL SELECT 'TAS' UNION ALL SELECT 'NT') j "
            . "ON JSON_CONTAINS(d.jurisdictions_json,JSON_QUOTE(j.code)) "
            . "WHERE d.is_public=1 AND d.publication_status='current' GROUP BY j.code"
        );
        $coverage = [];
        foreach ($rows as $row) {
            $coverage[(string) $row['code']] = (int) $row['total'];
        }

        return $coverage;
    }
}
