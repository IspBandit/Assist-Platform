<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/** Monitors source-owned motorsport rule and calendar pages without copying them. */
final class MotorsportSourceMonitor
{
    /** @return array{checked:int,baseline:int,unchanged:int,changed:int,failed:int} */
    public function checkDue(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $sources = Database::select(
            "(SELECT 'document' source_type,id,COALESCE(download_url,source_url) source_url,content_hash,source_etag,source_last_modified,check_interval_hours FROM motorsport_documents WHERE is_public=1 AND publication_status='current' AND (next_check_at IS NULL OR next_check_at<=NOW())) "
            . "UNION ALL (SELECT 'venue',id,COALESCE(calendar_url,website_url,source_url),content_hash,source_etag,source_last_modified,check_interval_hours FROM motorsport_venues WHERE is_public=1 AND (next_check_at IS NULL OR next_check_at<=NOW())) "
            . 'ORDER BY source_type,id LIMIT ' . $limit
        );
        $summary = ['checked' => 0, 'baseline' => 0, 'unchanged' => 0, 'changed' => 0, 'failed' => 0];
        foreach ($sources as $source) {
            $result = $this->check($source);
            $summary['checked']++;
            $summary[$result]++;
        }
        return $summary;
    }

    /** @param array<string,mixed> $source */
    private function check(array $source): string
    {
        $status = null;
        $etag = null;
        $lastModified = null;
        try {
            $headers = ['User-Agent: LocalTorque-Motorsport-Monitor/1.0 (+official-source-integrity)', 'Accept: application/pdf,text/html,application/xhtml+xml;q=0.9,*/*;q=0.5'];
            if (!empty($source['source_etag'])) {
                $headers[] = 'If-None-Match: ' . $source['source_etag'];
            }
            if (!empty($source['source_last_modified'])) {
                $headers[] = 'If-Modified-Since: ' . $source['source_last_modified'];
            }
            $context = stream_context_create(['http' => ['method' => 'GET','timeout' => 25,'follow_location' => 1,'max_redirects' => 5,'ignore_errors' => true,'header' => implode("\r\n", $headers)]]);
            $body = file_get_contents((string) $source['source_url'], false, $context);
            /** @var array<int,string> $responseHeaders */
            $responseHeaders = function_exists('http_get_last_response_headers')
                ? (http_get_last_response_headers() ?: [])
                : (get_defined_vars()['http_response_header'] ?? []);
            foreach ($responseHeaders as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
                    $status = (int) $match[1];
                } elseif (stripos($header, 'ETag:') === 0) {
                    $etag = trim(substr($header, 5));
                } elseif (stripos($header, 'Last-Modified:') === 0) {
                    $lastModified = trim(substr($header, 14));
                }
            }
            if ($status === 304) {
                return $this->persist($source, 'unchanged', 304, (string) ($source['content_hash'] ?? ''), $etag, $lastModified, null);
            }
            if ($body === false || $status === null || $status < 200 || $status >= 300) {
                return $this->persist($source, 'failed', $status, null, $etag, $lastModified, 'Source returned HTTP ' . ($status ?? 'unknown'));
            }
            $hash = hash('sha256', $body);
            return $this->persist($source, self::classify((string) ($source['content_hash'] ?? ''), $hash), $status, $hash, $etag, $lastModified, null);
        } catch (Throwable $exception) {
            return $this->persist($source, 'failed', $status, null, $etag, $lastModified, mb_substr($exception->getMessage(), 0, 1000));
        }
    }

    public static function classify(string $previousHash, string $observedHash): string
    {
        if ($previousHash === '') {
            return 'baseline';
        }
        return hash_equals($previousHash, $observedHash) ? 'unchanged' : 'changed';
    }

    /** @param array<string,mixed> $source */
    private function persist(array $source, string $result, ?int $status, ?string $hash, ?string $etag, ?string $lastModified, ?string $error): string
    {
        $type = (string) $source['source_type'];
        $table = $type === 'document' ? 'motorsport_documents' : 'motorsport_venues';
        $hours = max(1, (int) $source['check_interval_hours']);
        Database::beginTransaction();
        try {
            Database::query('INSERT INTO motorsport_source_checks (source_type,source_id,checked_at,http_status,result,observed_hash,source_etag,source_last_modified,error_message) VALUES (?,?,NOW(),?,?,?,?,?,?)', [$type,(int) $source['id'],$status,$result,$hash,$etag,$lastModified,$error]);
            if ($result === 'changed') {
                $statusUpdate = $type === 'document' ? ",publication_status='review'" : ',is_public=0';
                Database::query("UPDATE {$table} SET change_detected_at=NOW(),last_checked_at=NOW(),next_check_at=DATE_ADD(NOW(),INTERVAL ? HOUR){$statusUpdate},updated_at=NOW() WHERE id=?", [$hours,(int) $source['id']]);
            } elseif ($result === 'failed') {
                Database::query("UPDATE {$table} SET last_checked_at=NOW(),next_check_at=DATE_ADD(NOW(),INTERVAL 6 HOUR),updated_at=NOW() WHERE id=?", [(int) $source['id']]);
            } else {
                Database::query("UPDATE {$table} SET content_hash=?,source_etag=COALESCE(?,source_etag),source_last_modified=COALESCE(?,source_last_modified),last_checked_at=NOW(),next_check_at=DATE_ADD(NOW(),INTERVAL ? HOUR),updated_at=NOW() WHERE id=?", [$hash,$etag,$lastModified,$hours,(int) $source['id']]);
            }
            Database::commit();
        } catch (Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
        return $result;
    }
}
