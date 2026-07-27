<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\MotorsportDocument;
use App\Models\MotorsportVenue;
use App\Services\MotorsportCatalogue;

final class MotorsportController extends Controller
{
    public function index(Request $request): Response
    {
        if (current_brand()->id() !== 'localtorque') {
            $this->abort(404, 'Motorsport library not found.');
        }

        $disciplines = MotorsportCatalogue::disciplines();
        $discipline = $this->allowed((string) $request->query('discipline', ''), $disciplines);
        $filters = [
            'jurisdiction' => $this->allowed(strtoupper((string) $request->query('jurisdiction', '')), MotorsportCatalogue::JURISDICTIONS),
            'discipline' => $discipline,
            'family' => MotorsportCatalogue::familyFor($discipline),
            'rule_type' => $this->allowed((string) $request->query('rule_type', ''), MotorsportCatalogue::RULE_TYPES),
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
        ];

        return $this->view('localtorque.motorsport', [
            'title' => 'Australian motorsport rules and technical regulations — LocalTorque',
            'metaDescription' => 'Official Australian motorsport rulebooks for circuit, rally, off-road, drag, speedway, karting, drifting and motorcycle competition.',
            'canonical' => url('motorsport'),
            'families' => MotorsportCatalogue::FAMILIES,
            'jurisdictions' => MotorsportCatalogue::JURISDICTIONS,
            'ruleTypes' => MotorsportCatalogue::RULE_TYPES,
            'filters' => $filters,
            'coverage' => MotorsportDocument::coverage(),
            'documents' => MotorsportDocument::publicLibrary($filters),
            'venues' => MotorsportVenue::publicDirectory($filters['family'], $filters['jurisdiction']),
        ]);
    }

    /** @param array<string,string> $options */
    private function allowed(string $value, array $options): string
    {
        return isset($options[$value]) ? $value : '';
    }
}
