<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\SitemapController;
use App\Core\Database;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SitemapTownIndexabilityTest extends TestCase
{
    public function testTownNoindexOverridesLaunchAndFeaturedFlags(): void
    {
        $connection = new ReflectionProperty(Database::class, 'pdo');
        $previousConnection = $connection->getValue();
        $previousBrand = BrandContext::hasCurrent() ? BrandContext::current() : null;
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE towns (slug TEXT, updated_at TEXT, is_active INTEGER, noindex INTEGER, is_launch_town INTEGER, is_featured INTEGER)');
        $insert = $pdo->prepare('INSERT INTO towns VALUES (?, NULL, ?, ?, ?, ?)');
        $expected = [];
        foreach ([0, 1] as $active) {
            foreach ([0, 1] as $noindex) {
                foreach ([0, 1] as $launch) {
                    foreach ([0, 1] as $featured) {
                        $slug = "town-{$active}{$noindex}{$launch}{$featured}";
                        $insert->execute([$slug, $active, $noindex, $launch, $featured]);
                        $expected[$slug] = $active === 1 && $noindex === 0;
                    }
                }
            }
        }
        // Exercise conflicting URL identities in both insertion orders. An
        // inactive duplicate cannot be selected by the public town lookup.
        $duplicates = [
            'conflict-indexable-first' => [[1, 0, 1, 1], [1, 1, 0, 0]],
            'conflict-noindex-first' => [[1, 1, 0, 0], [1, 0, 1, 1]],
            'inactive-noindex-duplicate' => [[1, 0, 0, 0], [0, 1, 1, 1]],
            'both-indexable' => [[1, 0, 0, 0], [1, 0, 1, 1]],
            'both-noindex' => [[1, 1, 0, 0], [1, 1, 1, 1]],
        ];
        foreach ($duplicates as $slug => $rows) {
            foreach ($rows as $row) {
                $insert->execute([$slug, ...$row]);
            }
        }
        $expected += [
            'conflict-indexable-first' => false,
            'conflict-noindex-first' => false,
            'inactive-noindex-duplicate' => true,
            'both-indexable' => true,
            'both-noindex' => false,
        ];
        try {
            Database::setConnection($pdo);
            $brands = require base_path('config/brands.php');
            BrandContext::set(Brand::fromArray('vanassist', $brands['registry']['vanassist']));
            $response = (new SitemapController())->xml(new Request([], [], [], []));
            self::assertSame(200, $response->status());
            foreach ($expected as $slug => $included) {
                $entry = '<loc>' . url('towns/' . $slug) . '</loc>';
                self::assertSame($included, str_contains($response->content(), $entry), $slug);
                self::assertSame($included ? 1 : 0, substr_count($response->content(), $entry), 'Unique URL: ' . $slug);
            }
        } finally {
            $connection->setValue(null, $previousConnection);
            if ($previousBrand !== null) {
                BrandContext::set($previousBrand);
            } else {
                BrandContext::clear();
            }
        }
    }
}
