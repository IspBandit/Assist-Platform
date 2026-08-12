<?php

declare(strict_types=1);

/**
 * CLI migration runner. Usage (from project root):
 *   php scripts/migrate.php
 *
 * Works under cPanel "Setup Cron Job" or via SSH. Also callable in the browser
 * only through the installation wizard (never expose this script publicly).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\Migrator;
use App\Services\OrganisationOutreachImporter;
use App\Services\ProviderPackActivation;
use App\Services\RoadDistance\GoogleRoutesCredentialProvisioner;
use App\Services\RoadDistance\GoogleRoutesReleaseCredentialInput;
use App\Services\TownCoordinateActivation;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

try {
    $googleRoutesCredential = GoogleRoutesReleaseCredentialInput::read(STDIN);
    $migrator = new Migrator();
    if ($migrator->repairInterruptedDuplicateStayMigration()) {
        echo "Cleared the interrupted original migration 129 for its indexed retry.\n";
    }
    $ran = $migrator->run();
    if ($ran === []) {
        echo "Nothing to migrate. Database is up to date.\n";
    } else {
        echo "Applied migrations:\n";
        foreach ($ran as $name) {
            echo "  - {$name}\n";
        }
    }
    $townCoordinates = TownCoordinateActivation::afterMigrations();
    if (empty($townCoordinates['skipped'])) {
        echo 'Activated verified town coordinates: ' . (int) ($townCoordinates['updated'] ?? 0) . " rows.\n";
    }
    $providerPack = ProviderPackActivation::afterMigrations();
    if (empty($providerPack['skipped'])) {
        echo 'Activated authoritative provider pack: ' . (int) ($providerPack['total'] ?? 0) . " records.\n";
    }
    $organisations = OrganisationOutreachImporter::afterMigrations();
    echo 'Loaded PR outreach research: ' . (int) $organisations['imported'] . ' new, '
        . (int) $organisations['updated'] . ' refreshed, ' . (int) $organisations['held'] . " invalid held out.\n";
    if ($googleRoutesCredential !== null) {
        (new GoogleRoutesCredentialProvisioner())->provision($googleRoutesCredential);
        echo "Google Routes credential encrypted and provisioned from protected release input.\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    if ((string) Env::get('APP_ENV', 'production') !== 'production' && $e->getPrevious() !== null) {
        fwrite(STDERR, 'Cause: ' . $e->getPrevious()->getMessage() . "\n");
    }
    exit(1);
}
