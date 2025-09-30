<?php

declare(strict_types=1);

namespace Bamboo\Console\DatabaseSetup\Driver;

use Bamboo\Console\Command\DatabaseSetup;
use MongoDB\Client;
use RuntimeException;
use Throwable;

final class MongoDbDriver implements DriverInterface
{
    public function name(): string
    {
        return 'mongodb';
    }

    public function description(): string
    {
        return 'MongoDB via mongodb/mongodb client';
    }

    public function supportsSchemaProvisioning(): bool
    {
        return false;
    }

    /**
     * @param array<string, string> $env
     * @return array{connectionName: string, connection: array<string, mixed>, env: array<string, string>}
     */
    public function configure(DatabaseSetup $command, array $env): array
    {
        $defaultDsn = $env['MONGO_DSN'] ?? $env['DB_DSN'] ?? 'mongodb://127.0.0.1:27017';
        $dsn = $command->prompt('MongoDB connection string', $defaultDsn);

        $defaultDatabase = $env['MONGO_DATABASE'] ?? $env['DB_DATABASE'] ?? 'bamboo';
        $database = $command->prompt('MongoDB database', $defaultDatabase);

        $connection = [
            'driver' => 'mongodb',
            'dsn' => $dsn,
            'database' => $database,
            'options' => [],
        ];

        return [
            'connectionName' => 'mongodb',
            'connection' => $connection,
            'env' => [
                'DB_CONNECTION' => 'mongodb',
                'DB_DSN' => $dsn,
                'DB_DATABASE' => $database,
                'MONGO_DSN' => $dsn,
                'MONGO_DATABASE' => $database,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $connection
     * @return Client
     */
    public function verifyConnection(array $connection)
    {
        if (!class_exists(Client::class)) {
            throw new RuntimeException('MongoDB support requires the "mongodb/mongodb" Composer package.');
        }

        $client = new Client($connection['dsn'], $connection['options'] ?? []);

        try {
            $client->selectDatabase($connection['database'])->command(['ping' => 1]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to connect to MongoDB: ' . $exception->getMessage(), 0, $exception);
        }

        return $client;
    }
}
