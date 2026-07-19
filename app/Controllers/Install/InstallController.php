<?php

declare(strict_types=1);

namespace App\Controllers\Install;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\DemoSeeder;
use App\Services\Migrator;
use App\Services\Seeder;
use PDO;
use Throwable;

/**
 * Installation wizard. Available only while no install lock exists.
 * Performs requirement checks, writes .env, runs migrations, seeds data
 * and creates the first super administrator.
 */
final class InstallController extends Controller
{
    public function welcome(Request $request): Response
    {
        return $this->view('install.welcome', [
            'title'        => 'Install VanAssist',
            'cardClass'    => 'install-card',
            'requirements' => $this->checkRequirements(),
            'allPassed'    => $this->allRequirementsPassed(),
        ]);
    }

    public function setupForm(Request $request): Response
    {
        if (!$this->allRequirementsPassed()) {
            return $this->redirect('install');
        }
        return $this->view('install.setup', [
            'title'     => 'Configure VanAssist',
            'cardClass' => 'install-card',
            'errors'    => Session::errors(),
        ]);
    }

    public function install(Request $request): Response
    {
        if (Kernel::isInstalled()) {
            return $this->redirect('admin');
        }

        $processLock = $this->acquireInstallProcessLock();
        if ($processLock === null) {
            Session::flashErrors(['general' => 'Another installation is already running. Please wait and try again.']);
            return $this->redirect('install/setup');
        }

        try {
            return $this->performInstall($request);
        } finally {
            $this->releaseInstallProcessLock($processLock);
        }
    }

    /** @param resource $processLock */
    private function releaseInstallProcessLock($processLock): void
    {
        flock($processLock, LOCK_UN);
        fclose($processLock);
        @unlink(base_path('storage/installing.lock'));
    }

    /** @return resource|null */
    private function acquireInstallProcessLock()
    {
        $handle = @fopen(base_path('storage/installing.lock'), 'c+');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    private function performInstall(Request $request): Response
    {
        // Re-check after acquiring the process lock to close concurrent setup races.
        if (Kernel::isInstalled()) {
            return $this->redirect('admin');
        }

        $errors = $this->validate($request);
        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($request->all());
            return $this->redirect('install/setup');
        }

        $db = [
            'host'     => trim((string) $request->input('db_host', 'localhost')),
            'port'     => (int) $request->input('db_port', 3306),
            'name'     => trim((string) $request->input('db_name')),
            'user'     => trim((string) $request->input('db_user')),
            'password' => (string) $request->input('db_password'),
        ];

        // 1. Test the database connection.
        try {
            $pdo = $this->connectToDatabase($db);
        } catch (Throwable $e) {
            Session::flashErrors(['db_host' => 'Could not connect to the database: ' . $e->getMessage()]);
            Session::flashInput($request->all());
            return $this->redirect('install/setup');
        }

        // 2. Write the .env file.
        try {
            $this->writeEnv($request, $db);
        } catch (Throwable $e) {
            Session::flashErrors(['general' => 'Could not write the .env file: ' . $e->getMessage()]);
            Session::flashInput($request->all());
            return $this->redirect('install/setup');
        }

        // 3. Point the app at the new connection and run migrations + seeds.
        Database::setConnection($pdo);
        Config::set('database.name', $db['name']);

        try {
            (new Migrator())->run();
            (new Seeder())->seedAll();
            if ($request->input('seed_demo') === '1') {
                (new DemoSeeder())->seed();
            }
            $this->createSuperAdmin($request);
        } catch (Throwable $e) {
            Session::flashErrors(['general' => 'Installation failed during setup: ' . $e->getMessage()]);
            Session::flashInput($request->all());
            return $this->redirect('install/setup');
        }

        // 4. Atomically lock the installer and verify persistence before
        // reporting success. A missing lock would expose setup again.
        $this->lockInstaller();

        Session::flash('success', 'VanAssist was installed successfully. Please sign in.');
        return $this->redirect('install/complete');
    }

