<?php

namespace Tests\Stubs;

use Bamboo\Core\Application;
use Bamboo\Module\ModuleInterface;

class TestModuleAlpha implements ModuleInterface
{
    public const GLOBAL_MIDDLEWARE = 'Tests\\Stubs\\Middleware\\AlphaGlobal';
    public const WEB_MIDDLEWARE = 'tests.alpha.web';
    public const ALIAS_TARGET = 'Tests\\Stubs\\Middleware\\AlphaAlias';

    public function register(Application $app): void
    {
        ModuleLifecycleLog::record(static::class, 'register');
    }

    public function boot(Application $app): void
    {
        ModuleLifecycleLog::record(static::class, 'boot');
    }

    public function middleware(): array
    {
        return [
            'global' => [self::GLOBAL_MIDDLEWARE],
            'groups' => [
                'web' => [self::WEB_MIDDLEWARE],
            ],
            'aliases' => [
                'alpha' => self::ALIAS_TARGET,
            ],
        ];
    }
}
