<?php

declare(strict_types=1);

namespace Tests\Auth\Jwt;

use Bamboo\Auth\Jwt\JsonUserRepository;
use PHPUnit\Framework\TestCase;

final class JsonUserRepositoryTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $temp = tempnam(sys_get_temp_dir(), 'bamboo-jwt-users-');
        if ($temp === false) {
            $this->fail('Unable to create temporary file.');
        }
        $this->file = $temp;
        file_put_contents($this->file, '');
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            @unlink($this->file);
        }
        parent::tearDown();
    }

    public function testCreateAndFindUser(): void
    {
        $repo = new JsonUserRepository($this->file);
        $created = $repo->create('alice', 'secret', [
            'roles' => ['admin', 'admin', ''],
            'email' => 'alice@example.com',
        ]);

        $this->assertNotNull($created);
        $this->assertSame('alice', $created['username']);
        $this->assertSame(['admin'], $created['roles']);

        $found = $repo->find('alice');
        $this->assertNotNull($found);
        $this->assertSame($created['username'], $found['username']);
    }

    public function testVerifyCredentials(): void
    {
        $repo = new JsonUserRepository($this->file);
        $repo->create('bob', 'top-secret');

        $this->assertNotNull($repo->verifyCredentials('bob', 'top-secret'));
        $this->assertNull($repo->verifyCredentials('bob', 'wrong'));
        $this->assertNull($repo->verifyCredentials('missing', 'top-secret'));
    }

    public function testSanitizeRemovesPasswordHash(): void
    {
        $repo = new JsonUserRepository($this->file);
        $user = $repo->create('carol', 'pass');
        $this->assertNotNull($user);

        $sanitized = $repo->sanitize($user);
        $this->assertArrayNotHasKey('password_hash', $sanitized);
    }
}
