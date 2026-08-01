<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Request-scoped Admin API actor (set by bearer middleware).
 */
final class AdminApiContext
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    /** @var array<string,mixed>|null */
    private static ?array $client = null;

    /** @var list<string> */
    private static array $scopes = [];

    private static ?string $accessTokenId = null;

    private static ?string $actorType = null;

    /**
     * @param array<string,mixed> $user
     * @param list<string> $scopes
     */
    public static function setUser(array $user, array $scopes, string $accessTokenId): void
    {
        self::$user = $user;
        self::$client = null;
        self::$scopes = array_values($scopes);
        self::$accessTokenId = $accessTokenId;
        self::$actorType = 'user';
    }

    /**
     * @param array<string,mixed> $client
     * @param list<string> $scopes
     */
    public static function setService(array $client, array $scopes, string $accessTokenId): void
    {
        self::$client = $client;
        self::$user = null;
        self::$scopes = array_values($scopes);
        self::$accessTokenId = $accessTokenId;
        self::$actorType = 'service';
    }

    public static function clear(): void
    {
        self::$user = null;
        self::$client = null;
        self::$scopes = [];
        self::$accessTokenId = null;
        self::$actorType = null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        return self::$user;
    }

    /** @return array<string,mixed>|null */
    public static function client(): ?array
    {
        return self::$client;
    }

    public static function userId(): ?int
    {
        return self::$user !== null ? (int) self::$user['id'] : null;
    }

    public static function clientId(): ?string
    {
        return self::$client !== null ? (string) self::$client['id'] : null;
    }

    /** @return list<string> */
    public static function scopes(): array
    {
        return self::$scopes;
    }

    public static function hasScope(string $scope): bool
    {
        return in_array($scope, self::$scopes, true);
    }

    public static function hasAnyScope(string ...$scopes): bool
    {
        foreach ($scopes as $scope) {
            if (self::hasScope($scope)) {
                return true;
            }
        }

        return false;
    }

    public static function accessTokenId(): ?string
    {
        return self::$accessTokenId;
    }

    public static function actorType(): ?string
    {
        return self::$actorType;
    }

    public static function isHuman(): bool
    {
        return self::$actorType === 'user';
    }

    public static function isService(): bool
    {
        return self::$actorType === 'service';
    }
}
