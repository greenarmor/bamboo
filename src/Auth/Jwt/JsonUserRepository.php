<?php

declare(strict_types=1);

namespace Bamboo\Auth\Jwt;

final class JsonUserRepository
{
    private const PASSWORD_FIELD = 'password_hash';

    public function __construct(private string $storagePath)
    {
    }

    public function storagePath(): string
    {
        return $this->storagePath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->readUsers();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        foreach ($this->readUsers() as $user) {
            if (!isset($user['username'])) {
                continue;
            }

            if (strcasecmp((string) $user['username'], $username) === 0) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    public function create(string $username, string $password, array $attributes = []): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $users = $this->readUsers();
        foreach ($users as $user) {
            if (!isset($user['username'])) {
                continue;
            }

            if (strcasecmp((string) $user['username'], $username) === 0) {
                return null;
            }
        }

        $user = [
            'id' => $attributes['id'] ?? bin2hex(random_bytes(8)),
            'username' => $username,
            self::PASSWORD_FIELD => password_hash($password, PASSWORD_BCRYPT),
            'roles' => $this->normalizeRoles($attributes['roles'] ?? []),
            'email' => $this->stringOrNull($attributes['email'] ?? null),
            'meta' => is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [],
            'created_at' => gmdate('c'),
        ];

        $users[] = $user;
        $this->writeUsers($users);

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->find($username);
        if ($user === null) {
            return null;
        }

        $hash = $user[self::PASSWORD_FIELD] ?? null;
        if (!is_string($hash) || $hash === '') {
            return null;
        }

        if (!password_verify($password, $hash)) {
            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function sanitize(array $user): array
    {
        unset($user[self::PASSWORD_FIELD]);

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readUsers(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        $contents = file_get_contents($this->storagePath);
        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $users */
        $users = [];
        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                $users[] = $entry;
            }
        }

        return $users;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    private function writeUsers(array $users): void
    {
        $directory = dirname($this->storagePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new JwtException('Unable to encode user repository.');
        }

        file_put_contents($this->storagePath, $json . "\n", LOCK_EX);
    }

    /**
     * @param mixed $roles
     * @return list<string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (!is_array($roles)) {
            $roles = [$roles];
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

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
