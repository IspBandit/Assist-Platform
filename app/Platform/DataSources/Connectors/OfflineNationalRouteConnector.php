<?php

declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use RuntimeException;

/** Marker connector for uploaded, review-only national route discoveries. */
final class OfflineNationalRouteConnector implements ConnectorInterface
{
    public function key(): string
    {
        return 'national_route_places';
    }

    public function search(array $request, array $credentials, array $settings = []): array
    {
        throw new RuntimeException(
            'National route discovery files are staged through Admin → Import review; this connector does not run live searches.'
        );
    }
}
