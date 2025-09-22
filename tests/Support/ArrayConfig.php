<?php
namespace Tests\Support;

use Bamboo\Core\Config;

final class ArrayConfig extends Config
{
    public function __construct(private array $items)
    {
        parent::__construct(__DIR__);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function get(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->items;
        }

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
