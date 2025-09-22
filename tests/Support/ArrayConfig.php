<?php
namespace Tests\Support;

use Bamboo\Core\Config;

final class ArrayConfig extends Config
{
    public function __construct(private array $configuration)
    {
        parent::__construct(__DIR__);
    }

    protected function loadConfiguration(): array
    {
        return $this->configuration;
    }
}
