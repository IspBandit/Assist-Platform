<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is available only from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Models\User;
use App\Services\DemoSeeder;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$environment = (string) Config::get('app.env', 'production');
if (!in_array($environment, ['local', 'test'], true)) {
    fwrite(STDERR, "Refusing to create review access outside a local or test environment.\n");
    exit(1);
}

try {
    (new DemoSeeder())->seed();
    $email = 'provider-review@assist.test';
    $password = 'Review-' . bin2hex(random_bytes(8)) . '!';
    $user = Database::selectOne('SELECT id FROM users WHERE email=? AND deleted_at IS NULL', [$email]);
    if ($user === null) {
        $userId = User::create([
            'name' => 'Local Provider Reviewer',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    } else {
        $userId = (int) $user['id'];
        Database::query('UPDATE users SET password_hash=?,status=\'active\',email_verified_at=COALESCE(email_verified_at,NOW()),updated_at=NOW() WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
    }
    User::assignRoleBySlug($userId, 'provider');

    $provider = Database::selectOne("SELECT id,slug,business_name FROM providers WHERE slug='demo-coastal-caravan-care' AND is_demo=1");
    if ($provider === null) {
        throw new RuntimeException('The labelled demo provider could not be created.');
    }
    $providerId = (int) $provider['id'];
    Database::query('UPDATE providers SET user_id=?,updated_at=NOW() WHERE id=?', [$userId, $providerId]);

    $brands = Database::select('SELECT id,brand_key FROM brands ORDER BY id');
    foreach ($brands as $brand) {
        Database::query(
            "INSERT IGNORE INTO provider_brand_listings (brand_id,provider_id,slug,display_name,status,is_featured,is_verified,search_visible,created_at,updated_at) VALUES (?,?,?,?,'active',0,1,1,NOW(),NOW())",
            [(int) $brand['id'], $providerId, (string) $provider['slug'], (string) $provider['business_name']]
        );
    }

    echo "Local provider review access created.\n\n";
    echo "Email: {$email}\n";
    echo "Password: {$password}\n\n";
    echo "Open each site and sign in with the same local-only account:\n";
    echo "  VanAssist:    http://vanassist.test/provider\n";
    echo "  TowSmart:     http://towsmart.test/provider\n";
    echo "  TrailerWise:  http://trailerwise.test/provider\n";
    echo "  LocalTorque:  http://localtorque.test/provider\n\n";
    echo "Running this command again rotates the local review password.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Unable to create local provider review access: ' . $exception->getMessage() . "\n");
    exit(1);
}
