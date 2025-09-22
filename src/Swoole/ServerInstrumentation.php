<?php

declare(strict_types=1);

namespace Bamboo\Swoole;

use OpenSwoole\HTTP\Server;

final class ServerInstrumentation
{
    private static ?Server $server = null;

    private static ?string $host = null;

    private static ?int $port = null;

    private static bool $started = false;

    public static function record(Server $server, string $host, int $port): void
    {
        self::$server = $server;
        self::$host = $host;
        self::$port = $port;
        self::$started = false;
    }

    public static function markStarted(): void
    {
        if (self::$server !== null) {
            self::$started = true;
        }
    }

    public static function server(): ?Server
    {
        return self::$server;
    }

    public static function host(): ?string
    {
        return self::$host;
    }

    public static function port(): ?int
    {
        return self::$port;
    }

    public static function started(): bool
    {
        return self::$started;
    }

    public static function reset(): void
    {
        self::$server = null;
        self::$host = null;
        self::$port = null;
        self::$started = false;
    }
}
