<?php

declare(strict_types=1);

namespace Bamboo\Console\DatabaseSetup\Driver;

use Bamboo\Console\Command\DatabaseSetup;

interface DriverInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @param array<string, string> $env
     * @return array{connectionName: string, connection: array<string, mixed>, env: array<string, string>}
     */
    public function configure(DatabaseSetup $command, array $env): array;

    /**
     * @param array<string, mixed> $connection
     * @return mixed
     */
    public function verifyConnection(array $connection);

    public function supportsSchemaProvisioning(): bool;
}
