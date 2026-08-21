<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class FacebookPagePublisher
{
    public static function configured(string $brandKey): bool
    {
        $page = (array) config('social.facebook.pages.' . $brandKey, []);
        return trim((string) ($page['page_id'] ?? '')) !== ''
            && trim((string) ($page['access_token'] ?? '')) !== '';
    }

    /** @param array<string,mixed> $asset @return array{post_id:string} */
    public static function publish(string $brandKey, array $asset): array
    {
        if (($asset['platform'] ?? '') !== 'facebook' || ($asset['status'] ?? '') !== 'approved') {
            throw new RuntimeException('Only approved Facebook assets can be published.');
        }
        if (!self::configured($brandKey)) {
            throw new RuntimeException('Connect this brand’s Facebook Page before publishing.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The server cURL extension is required for Facebook publishing.');
        }

        $page = (array) config('social.facebook.pages.' . $brandKey, []);
        $version = preg_replace('/[^A-Za-z0-9.]/', '', (string) config('social.facebook.graph_version', 'v24.0')) ?: 'v24.0';
        $pageId = trim((string) $page['page_id']);
        $token = trim((string) $page['access_token']);
        $path = FileStorage::pathForRead('social_media_assets', (string) $asset['image_path']);
        $endpoint = 'https://graph.facebook.com/' . $version . '/' . rawurlencode($pageId) . '/photos';

        $handle = curl_init($endpoint);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialise Facebook publishing.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POSTFIELDS => [
                'message' => (string) $asset['caption'],
                'source' => new \CURLFile($path, 'image/png', basename($path)),
                'access_token' => $token,
            ],
        ]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $transportError = curl_error($handle);
        curl_close($handle);

        if (!is_string($raw) || $raw === '' || $transportError !== '') {
            throw new RuntimeException('Facebook could not be reached. Try again after checking the connection.');
        }
        $payload = json_decode($raw, true);
        $postId = is_array($payload) ? (string) ($payload['post_id'] ?? $payload['id'] ?? '') : '';
        if ($status < 200 || $status >= 300 || $postId === '') {
            $message = is_array($payload) ? trim((string) ($payload['error']['message'] ?? '')) : '';
            throw new RuntimeException('Facebook rejected the post' . ($message !== '' ? ': ' . mb_substr($message, 0, 220) : '.'));
        }

        return ['post_id' => $postId];
    }
}
