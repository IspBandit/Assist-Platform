<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RegulatoryDocument;
use App\Models\Town;
use App\Services\ComplianceGuide;
use App\Services\RegulatorySponsor;
use App\Services\RegulatoryTaxonomy;

final class RegulatoryLibraryController extends Controller
{
    private const JURISDICTIONS = [
        'AUS' => 'Federal & national', 'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales', 'VIC' => 'Victoria', 'QLD' => 'Queensland',
        'SA' => 'South Australia', 'WA' => 'Western Australia',
        'TAS' => 'Tasmania', 'NT' => 'Northern Territory',
    ];

    private const VISUAL_VEHICLES = ['car', '4wd', 'light-truck', 'heavy-vehicle', 'motorcycle', 'street-rod'];

    public function index(Request $request): Response
    {
        $brand = current_brand();
        if (!in_array($brand->id(), ['vanassist', 'localtorque', 'towsmart', 'trailerwise'], true)) {
            $this->abort(404, 'Rules library not found.');
        }
        $vehicles = RegulatoryTaxonomy::vehiclesForBrand($brand->id());
        $kinds = RegulatoryTaxonomy::kindsForBrand($brand->id());

        $townId = max(0, (int) $request->query('town', 0));
        $location = trim((string) $request->query('location', ''));
        if ($location !== '') {
            $townMatches = Town::searchActive($location, 1);
            $townId = isset($townMatches[0]['id']) ? (int) $townMatches[0]['id'] : 0;
        }
        $filters = [
            'jurisdiction' => $this->allowed((string) $request->query('jurisdiction', ''), self::JURISDICTIONS),
            'vehicle' => $this->allowed((string) $request->query('vehicle', ''), $vehicles),
            'kind' => $this->allowed((string) $request->query('kind', ''), $kinds),
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'town' => $townId,
        ];
        $normalized = RegulatoryTaxonomy::normalize($filters['vehicle'], $filters['kind']);
        $filters['vehicle'] = $normalized['vehicle'];
        $filters['kind'] = $normalized['kind'];
        $sponsors = new RegulatorySponsor();
        $selectedTown = $sponsors->town($filters['town']);
        $page = $this->pageCopy($brand->id(), $brand->name(), $filters['vehicle']);
        $heroAsset = $brand->id() === 'localtorque' && in_array($filters['vehicle'], self::VISUAL_VEHICLES, true)
            ? 'rules-' . $filters['vehicle'] . '-hero'
            : 'rules-hero';

        return $this->view('localtorque.regulatory-library', [
            'title' => $page['title'],
            'metaDescription' => $page['metaDescription'],
            'canonical' => url('rules'),
            'documents' => RegulatoryDocument::publicLibrary($brand->databaseId(), $filters),
            'coverage' => RegulatoryDocument::publicCoverage($brand->databaseId()),
            'jurisdictions' => self::JURISDICTIONS,
            'vehicles' => $vehicles,
            'kinds' => $kinds,
            'filters' => $filters,
            'selectedTown' => $selectedTown,
            'page' => $page,
            'heroAsset' => $heroAsset,
            'sponsoredCampaigns' => $sponsors->campaigns(
                $selectedTown,
                $filters['jurisdiction'],
                $filters['kind'],
                $filters['vehicle'],
                $filters['q']
            ),
        ]);
    }

    public function guide(Request $request): Response
    {
        $selection = ComplianceGuide::selections(
            strtoupper(trim((string) $request->query('jurisdiction', ''))),
            trim((string) $request->query('vehicle', '')),
            trim((string) $request->query('intention', '')),
            current_brand()->id()
        );
        $documents = [];
        if ($selection !== null) {
            $filters = [
                'jurisdiction' => $selection['jurisdiction'],
                'vehicle' => $selection['vehicle'],
                'kind' => $selection['kind'],
                'q' => '',
            ];
            $documents = RegulatoryDocument::publicLibrary(current_brand()->databaseId(), $filters);
            if ($documents === [] && $selection['kind'] !== '') {
                $filters['kind'] = '';
                $documents = RegulatoryDocument::publicLibrary(current_brand()->databaseId(), $filters);
            }
        }

        return $this->view('localtorque.guided-rules', [
            'title' => 'Guided compliance check — ' . current_brand()->name(),
            'selection' => $selection,
            'documents' => $documents,
            'jurisdictions' => ComplianceGuide::JURISDICTIONS,
            'vehicles' => RegulatoryTaxonomy::vehiclesForBrand(current_brand()->id()),
            'intentions' => ComplianceGuide::INTENTIONS,
            'steps' => $selection === null ? [] : ComplianceGuide::steps($selection['intention'], $selection['vehicle']),
            'limitation' => ComplianceGuide::limitation(),
        ]);
    }

