<?php
namespace Tests\Core;

use Bamboo\Core\ConfigValidator;
use Bamboo\Core\ConfigurationException;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    public function testValidConfigurationPasses(): void
    {
        $validator = new ConfigValidator();

        $validator->validate($this->validConfig());

        $this->addToAssertionCount(1); // No exception thrown.
    }

    public function testServerHostMustBeNonEmptyString(): void
    {
        $config = $this->validConfig();
        $config['server']['host'] = '';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['server.host must be a non-empty string.'], $exception->errors());
            $this->assertStringContainsString('server.host must be a non-empty string.', $exception->getMessage());
        }
    }

    public function testServerPortMustBePositiveInteger(): void
    {
        $config = $this->validConfig();
        $config['server']['port'] = '9501';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['server.port must be an integer between 1 and 65535.'], $exception->errors());
            $this->assertStringContainsString('server.port must be an integer between 1 and 65535.', $exception->getMessage());
        }
    }

    public function testCacheRoutesMustBeString(): void
    {
        $config = $this->validConfig();
        $config['cache']['routes'] = null;

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['cache.routes must be a non-empty string path.'], $exception->errors());
            $this->assertStringContainsString('cache.routes must be a non-empty string path.', $exception->getMessage());
        }
    }

    public function testRedisUrlMustBeString(): void
    {
        $config = $this->validConfig();
        $config['redis']['url'] = '';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['redis.url must be a non-empty string.'], $exception->errors());
            $this->assertStringContainsString('redis.url must be a non-empty string.', $exception->getMessage());
        }
    }

    public function testHttpDefaultTimeoutMustBePositiveNumber(): void
    {
        $config = $this->validConfig();
        $config['http']['default']['timeout'] = 0;

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['http.default.timeout must be a positive number.'], $exception->errors());
            $this->assertStringContainsString('http.default.timeout must be a positive number.', $exception->getMessage());
        }
    }

    public function testAppKeyRequiredWhenDebugDisabled(): void
    {
        $config = $this->validConfig();
        $config['app']['debug'] = false;
        $config['app']['key'] = '';
        $config['auth']['jwt']['secret'] = 'secret';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['app.key must be set when app.debug is disabled.'], $exception->errors());
            $this->assertStringContainsString('app.key must be set when app.debug is disabled.', $exception->getMessage());
        }
    }

    public function testMultipleViolationsAreAggregated(): void
    {
        $config = $this->validConfig();
        $config['server']['host'] = '';
        $config['redis']['url'] = '';
        $config['http']['default']['timeout'] = 0;
        $config['app']['debug'] = false;
        $config['app']['key'] = '';
        $config['auth']['jwt']['secret'] = '';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $expected = [
                'server.host must be a non-empty string.',
                'redis.url must be a non-empty string.',
                'http.default.timeout must be a positive number.',
                'app.key must be set when app.debug is disabled.',
                'auth.jwt.secret must be set when app.debug is disabled.',
            ];

            $this->assertSame($expected, $exception->errors());
            foreach ($expected as $message) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testAuthSecretRequiredWhenDebugDisabled(): void
    {
        $config = $this->validConfig();
        $config['app']['debug'] = false;
        $config['auth']['jwt']['secret'] = '';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['auth.jwt.secret must be set when app.debug is disabled.'], $exception->errors());
        }
    }

    public function testAuthTtlMustBePositive(): void
    {
        $config = $this->validConfig();
        $config['auth']['jwt']['ttl'] = 0;

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['auth.jwt.ttl must be a positive integer.'], $exception->errors());
        }
    }

    public function testAuthStoragePathMustBeString(): void
    {
        $config = $this->validConfig();
        $config['auth']['jwt']['storage']['path'] = '';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['auth.jwt.storage.path must be a non-empty string path.'], $exception->errors());
        }
    }

    public function testViewConfigurationRequiresDefaultAndEngines(): void
    {
        $config = $this->validConfig();
        $config['view']['default'] = '';
        $config['view']['engines'] = [];

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame([
                'view.default must be a non-empty string.',
                'view.engines must be an associative array of engine definitions.',
            ], $exception->errors());
        }
    }

    public function testViewPageOverrideMustReferenceKnownEngine(): void
    {
        $config = $this->validConfig();
        $config['view']['pages']['landing'] = 'custom';

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame([
                'view.pages.landing references unknown engine "custom".',
            ], $exception->errors());
        }
    }

    public function testAuthDefaultRolesMustBeStrings(): void
    {
        $config = $this->validConfig();
        $config['auth']['jwt']['registration']['default_roles'] = ['admin', ''];

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['auth.jwt.registration.default_roles must contain only non-empty strings.'], $exception->errors());
        }
    }

    public function testResilienceTimeoutDefaultMustBePositive(): void
    {
        $config = $this->validConfig();
        $config['resilience']['timeouts']['default'] = 0.0;

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['resilience.timeouts.default must be a positive number.'], $exception->errors());
        }
    }

    public function testCircuitBreakerFailureThresholdMustBePositive(): void
    {
        $config = $this->validConfig();
        $config['resilience']['circuit_breaker']['failure_threshold'] = 0;

        $validator = new ConfigValidator();

        try {
            $validator->validate($config);
            $this->fail('Expected ConfigurationException to be thrown.');
        } catch (ConfigurationException $exception) {
            $this->assertSame(['resilience.circuit_breaker.failure_threshold must be a positive integer.'], $exception->errors());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validConfig(): array
    {
        return [
            'app' => [
                'name' => 'Bamboo',
                'env' => 'testing',
                'debug' => true,
                'key' => 'base64:secret',
                'log_file' => '/tmp/app.log',
            ],
            'server' => [
                'host' => '127.0.0.1',
                'port' => 9501,
                'workers' => 1,
                'task_workers' => 0,
                'max_requests' => 100,
                'static_enabled' => true,
            ],
            'cache' => [
                'path' => '/tmp/cache',
                'routes' => '/tmp/routes.cache.php',
            ],
            'redis' => [
                'url' => 'tcp://127.0.0.1:6379',
                'queue' => 'jobs',
            ],
            'database' => [],
            'ws' => [],
            'http' => [
                'default' => [
                    'timeout' => 5.0,
                    'headers' => [],
                    'retries' => [
                        'max' => 2,
                        'base_delay_ms' => 150,
                        'status_codes' => [429],
                    ],
                ],
                'services' => [],
            ],
            'auth' => [
                'jwt' => [
                    'secret' => 'testing-secret',
                    'ttl' => 3600,
                    'issuer' => 'Bamboo',
                    'audience' => 'BambooUsers',
                    'storage' => [
                        'driver' => 'json',
                        'path' => '/tmp/bamboo-users.json',
                    ],
                    'registration' => [
                        'enabled' => true,
                        'default_roles' => ['user'],
                    ],
                ],
            ],
            'view' => [
                'default' => 'components',
                'pages' => [
                    'landing' => null,
                ],
                'engines' => [
                    'components' => [
                        'driver' => 'components',
                    ],
                ],
            ],
            'resilience' => [
                'timeouts' => [
                    'default' => 3.0,
                    'per_route' => [],
                ],
                'circuit_breaker' => [
                    'enabled' => true,
                    'failure_threshold' => 5,
                    'cooldown_seconds' => 1.0,
                    'success_threshold' => 1,
                    'per_route' => [],
                ],
                'health' => [
                    'dependencies' => [],
                ],
            ],
        ];
    }
}
