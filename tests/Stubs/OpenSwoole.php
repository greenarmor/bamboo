<?php
namespace Tests\Stubs;

final class OpenSwooleHook
{
    /** @var list<float> */
    public static array $sleeps = [];

    /** @var list<int> */
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
        /** @var list<callable> */
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

if (!class_exists(__NAMESPACE__ . '\\Server')) {
    class Server
    {
        /** @var array<string, callable> */
        protected array $listeners = [];

        public function on(string $event, callable $handler): void
        {
            $this->listeners[$event] = $handler;
        }
    }
}

if (!class_exists(__NAMESPACE__ . '\\Table')) {
    class Table implements \IteratorAggregate, \Countable
    {
        public const TYPE_INT = 1;
        public const TYPE_FLOAT = 2;
        public const TYPE_STRING = 3;

        /** @var array<string, array{type: int, size: int}> */
        private array $columns = [];

        /** @var array<string, array<string, scalar>> */
        private array $rows = [];

        public function __construct(private int $size)
        {
        }

        public function column(string $name, int $type, int $size = 0): void
        {
            $this->columns[$name] = ['type' => $type, 'size' => $size];
        }

        public function create(): bool
        {
            return true;
        }

        public function set(string $key, array $values): bool
        {
            $row = $this->rows[$key] ?? [];
            foreach ($values as $column => $value) {
                if (!array_key_exists($column, $this->columns)) {
                    continue;
                }

                $row[$column] = $value;
            }

            $this->rows[$key] = $row;

            return true;
        }

        public function get(string $key, ?string $column = null): mixed
        {
            if (!array_key_exists($key, $this->rows)) {
                return false;
            }

            if ($column === null) {
                return $this->rows[$key];
            }

            return $this->rows[$key][$column] ?? null;
        }

        public function exists(string $key): bool
        {
            return array_key_exists($key, $this->rows);
        }

        public function incr(string $key, string $column, float $value = 1.0): float
        {
            $row = $this->rows[$key] ?? [];
            $current = (float) ($row[$column] ?? 0.0);
            $row[$column] = $current + $value;
            $this->rows[$key] = $row;

            return $row[$column];
        }

        public function del(string $key): bool
        {
            if (!array_key_exists($key, $this->rows)) {
                return false;
            }

            unset($this->rows[$key]);

            return true;
        }

        public function getIterator(): \Traversable
        {
            foreach ($this->rows as $key => $row) {
                yield $key => $row;
            }
        }

        public function count(): int
        {
            return count($this->rows);
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

        /** @var array<string, mixed> */
        public array $settings = [];

        /** @var array<string, callable> */
        public array $listeners = [];
        public bool $started = false;

        public function __construct(public string $host, public int $port)
        {
            self::$lastInstance = $this;
        }

        /**
         * @param array<string, mixed> $settings
         */
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

        public function trigger(string $event, mixed ...$args): mixed
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
        /** @var array<string, mixed> */
        public array $server = [];

        /** @var array<string, mixed> */
        public array $get = [];

        /** @var array<string, mixed> */
        public array $post = [];

        /** @var array<string, string|string[]> */
        public array $header = [];

        private string $rawContent = '';

        public function rawContent(): string
        {
            return $this->rawContent;
        }

        public function setRawContent(string $content): void
        {
            $this->rawContent = $content;
        }
    }
}

if (!class_exists(__NAMESPACE__ . '\\Response')) {
    class Response
    {
        /** @var array<string, string> */
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
