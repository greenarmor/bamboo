<?php

declare(strict_types=1);

namespace Bamboo\Console\DatabaseSetup\Driver;

use Bamboo\Console\Command\DatabaseSetup;
use Illuminate\Database\Capsule\Manager as Capsule;
use RuntimeException;
use Throwable;

abstract class AbstractPdoDriver implements DriverInterface
{
    final public function supportsSchemaProvisioning(): bool
    {
        return true;
    }

    /**
     * @param array<string, string> $env
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    final protected function promptForCredentials(DatabaseSetup $command, array $env, array $defaults): array
    {
        $host = $command->prompt('Database host', $env['DB_HOST'] ?? $defaults['host']);
        $port = $command->prompt('Database port', $env['DB_PORT'] ?? $defaults['port']);
        $database = $command->prompt('Database name', $env['DB_DATABASE'] ?? $defaults['database']);
        $username = $command->prompt('Database username', $env['DB_USERNAME'] ?? $defaults['username']);
        $password = $command->prompt('Database password (input hidden not supported)', $env['DB_PASSWORD'] ?? $defaults['password'], true);

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * @param array<string, mixed> $connection
     */
    public function verifyConnection(array $connection): Capsule
    {
        $capsule = new Capsule();
        $capsule->addConnection($connection);

        try {
            $capsule->getConnection()->getPdo();
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to connect to the database: ' . $exception->getMessage(), 0, $exception);
        }

        return $capsule;
    }
}
