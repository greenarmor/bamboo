<?php

declare(strict_types=1);

namespace Bamboo\Provider;

use Bamboo\Core\Application;
use Bamboo\Web\Health\HealthState;
use Bamboo\Web\Resilience\CircuitBreakerRegistry;

final class ResilienceProvider
{
    public function register(Application $app): void
    {
        $app->singleton(HealthState::class, static fn(): HealthState => new HealthState());
        $app->bind('health.state', static fn(Application $app): HealthState => $app->get(HealthState::class));

        $app->singleton(CircuitBreakerRegistry::class, static fn(): CircuitBreakerRegistry => new CircuitBreakerRegistry());
    }
}
