<?php
declare(strict_types=1);

namespace App\Platform\DataSources;

use InvalidArgumentException;

final class ConnectorRegistry
{
    /** @var array<string,ConnectorInterface> */
    private array $connectors = [];

    public function register(ConnectorInterface $connector): void { $this->connectors[$connector->key()] = $connector; }

    public function get(string $key): ConnectorInterface
    {
        if (!isset($this->connectors[$key])) { throw new InvalidArgumentException('Unknown data source connector: ' . $key); }
        return $this->connectors[$key];
    }

    public function resolve(string $key, string $class): ConnectorInterface
    {
        if (isset($this->connectors[$key])) { return $this->connectors[$key]; }
        if ($class === '' || !class_exists($class)) { throw new InvalidArgumentException('Connector class is unavailable for: ' . $key); }
        $connector = new $class();
        if (!$connector instanceof ConnectorInterface || $connector->key() !== $key) { throw new InvalidArgumentException('Connector class does not satisfy its registered contract: ' . $key); }
        $this->register($connector);
        return $connector;
    }
}