    private function lockInstaller(): void
    {
        $path = base_path('storage/installed.lock');
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            if (is_file($path)) {
                return;
            }
            throw new \RuntimeException('Installation completed, but the installer lock could not be created.');
        }

        $written = fwrite($handle, 'Installed at ' . date('c') . PHP_EOL);
        $flushed = fflush($handle);
        fclose($handle);

        if ($written === false || !$flushed || !is_file($path)) {
            @unlink($path);
            throw new \RuntimeException('Installation completed, but the installer lock could not be persisted.');
        }
        @chmod($path, 0640);
    }

    public function complete(Request $request): Response
    {
        return $this->view('install.complete', ['title' => 'Installation complete']);
    }

    // ------------------------------------------------------------------

    private function connectToDatabase(array $db): PDO
    {
        $pdo = Database::testConnection($db);

        if ($db['name'] !== '') {
            // Ensure the schema database exists (it usually does on cPanel).
            $pdo->exec(
                'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '` '
                . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $pdo->exec('USE `' . str_replace('`', '', $db['name']) . '`');
        }
        return $pdo;
    }

    private function validate(Request $request): array
    {
        $errors = [];
        if ($request->input('db_name') === '' || $request->input('db_name') === null) {
            $errors['db_name'] = 'Database name is required.';
        }
        if ($request->input('db_user') === '' || $request->input('db_user') === null) {
            $errors['db_user'] = 'Database user is required.';
        }
        if (!filter_var($request->input('admin_email'), FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'A valid administrator email is required.';
        }
        if (strlen((string) $request->input('admin_password')) < 10) {
            $errors['admin_password'] = 'Administrator password must be at least 10 characters.';
        }
        if ($request->input('admin_password') !== $request->input('admin_password_confirm')) {
            $errors['admin_password_confirm'] = 'Passwords do not match.';
        }
        if (trim((string) $request->input('admin_name')) === '') {
            $errors['admin_name'] = 'Administrator name is required.';
        }
        if (!filter_var($request->input('app_url'), FILTER_VALIDATE_URL)) {
            $errors['app_url'] = 'A valid site URL is required (e.g. https://vanassist.example.com).';
        }
        return $errors;
    }

    private function writeEnv(Request $request, array $db): void
    {
        $appKey = base64_encode(random_bytes(32));

        $vars = [
            'APP_NAME'        => $request->input('site_name', 'VanAssist'),
            'APP_ENV'         => 'production',
            'APP_DEBUG'       => 'false',
            'APP_URL'         => rtrim((string) $request->input('app_url'), '/'),
            'APP_KEY'         => $appKey,
            'LAUNCH_MODE'     => $request->input('launch_mode', 'private'),
            'DB_HOST'         => $db['host'],
            'DB_PORT'         => (string) $db['port'],
            'DB_NAME'         => $db['name'],
            'DB_USER'         => $db['user'],
            'DB_PASSWORD'     => $db['password'],
            'SESSION_LIFETIME' => '120',
            'SESSION_SECURE'  => 'true',
            'MAIL_DRIVER'     => 'smtp',
            'MAIL_HOST'       => $request->input('mail_host', ''),
            'MAIL_PORT'       => $request->input('mail_port', '587'),
            'MAIL_USERNAME'   => $request->input('mail_username', ''),
            'MAIL_PASSWORD'   => $request->input('mail_password', ''),
            'MAIL_ENCRYPTION' => $request->input('mail_encryption', 'tls'),
            'MAIL_FROM_ADDRESS' => $request->input('mail_from_address', ''),
            'MAIL_FROM_NAME'  => $request->input('mail_from_name', 'VanAssist'),
            'TURNSTILE_ENABLED' => 'false',
            'TURNSTILE_SITE_KEY' => '',
            'TURNSTILE_SECRET_KEY' => '',
            'MAX_REQUEST_IMAGES' => '6',
            'MAX_IMAGE_UPLOAD_MB' => '8',
            'IMAGE_MAX_WIDTH' => '1800',
            'THUMBNAIL_WIDTH' => '480',
            'ENABLE_BILLING'  => 'false',
            'ENABLE_SMS'      => 'false',
            'ENABLE_REVIEWS'  => 'false',
            'ENABLE_PUBLIC_PHONE' => 'false',
            'BACKUP_RETENTION_DAILY' => '7',
            'BACKUP_RETENTION_WEEKLY' => '4',
            'BACKUP_RETENTION_MONTHLY' => '3',
            'LOGIN_MAX_ATTEMPTS' => '5',
            'LOGIN_LOCKOUT_MINUTES' => '15',
            'ADMIN_SESSION_TIMEOUT' => '30',
        ];

        $lines = ['# VanAssist environment - generated by the installer on ' . date('c'), ''];
        foreach ($vars as $key => $value) {
            $lines[] = $key . '=' . $this->envValue((string) $value);
        }
        $content = implode("\n", $lines) . "\n";

        $path = base_path('.env');
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Unable to write .env. Check folder permissions.');
        }
        @chmod($path, 0640);
    }

    private function envValue(string $value): string
    {
        return ($value !== '' && (str_contains($value, ' ') || str_contains($value, '#')))
            ? '"' . str_replace('"', '\"', $value) . '"'
            : $value;
    }

    private function createSuperAdmin(Request $request): void
    {
        $email = strtolower(trim((string) $request->input('admin_email')));
        $existing = User::findByEmail($email);
        if ($existing !== null) {
            User::assignRoleBySlug((int) $existing['id'], 'super-administrator');
            return;
        }

        $userId = User::create([
            'name'              => trim((string) $request->input('admin_name')),
            'email'             => $email,
            'password_hash'     => password_hash((string) $request->input('admin_password'), PASSWORD_DEFAULT),
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        User::assignRoleBySlug($userId, 'super-administrator');
    }

    /** @return array<int,array{label:string,passed:bool,detail:string}> */
    private function checkRequirements(): array
    {
        $writable = static function (string $rel): bool {
            $p = base_path($rel);
            return is_dir($p) && is_writable($p);
        };

        return [
            ['label' => 'PHP 8.1 or newer', 'passed' => PHP_VERSION_ID >= 80100, 'detail' => PHP_VERSION],
            ['label' => 'PDO MySQL extension', 'passed' => extension_loaded('pdo_mysql'), 'detail' => extension_loaded('pdo_mysql') ? 'enabled' : 'missing'],
            ['label' => 'mbstring extension', 'passed' => extension_loaded('mbstring'), 'detail' => extension_loaded('mbstring') ? 'enabled' : 'missing'],
            ['label' => 'JSON extension', 'passed' => extension_loaded('json'), 'detail' => extension_loaded('json') ? 'enabled' : 'missing'],
            ['label' => 'fileinfo extension', 'passed' => extension_loaded('fileinfo'), 'detail' => extension_loaded('fileinfo') ? 'enabled' : 'missing'],
            ['label' => 'GD extension (image processing)', 'passed' => extension_loaded('gd'), 'detail' => extension_loaded('gd') ? 'enabled' : 'recommended'],
            ['label' => 'Project root writable (.env)', 'passed' => is_writable(base_path()), 'detail' => is_writable(base_path()) ? 'writable' : 'not writable'],
            ['label' => 'storage/ writable', 'passed' => $writable('storage'), 'detail' => 'storage'],
            ['label' => 'storage/logs writable', 'passed' => $writable('storage/logs'), 'detail' => 'logs'],
            ['label' => 'storage/sessions writable', 'passed' => $writable('storage/sessions'), 'detail' => 'sessions'],
            ['label' => 'storage/cache writable', 'passed' => $writable('storage/cache'), 'detail' => 'cache'],
            ['label' => 'public/uploads-public writable', 'passed' => $writable('public/uploads-public'), 'detail' => 'uploads'],
        ];
    }

    private function allRequirementsPassed(): bool
    {
        foreach ($this->checkRequirements() as $req) {
            // GD is recommended, not mandatory.
            if (!$req['passed'] && !str_contains($req['label'], 'GD extension')) {
                return false;
            }
        }
        return true;
    }
}
