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

        $storage = $this->ensureUserStore();
        if ($storage['seeded']) {
            $messages[] = sprintf('Seeded default admin user at %s (admin/password).', $storage['descriptor']);
        } elseif ($storage['driver'] === 'json') {
            $messages[] = sprintf('User store ready at %s.', $storage['descriptor']);
        } else {
            $messages[] = sprintf('User store configured for %s.', $storage['descriptor']);
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
     * @return array{driver: string, descriptor: string, seeded: bool}
     */
    private function ensureUserStore(): array
    {
        $configPath = $this->projectRoot . '/etc/auth.php';
        $driver = 'json';
        $storagePath = 'var/auth/users.json';
        $storageConfig = [];

        if (file_exists($configPath)) {
            $config = require $configPath;
            if (is_array($config) && isset($config['jwt']) && is_array($config['jwt'])) {
                $storageValue = $config['jwt']['storage'] ?? [];
                if (is_array($storageValue)) {
                    $storageConfig = $storageValue;
                }
            }
        }

        if (isset($storageConfig['driver']) && is_string($storageConfig['driver']) && $storageConfig['driver'] !== '') {
            $driver = strtolower($storageConfig['driver']);
        }

        if ($driver === 'json') {
            $candidatePath = $storageConfig['path'] ?? null;

            if (isset($storageConfig['drivers']) && is_array($storageConfig['drivers'])) {
                $jsonDriver = $storageConfig['drivers']['json'] ?? null;
                if (is_array($jsonDriver) && isset($jsonDriver['path']) && is_string($jsonDriver['path']) && $jsonDriver['path'] !== '') {
                    $candidatePath = $jsonDriver['path'];
                }
            }

            if (is_string($candidatePath) && $candidatePath !== '') {
                $storagePath = $candidatePath;
            }

            $absolute = $this->toAbsolutePath($storagePath);

            if (!is_file($absolute) || trim((string) file_get_contents($absolute)) === '') {
                $this->seedDefaultUser($absolute);

                return [
                    'driver' => 'json',
                    'descriptor' => $absolute,
                    'seeded' => true,
                ];
            }

            return [
                'driver' => 'json',
                'descriptor' => $absolute,
                'seeded' => false,
            ];
        }

        return [
            'driver' => $driver,
            'descriptor' => $this->describeUserStore($driver, $storageConfig),
            'seeded' => false,
        ];
    }

    /**
     * @param array<string, mixed> $storage
     */
    private function describeUserStore(string $driver, array $storage): string
    {
        $drivers = $storage['drivers'] ?? [];
        $driverConfig = [];

        if (is_array($drivers) && isset($drivers[$driver]) && is_array($drivers[$driver])) {
            $driverConfig = $drivers[$driver];
        }

        $label = match ($driver) {
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'firebase' => 'Firebase',
            'nosql' => 'NoSQL',
            default => ucfirst($driver),
        };

        return match ($driver) {
            'mysql', 'pgsql' => $this->describeSqlStore($label, $driverConfig, $storage),
            'firebase' => $this->describeFirebaseStore($label, $driverConfig, $storage),
            'nosql' => $this->describeNoSqlStore($label, $driverConfig, $storage),
            default => sprintf('%s user storage', $label),
        };
    }

    /**
     * @param array<string, mixed> $driverConfig
     * @param array<string, mixed> $storage
     */
    private function describeSqlStore(string $label, array $driverConfig, array $storage): string
    {
        $table = $driverConfig['table'] ?? ($storage['table'] ?? null);
        if (!is_string($table) || $table === '') {
            $table = 'auth_users';
        }

        return sprintf('%s table %s', $label, $table);
    }

    /**
     * @param array<string, mixed> $driverConfig
     * @param array<string, mixed> $storage
     */
    private function describeFirebaseStore(string $label, array $driverConfig, array $storage): string
    {
        $collection = $driverConfig['collection'] ?? ($storage['collection'] ?? null);
        if (!is_string($collection) || $collection === '') {
            $collection = 'auth_users';
        }

        return sprintf('%s collection %s', $label, $collection);
    }

    /**
     * @param array<string, mixed> $driverConfig
     * @param array<string, mixed> $storage
     */
    private function describeNoSqlStore(string $label, array $driverConfig, array $storage): string
    {
        $database = $driverConfig['database'] ?? ($storage['database'] ?? null);
        $collection = $driverConfig['collection'] ?? ($storage['collection'] ?? null);

        if (!is_string($database) || $database === '') {
            $database = 'bamboo';
        }

        if (!is_string($collection) || $collection === '') {
            $collection = 'auth_users';
        }

        return sprintf('%s collection %s.%s', $label, $database, $collection);
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
