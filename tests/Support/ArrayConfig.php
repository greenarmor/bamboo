<?php
namespace Tests\Support;

use Bamboo\Core\Config;

final class ArrayConfig extends Config
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private array $configuration)
    {
        parent::__construct(__DIR__);
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadConfiguration(): array
    {
        return $this->configuration;
    }
}
