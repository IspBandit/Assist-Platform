<?php
declare(strict_types=1);
namespace App\Platform\DataIntelligence;

use RuntimeException;

final class SourceRegistry
{
    /** @var array<string,MetricSourceInterface> */
    private array $sources = [];
    public function register(MetricSourceInterface $source): void { $this->sources[$source->key()] = $source; }
    public function get(string $key): MetricSourceInterface
    {
        if (!isset($this->sources[$key])) { throw new RuntimeException('Unknown Data Intelligence source: ' . $key); }
        return $this->sources[$key];
    }
}
