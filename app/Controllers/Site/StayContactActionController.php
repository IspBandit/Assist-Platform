<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\CaravanPark;
use App\Services\Demand\DemandRecorder;

/**
 * Attributable place-to-stay contact actions. Records (when demand_analytics
 * is on) then redirects to phone, email, website, booking or maps directions.
 * Tracking failure never blocks the redirect.
 *
 * Route: GET /go/stay/{action}/{slug}
 */
final class StayContactActionController extends Controller
{
    public function go(Request $request): Response
    {
        $action = strtolower((string) $request->route('action'));
        $slug = (string) $request->route('slug');

        $park = CaravanPark::findPublicBySlug($slug);
        if ($park === null) {
            $this->abort(404, 'Place to stay not found.');
        }

        $target = $this->targetFor($action, $park);
        if ($target === null) {
            return $this->redirect('caravan-parks/' . $park['slug']);
        }

        DemandRecorder::recordStayContactAction((int) $park['id'], $action, [
            'route' => 'caravan-parks/' . $park['slug'],
        ]);

        return $this->redirect($target);
    }

    /**
     * @param array<string,mixed> $park
     */
    private function targetFor(string $action, array $park): ?string
    {
        return match ($action) {
            'phone' => $this->phoneTarget($park),
            'email' => $this->emailTarget($park),
            'website' => $this->httpTarget((string) ($park['website'] ?? '')),
            'booking' => $this->httpTarget((string) ($park['booking_url'] ?? '')),
            'directions' => $this->directionsTarget($park),
            default => null,
        };
    }

    /** @param array<string,mixed> $park */
    private function phoneTarget(array $park): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim((string) ($park['phone'] ?? '')));
        return $phone !== '' ? 'tel:' . $phone : null;
    }

    /** @param array<string,mixed> $park */
    private function emailTarget(array $park): ?string
    {
        $email = trim((string) ($park['email'] ?? ''));
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $email : null;
    }

    private function httpTarget(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        return preg_match('#^https?://#i', $url) === 1 ? $url : 'https://' . $url;
    }

    /** @param array<string,mixed> $park */
    private function directionsTarget(array $park): ?string
    {
        $destination = map_destination(
            $park['latitude'] ?? null,
            $park['longitude'] ?? null,
            [$park['address'] ?? '', $park['town_name'] ?? '']
        );
        if ($destination === '') {
            return null;
        }
        return map_directions_url($destination);
    }
}
