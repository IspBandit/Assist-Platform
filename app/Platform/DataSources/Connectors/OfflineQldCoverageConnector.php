<?php

declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use RuntimeException;

/**
 * Marker connector for offline QLD coverage pack promotion into the review queue.
 * Live search is intentionally unsupported — candidates come from seed artefacts.
 */
final class OfflineQldCoverageConnector implements ConnectorInterface
{
    public function key(): string
    {
        return 'qld_coverage_offline';
    }

    public function search(array $request, array $credentials, array $settings = []): array
    {
        throw new RuntimeException(
            'QLD coverage offline connector does not perform live search. Use scripts/qld-coverage-import-dry-run.php.'
        );
    }
}
