<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use RuntimeException;

/**
 * Secure private file handling. Uploads are validated server-side (real MIME
 * via finfo, not the client-declared type), given random opaque names and
 * stored outside the web root. Files are only ever served back through an
 * authenticated controller after an ownership check.
 */
final class FileStorage
{
    /**
     * Validate and store an uploaded file under a configured private path key.
     *
     * @param array<string,mixed>   $file          A single $_FILES entry.
     * @param array<string,string>  $allowedMimes  mime => extension map.
     * @return array{stored_name:string,original_name:string,mime_type:string,file_size:int}
     *
     * @throws RuntimeException on any validation or move failure.
     */
    public static function storeUpload(array $file, string $pathKey, array $allowedMimes, int $maxBytes): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('The file is too large.');
        }
        if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('No valid file was uploaded.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new RuntimeException('The uploaded file is empty.');
        }
        if ($size > $maxBytes) {
            throw new RuntimeException('The file exceeds the maximum allowed size.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!isset($allowedMimes[$mime])) {
            throw new RuntimeException('That file type is not accepted.');
        }

        $dir = self::resolveDir($pathKey);
        $storedName = bin2hex(random_bytes(20)) . '.' . $allowedMimes[$mime];
        $destination = $dir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('The file could not be saved.');
        }
        @chmod($destination, 0640);

        return [
            'stored_name'   => $storedName,
            'original_name' => self::sanitiseName((string) ($file['name'] ?? $storedName)),
            'mime_type'     => $mime,
            'file_size'     => $size,
        ];
    }

    public static function delete(string $pathKey, string $storedName): void
    {
        $path = self::resolveDir($pathKey) . DIRECTORY_SEPARATOR . basename($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Build an authenticated download/stream response for a stored private file.
     * The caller MUST perform the ownership/authorisation check first.
     */
    public static function serve(string $pathKey, string $storedName, string $originalName, string $mime, bool $inline = true): Response
    {
        $path = self::resolveDir($pathKey) . DIRECTORY_SEPARATOR . basename($storedName);
        if (!is_file($path)) {
            return new Response('File not found.', 404);
        }

        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . self::sanitiseName($originalName) . '"';
        return (new Response((string) file_get_contents($path), 200))
            ->withHeader('Content-Type', $mime ?: 'application/octet-stream')
            ->withHeader('Content-Disposition', $disposition)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    private static function resolveDir(string $pathKey): string
    {
        $relative = (string) config('uploads.paths.' . $pathKey);
        if ($relative === '') {
            throw new RuntimeException('Unknown storage path.');
        }
        $dir = base_path($relative);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Storage directory could not be created.');
        }
        return $dir;
    }

    private static function sanitiseName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'file';
        return substr($name, 0, 120) ?: 'file';
    }
}
