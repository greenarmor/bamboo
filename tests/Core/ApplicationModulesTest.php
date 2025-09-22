<?php

namespace Tests\Core;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Web\Middleware\RequestId;
use Bamboo\Web\Middleware\SignatureHeader;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\ModuleLifecycleLog;
use Tests\Stubs\TestModuleAlpha;
use Tests\Stubs\TestModuleBeta;

class ApplicationModulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ModuleLifecycleLog::reset();
    }

    public function testModulesRegisterBeforeBootAndMergeMiddleware(): void
    {
        $config = new Config(dirname(__DIR__, 2) . '/etc');
        $app = new Application($config);

        $app->bootModules([
            TestModuleAlpha::class,
            TestModuleBeta::class,
        ]);

        $this->assertSame([
            TestModuleAlpha::class . ':register',
            TestModuleBeta::class . ':register',
            TestModuleAlpha::class . ':boot',
            TestModuleBeta::class . ':boot',
        ], ModuleLifecycleLog::$events);

        $middleware = $app->config('middleware');
        $this->assertIsArray($middleware);
        $this->assertArrayHasKey('groups', $middleware);
        $this->assertArrayHasKey('aliases', $middleware);
        $this->assertArrayHasKey('web', $middleware['groups']);

        $this->assertSame([
            RequestId::class,
            TestModuleAlpha::GLOBAL_MIDDLEWARE,
            TestModuleBeta::GLOBAL_MIDDLEWARE,
        ], $middleware['global']);

        $this->assertSame([
            SignatureHeader::class,
            TestModuleAlpha::WEB_MIDDLEWARE,
        ], $middleware['groups']['web']);

        $this->assertArrayHasKey('api', $middleware['groups']);
        $this->assertSame([
            TestModuleBeta::API_MIDDLEWARE,
        ], $middleware['groups']['api']);

        $this->assertSame([
            'alpha' => TestModuleAlpha::ALIAS_TARGET,
            'beta' => TestModuleBeta::ALIAS_TARGET,
        ], $middleware['aliases']);
    }
}
