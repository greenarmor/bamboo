<?php

declare(strict_types=1);

namespace Bamboo\Console\DatabaseSetup\Driver;

use Bamboo\Console\Command\DatabaseSetup;

final class MysqlDriver extends AbstractPdoDriver
{
    public function name(): string
    {
        return 'mysql';
    }

    public function description(): string
    {
        return 'MySQL or MariaDB via PDO';
    }

    /**
     * @param array<string, string> $env
     * @return array{connectionName: string, connection: array<string, mixed>, env: array<string, string>}
     */
    public function configure(DatabaseSetup $command, array $env): array
    {
        $defaults = [
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'bamboo',
            'username' => 'root',
            'password' => '',
        ];

        $credentials = $this->promptForCredentials($command, $env, $defaults);

        $connection = [
            'driver' => 'mysql',
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'database' => $credentials['database'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        return [
            'connectionName' => 'mysql',
            'connection' => $connection,
            'env' => [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $credentials['host'],
                'DB_PORT' => $credentials['port'],
                'DB_DATABASE' => $credentials['database'],
                'DB_USERNAME' => $credentials['username'],
                'DB_PASSWORD' => $credentials['password'],
            ],
        ];
    }
}
