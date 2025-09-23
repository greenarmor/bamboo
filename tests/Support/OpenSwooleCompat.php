<?php

declare(strict_types=1);

namespace Tests\Support;

use ReflectionClass;

final class OpenSwooleCompat
{
    public static function coroutineUsesStub(): bool
    {
        if (!class_exists(\OpenSwoole\Coroutine::class)) {
            return false;
        }

        $reflection = new ReflectionClass(\OpenSwoole\Coroutine::class);

        return $reflection->getFileName() !== false;
    }

    public static function httpServerUsesStub(): bool
    {
        if (!class_exists(\OpenSwoole\HTTP\Server::class)) {
            return false;
        }

        $reflection = new ReflectionClass(\OpenSwoole\HTTP\Server::class);

        return $reflection->getFileName() !== false;
    }
}
