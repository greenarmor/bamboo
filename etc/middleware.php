<?php

declare(strict_types=1);

/**
 * Middleware configuration for the Bamboo HTTP kernel.
 *
 * Define the global middleware stack, reusable groups, and aliases inside this
 * structure. Middleware entries should be fully-qualified class names or alias
 * references that the router and kernel can expand at runtime.
 *
 * @return array{
 *     global?: array<int, string>,
 *     groups?: array<string, array<int, string>>, 
 *     aliases?: array<string, string>
 * }
 */
return [
    'global' => [
        Bamboo\Web\Middleware\RequestId::class,
    ],
    'groups' => [
        'web' => [
            Bamboo\Web\Middleware\SignatureHeader::class,
        ],
    ],
    'aliases' => [],
];

