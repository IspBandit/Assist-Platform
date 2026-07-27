<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RegulatoryDocument;
use App\Services\ComplianceGuide;
use App\Services\RegulatorySponsor;

final class RegulatoryLibraryController extends Controller
{
    private const JURISDICTIONS = [
        'AUS' => 'Federal & national', 'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales', 'VIC' => 'Victoria', 'QLD' => 'Queensland',
        'SA' => 'South Australia', 'WA' => 'Western Australia',
        'TAS' => 'Tasmania', 'NT' => 'Northern Territory',
    ];

    private const VEHICLES = [
        'car' => 'Cars', 'light-truck' => 'Utes & light trucks',
        'heavy-vehicle' => 'Heavy vehicles', 'motorcycle' => 'Motorcycles',
        'trailer' => 'Trailers', 'street-rod' => 'Street rods & hot rods',
    ];

    private const KINDS = [
        'roadworthiness' => 'Roadworthy requirements',
        'inspection_manual' => 'Inspection manuals',
        'modifications' => 'Modification rules',
        'code_of_practice' => 'Codes of practice',
        'design_rules' => 'Design rules',
        'street_rods' => 'Street rod rules',
        'towing' => 'Towing rules',
        'trailer_construction' => 'Trailer construction',
        'load_restraint' => 'Load restraint',
        'registration' => 'Registration',
    ];

    public function index(Request $request): Response
    {
        $brand = current_brand();
        if (!in_array($brand->id(), ['vanassist', 'localtorque', 'towsmart', 'trailerwise'], true)) {
            $this->abort(404, 'Rules library not found.');
        }

        $filters = [
            'jurisdiction' => $this->allowed((string) $request->query('jurisdiction', ''), self::JURISDICTIONS),
            'vehicle' => $this->allowed((string) $request->query('vehicle', ''), self::VEHICLES),
            'kind' => $this->allowed((string) $request->query('kind', ''), self::KINDS),
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'town' => max(0, (int) $request->query('town', 0)),
        ];
        $sponsors = new RegulatorySponsor();
        $selectedTown = $sponsors->town($filters['town']);
        $page = $this->pageCopy($brand->id(), $brand->name());

        return $this->view('localtorque.regulatory-library', [
            'title' => $page['title'],
            'metaDescription' => $page['metaDescription'],
            'canonical' => url('rules'),
            'documents' => RegulatoryDocument::publicLibrary($brand->databaseId(), $filters),
            'coverage' => RegulatoryDocument::publicCoverage($brand->databaseId()),
            'jurisdictions' => self::JURISDICTIONS,
            'vehicles' => self::VEHICLES,
            'kinds' => self::KINDS,
            'filters' => $filters,
            'selectedTown' => $selectedTown,
            'page' => $page,
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
            trim((string) $request->query('intention', ''))
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
            'vehicles' => ComplianceGuide::VEHICLES,
            'intentions' => ComplianceGuide::INTENTIONS,
            'steps' => $selection === null ? [] : ComplianceGuide::steps($selection['intention']),
            'limitation' => ComplianceGuide::limitation(),
        ]);
    }

    /** @param array<string,string> $options */
    private function allowed(string $value, array $options): string
    {
        return isset($options[$value]) ? $value : '';
    }

    /** @return array{title:string,metaDescription:string,kicker:string,heading:string,intro:string,vehicleSummary:string} */
    private function pageCopy(string $brandId, string $brandName): array
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
