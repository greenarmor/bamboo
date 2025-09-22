<?php

namespace Tests\Stubs;

final class ModuleLifecycleLog
{
    /**
     * @var list<string>
     */
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public static function record(string $module, string $event): void
    {
        self::$events[] = sprintf('%s:%s', $module, $event);
    }
}
