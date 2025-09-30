<?php

declare(strict_types=1);

namespace Bamboo\Console\DatabaseSetup\Driver;

use Bamboo\Console\Command\DatabaseSetup;

final class SqlServerDriver extends AbstractPdoDriver
{
    public function name(): string
    {
        return 'sqlsrv';
    }

    public function description(): string
    {
        return 'Microsoft SQL Server via sqlsrv PDO';
    }

    /**
     * @param array<string, string> $env
     * @return array{connectionName: string, connection: array<string, mixed>, env: array<string, string>}
     */
    public function configure(DatabaseSetup $command, array $env): array
    {
        $defaults = [
            'host' => '127.0.0.1',
            'port' => '1433',
            'database' => 'bamboo',
            'username' => 'sa',
            'password' => '',
        ];

        $credentials = $this->promptForCredentials($command, $env, $defaults);

        $connection = [
            'driver' => 'sqlsrv',
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'database' => $credentials['database'],
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'charset' => 'utf8',
        ];

        return [
            'connectionName' => 'sqlsrv',
            'connection' => $connection,
            'env' => [
                'DB_CONNECTION' => 'sqlsrv',
                'DB_HOST' => $credentials['host'],
                'DB_PORT' => $credentials['port'],
                'DB_DATABASE' => $credentials['database'],
                'DB_USERNAME' => $credentials['username'],
                'DB_PASSWORD' => $credentials['password'],
            ],
        ];
    }
}
