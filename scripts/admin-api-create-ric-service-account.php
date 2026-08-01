<?php

declare(strict_types=1);

/**
 * Create a least-privilege Assist RIC Admin API service account (OPS-010 / DATA-011).
 *
 * Usage (from project root, staging/local only by default):
 *   php scripts/admin-api-create-ric-service-account.php --user-id=1
 *   php scripts/admin-api-create-ric-service-account.php --email=admin@example.test
 *
 * Prints client_key and client_secret once. Store them only in the Assist RIC
 * OS credential vault. Never commit the secret.
 *
 * Refuses APP_ENV=production unless --i-understand-production is passed.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Helpers\Env;
use App\Models\User;
use App\Services\Api\AdminApiContext;
use App\Services\Api\AdminApiScopes;
use App\Services\Api\AdminApiServiceAccountService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$options = getopt('', [
    'user-id::',
    'email::',
    'name::',
    'i-understand-production',
    'help',
]);

if (isset($options['help'])) {
    echo <<<TXT
Create an Assist RIC Admin API service account.

Options:
  --user-id=N     Acting administrator user id (must hold a manager role)
  --email=ADDR    Acting administrator email (alternative to --user-id)
  --name=LABEL    Service account name (default: Assist RIC)
  --i-understand-production
                  Required when APP_ENV=production
  --help

TXT;
    exit(0);
}

$environment = strtolower((string) Config::get('app.env', 'production'));
if ($environment === 'production' && !isset($options['i-understand-production'])) {
    fwrite(STDERR, "Refusing to create service accounts in production without --i-understand-production.\n");
    exit(1);
}

if (!(bool) Config::get('admin_api.enabled', false)) {
    fwrite(STDERR, "ADMIN_API_ENABLED is false. Enable the Admin API in .env before creating service accounts.\n");
    exit(1);
}

foreach (['api_oauth_clients', 'api_access_tokens', 'users'] as $table) {
    if (!Database::tableExists($table)) {
        fwrite(STDERR, "Missing table {$table}. Run php scripts/migrate.php first.\n");
        exit(1);
    }
}

$user = null;
if (isset($options['user-id']) && trim((string) $options['user-id']) !== '') {
    $user = User::find((int) $options['user-id']);
} elseif (isset($options['email']) && trim((string) $options['email']) !== '') {
    $user = User::findByEmail(strtolower(trim((string) $options['email'])));
} else {
    fwrite(STDERR, "Provide --user-id or --email for the acting administrator.\n");
    exit(1);
}

if ($user === null || ($user['deleted_at'] ?? null) !== null || ($user['status'] ?? '') === 'suspended') {
    fwrite(STDERR, "Acting administrator user not found or not active.\n");
    exit(1);
}

$roles = User::roleSlugs((int) $user['id']);
if (array_intersect($roles, AdminApiServiceAccountService::MANAGER_ROLES) === []) {
    fwrite(STDERR, "Acting user must hold administrator / super-administrator / platform-administrator.\n");
    exit(1);
}

$name = trim((string) ($options['name'] ?? 'Assist RIC'));
if ($name === '') {
    $name = 'Assist RIC';
}

$request = new Request(
    [],
    [],
    [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/cli/admin-api-create-ric-service-account',
        'REMOTE_ADDR' => '127.0.0.1',
    ],
    []
);

AdminApiContext::clear();
AdminApiContext::setUser($user, ['service_accounts:admin'], 'cli-bootstrap');

try {
    $created = (new AdminApiServiceAccountService())->create(
        [
            'name' => $name,
            'scopes' => AdminApiScopes::RIC_SERVICE,
            'status' => 'active',
        ],
        $request
    );
} catch (AdminApiException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    AdminApiContext::clear();
}

echo "Assist RIC service account created.\n";
echo "Store these values only in the Assist RIC OS credential vault.\n";
echo "Never commit them or paste them into chat logs.\n\n";
echo 'id:            ' . (string) ($created['id'] ?? '') . "\n";
echo 'name:          ' . (string) ($created['name'] ?? '') . "\n";
echo 'client_key:    ' . (string) ($created['client_key'] ?? '') . "\n";
echo 'client_secret: ' . (string) ($created['client_secret'] ?? '') . "\n";
echo 'scopes:        ' . implode(', ', AdminApiScopes::RIC_SERVICE) . "\n";
echo "\nRIC Settings:\n";
echo "  ASSIST_RIC_ADMIN_API_ENABLED=true\n";
echo "  ASSIST_RIC_ADMIN_API_BASE_URL=<your-host>/api/v1/admin\n";
echo "  Vault: admin_api_client_key / admin_api_client_secret\n";

exit(0);
