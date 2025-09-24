<?php

declare(strict_types=1);

namespace Bamboo\Console\Command;

use Bamboo\Auth\Jwt\JwtAuthModule;
use Bamboo\Core\Application;

final class AuthJwtSetup extends Command
{
    private string $projectRoot;

    public function __construct(Application $app, ?string $projectRoot = null)
    {
        parent::__construct($app);
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    public function name(): string
    {
        return 'auth.jwt.setup';
    }

    public function description(): string
    {
        return 'Scaffold JWT auth configuration, module registration, and seed users.';
    }

    /**
     * @param list<string> $args
     */
    public function handle(array $args): int
    {
        if (!is_dir($this->projectRoot)) {
            echo "Project root not found: {$this->projectRoot}\n";

            return 1;
        }

        $messages = [];

        [$secretUpdated, $secret] = $this->ensureSecret();
        if ($secretUpdated) {
            $messages[] = 'Generated AUTH_JWT_SECRET in .env.';
        } else {
            $messages[] = 'AUTH_JWT_SECRET already present; leaving existing value.';
        }

        if ($secret !== null) {
            putenv('AUTH_JWT_SECRET=' . $secret);
            $_ENV['AUTH_JWT_SECRET'] = $secret;
        }

        if ($this->ensureAuthConfig()) {
            $messages[] = 'Published etc/auth.php configuration.';
        }

        [$storagePath, $wasSeeded] = $this->ensureUserStore();
        if ($wasSeeded) {
            $messages[] = sprintf('Seeded default admin user at %s (admin/password).', $storagePath);
        } else {
            $messages[] = sprintf('User store ready at %s.', $storagePath);
        }

        if ($this->ensureModuleRegistration()) {
            $messages[] = 'Registered Bamboo\\Auth\\Jwt\\JwtAuthModule in etc/modules.php.';
        }

        foreach ($messages as $message) {
            echo $message . "\n";
        }

        echo "JWT authentication scaffolding is ready to use.\n";

        return 0;
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function ensureSecret(): array
    {
        $envPath = $this->projectRoot . '/.env';
        $envExample = $this->projectRoot . '/.env.example';

        if (!file_exists($envPath) && file_exists($envExample)) {
            @copy($envExample, $envPath);
        }

        $contents = file_exists($envPath) ? file_get_contents($envPath) : '';
        if ($contents === false) {
            $contents = '';
        }

        if ($contents !== '') {
            if (preg_match('/^AUTH_JWT_SECRET\s*=\s*(\S+)/m', $contents, $matches)) {
                $value = trim($matches[1]);
                if ($value !== '') {
                    return [false, $value];
                }
            }
        }

        $secret = bin2hex(random_bytes(32));

        if (preg_match('/^AUTH_JWT_SECRET\s*=.*$/m', $contents)) {
            $updated = preg_replace('/^AUTH_JWT_SECRET\s*=.*$/m', 'AUTH_JWT_SECRET=' . $secret, (string) $contents);
            $contents = is_string($updated) ? $updated : (string) $contents;
        } else {
            $contents = rtrim((string) $contents, "\r\n") . "\nAUTH_JWT_SECRET={$secret}\n";
        }

        file_put_contents($envPath, (string) $contents);

        return [true, $secret];
    }

    private function ensureAuthConfig(): bool
    {
        $configPath = $this->projectRoot . '/etc/auth.php';
        if (file_exists($configPath)) {
            return false;
        }

        $stub = $this->projectRoot . '/stubs/auth/jwt-auth.php';
        if (!file_exists($stub)) {
            throw new \RuntimeException('Auth configuration stub missing: ' . $stub);
        }

        @mkdir(dirname($configPath), 0775, true);
        copy($stub, $configPath);

        return true;
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function ensureUserStore(): array
    {
        $configPath = $this->projectRoot . '/etc/auth.php';
        $storage = 'var/auth/users.json';

        if (file_exists($configPath)) {
            $config = require $configPath;
            if (is_array($config) && isset($config['jwt']) && is_array($config['jwt'])) {
                $candidate = $config['jwt']['storage']['path'] ?? $storage;
                if (is_string($candidate) && $candidate !== '') {
                    $storage = $candidate;
                }
            }
        }

        $absolute = $this->toAbsolutePath($storage);

        if (!is_file($absolute) || trim((string) file_get_contents($absolute)) === '') {
            $this->seedDefaultUser($absolute);

            return [$absolute, true];
        }

        return [$absolute, false];
    }

    private function seedDefaultUser(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $user = [
            'id' => bin2hex(random_bytes(8)),
            'username' => 'admin',
            'password_hash' => password_hash('password', PASSWORD_BCRYPT),
            'roles' => ['admin'],
            'email' => 'admin@example.com',
            'meta' => ['display_name' => 'Administrator'],
            'created_at' => gmdate('c'),
        ];

        $json = json_encode([$user], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode default user.');
        }

        file_put_contents($path, $json . "\n", LOCK_EX);
    }

    private function ensureModuleRegistration(): bool
    {
        $modulesPath = $this->projectRoot . '/etc/modules.php';
        $modules = [];

        if (file_exists($modulesPath)) {
            $loaded = require $modulesPath;
            if (is_array($loaded)) {
                $modules = $loaded;
            }
        }

        if (in_array(JwtAuthModule::class, $modules, true)) {
            return false;
        }

        $modules[] = JwtAuthModule::class;

        $header = <<<'PHP'
<?php

declare(strict_types=1);

/**
 * Application module registration list.
 *
 * Each entry should be a fully-qualified class string implementing
 * \Bamboo\Module\ModuleInterface. Modules will be instantiated and invoked in
 * the order they appear in this array during application bootstrap.
 *
 * @return list<class-string<\Bamboo\Module\ModuleInterface>>
 */
PHP;

        $lines = array_map(
            static fn (string $class): string => '    ' . $class . '::class,',
            $modules
        );

        $body = "return [\n" . implode("\n", $lines) . "\n];\n";

        file_put_contents($modulesPath, $header . "\n" . $body);

        return true;
    }

    private function toAbsolutePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));

        return $this->projectRoot . DIRECTORY_SEPARATOR . $normalized;
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
