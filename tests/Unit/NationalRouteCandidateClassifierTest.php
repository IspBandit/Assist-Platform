<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NationalRouteCandidateClassifier;
use PHPUnit\Framework\TestCase;

final class NationalRouteCandidateClassifierTest extends TestCase
{
    public function testFuelTypeOutranksBroadDiscoverySuggestions(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name' => 'BP Highway Service Centre',
            'place_types' => ['gas_station'],
            'category_slugs' => ['caravan-repairs', 'fuel-station'],
            'phone' => '02 1234 5678',
            'website' => 'https://example.test',
            'business_status' => 'OPERATIONAL',
            'state' => 'NSW',
            'route_hubs' => ['Dubbo, NSW'],
        ]);

        self::assertSame('fuel-travel-stops', $result['category_key']);
        self::assertSame('pending', $result['review_status']);
        self::assertSame('NSW', $result['state']);
        self::assertSame('Dubbo, NSW', $result['route_hub']);
        self::assertGreaterThanOrEqual(75, $result['confidence']);
    }

    public function testCaravanBusinessGetsCaravanCategory(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name' => 'Outback Caravan and RV Repairs',
            'category_slugs' => ['mobile-mechanic'],
            'phone' => '0400 000 000',
            'website' => 'https://outback.example',
            'business_status' => 'OPERATIONAL',
        ]);

        self::assertSame('caravan-rv-repairs', $result['category_key']);
        self::assertSame('pending', $result['review_status']);
    }

    public function testContactlessCandidateIsHeld(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name' => 'Remote Mechanical',
            'category_slugs' => ['mobile-mechanic'],
            'business_status' => 'OPERATIONAL',
        ]);

        self::assertSame('held', $result['review_status']);
        self::assertStringContainsString('No phone or website', (string)$result['hold_reason']);
    }

    public function testLikelyRetailOnlyResultIsHeld(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name' => 'Supercheap Auto Example',
            'category_slugs' => ['auto-electrician'],
            'phone' => '07 1234 5678',
            'website' => 'https://example.test',
            'business_status' => 'OPERATIONAL',
        ]);

        self::assertSame('held', $result['review_status']);
        self::assertStringContainsString('retail-only', (string)$result['hold_reason']);
    }

    public function testBatteryRetailerIsHeldEvenWhenAutoElectricalIsSuggested(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name'=>'Battery World Example', 'phone'=>'07 0000 0000',
            'website'=>'https://example.test', 'business_status'=>'OPERATIONAL',
            'category_slugs'=>['auto-electrician'], 'state'=>'QLD',
        ]);
        self::assertSame('auto-electrical', $result['category_key']);
        self::assertSame('held', $result['review_status']);
    }

    public function testSpecificCaravanApplianceSignalWinsOverGenericCaravanSignal(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name'=>'Regional Caravan Refrigeration & Appliances',
            'phone'=>'07 0000 0000', 'website'=>'https://example.test',
            'business_status'=>'OPERATIONAL', 'state'=>'QLD',
        ]);
        self::assertSame('caravan-gas-appliances', $result['category_key']);
    }

    public function testEvSignalWinsForCombinedFuelAndChargingLocation(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name'=>'Highway Fuel and EV Charging',
            'place_types'=>['gas_station','electric_vehicle_charging_station'],
            'phone'=>'07 0000 0000', 'website'=>'https://example.test',
            'business_status'=>'OPERATIONAL', 'state'=>'QLD',
        ]);
        self::assertSame('ev-charging', $result['category_key']);
    }

    public function testNonOperationalCandidateIsHeld(): void
    {
        $result = (new NationalRouteCandidateClassifier())->classify([
            'business_name' => 'Old Tyre Service',
            'category_slugs' => ['tyres'],
            'phone' => '03 1234 5678',
            'website' => 'https://example.test',
            'business_status' => 'CLOSED_PERMANENTLY',
        ]);

        self::assertSame('tyres-wheels-bearings', $result['category_key']);
        self::assertSame('held', $result['review_status']);
        self::assertStringContainsString('operational', (string)$result['hold_reason']);
    }
}
