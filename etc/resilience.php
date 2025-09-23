<?php

declare(strict_types=1);

return [
    'timeouts' => [
        'default' => isset($_ENV['BAMBOO_HTTP_TIMEOUT_DEFAULT'])
            ? max(0.0, (float) $_ENV['BAMBOO_HTTP_TIMEOUT_DEFAULT'])
            : 3.0,
        'per_route' => [],
    ],
    'circuit_breaker' => [
        'enabled' => filter_var($_ENV['BAMBOO_CIRCUIT_BREAKER_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'failure_threshold' => isset($_ENV['BAMBOO_CIRCUIT_BREAKER_FAILURES'])
            ? max(1, (int) $_ENV['BAMBOO_CIRCUIT_BREAKER_FAILURES'])
            : 5,
        'cooldown_seconds' => isset($_ENV['BAMBOO_CIRCUIT_BREAKER_COOLDOWN'])
            ? max(0.0, (float) $_ENV['BAMBOO_CIRCUIT_BREAKER_COOLDOWN'])
            : 30.0,
        'success_threshold' => isset($_ENV['BAMBOO_CIRCUIT_BREAKER_SUCCESS'])
            ? max(1, (int) $_ENV['BAMBOO_CIRCUIT_BREAKER_SUCCESS'])
            : 1,
        'per_route' => [],
    ],
    'health' => [
        'dependencies' => [],
    ],
];
