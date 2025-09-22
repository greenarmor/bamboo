#!/usr/bin/env php
<?php
declare(strict_types=1);

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, static function (): void {
        fwrite(STDOUT, "fake-server: stopping\n");
        exit(0);
    });
    pcntl_signal(SIGINT, static function (): void {
        fwrite(STDOUT, "fake-server: interrupt\n");
        exit(0);
    });
}

fwrite(STDOUT, "fake-server: started\n");

while (true) {
    usleep(100000);
}
