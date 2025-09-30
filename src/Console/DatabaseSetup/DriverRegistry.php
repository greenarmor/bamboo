<?php

declare(strict_types=1);

namespace Bamboo\Console\DatabaseSetup;

use Bamboo\Console\DatabaseSetup\Driver\DriverInterface;
use Bamboo\Console\DatabaseSetup\Driver\MongoDbDriver;
use Bamboo\Console\DatabaseSetup\Driver\MysqlDriver;
use Bamboo\Console\DatabaseSetup\Driver\PgsqlDriver;
use Bamboo\Console\DatabaseSetup\Driver\SqliteDriver;
use Bamboo\Console\DatabaseSetup\Driver\SqlServerDriver;
use InvalidArgumentException;

final class DriverRegistry
{
    /** @var array<string, DriverInterface> */
    private array $drivers;

    /**
     * @param iterable<DriverInterface> $drivers
     */
    public function __construct(iterable $drivers)
    {
        $registered = [];
        foreach ($drivers as $driver) {
            $name = $driver->name();
            if (isset($registered[$name])) {
                throw new InvalidArgumentException(sprintf('Duplicate database driver "%s" registered.', $name));
            }

            $registered[$name] = $driver;
        }

        $this->drivers = $registered;
    }

    public static function default(): self
    {
        return new self([
            new SqliteDriver(),
            new MysqlDriver(),
            new PgsqlDriver(),
            new SqlServerDriver(),
            new MongoDbDriver(),
        ]);
    }

    /**
     * @return list<DriverInterface>
     */
    public function all(): array
    {
        return array_values($this->drivers);
    }

    public function get(string $name): DriverInterface
    {
        if (!isset($this->drivers[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown database driver "%s".', $name));
        }

        return $this->drivers[$name];
    }
}
