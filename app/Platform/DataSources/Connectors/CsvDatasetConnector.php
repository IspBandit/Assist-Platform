<?php

declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use App\Platform\DataSources\FacilityTypeMapper;
use RuntimeException;

/**
 * CSV payload / upload connector for government datasets (DATA-012).
 * Request may include `payload` string or `path` to a local staged file.
 */
final class CsvDatasetConnector implements ConnectorInterface
{
    public function key(): string
    {
        return 'gov_csv';
    }

    public function search(array $request, array $credentials, array $settings = []): array
    {
        unset($credentials);
        $payload = (string) ($request['payload'] ?? '');
        if ($payload === '' && !empty($request['path']) && is_string($request['path']) && is_file($request['path'])) {
            $payload = (string) file_get_contents($request['path']);
        }
        if (trim($payload) === '') {
            throw new RuntimeException('CSV connector requires payload or path.');
        }
        // Government extracts can legitimately contain a whole state or nation.
        // Keep a hard ceiling, but do not silently truncate useful national data
        // at the former 1,000-row development limit.
        $limit = max(1, min(25000, (int) ($request['limit'] ?? 500)));
        $defaultType = FacilityTypeMapper::normalise((string) ($settings['default_facility_type'] ?? 'other_essential'));
        $nameCol = strtolower((string) ($settings['name_field'] ?? 'name'));
        $typeCol = strtolower((string) ($settings['type_field'] ?? 'facility_type'));
        $idCol = strtolower((string) ($settings['id_field'] ?? 'id'));
        $latCol = strtolower((string) ($settings['lat_field'] ?? 'latitude'));
        $lngCol = strtolower((string) ($settings['lng_field'] ?? 'longitude'));
        $addrCol = strtolower((string) ($settings['address_field'] ?? 'address'));
        $filterField = strtolower(trim((string) ($settings['filter_field'] ?? '')));
        $filterValue = strtolower(trim((string) ($settings['filter_value'] ?? '')));
        $filters = [];
        if (isset($settings['filters']) && is_array($settings['filters'])) {
            foreach ($settings['filters'] as $field => $value) {
                $field = strtolower(trim((string) $field));
                if ($field !== '') {
                    $filters[$field] = strtolower(trim((string) $value));
                }
            }
        }

        $payload = preg_replace('/^\xEF\xBB\xBF/', '', $payload) ?? $payload;
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('Unable to open CSV buffer.');
        }
        fwrite($fh, $payload);
        rewind($fh);
        $header = fgetcsv($fh, 0, ',', '"', '\\');
        if ($header === false || $header === [null]) {
            fclose($fh);
            return [];
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        $rows = [];
        $i = 0;
        while (($data = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if ($data === [null]) {
                continue;
            }
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = trim((string) ($data[$idx] ?? ''));
            }
            if (implode('', $row) === '') {
                continue;
            }
            if ($filterField !== '' && $filterValue !== '') {
                $actual = strtolower(trim((string) ($row[$filterField] ?? '')));
                if ($actual !== $filterValue) {
                    continue;
                }
            }
            foreach ($filters as $field => $expected) {
                $actual = strtolower(trim((string) ($row[$field] ?? '')));
                if ($actual !== $expected) {
                    continue 2;
                }
            }
            $name = $row[$nameCol] ?? $row['title'] ?? $row['facility'] ?? '';
            $externalId = $row[$idCol] ?? $row['facilityid'] ?? $row['external_id'] ?? '';
            if ($externalId === '') {
                $externalId = 'csv-' . md5(json_encode($row) ?: (string) $i);
            }
            if ($name === '') {
                continue;
            }
            $type = FacilityTypeMapper::normalise((string) ($row[$typeCol] ?? ''), $defaultType);
            $address = (string) ($row[$addrCol] ?? $row['address1'] ?? $row['formatted_address'] ?? '');
            $rows[] = [
                'external_id' => $externalId,
                'business_name' => $name,
                'name' => $name,
                'facility_type' => $type,
                'formatted_address' => $address,
                'locality' => (string) ($row['locality'] ?? $row['suburb'] ?? $row['town'] ?? ''),
                'latitude' => is_numeric($row[$latCol] ?? null) ? (float) $row[$latCol] : (is_numeric($row['lat'] ?? null) ? (float) $row['lat'] : null),
                'longitude' => is_numeric($row[$lngCol] ?? null) ? (float) $row[$lngCol] : (is_numeric($row['lng'] ?? null) ? (float) $row['lng'] : (is_numeric($row['lon'] ?? null) ? (float) $row['lon'] : null)),
                'source_url' => (string) ($settings['source_url'] ?? ''),
                'licence' => (string) ($settings['licence'] ?? ''),
                'attribution' => (string) ($settings['attribution'] ?? ''),
                'raw' => $row,
            ];
            $i++;
            if (count($rows) >= $limit) {
                break;
            }
        }
        fclose($fh);
        return $rows;
    }
}
