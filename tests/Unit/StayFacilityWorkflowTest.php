<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Services\StayFacilityService;
use PHPUnit\Framework\TestCase;

final class StayFacilityWorkflowTest extends TestCase
{
    public function testSpecificGovernmentFactBeatsGenericNoFacilitiesSummary(): void
    {
        $facts=(new StayFacilityService())->resolve([
            ['id'=>1,'facility_type'=>'dump_point','facility_status'=>'no','source_type'=>'government','source_specificity'=>'generic','source_confidence'=>100,'source_name'=>'Booking summary','verified_at'=>'2026-08-01'],
            ['id'=>2,'facility_type'=>'dump_point','facility_status'=>'yes','source_type'=>'government','source_specificity'=>'facility','source_confidence'=>100,'source_name'=>'Official campground map','verified_at'=>'2026-07-01'],
        ]);
        self::assertSame('yes',$facts['dump_point']['facility_status']);
    }

    public function testAuthorityBeatsApprovedUserAndWaterRetainsTreatmentMeaning(): void
    {
        $facts=(new StayFacilityService())->resolve([
            ['id'=>1,'facility_type'=>'water','facility_status'=>'yes','facility_value'=>'potable','source_type'=>'user_approved','source_specificity'=>'facility','source_confidence'=>90,'source_name'=>'Community','verified_at'=>'2026-08-01'],
            ['id'=>2,'facility_type'=>'water','facility_status'=>'conditional','facility_value'=>'untreated','source_type'=>'government','source_specificity'=>'facility','source_confidence'=>100,'source_name'=>'Queensland Parks','verified_at'=>'2026-07-01'],
        ]);
        self::assertSame('Available — treat before drinking',$facts['water']['display']);
    }

    public function testFacilitySearchSynonymsRemainStructured(): void
    {
        $engine=new IntentRuleEngine();
        foreach(['cassette disposal near me','portable toilet waste disposal near me','black water dump near me'] as $query){
            self::assertContains('dump_point',$engine->interpret($query)->facilityTypeKeys,$query);
        }
        foreach(['untreated water near me','water I need to treat before drinking near me','potable water near me'] as $query){
            self::assertContains('drinking_water',$engine->interpret($query)->facilityTypeKeys,$query);
        }
    }

    public function testModerationAndPublicRoutesAreSeparated(): void
    {
        $root=dirname(__DIR__,2);
        $web=(string)file_get_contents($root.'/routes/web.php');
        $admin=(string)file_get_contents($root.'/routes/admin.php');
        $service=(string)file_get_contents($root.'/app/Services/StayFacilityService.php');
        self::assertStringContainsString('public.facility-contribution,8,3600,3600',$web);
        self::assertStringNotContainsString('moderate',$web);
        self::assertStringContainsString('/facility-contributions/moderate',$admin);
        self::assertStringContainsString("'user_approved'",$service);
        self::assertStringContainsString('facility_moderation_actions',$service);
    }

    public function testGriffithsCreekRegressionUsesNormalEvidencePipeline(): void
    {
        $root=dirname(__DIR__,2);
        $migration=(string)file_get_contents($root.'/database/migrations/128_stay_facility_enrichment_and_contributions.sql');
        self::assertStringContainsString("'dump_point'",$migration);
        self::assertStringContainsString("'untreated'",$migration);
        self::assertStringContainsString("'toilets', 'no'",$migration);
        self::assertStringNotContainsString('Griffiths Creek', (string)file_get_contents($root.'/app/Platform/AiSearch/Adapters/StaySearchAdapter.php'));
    }

    public function testGriffithsCreekDuplicateMergeIsAuditableAndImportSafe(): void
    {
        $root=dirname(__DIR__,2);
        $migration=(string)file_get_contents($root.'/database/migrations/129_merge_duplicate_stays.sql');
        $seeder=(string)file_get_contents($root.'/scripts/seed-stays.php');

        self::assertStringContainsString('caravan_park_source_aliases',$migration);
        self::assertStringContainsString('idx_stay_duplicate_identity',$migration);
        $migrator=(string)file_get_contents($root.'/app/Services/Migrator.php');
        self::assertStringContainsString('repairInterruptedDuplicateStayMigration',$migrator);
        self::assertStringContainsString('0cb77da02fef070256fa587e101f01924d7357c53d0e18a05d861ad9a323b05a',$migrator);
        self::assertStringContainsString('49ce0a65e93c60ae91440f33edf35d4f29531a1e8dd70d7b4b8c7f8eb64b002e',$migrator);
        self::assertStringContainsString("'same_name_state_within_2km'",$migration);
        self::assertStringContainsString("'stay.duplicate_merged'",$migration);
        self::assertStringContainsString('p.deleted_at=NOW()',$migration);
        self::assertStringContainsString('UPDATE stay_facility_claims x',$migration);
        self::assertStringContainsString('caravan_park_source_aliases', $seeder);
        self::assertStringNotContainsString('deleted_at=', $seeder, 'OSM refreshes must not reactivate a merged source row.');
    }
}
