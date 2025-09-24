<?php

namespace OpenSwoole\HTTP;

if (!class_exists(Server::class)) {
    class Server
    {
        public function __construct(string $host, int $port)
        {
        }

        public function set(array $settings): bool
        {
            return true;
        }

        public function on(string $event, callable $handler): bool
        {
            return true;
        }

        public function start(): bool
        {
            return true;
        }
    }
}

if (!class_exists(Request::class)) {
    class Request
    {
        /** @var array<string, mixed> */
        public array $get = [];

        /** @var array<string, mixed> */
        public array $header = [];

        /** @var array<string, mixed> */
        public array $server = [];

        public function rawContent(): string
        {
            return '';
        }
    }
}

if (!class_exists(Response::class)) {
    class Response
    {
        public function header(string $key, string $value, int $ucwords = 0): bool
        {
            return true;
        }

        public function status(int $code, string $reason = ''): bool
        {
            return true;
        }

        public function end(string $content = ''): bool
        {
            return true;
        }
    }
}

namespace OpenSwoole;

if (!class_exists(Server::class)) {
    class Server
    {
        public function on(string $event, callable $handler): bool
        {
            return true;
        }
    }
}

if (!class_exists(Util::class)) {
    class Util
    {
        public static function getCPUNum(): int
        {
            return 1;
        }
    }
}

namespace OpenSwoole\WebSocket;

if (!class_exists(Server::class)) {
    class Server extends \OpenSwoole\Server
    {
        public function __construct(string $host, int $port)
        {
        }

        public function on(string $event, callable $handler): bool
        {
            return true;
        }

        public function push(int $fd, string $data, int $opcode = 1, bool $finish = true): bool
        {
            return true;
        }

        public function start(): bool
        {
            return true;
        }
    }
}
