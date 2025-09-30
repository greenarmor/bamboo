<?php

declare(strict_types=1);

namespace Bamboo\Console\DatabaseSetup\Driver;

use Bamboo\Console\Command\DatabaseSetup;
use RuntimeException;

final class SqliteDriver extends AbstractPdoDriver
{
    public function name(): string
    {
        return 'sqlite';
    }

    public function description(): string
    {
        return 'SQLite file database';
    }

    /**
     * @param array<string, string> $env
     * @return array{connectionName: string, connection: array<string, mixed>, env: array<string, string>}
     */
    public function configure(DatabaseSetup $command, array $env): array
    {
        $defaultPath = $env['DB_DATABASE'] ?? 'var/database/database.sqlite';
        $relativePath = $command->prompt('SQLite database path (relative paths live in project root)', $defaultPath);
        $absolutePath = $command->toAbsolutePath($relativePath);
        $command->ensureDirectory(dirname($absolutePath));

        if (!file_exists($absolutePath)) {
            $handle = @fopen($absolutePath, 'c');
            if ($handle === false) {
                throw new RuntimeException(sprintf('Unable to create SQLite database file: %s', $absolutePath));
            }

            fclose($handle);
        }

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
}
