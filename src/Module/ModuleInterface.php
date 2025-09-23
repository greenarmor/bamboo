<?php

declare(strict_types=1);

namespace Bamboo\Module;

use Bamboo\Core\Application;

/**
 * @phpstan-type MiddlewareList list<string>
 * @phpstan-type MiddlewareGroups array<string, MiddlewareList>
 * @phpstan-type MiddlewareAliases array<string, string>
 * @phpstan-type ModuleMiddleware array{
 *     global?: MiddlewareList,
 *     groups?: MiddlewareGroups,
 *     aliases?: MiddlewareAliases,
 * }
 */
interface ModuleInterface
{
    /**
     * Register bindings or configuration into the container.
     */
    public function register(Application $app): void;

    /**
     * Perform post-registration boot logic once all modules are loaded.
     */
    public function boot(Application $app): void;

    /**
     * Optionally contribute middleware aliases or stacks.
     *
     * @return ModuleMiddleware
     */
    public function middleware(): array;
}
