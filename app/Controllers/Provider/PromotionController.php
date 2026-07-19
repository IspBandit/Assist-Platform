<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FoundingGraphicService;
use RuntimeException;

/**
 * Founding launch-town providers: request and view their free ad graphic.
 */
final class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        if ($provider === null) {
            $this->abort(404);
        }

        $providerId = (int) $provider['id'];
        $promo = FoundingGraphicService::forProvider($providerId);
        if ($promo === null) {
            $this->abort(404, 'This promotion is not available for your business.');
        }

        $town = $this->townLabel($providerId);

        return $this->view('provider.promotion', [
            'title'      => 'Promote your business',
            'provider'   => $provider,
            'promo'      => $promo,
            'townLabel'  => $town,
            'imageUrls'  => FoundingGraphicService::imageUrls($promo),
            'canRequest' => FoundingGraphicService::canRequest($providerId),
            'formErrors' => Session::errors(),
            'promoSpecs' => [
                'desktop' => (string) config('promotions.desktop.label'),
                'mobile'  => (string) config('promotions.mobile.label'),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        $provider = $this->currentProvider();
        if ($provider === null) {
            $this->abort(404);
        }

        $providerId = (int) $provider['id'];
        try {
            $logoFile = $request->file('logo');
            if ($logoFile !== null && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $logoFile = null;
            }
            FoundingGraphicService::submitRequest(
                $providerId,
                $request->all(),
                $logoFile
            );
        } catch (RuntimeException $e) {
            return $this->redirectWith('/provider/promotion', 'error', $e->getMessage());
        }

        return $this->redirectWith(
            '/provider/promotion',
            'success',
            'Thanks — we have your brief. We will design your free ad graphic and email you when it is ready (usually within a few business days).'
        );
    }

    /** @return array<string,mixed>|null */
    private function currentProvider(): ?array
    {
        $user = current_user();
        if ($user === null) {
            return null;
        }

        return \App\Core\Database::selectOne(
            'SELECT * FROM providers WHERE user_id = ? AND deleted_at IS NULL',
            [(int) $user['id']]
        );
    }

    private function townLabel(int $providerId): string
    {
        $row = \App\Core\Database::selectOne(
            'SELECT t.name, s.abbreviation AS state_abbr FROM providers p '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id WHERE p.id = ?',
            [$providerId]
        );
        if ($row === null || empty($row['name'])) {
            return 'your area';
        }
        $label = (string) $row['name'];
        if (!empty($row['state_abbr'])) {
            $label .= ', ' . $row['state_abbr'];
        }

        return $label;
    }
}
