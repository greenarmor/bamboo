<?php

declare(strict_types=1);

namespace MongoDB;

if (!class_exists(CommandResult::class, false)) {
    class CommandResult
    {
    }
}

if (!class_exists(Database::class, false)) {
    class Database
    {
        public function command(array $command): CommandResult
        {
            return new CommandResult();
        }
    }
}

if (!class_exists(Client::class, false)) {
    class Client
    {
        public function __construct(string $uri = 'mongodb://127.0.0.1:27017', array $uriOptions = [], array $driverOptions = [])
        {
        }

        public function selectDatabase(string $databaseName, array $options = []): Database
        {
            return new Database();
        }
    }
}
