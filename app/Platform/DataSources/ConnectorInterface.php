<?php
declare(strict_types=1);

namespace App\Platform\DataSources;

interface ConnectorInterface
{
    public function key(): string;

    /** @param array<string,mixed> $request @return array<int,array<string,mixed>> */
    public function search(array $request, array $credentials, array $settings = []): array;
}

