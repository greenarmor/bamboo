<?php

declare(strict_types=1);

namespace Bamboo\Module;

use Bamboo\Core\Application;

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
     * @return array<string, array<int, string>|string>
     */
    public function middleware(): array;
}
