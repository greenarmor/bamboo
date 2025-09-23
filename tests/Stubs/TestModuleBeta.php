<?php

namespace Tests\Stubs;

use Bamboo\Core\Application;
use Bamboo\Module\ModuleInterface;

class TestModuleBeta implements ModuleInterface
{
    public const GLOBAL_MIDDLEWARE = 'Tests\\Stubs\\Middleware\\BetaGlobal';
    public const API_MIDDLEWARE = 'tests.beta.api';
    public const ALIAS_TARGET = 'Tests\\Stubs\\Middleware\\BetaAlias';

    public function register(Application $app): void
    {
        ModuleLifecycleLog::record(static::class, 'register');
    }

    public function boot(Application $app): void
    {
        ModuleLifecycleLog::record(static::class, 'boot');
    }

    /**
     * @return array{
     *     global: list<string>,
     *     groups: array<string, list<string>>,
     *     aliases: array<string, string>,
     * }
     */
    public function middleware(): array
    {
        return [
            'global' => [self::GLOBAL_MIDDLEWARE],
            'groups' => [
                'api' => [self::API_MIDDLEWARE],
            ],
            'aliases' => [
                'beta' => self::ALIAS_TARGET,
            ],
        ];
    }
}
