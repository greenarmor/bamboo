<?php

declare(strict_types=1);

namespace Bamboo\Auth\Jwt;

use Bamboo\Core\Application;
use Bamboo\Core\RouteDefinition;
use Bamboo\Module\ModuleInterface;

final class JwtAuthModule implements ModuleInterface
{
    public function register(Application $app): void
    {
        $app->singleton(JsonUserRepository::class, function (Application $app): JsonUserRepository {
            $config = $this->config($app);
            $path = $config['storage']['path'] ?? 'var/auth/users.json';
            if (!is_string($path) || $path === '') {
                $path = 'var/auth/users.json';
            }

            return new JsonUserRepository($this->toAbsolutePath($path));
        });

        $app->singleton(JwtTokenService::class, function (Application $app): JwtTokenService {
            $config = $this->config($app);
            $secret = isset($config['secret']) && is_string($config['secret']) ? $config['secret'] : '';
            $ttl = $config['ttl'] ?? 3600;
            $issuer = isset($config['issuer']) && is_string($config['issuer']) && $config['issuer'] !== ''
                ? $config['issuer']
                : 'Bamboo';
            $audience = isset($config['audience']) && is_string($config['audience']) && $config['audience'] !== ''
                ? $config['audience']
                : 'BambooUsers';

            $ttl = is_numeric($ttl) ? (int) $ttl : 3600;

            return new JwtTokenService($secret, $ttl, $issuer, $audience);
        });

        $app->singleton(JwtAuthenticationMiddleware::class, function (Application $app): JwtAuthenticationMiddleware {
            return new JwtAuthenticationMiddleware(
                $app->get(JwtTokenService::class),
                $app->get(JsonUserRepository::class)
            );
        });
    }

    public function boot(Application $app): void
    {
        $router = $app->get('router');
        $router->post(
            '/api/auth/register',
            RouteDefinition::forHandler([AuthController::class, 'register'], signature: 'POST /api/auth/register')
        );
        $router->post(
            '/api/auth/login',
            RouteDefinition::forHandler([AuthController::class, 'login'], signature: 'POST /api/auth/login')
        );
        $router->get(
            '/api/auth/profile',
            RouteDefinition::forHandler(
                [AuthController::class, 'profile'],
                middleware: ['auth.jwt'],
                signature: 'GET /api/auth/profile'
            )
        );
    }

    public function middleware(): array
    {
        return [
            'aliases' => [
                'auth.jwt' => JwtAuthenticationMiddleware::class,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(Application $app): array
    {
        $config = $app->config('auth.jwt');

        return is_array($config) ? $config : [];
    }

    private function toAbsolutePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        $base = dirname(__DIR__, 3);
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));

        return $base . DIRECTORY_SEPARATOR . $normalized;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
    }
}
