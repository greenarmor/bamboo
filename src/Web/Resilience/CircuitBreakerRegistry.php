<?php

declare(strict_types=1);

namespace Bamboo\Web\Resilience;

final class CircuitBreakerRegistry
{
    /**
     * @var array<string, array{state: string, failures: int, successes: int, opened_at: float|null}>
     */
    private array $states = [];

    /**
     * @return array{state: string, failures: int, successes: int, opened_at: float|null}
     */
    public function get(string $key): array
    {
        if (!isset($this->states[$key])) {
            $this->states[$key] = [
                'state' => 'closed',
                'failures' => 0,
                'successes' => 0,
                'opened_at' => null,
            ];
        }

        return $this->states[$key];
    }

    /**
     * @param array{state?: string, failures?: int, successes?: int, opened_at?: float|null} $state
     */
    public function set(string $key, array $state): void
    {
        $defaults = [
            'state' => 'closed',
            'failures' => 0,
            'successes' => 0,
            'opened_at' => null,
        ];

        $this->states[$key] = array_replace($defaults, $state);
    }

    public function reset(): void
    {
        $this->states = [];
    }
}
