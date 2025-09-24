<?php

declare(strict_types=1);

namespace Bamboo\Auth\Jwt;

use Bamboo\Core\Application;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    private JsonUserRepository $users;
    private JwtTokenService $tokens;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(private Application $app)
    {
        $this->users = $this->app->get(JsonUserRepository::class);
        $this->tokens = $this->app->get(JwtTokenService::class);
        $this->config = $this->app->config('auth.jwt') ?? [];
    }

    public function register(Request $request): ResponseInterface
    {
        if (!$this->registrationEnabled()) {
            return $this->jsonResponse(403, [
                'error' => 'registration_disabled',
                'message' => 'Account registration is disabled.',
            ]);
        }

        $payload = $this->parseJson($request);
        if ($payload === null) {
            return $this->jsonResponse(400, [
                'error' => 'invalid_payload',
                'message' => 'Request body must be valid JSON.',
            ]);
        }

        $username = $this->stringValue($payload['username'] ?? null);
        $password = $this->stringValue($payload['password'] ?? null);
        $email = $this->stringValue($payload['email'] ?? null);

        if ($username === null || $password === null) {
            return $this->jsonResponse(422, [
                'error' => 'validation_error',
                'message' => 'Username and password are required.',
            ]);
        }

        $user = $this->users->create($username, $password, [
            'email' => $email,
            'roles' => $this->defaultRoles(),
        ]);

        if ($user === null) {
            return $this->jsonResponse(409, [
                'error' => 'user_exists',
                'message' => 'A user with that username already exists.',
            ]);
        }

        $token = $this->tokens->issueToken($user['username'], [
            'roles' => $user['roles'] ?? [],
        ]);

        return $this->jsonResponse(201, [
            'token' => $token,
            'user' => $this->users->sanitize($user),
        ]);
    }

    public function login(Request $request): ResponseInterface
    {
        $payload = $this->parseJson($request);
        if ($payload === null) {
            return $this->jsonResponse(400, [
                'error' => 'invalid_payload',
                'message' => 'Request body must be valid JSON.',
            ]);
        }

        $username = $this->stringValue($payload['username'] ?? null);
        $password = $this->stringValue($payload['password'] ?? null);

        if ($username === null || $password === null) {
            return $this->jsonResponse(422, [
                'error' => 'validation_error',
                'message' => 'Username and password are required.',
            ]);
        }

        $user = $this->users->verifyCredentials($username, $password);
        if ($user === null) {
            return $this->jsonResponse(401, [
                'error' => 'invalid_credentials',
                'message' => 'The provided credentials are incorrect.',
            ]);
        }

        $token = $this->tokens->issueToken($user['username'], [
            'roles' => $user['roles'] ?? [],
        ]);

        return $this->jsonResponse(200, [
            'token' => $token,
            'user' => $this->users->sanitize($user),
        ]);
    }

    public function profile(Request $request): ResponseInterface
    {
        $user = $request->getAttribute('auth.user');
        if (!is_array($user)) {
            return $this->jsonResponse(401, [
                'error' => 'unauthenticated',
                'message' => 'Authentication token missing or invalid.',
            ]);
        }

        return $this->jsonResponse(200, [
            'user' => $this->users->sanitize($user),
            'claims' => $request->getAttribute('auth.claims', []),
        ]);
    }

    private function registrationEnabled(): bool
    {
        $flag = $this->config['registration']['enabled'] ?? true;
        if (is_bool($flag)) {
            return $flag;
        }

        if (is_string($flag)) {
            $normalized = strtolower($flag);
            return !in_array($normalized, ['0', 'false', 'off'], true);
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function defaultRoles(): array
    {
        $roles = $this->config['registration']['default_roles'] ?? [];
        if (!is_array($roles)) {
            return [];
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (!is_string($role)) {
                continue;
            }

            $role = trim($role);
            if ($role === '') {
                continue;
            }

            $normalized[] = $role;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(int $status, array $payload): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(Request $request): ?array
    {
        $body = (string) $request->getBody();
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
