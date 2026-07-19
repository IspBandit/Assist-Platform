<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Demand\ActivityTracker;
use App\Services\Demand\ReportingService;

/**
 * Provider-facing analytics dashboard. A provider only ever sees its own
 * figures (resolved from the signed-in user's linked provider row), with
 * estimated metrics (impressions/clicks) clearly separated from confirmed
 * provider usage (service_outcomes). Includes AU financial-year filters.
 */
final class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $provider = $this->requireProvider();

        if (!ActivityTracker::enabled()) {
            return $this->view('provider.analytics', [
                'title'    => 'Analytics',
                'provider' => $provider,
                'disabled' => true,
                'range'    => '30d',
                'rangeLabel' => '',
                'from'     => '',
                'to'       => '',
                'summary'  => null,
            ]);
        }

        $range = (string) $request->input('range', '30d');
        [$from, $to, $label] = ReportingService::resolveRange(
            $range,
            (string) $request->input('from', ''),
            (string) $request->input('to', '')
        );

        return $this->view('provider.analytics', [
            'title'      => 'Analytics',
            'provider'   => $provider,
            'disabled'   => false,
            'range'      => $range,
            'rangeLabel' => $label,
            'from'       => $from,
            'to'         => $to,
            'summary'    => ReportingService::providerSummary((int) $provider['id'], $from, $to),
        ]);
    }

    /** @return array<string,mixed> */
    private function requireProvider(): array
    {
        $user = current_user();
        $provider = $user ? Database::selectOne('SELECT * FROM providers WHERE user_id = ? AND deleted_at IS NULL', [(int) $user['id']]) : null;
        if ($provider === null) {
            $this->abort(404, 'No provider profile is linked to your account.');
        }
        return $provider;
    }
}
