<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class PortAllocator
{
    private const HOST = '127.0.0.1';

    public static function allocate(): int
    {
        $socket = @stream_socket_server('tcp://' . self::HOST . ':0', $errno, $errstr);

        if ($socket === false) {
            throw new RuntimeException(sprintf('Unable to allocate test port: %s', $errstr));
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            throw new RuntimeException('Unable to determine allocated port name.');
        }

        $parts = explode(':', $name);
        $port = (int) array_pop($parts);

        if ($port <= 0) {
            throw new RuntimeException(sprintf('Invalid port discovered from "%s".', $name));
        }

        return $port;
    }
}
