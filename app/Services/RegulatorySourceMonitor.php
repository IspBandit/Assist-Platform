<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Monitors official source bytes. A change puts the record into review; it
 * never rewrites legal summaries or silently declares changed material current.
 */
final class RegulatorySourceMonitor
{
    /** @return array{checked:int,baseline:int,unchanged:int,changed:int,failed:int} */
    public function checkDue(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $documents = Database::select(
            "SELECT * FROM regulatory_documents WHERE is_public = 1 "
            . "AND publication_status IN ('current','upcoming') "
            . 'AND (next_check_at IS NULL OR next_check_at <= NOW()) ORDER BY next_check_at, id LIMIT ' . $limit
        );
        $summary = ['checked' => 0, 'baseline' => 0, 'unchanged' => 0, 'changed' => 0, 'failed' => 0];
        foreach ($documents as $document) {
            $result = $this->check($document);
            $summary['checked']++;
            $summary[$result]++;
        }

        return $summary;
    }

    /** @param array<string,mixed> $document */
    private function check(array $document): string
    {
        $url = (string) ($document['download_url'] ?: $document['source_url']);
        $status = null;
        $etag = null;
        $lastModified = null;
        $hash = null;

        try {
            $headers = [
                'User-Agent: LocalTorque-Regulatory-Monitor/1.0 (+official-source-integrity)',
                'Accept: application/pdf,text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
            ];
            if (!empty($document['source_etag'])) {
                $headers[] = 'If-None-Match: ' . $document['source_etag'];
            }
            if (!empty($document['source_last_modified'])) {
                $headers[] = 'If-Modified-Since: ' . $document['source_last_modified'];
            }
            $context = stream_context_create(['http' => [
                'method' => 'GET', 'timeout' => 25, 'follow_location' => 1,
                'max_redirects' => 5, 'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ]]);
            $body = file_get_contents($url, false, $context);
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
                return $this->persist($document, 'unchanged', 304, (string) ($document['content_hash'] ?? ''), $etag, $lastModified, null);
            }
            if ($body === false || $status === null || $status < 200 || $status >= 300) {
                $error = 'Official source returned HTTP ' . ($status ?? 'unknown');
                return $this->persist($document, 'failed', $status, null, $etag, $lastModified, $error);
            }
            $hash = hash('sha256', $body);
            $result = self::classify((string) ($document['content_hash'] ?? ''), $hash);

            return $this->persist($document, $result, $status, $hash, $etag, $lastModified, null);
        } catch (Throwable $exception) {
            $error = mb_substr($exception->getMessage(), 0, 1000);
            return $this->persist($document, 'failed', $status, $hash, $etag, $lastModified, $error);
        }
    }

    public static function classify(string $previousHash, string $observedHash): string
    {
        if ($previousHash === '') {
            return 'baseline';
        }

        return hash_equals($previousHash, $observedHash) ? 'unchanged' : 'changed';
    }

    /** @param array<string,mixed> $document */
    private function persist(
        array $document,
        string $result,
        ?int $status,
        ?string $hash,
        ?string $etag,
        ?string $lastModified,
        ?string $error
    ): string {
        Database::beginTransaction();
        try {
            Database::query(
                'INSERT INTO regulatory_source_checks '
                . '(document_id, checked_at, http_status, result, observed_hash, source_etag, source_last_modified, error_message) '
                . 'VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)',
                [(int) $document['id'], $status, $result, $hash, $etag, $lastModified, $error]
            );
            $hours = max(1, (int) $document['check_interval_hours']);
            if ($result === 'changed') {
                Database::query(
                    "UPDATE regulatory_documents SET publication_status='review', change_detected_at=NOW(), "
                    . 'last_checked_at=NOW(), next_check_at=DATE_ADD(NOW(), INTERVAL ? HOUR), updated_at=NOW() WHERE id=?',
                    [$hours, (int) $document['id']]
                );
            } elseif ($result === 'failed') {
                Database::query(
                    'UPDATE regulatory_documents SET last_checked_at=NOW(), next_check_at=DATE_ADD(NOW(), INTERVAL 6 HOUR), updated_at=NOW() WHERE id=?',
                    [(int) $document['id']]
                );
            } else {
                Database::query(
                    'UPDATE regulatory_documents SET content_hash=?, source_etag=COALESCE(?,source_etag), '
                    . 'source_last_modified=COALESCE(?,source_last_modified), last_checked_at=NOW(), '
                    . 'next_check_at=DATE_ADD(NOW(), INTERVAL ? HOUR), updated_at=NOW() WHERE id=?',
                    [$hash, $etag, $lastModified, $hours, (int) $document['id']]
                );
            }
            Database::commit();
        } catch (Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }

        return $result;
    }
}
