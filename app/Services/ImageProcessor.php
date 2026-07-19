<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Secure image handling for customer request uploads. Validates the real MIME
 * type via finfo (never the client-declared type), re-encodes through GD to
 * strip EXIF/metadata and any embedded payloads, downsizes to a sane maximum,
 * generates a thumbnail, assigns random opaque names and stores everything in
 * the private storage area (outside the web root).
 */
final class ImageProcessor
{
    /**
     * @param array<string,mixed> $file A single $_FILES entry.
     * @return array{stored_name:string,thumb_name:string,mime_type:string,file_size:int,width:int,height:int}
     *
     * @throws RuntimeException on any validation/processing failure.
     */
    public static function process(array $file): array
    {
        if (!\function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Image processing is unavailable on this server.');
        }

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('The image is too large.');
        }
        if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The image could not be uploaded.');
        }

        $maxBytes = ((int) config('uploads.max_image_mb', 8)) * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('The image exceeds the maximum allowed size.');
        }

        $allowed = (array) config('uploads.allowed_image_mimes', []);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('That image type is not accepted (use JPG, PNG or WEBP).');
        }
        $ext = $allowed[$mime];

        // Inspect dimensions from the image header before GD allocates the
        // decoded pixel buffer. Compressed "image bombs" can be small on disk
        // while exhausting a PHP worker during imagecreatefrom*().
        $dimensions = @getimagesize($file['tmp_name']);
        if ($dimensions === false) {
            throw new RuntimeException('The image dimensions could not be read.');
        }
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        $headerMime = (string) ($dimensions['mime'] ?? '');
        $maxDimension = max(1, (int) config('uploads.image_max_dimension', 12000));
        $maxPixels = max(1, (int) config('uploads.image_max_pixels', 24000000));
        if ($width < 1 || $height < 1 || $headerMime !== $mime) {
            throw new RuntimeException('The image header is invalid or does not match its file type.');
        }
        if ($width > $maxDimension || $height > $maxDimension || ($width * $height) > $maxPixels) {
            throw new RuntimeException('The image dimensions are too large to process safely.');
        }

        $source = self::createFrom($mime, $file['tmp_name']);
        if ($source === null) {
            throw new RuntimeException('The image could not be read.');
        }

        $dir = self::resolveDir();
        $storedName = bin2hex(random_bytes(20)) . '.' . $ext;
        $thumbName  = bin2hex(random_bytes(20)) . '.' . $ext;

        try {
            [$w, $h] = self::saveResized($source, $mime, $dir . '/' . $storedName, (int) config('uploads.image_max_width', 1800));
            self::saveResized($source, $mime, $dir . '/' . $thumbName, (int) config('uploads.thumbnail_width', 480));
        } finally {
            imagedestroy($source);
        }

        return [
            'stored_name' => $storedName,
            'thumb_name'  => $thumbName,
            'mime_type'   => $mime,
            'file_size'   => (int) (filesize($dir . '/' . $storedName) ?: 0),
            'width'       => $w,
            'height'      => $h,
        ];
    }

    public static function delete(string ...$storedNames): void
    {
        $dir = self::resolveDir();
        foreach ($storedNames as $name) {
            if ($name === '') {
                continue;
            }
            $path = $dir . '/' . basename($name);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** @return \GdImage|null */
    private static function createFrom(string $mime, string $path)
    {
        $image = match ($mime) {
            'image/jpeg' => \function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png'  => \function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'image/webp' => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
        return $image instanceof \GdImage ? $image : null;
    }

    /**
     * Resize (only down) preserving aspect ratio and re-encode to strip metadata.
     *
     * @param \GdImage $source
     * @return array{0:int,1:int} resulting width/height
     */
    private static function saveResized($source, string $mime, string $destination, int $maxWidth): array
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $scale = $srcW > $maxWidth ? $maxWidth / $srcW : 1.0;
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor($dstW, $dstH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $transparent);
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $destination, 82),
            'image/png'  => imagepng($canvas, $destination, 6),
            'image/webp' => imagewebp($canvas, $destination, 82),
            default      => false,
        };
        imagedestroy($canvas);

        if ($ok === false) {
            throw new RuntimeException('The image could not be saved.');
        }
        @chmod($destination, 0640);
        return [$dstW, $dstH];
    }

    private static function resolveDir(): string
    {
        $relative = (string) config('uploads.paths.request_images', 'storage/private/request-images');
        $dir = base_path($relative);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Storage directory could not be created.');
        }
        return $dir;
    }
}
