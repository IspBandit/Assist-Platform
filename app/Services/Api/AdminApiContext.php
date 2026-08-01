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
        self::$scopes = array_values($scopes);
        self::$accessTokenId = $accessTokenId;
        self::$actorType = 'user';
    }

    public static function clear(): void
    {
        self::$user = null;
        self::$scopes = [];
        self::$accessTokenId = null;
        self::$actorType = null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        return self::$user;
    }

    public static function userId(): ?int
    {
        return self::$user !== null ? (int) self::$user['id'] : null;
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

    public static function accessTokenId(): ?string
    {
        return self::$accessTokenId;
    }

    public static function actorType(): ?string
    {
        return self::$actorType;
    }
}
