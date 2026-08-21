<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\CaravanPark;
use App\Services\StayFacilityService;

final class FacilityContributionController extends Controller
{
    public function form(Request $request): Response
    {
        $park = $this->park((string) $request->route('slug'));
        $service = new StayFacilityService();
        return $this->view('public.facility-contribution', [
            'title' => 'Suggest a facility — ' . $park['name'], 'park' => $park,
            'types' => StayFacilityService::TYPES, 'facilities' => $service->forPark((int) $park['id']),
            'errors' => Session::errors(),
        ]);
    }

    public function store(Request $request): Response
    {
        $park = $this->park((string) $request->route('slug'));
        if ((string) $request->input('website', '') !== '') {
            return $this->redirect('caravan-parks/' . $park['slug']);
        }
        $types = (array) $request->input('facility_type', []);
        $statuses = (array) $request->input('suggested_status', []);
        $values = (array) $request->input('suggested_value', []);
        $details = (array) $request->input('suggested_details', []);
        $items = [];
        foreach ($types as $index => $type) {
            $type = (string) $type;
            $status = (string) ($statuses[$index] ?? '');
            if (!isset(StayFacilityService::TYPES[$type]) || !in_array($status, ['yes','no','unknown','conditional'], true)) {
                continue;
            }
            $items[] = ['facility_type' => $type, 'suggested_status' => $status,
                'suggested_value' => substr(trim((string) ($values[$index] ?? '')), 0, 120),
                'suggested_details' => substr(trim((string) ($details[$index] ?? '')), 0, 1000)];
        }
        $comment = substr(trim((string) $request->input('comment', '')), 0, 2000);
        $url = trim((string) $request->input('evidence_url', ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            Session::flashErrors(['evidence_url' => 'Enter a valid http or https source link.']);
            Session::flashInput($request->all());
            return $this->redirect('caravan-parks/' . $park['slug'] . '/suggest-facility');
        }
        if ($url !== '' && !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)) {
            $url = '';
        }
        if ($items === []) {
            Session::flashErrors(['facility_type' => 'Select at least one facility and its suggested status.']);
            Session::flashInput($request->all());
            return $this->redirect('caravan-parks/' . $park['slug'] . '/suggest-facility');
        }
        $result = (new StayFacilityService())->submit((int) $park['id'], $items, $comment, $url ?: null, $request);
        Session::flash('success', $result['duplicate_of_id']
            ? 'Thanks. Your report has been added as an independent confirmation of an existing pending suggestion.'
            : 'Thanks. Your suggestion is pending admin review and has not changed the public listing.');
        return $this->redirect('caravan-parks/' . $park['slug'] . '?contribution=' . $result['id']);
    }

    private function park(string $slug): array
    {
        if (current_brand()->id() !== 'vanassist' || !Database::tableExists('facility_contributions')) {
            $this->abort(404, 'Page not found.');
        }
        $park = CaravanPark::findPublicBySlug($slug);
        if ($park === null) {
            $this->abort(404, 'Place to stay not found.');
        }
        return $park;
    }
}
