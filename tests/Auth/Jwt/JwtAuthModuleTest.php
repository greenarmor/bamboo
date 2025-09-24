<?php

declare(strict_types=1);

namespace Tests\Auth\Jwt;

use Bamboo\Auth\Jwt\JwtAuthModule;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouterTestApplication;

final class JwtAuthModuleTest extends TestCase
{
    private string $userStore;

    protected function setUp(): void
    {
        parent::setUp();
        $temp = tempnam(sys_get_temp_dir(), 'bamboo-jwt-store-');
        if ($temp === false) {
            $this->fail('Unable to create temporary user store.');
        }
        $this->userStore = $temp;
        file_put_contents($this->userStore, json_encode([]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->userStore)) {
            @unlink($this->userStore);
        }
        parent::tearDown();
    }

    public function testRegisterLoginAndProfileFlow(): void
    {
        $configOverrides = [
            'auth' => [
                'jwt' => [
                    'secret' => 'integration-secret',
                    'ttl' => 3600,
                    'issuer' => 'TestIssuer',
                    'audience' => 'TestAudience',
                    'storage' => [
                        'path' => $this->userStore,
                    ],
                    'registration' => [
                        'enabled' => true,
                        'default_roles' => ['user'],
                    ],
                ],
            ],
            'middleware' => [
                'global' => [],
                'groups' => [],
                'aliases' => [],
            ],
        ];

        $app = new RouterTestApplication([], $configOverrides);
        $app->bootModules([JwtAuthModule::class]);

        $psr17 = new Psr17Factory();

        $registerRequest = $psr17->createServerRequest('POST', '/api/auth/register')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($psr17->createStream(json_encode([
                'username' => 'test-user',
                'password' => 'secret',
                'email' => 'user@example.com',
            ], JSON_THROW_ON_ERROR)));

        $registerResponse = $app->handle($registerRequest);
        $this->assertSame(201, $registerResponse->getStatusCode());
        $registerPayload = json_decode((string) $registerResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $registerPayload);
        $this->assertSame('test-user', $registerPayload['user']['username']);

        $loginRequest = $psr17->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($psr17->createStream(json_encode([
                'username' => 'test-user',
                'password' => 'secret',
            ], JSON_THROW_ON_ERROR)));

        $loginResponse = $app->handle($loginRequest);
        $this->assertSame(200, $loginResponse->getStatusCode());
        $loginPayload = json_decode((string) $loginResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $loginPayload);
        $this->assertSame('test-user', $loginPayload['user']['username']);

        $profileRequest = $psr17->createServerRequest('GET', '/api/auth/profile')
            ->withHeader('Authorization', 'Bearer ' . $loginPayload['token']);
        $profileResponse = $app->handle($profileRequest);

        $this->assertSame(200, $profileResponse->getStatusCode());
        $profilePayload = json_decode((string) $profileResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('test-user', $profilePayload['user']['username']);
        $this->assertSame(['user'], $profilePayload['user']['roles']);
    }
}
