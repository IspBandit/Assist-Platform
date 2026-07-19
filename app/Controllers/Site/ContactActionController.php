<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Provider;
use App\Services\Demand\DemandRecorder;

/**
 * Attributable provider contact actions. Each action is recorded (when the
 * demand_analytics flag is on) and then the user is redirected to the real
 * target — phone, email, website or maps directions. Recording is best-effort:
 * a tracking failure never prevents the redirect, so usability is preserved
 * even if analytics is down (requirement sections 7 & 25).
 *
 * Routes: GET /go/{action}/{slug}. GET-only and side-effect-light (it only
 * appends an analytics row), so it sits in the public group without CSRF.
 */
final class ContactActionController extends Controller
{
    public function go(Request $request): Response
    {
        $action = strtolower((string) $request->route('action'));
        $slug = (string) $request->route('slug');

        $provider = Provider::findPublicBySlug($slug);
        if ($provider === null) {
            $this->abort(404, 'Provider not found.');
        }

        $target = $this->targetFor($action, $provider);
        if ($target === null) {
            // Unknown action or nothing to open — return to the profile.
            return $this->redirect('providers/' . $provider['slug']);
        }

        DemandRecorder::recordContactAction((int) $provider['id'], $action, [
            'search_id' => (int) $request->input('s') ?: null,
            'route'     => 'providers/' . $provider['slug'],
        ]);

        return $this->redirect($target);
    }

    /**
     * Resolve the redirect target for a contact action from public details.
     *
     * @param array<string,mixed> $p
     */
    private function targetFor(string $action, array $p): ?string
    {
        $isUnclaimed = !empty($p['is_unclaimed']);

        switch ($action) {
            case 'phone':
                if (empty($p['show_public_phone']) && !$isUnclaimed) {
                    return null;
                }
                $phone = (string) ($p['public_phone'] ?? '') ?: (string) ($p['phone'] ?? '');
                $phone = preg_replace('/[^0-9+]/', '', $phone);
                return $phone !== '' ? 'tel:' . $phone : null;

            case 'email':
                if (empty($p['show_public_email']) && !$isUnclaimed) {
                    return null;
                }
                $email = (string) ($p['public_email'] ?? '') ?: (string) ($p['email'] ?? '');
                return $email !== '' ? 'mailto:' . $email : null;

            case 'website':
                $website = trim((string) ($p['website'] ?? ''));
                if ($website === '') {
                    return null;
                }
                return preg_match('#^https?://#i', $website) === 1 ? $website : 'https://' . $website;

            case 'directions':
                $dest = $this->directionsDestination($p);
                return $dest !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($dest) : null;

            default:
                return null;
        }
    }

    /** @param array<string,mixed> $p */
    private function directionsDestination(array $p): string
    {
        $address = trim((string) ($p['street_address'] ?? ''));
        $model = (string) ($p['service_model'] ?? '');
        $isWorkshop = in_array($model, ['workshop', 'both'], true);
        if (!$isWorkshop || $address === '' || stripos($address, 'mobile') !== false) {
            return '';
        }
        $dest = $address;
        $town = (string) ($p['town_name'] ?? '');
        if ($town !== '' && stripos($address, $town) === false) {
            $dest .= ', ' . $town;
        }
        if (!empty($p['state_abbr'])) {
            $dest .= ' ' . (string) $p['state_abbr'];
        }
        if (!empty($p['town_postcode'])) {
            $dest .= ' ' . (string) $p['town_postcode'];
        }
        return $dest;
    }
}
