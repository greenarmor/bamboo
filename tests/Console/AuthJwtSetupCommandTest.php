<?php

declare(strict_types=1);

namespace Tests\Console;

use Bamboo\Auth\Jwt\JwtAuthModule;
use Bamboo\Console\Command\AuthJwtSetup;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouterTestApplication;

final class AuthJwtSetupCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/bamboo-auth-cli-' . bin2hex(random_bytes(6));
        @mkdir($this->root . '/etc', 0775, true);
        @mkdir($this->root . '/stubs/auth', 0775, true);

        file_put_contents($this->root . '/.env', "APP_ENV=testing\n");
        copy(dirname(__DIR__, 2) . '/stubs/auth/jwt-auth.php', $this->root . '/stubs/auth/jwt-auth.php');
        copy(dirname(__DIR__, 2) . '/etc/modules.php', $this->root . '/etc/modules.php');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);
        parent::tearDown();
    }

    public function testScaffoldsJwtAuthentication(): void
    {
        $app = new RouterTestApplication();
        $command = new AuthJwtSetup($app, $this->root);

        ob_start();
        $exitCode = $command->handle([]);
        $output = ob_get_clean() ?: '';

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('JWT authentication scaffolding is ready to use.', $output);

        $env = file_get_contents($this->root . '/.env');
        $this->assertIsString($env);
        $this->assertMatchesRegularExpression('/AUTH_JWT_SECRET=\w+/', $env);

        $modules = require $this->root . '/etc/modules.php';
        $this->assertContains(JwtAuthModule::class, $modules);

        $usersPath = $this->root . '/var/auth/users.json';
        $this->assertFileExists($usersPath);
        $users = json_decode((string) file_get_contents($usersPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($users);
        $this->assertCount(1, $users);
        $this->assertSame('admin', $users[0]['username']);
        $this->assertTrue(password_verify('password', $users[0]['password_hash']));

        ob_start();
        $secondExitCode = $command->handle([]);
        ob_end_clean();
        $this->assertSame(0, $secondExitCode);

        $modulesAgain = require $this->root . '/etc/modules.php';
        $this->assertSame($modules, $modulesAgain);

        $usersAgain = json_decode((string) file_get_contents($usersPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $usersAgain);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->deleteDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
