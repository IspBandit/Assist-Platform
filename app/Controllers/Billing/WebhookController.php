<?php

declare(strict_types=1);

namespace App\Controllers\Billing;

use App\Billing\BillingManager;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentEventService;

/**
 * Receives gateway webhooks (e.g. Stripe). CSRF is intentionally NOT applied —
 * authenticity is established by verifying the gateway signature instead.
 *
 * While ENABLE_BILLING=false the endpoint returns 404 so the integration
 * surface is completely hidden.
 */
final class WebhookController extends Controller
{
    public function stripe(Request $request): Response
    {
        if (!BillingManager::enabled() || config('billing.gateway') !== 'stripe') {
            $this->abort(404);
        }

        $rawBody = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        $result = (new PaymentEventService())->ingest($rawBody, $signature);

        // Acknowledge receipt (2xx) for handled outcomes so the gateway does not
        // retry indefinitely; signal 400 only for an invalid signature.
        $status = $result === 'invalid_signature' ? 400 : 200;

        return $this->json(['status' => $result], $status);
    }
}
