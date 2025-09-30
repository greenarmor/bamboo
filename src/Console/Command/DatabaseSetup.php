<?php

declare(strict_types=1);

namespace Bamboo\Console\Command;

use Bamboo\Core\Application;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use RuntimeException;

final class DatabaseSetup extends Command
{
    private string $projectRoot;

    /** @var resource */
    private $stdin;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdin
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(
        Application $app,
        ?string $projectRoot = null,
        $stdin = null,
        $stdout = null,
        $stderr = null
    ) {
        parent::__construct($app);

        if ($stdin !== null && !is_resource($stdin)) {
            throw new InvalidArgumentException('STDIN stream must be a resource.');
        }

        if ($stdout !== null && !is_resource($stdout)) {
            throw new InvalidArgumentException('STDOUT stream must be a resource.');
        }

        if ($stderr !== null && !is_resource($stderr)) {
            throw new InvalidArgumentException('STDERR stream must be a resource.');
        }

        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    public function name(): string
    {
        return 'database.setup';
    }

    public function description(): string
    {
        return 'Interactive wizard to configure database connections, schema, and seeds.';
    }

    /**
     * @param list<string> $args
     */
    public function handle(array $args): int
    {
        if (!is_dir($this->projectRoot)) {
            $this->writeln($this->stderr, sprintf('Project root not found: %s', $this->projectRoot));

            return 1;
        }

        $this->writeln($this->stdout, 'Database Setup Wizard');
        $this->writeln($this->stdout, '======================');
        $this->writeln($this->stdout, '');

        $connectionData = $this->collectConnectionConfiguration();
        if ($connectionData === null) {
            return 1;
        }

        $this->writeEnv($connectionData['env']);
        $this->writeDatabaseConfig($connectionData['connectionName'], $connectionData['connection']);

        try {
            $capsule = $this->bootConnection($connectionData['connection']);
        } catch (RuntimeException $exception) {
            $this->writeln($this->stderr, $exception->getMessage());

            return 1;
        }

        $this->writeln($this->stdout, 'Connection verified.');

        $tables = $this->collectTableDefinitions($capsule);
        if ($tables !== []) {
            $this->provisionTables($capsule, $tables);
        }

        $this->writeln($this->stdout, 'Database setup complete.');

        return 0;
    }

    /**
     * @return array{connectionName: string, connection: array<string, mixed>, env: array<string, string>}|null
     */
    private function collectConnectionConfiguration(): ?array
    {
        $env = $this->readEnvFile();
        $driverDefault = strtolower($env['DB_CONNECTION'] ?? 'sqlite');

        $driver = $this->promptChoice(
            'Select database driver (mysql/pgsql/sqlite)',
            ['mysql', 'pgsql', 'sqlite'],
            $driverDefault
        );

        if ($driver === 'sqlite') {
            $defaultPath = $env['DB_DATABASE'] ?? 'var/database/database.sqlite';
            $relativePath = $this->prompt('SQLite database path (relative paths live in project root)', $defaultPath);
            $absolutePath = $this->toAbsolutePath($relativePath);
            $this->ensureDirectory(dirname($absolutePath));

            $connection = [
                'driver' => 'sqlite',
                'database' => $absolutePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];

            return [
                'connectionName' => 'sqlite',
                'connection' => $connection,
                'env' => [
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => $relativePath,
                    'DB_HOST' => '',
                    'DB_PORT' => '',
                    'DB_USERNAME' => '',
                    'DB_PASSWORD' => '',
                ],
            ];
        }

        $hostDefault = $env['DB_HOST'] ?? '127.0.0.1';
        $portDefault = $env['DB_PORT'] ?? ($driver === 'mysql' ? '3306' : '5432');
        $databaseDefault = $env['DB_DATABASE'] ?? 'bamboo';
        $usernameDefault = $env['DB_USERNAME'] ?? 'root';
        $passwordDefault = $env['DB_PASSWORD'] ?? '';

        $host = $this->prompt('Database host', $hostDefault);
        $port = $this->prompt('Database port', $portDefault);
        $database = $this->prompt('Database name', $databaseDefault);
        $username = $this->prompt('Database username', $usernameDefault);
        $password = $this->prompt('Database password (input hidden not supported)', $passwordDefault, true);

        $connection = [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];

        if ($driver === 'mysql') {
            $connection['charset'] = 'utf8mb4';
            $connection['collation'] = 'utf8mb4_unicode_ci';
        } elseif ($driver === 'pgsql') {
            $connection['charset'] = 'utf8';
            $connection['prefix'] = '';
            $connection['schema'] = 'public';
        }

        return [
            'connectionName' => $driver,
            'connection' => $connection,
            'env' => [
                'DB_CONNECTION' => $driver,
                'DB_HOST' => $host,
                'DB_PORT' => $port,
                'DB_DATABASE' => $database,
                'DB_USERNAME' => $username,
                'DB_PASSWORD' => $password,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function bootConnection(array $connection): Capsule
    {
        $capsule = new Capsule();
        $capsule->addConnection($connection);

        try {
            $capsule->getConnection()->getPdo();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to connect to the database: ' . $exception->getMessage(), 0, $exception);
        }

        return $capsule;
    }

    /**
     * @return list<array{name: string, columns: list<array{name: string, type: string, nullable: bool, default: mixed}>, seeds: list<array<string, mixed>>}>
     */
    private function collectTableDefinitions(Capsule $capsule): array
    {
        $definitions = [];
        $schema = $capsule->getConnection()->getSchemaBuilder();

        while ($this->confirm('Would you like to create a table?', false)) {
            $tableName = $this->prompt('Table name');
            if ($tableName === '') {
                $this->writeln($this->stdout, 'Table name cannot be empty.');

                continue;
            }

            if ($schema->hasTable($tableName)) {
                $this->writeln($this->stdout, sprintf('Table "%s" already exists; skipping.', $tableName));

                continue;
            }

            $columns = $this->collectColumns();
            if ($columns === []) {
                $this->writeln($this->stdout, 'At least one column is required to create a table.');

                continue;
            }

            $seeds = $this->collectSeeds($columns, $tableName);

            $definitions[] = [
                'name' => $tableName,
                'columns' => $columns,
                'seeds' => $seeds,
            ];
        }

        return $definitions;
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool, default: mixed}>
     */
    private function collectColumns(): array
    {
        $columns = [];

        while (true) {
            $columnName = $this->prompt('Column name (leave blank to finish)', null, true);
            if ($columnName === '') {
                break;
            }

            $type = $this->promptChoice(
                sprintf('Column "%s" type', $columnName),
                $this->availableColumnTypes(),
                'string'
            );

            $nullable = $type === 'increments' ? false : $this->confirm(sprintf('Allow NULL for "%s"?', $columnName), false);
            $default = null;
            if ($type !== 'increments' && $this->confirm(sprintf('Set a default for "%s"?', $columnName), false)) {
                $defaultInput = $this->prompt(sprintf('Default value for "%s"', $columnName), '', true);
                if ($defaultInput === '') {
                    $default = null;
                } else {
                    $default = $this->castToColumnType($defaultInput, $type);
                }
            }

            $columns[] = [
                'name' => $columnName,
                'type' => $type,
                'nullable' => $nullable,
                'default' => $default,
            ];
        }

        return $columns;
    }

    /**
     * @param list<array{name: string, type: string, nullable: bool, default: mixed}> $columns
     * @return list<array<string, mixed>>
     */
    private function collectSeeds(array $columns, string $tableName): array
    {
        if (!$this->confirm(sprintf('Seed data for "%s"?', $tableName), false)) {
            return [];
        }

        $rows = [];

        while (true) {
            $row = [];
            foreach ($columns as $column) {
                if ($column['type'] === 'increments') {
                    continue;
                }

                $prompt = sprintf('Value for column "%s"', $column['name']);
                $allowEmpty = $column['nullable'];
                $valueInput = $this->prompt($prompt, $allowEmpty ? '' : null, $allowEmpty);

                if ($allowEmpty && $valueInput === '') {
                    $row[$column['name']] = null;
                } else {
                    $row[$column['name']] = $this->castToColumnType($valueInput, $column['type']);
                }
            }

            $rows[] = $row;

            if (!$this->confirm('Add another seed row?', false)) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param list<array{name: string, columns: list<array{name: string, type: string, nullable: bool, default: mixed}>, seeds: list<array<string, mixed>>}> $tables
     */
    private function provisionTables(Capsule $capsule, array $tables): void
    {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $connection = $capsule->getConnection();

        foreach ($tables as $table) {
            if ($schema->hasTable($table['name'])) {
                $this->writeln($this->stdout, sprintf('Table "%s" already exists; skipping.', $table['name']));

                continue;
            }

            $schema->create($table['name'], function (Blueprint $blueprint) use ($table): void {
                foreach ($table['columns'] as $column) {
                    $this->applyColumnDefinition($blueprint, $column);
                }
            });

            $this->writeln($this->stdout, sprintf('Created table "%s".', $table['name']));

            if ($table['seeds'] === []) {
                continue;
            }

            $count = (int) $connection->table($table['name'])->count();
            if ($count > 0) {
                $this->writeln($this->stdout, sprintf('Seed skipped for "%s" because rows already exist.', $table['name']));

                continue;
            }

            foreach ($table['seeds'] as $row) {
                $connection->table($table['name'])->insert($row);
            }

            $this->writeln($this->stdout, sprintf('Seeded %d row(s) into "%s".', count($table['seeds']), $table['name']));
        }
    }

    /**
     * @param array{name: string, type: string, nullable: bool, default: mixed} $column
     */
    private function applyColumnDefinition(Blueprint $blueprint, array $column): void
    {
        $name = $column['name'];
        $type = $column['type'];

        if ($type === 'increments') {
            $blueprint->increments($name);

            return;
        }

        $method = $type;
        if (!method_exists($blueprint, $method)) {
            throw new RuntimeException(sprintf('Unsupported column type "%s".', $type));
        }

        /** @var \Illuminate\Database\Schema\ColumnDefinition $definition */
        $definition = $blueprint->{$method}($name);

        if ($column['nullable']) {
            $definition->nullable();
        }

        if ($column['default'] !== null) {
            $definition->default($column['default']);
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function writeEnv(array $values): void
    {
        $envPath = $this->projectRoot . '/.env';
        $contents = '';
        if (file_exists($envPath)) {
            $existing = file_get_contents($envPath);
            if ($existing !== false) {
                $contents = $existing;
            }
        }

        foreach ($values as $key => $value) {
            $line = $key . '=' . $value;
            if (preg_match('/^' . preg_quote($key, '/') . '\s*=.*$/m', $contents)) {
                $contents = (string) preg_replace(
                    '/^' . preg_quote($key, '/') . '\s*=.*$/m',
                    $line,
                    $contents
                );
            } else {
                if ($contents === '') {
                    $contents = $line;
                } else {
                    $contents = rtrim($contents, "\r\n") . "\n" . $line;
                }
            }
        }

        $contents = rtrim($contents, "\r\n") . "\n";

        file_put_contents($envPath, $contents);
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function writeDatabaseConfig(string $connectionName, array $connection): void
    {
        $configPath = $this->projectRoot . '/etc/database.php';
        $this->ensureDirectory(dirname($configPath));

        $config = [
            'default' => $connectionName,
            'connections' => [
                $connectionName => $connection,
            ],
        ];

        $export = var_export($config, true);
        $contents = "<?php\nreturn " . $export . ";\n";

        file_put_contents($configPath, $contents);
    }

    /**
     * @return array<string, string>
     */
    private function readEnvFile(): array
    {
        $envPath = $this->projectRoot . '/.env';
        $values = [];

        if (!file_exists($envPath)) {
            return $values;
        }

        $contents = file_get_contents($envPath);
        if ($contents === false) {
            return $values;
        }

        $lines = preg_split('/\r?\n/', $contents);
        if ($lines === false) {
            return $values;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value);
        }

        return $values;
    }

    /**
     * @param list<string> $choices
     */
    private function promptChoice(string $label, array $choices, string $default): string
    {
        $lowerChoices = array_map('strtolower', $choices);
        $default = strtolower($default);
        if (!in_array($default, $lowerChoices, true)) {
            $default = $lowerChoices[0];
        }

        while (true) {
            $this->write($this->stdout, sprintf('%s [%s]: ', $label, implode('/', $choices)));
            $line = fgets($this->stdin);
            if ($line === false) {
                $value = $default;
            } else {
                $value = strtolower(trim($line));
                if ($value === '') {
                    $value = $default;
                }
            }

            $index = array_search($value, $lowerChoices, true);
            if ($index === false) {
                $this->writeln($this->stdout, sprintf('Please choose one of: %s', implode(', ', $choices)));

                continue;
            }

            return $lowerChoices[$index];
        }
    }

    private function prompt(string $label, ?string $default = null, bool $allowEmpty = false): string
    {
        while (true) {
            $prompt = $label;
            if ($default !== null && $default !== '') {
                $prompt .= sprintf(' [%s]', $default);
            }
            $prompt .= ': ';

            $this->write($this->stdout, $prompt);
            $line = fgets($this->stdin);
            if ($line === false) {
                return $default ?? '';
            }

            $value = trim($line);
            if ($value === '') {
                if ($default !== null) {
                    return $default;
                }

                if ($allowEmpty) {
                    return '';
                }

                $this->writeln($this->stdout, 'A value is required.');

                continue;
            }

            return $value;
        }
    }

    private function confirm(string $label, bool $default = true): bool
    {
        $suffix = $default ? 'Y/n' : 'y/N';

        while (true) {
            $this->write($this->stdout, sprintf('%s [%s]: ', $label, $suffix));
            $line = fgets($this->stdin);
            if ($line === false) {
                return $default;
            }

            $value = strtolower(trim($line));
            if ($value === '') {
                return $default;
            }

            if (in_array($value, ['y', 'yes'], true)) {
                return true;
            }

            if (in_array($value, ['n', 'no'], true)) {
                return false;
            }

            $this->writeln($this->stdout, 'Please answer yes or no.');
        }
    }

    private function castToColumnType(string $value, string $type): mixed
    {
        switch ($type) {
            case 'integer':
            case 'bigInteger':
                return (int) $value;
            case 'boolean':
                $lower = strtolower($value);
                if (in_array($lower, ['1', 'true', 'yes', 'y'], true)) {
                    return true;
                }
                if (in_array($lower, ['0', 'false', 'no', 'n'], true)) {
                    return false;
                }

                return (bool) $value;
            default:
                return $value;
        }
    }

    /**
     * @return list<string>
     */
    private function availableColumnTypes(): array
    {
        return [
            'increments',
            'integer',
            'bigInteger',
            'string',
            'text',
            'boolean',
            'timestamp',
        ];
    }

    private function toAbsolutePath(string $path): string
    {
        if ($path === '') {
            return $this->projectRoot . '/var/database/database.sqlite';
        }

        if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $this->projectRoot . '/' . ltrim($path, '/');
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory: %s', $directory));
        }
    }

    /**
     * @param resource $stream
     */
    private function write($stream, string $message): void
    {
        fwrite($stream, $message);
    }

    /**
     * @param resource $stream
     */
    private function writeln($stream, string $message): void
    {
        $this->write($stream, $message . "\n");
    }
}