    /** @param array<string,string> $options */
    private function allowed(string $value, array $options): string
    {
        return isset($options[$value]) ? $value : '';
    }

    /** @return array{title:string,metaDescription:string,kicker:string,heading:string,intro:string,vehicleSummary:string} */
    private function pageCopy(string $brandId, string $brandName, string $vehicle = ''): array
    {
        if ($brandId === 'vanassist') {
            return [
                'title' => 'Australian caravan, motorhome and travel rules — VanAssist',
                'metaDescription' => 'Official Australian caravan, motorhome, camper trailer, towing, roadworthy and modification sources for safer travel.',
                'kicker' => 'VanAssist travel rules & safety',
                'heading' => 'Know the rules before the next road takes you across a border.',
                'intro' => 'Official caravan, motorhome, camper trailer, towing, loading, roadworthy and modification resources — organised by jurisdiction for Australian travellers.',
                'vehicleSummary' => 'Caravans, motorhomes, camper trailers and tow vehicles',
            ];
        }
        if ($brandId === 'towsmart') {
            return [
                'title' => 'Australian towing rules, loading and compliance — TowSmart',
                'metaDescription' => 'Official Australian towing, trailer, loading, mass, braking and coupling sources for safer tow-vehicle and trailer combinations.',
                'kicker' => 'TowSmart rules & compliance',
                'heading' => 'Check the rule behind every safer towing decision.',
                'intro' => 'Official towing, trailer, loading, braking, coupling and mass resources — organised by jurisdiction and linked to the authority that issued them.',
                'vehicleSummary' => 'Tow vehicles, trailers, caravans and heavy combinations',
            ];
        }
        if ($brandId === 'trailerwise') {
            return [
                'title' => 'Australian trailer construction, registration and inspection rules — TrailerWise',
                'metaDescription' => 'Official Australian trailer construction, registration, inspection, roadworthy and modification resources.',
                'kicker' => 'TrailerWise rules & compliance',
                'heading' => 'The official rules for building, registering and keeping a trailer roadworthy.',
                'intro' => 'Authoritative trailer construction, registration, inspection, towing and modification resources from national and jurisdiction authorities.',
                'vehicleSummary' => 'Trailers, caravans, campers and their tow combinations',
            ];
        }

        $vehicleJourneys = [
            'car' => ['Cars', 'Car roadworthy and modification rules', 'Know what applies before you modify or inspect your car.', 'Official roadworthy, inspection and modification sources for Australian passenger vehicles.'],
            '4wd' => ['4WDs', 'Australian 4WD modification and roadworthy rules', 'Build a capable 4WD. Keep it legal.', 'Official sources covering suspension, tyres, wheels, steering, body, seating, lighting, inspection and certification for Australian 4WDs.'],
            'light-truck' => ['Utes & light trucks', 'Ute and light-truck modification rules', 'Plan the work before your ute goes under the spanner.', 'Official roadworthy, modification, load and inspection sources for Australian utes and light commercial vehicles.'],
            'heavy-vehicle' => ['Heavy vehicles', 'Heavy-vehicle inspection and modification rules', 'Keep serious machinery compliant and on the road.', 'Official heavy-vehicle inspection, modification, load-restraint and design sources from national and jurisdiction authorities.'],
            'motorcycle' => ['Motorcycles', 'Motorcycle roadworthy and modification rules', 'Know the rule before you change the bike.', 'Official motorcycle inspection, registration and modification sources for every Australian state and territory.'],
            'street-rod' => ['Street rods & hot rods', 'Street rod and hot rod rules', 'Craftsmanship deserves an approval pathway as considered as the build.', 'Official national and jurisdiction sources for street-rod construction, modification, inspection, certification and registration.'],
        ];
        if (isset($vehicleJourneys[$vehicle])) {
            [$summary, $title, $heading, $intro] = $vehicleJourneys[$vehicle];
            return [
                'title' => $title . ' — ' . $brandName,
                'metaDescription' => $intro,
                'kicker' => $summary . ' · official rules',
                'heading' => $heading,
                'intro' => $intro,
                'vehicleSummary' => $summary,
            ];
        }

        return [
            'title' => 'Australian roadworthy and vehicle modification rules — ' . $brandName,
            'metaDescription' => 'Official Australian federal, state and territory roadworthy, inspection and vehicle modification resources for cars, trucks, motorcycles, trailers and street rods.',
            'kicker' => $brandName . ' rules library',
            'heading' => 'Know the rules before you build, buy or inspect.',
            'intro' => 'One place for official Australian roadworthy, inspection and vehicle modification resources — linked directly to the government authority that issued them.',
            'vehicleSummary' => 'Cars, trucks, motorcycles, trailers and street rods',
        ];
    }
}
