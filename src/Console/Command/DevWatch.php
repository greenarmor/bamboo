<?php
namespace Bamboo\Console\Command;

use Bamboo\Console\Command\DevWatch\DevWatchSupervisor;
use Bamboo\Console\Command\DevWatch\FileWatcher;
use Bamboo\Console\Command\DevWatch\FinderFileWatcher;
use Bamboo\Console\Command\DevWatch\InotifyFileWatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class DevWatch extends Command
{
    private const DEFAULT_WATCH_PATHS = ['src', 'etc', 'routes', 'bootstrap', 'public'];
    private const DEFAULT_DEBOUNCE_MS = 500;
    private const DEFAULT_POLL_INTERVAL_US = 100_000;

    public function name(): string
    {
        return 'dev.watch';
    }

    public function description(): string
    {
        return 'Restart HTTP on file changes using an internal watcher loop';
    }

    public function handle(array $args): int
    {
        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            $this->printHelp();
            return 0;
        }

        try {
            $options = $this->parseOptions($args);
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
        $logger = $this->resolveLogger();

        try {
            $paths = $this->resolveWatchPaths($options['watch'], $logger);
        } catch (\RuntimeException $e) {
            $logger->error($e->getMessage());
            return 1;
        }

        try {
            $watcher = $this->createWatcher($paths, $logger);
        } catch (\Throwable $e) {
            $logger->error('Unable to start file watcher', ['exception' => $e]);
            return 1;
        }

        $logger->info('Starting development watcher', [
            'paths' => $paths,
            'debounce_ms' => $options['debounce'],
            'command' => $options['command'],
            'driver' => $watcher->label(),
        ]);

        $factory = $this->createProcessFactory($options['command']);
        $supervisor = new DevWatchSupervisor(
            $watcher,
            $factory,
            $logger,
            $options['debounce'] / 1000,
            null,
            self::DEFAULT_POLL_INTERVAL_US
        );

        $this->registerSignalHandlers($supervisor, $logger);

        $supervisor->run();

        return 0;
    }

    private function parseOptions(array $args): array
    {
        $options = [
            'debounce' => self::DEFAULT_DEBOUNCE_MS,
            'watch' => self::DEFAULT_WATCH_PATHS,
            'command' => $this->defaultCommand(),
        ];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if ($arg === '--') {
                $remaining = array_slice($args, $i + 1);
                if ($remaining) {
                    $options['command'] = implode(' ', $remaining);
                }
                break;
            }

            if (str_starts_with($arg, '--debounce=')) {
                $options['debounce'] = $this->normalizeDebounce(substr($arg, 11));
                continue;
            }

            if ($arg === '--debounce') {
                $value = $args[$i + 1] ?? null;
                if ($value !== null) {
                    $options['debounce'] = $this->normalizeDebounce($value);
                    $i++;
                }
                continue;
            }

            if (str_starts_with($arg, '--watch=')) {
                $paths = $this->parseWatchList(substr($arg, 8));
                if ($paths) {
                    $options['watch'] = $paths;
                }
                continue;
            }

            if ($arg === '--watch') {
                $value = $args[$i + 1] ?? null;
                if ($value !== null) {
                    $paths = $this->parseWatchList($value);
                    if ($paths) {
                        $options['watch'] = $paths;
                    }
                    $i++;
                }
                continue;
            }

            if (str_starts_with($arg, '--command=')) {
                $cmd = trim(substr($arg, 10));
                if ($cmd !== '') {
                    $options['command'] = $cmd;
                }
                continue;
            }

            if ($arg === '--command') {
                $value = $args[$i + 1] ?? null;
                if ($value !== null) {
                    $cmd = trim($value);
                    if ($cmd !== '') {
                        $options['command'] = $cmd;
                    }
                    $i++;
                }
                continue;
            }

            // Treat remaining arguments as the command to run.
            $remaining = array_slice($args, $i);
            if ($remaining) {
                $options['command'] = implode(' ', $remaining);
            }
            break;
        }

        return $options;
    }

    private function printHelp(): void
    {
        $help = <<<HELP
Usage: php bin/bamboo dev.watch [options]

Options:
  --debounce=<ms>        Debounce interval in milliseconds (default: {self::DEFAULT_DEBOUNCE_MS}).
  --watch=<paths>        Comma-separated list of directories or files to watch.
  --command=<cmd>        Custom command to run instead of http.serve.
  --                     Treat all subsequent arguments as the command to run.
  --help                 Display this help text.

Examples:
  php bin/bamboo dev.watch
  php bin/bamboo dev.watch --debounce=250
  php bin/bamboo dev.watch --watch=src,etc,routes --command="php artisan serve"
  php bin/bamboo dev.watch -- php bin/bamboo http.serve --debug
HELP;
        echo str_replace('{self::DEFAULT_DEBOUNCE_MS}', (string) self::DEFAULT_DEBOUNCE_MS, $help), "\n";
    }

    private function normalizeDebounce(?string $value): int
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_DEBOUNCE_MS;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Debounce value must be numeric milliseconds.');
        }

        $numeric = max(0, (int) round((float) $value));
        return $numeric;
    }

    private function parseWatchList(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn(string $part) => $part !== '');
        return array_values(array_unique($parts));
    }

    private function resolveLogger(): LoggerInterface
    {
        $logger = $this->app->get('log');
        if (!$logger instanceof LoggerInterface) {
            throw new \RuntimeException('Logger service must implement Psr\\Log\\LoggerInterface.');
        }

        return $logger;
    }

    private function resolveWatchPaths(array $paths, LoggerInterface $logger): array
    {
        $base = $this->basePath();
        $resolved = [];

        foreach ($paths as $path) {
            $path = trim($path);
            if ($path === '') {
                continue;
            }

            $absolute = $this->normalizePath($path, $base);
            if (!file_exists($absolute)) {
                $logger->warning('Watch path does not exist', ['path' => $absolute]);
                continue;
            }

            $real = realpath($absolute) ?: $absolute;
            $resolved[] = $real;
        }

        $resolved = array_values(array_unique($resolved));

        if (!$resolved) {
            throw new \RuntimeException('No existing watch paths were resolved.');
        }

        return $resolved;
    }

    private function normalizePath(string $path, string $base): string
    {
        if ($path === '') {
            return $base;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === DIRECTORY_SEPARATOR) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    private function basePath(): string
    {
        return getcwd() ?: dirname(__DIR__, 3);
    }

    private function defaultCommand(): string
    {
        $binary = escapeshellarg(PHP_BINARY);
        $cli = escapeshellarg($this->normalizePath('bin/bamboo', $this->basePath()));
        return sprintf('%s %s http.serve', $binary, $cli);
    }

    private function createProcessFactory(string $command): callable
    {
        $cwd = $this->basePath();

        return function () use ($command, $cwd): Process {
            $process = Process::fromShellCommandline($command, $cwd);
            $process->setTimeout(null);
            $process->setIdleTimeout(null);
            return $process;
        };
    }

    private function createWatcher(array $paths, LoggerInterface $logger): FileWatcher
    {
        if (extension_loaded('inotify')) {
            try {
                return new InotifyFileWatcher($paths);
            } catch (\Throwable $e) {
                $logger->warning('Falling back to Finder-based watcher', ['exception' => $e]);
            }
        }

        return new FinderFileWatcher($paths);
    }

    private function registerSignalHandlers(DevWatchSupervisor $supervisor, LoggerInterface $logger): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        $handler = function (int $signal) use ($supervisor, $logger): void {
            $logger->info('Received shutdown signal', ['signal' => $signal]);
            $supervisor->requestStop();
        };

        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }
}
