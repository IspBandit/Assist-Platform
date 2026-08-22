<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Api\AdminApiContext;
use App\Services\Api\AdminApiScopes;

/**
 * Bridge HTML admin sessions into Admin API services without duplicating logic.
 */
final class AdminApiHtmlBridge
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        $user = current_user();
        if ($user === null) {
            throw new \RuntimeException('Authenticated administrator required.');
        }

        AdminApiContext::setUser($user, AdminApiScopes::ALL, 'html-admin');
        try {
            return $callback();
        } finally {
            AdminApiContext::clear();
        }
    }
}
