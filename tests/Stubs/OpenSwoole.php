<?php
namespace Tests\Stubs;

final class OpenSwooleHook
{
    public static array $sleeps = [];
    public static array $microSleeps = [];

    public static function reset(): void
    {
        self::$sleeps = [];
        self::$microSleeps = [];
    }
}

namespace OpenSwoole;

if (!class_exists(__NAMESPACE__ . '\\Coroutine')) {
    class Coroutine
    {
        public static array $created = [];
        private static int $nextCid = 1;
        private static int $currentCid = -1;

        public static function create(callable $fn): void
        {
            self::$created[] = $fn;
            self::withCid($fn);
        }

        public static function run(callable $fn): bool
        {
            self::withCid($fn);

            return true;
        }

        public static function getCid(): int
        {
            return self::$currentCid;
        }

        private static function withCid(callable $fn): void
        {
            $previousCid = self::$currentCid;
            self::$currentCid = self::$nextCid++;

            try {
                $fn();
            } finally {
                self::$currentCid = $previousCid;
            }
        }

        public static function sleep(float $seconds): void
        {
            \Tests\Stubs\OpenSwooleHook::$sleeps[] = $seconds;
        }

        public static function usleep(int $microseconds): void
        {
            \Tests\Stubs\OpenSwooleHook::$microSleeps[] = $microseconds;
        }
    }
}

namespace OpenSwoole\Coroutine;

if (!class_exists(__NAMESPACE__ . '\\WaitGroup')) {
    class WaitGroup
    {
        private int $count = 0;

        public function add(int $delta = 1): void
        {
            $this->count += $delta;
        }

        public function done(): void
        {
            $this->count = max(0, $this->count - 1);
        }

        public function wait(): void
        {
            $this->count = 0;
        }

        public function getCount(): int
        {
            return $this->count;
        }
    }
}

namespace OpenSwoole\HTTP;

if (!class_exists(__NAMESPACE__ . '\\Server')) {
    class Server
    {
        public static ?Server $lastInstance = null;

        public array $settings = [];
        public array $listeners = [];
        public bool $started = false;

        public function __construct(public string $host, public int $port)
        {
            self::$lastInstance = $this;
        }

        public function set(array $settings): void
        {
            $this->settings = $settings;
        }

        public function on(string $event, callable $handler): void
        {
            $this->listeners[$event] = $handler;
        }

        public function start(): void
        {
            $this->started = true;
            if (isset($this->listeners['start'])) {
                ($this->listeners['start'])();
            }
        }

        public function trigger(string $event, ...$args): mixed
        {
            if (!isset($this->listeners[$event])) {
                return null;
            }

            return $this->listeners[$event](...$args);
        }
    }
}

if (!class_exists(__NAMESPACE__ . '\\Request')) {
    class Request
    {
        public array $server = [];
        public array $get = [];
        public array $post = [];
    }
}

if (!class_exists(__NAMESPACE__ . '\\Response')) {
    class Response
    {
        public array $headers = [];
        public int $status = 200;
        public string $body = '';

        public function header(string $name, string $value): void
        {
            $this->headers[$name] = $value;
        }

        public function status(int $status): void
        {
            $this->status = $status;
        }

        public function end(string $body = ''): void
        {
            $this->body .= $body;
        }
    }
}
