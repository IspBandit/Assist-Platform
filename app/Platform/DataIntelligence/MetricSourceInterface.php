<?php
declare(strict_types=1);
namespace App\Platform\DataIntelligence;

interface MetricSourceInterface
{
    public function key(): string;
    /** @return array<int,array<string,mixed>> */
    public function coverage(int $brandId, array $filters = []): array;
}
