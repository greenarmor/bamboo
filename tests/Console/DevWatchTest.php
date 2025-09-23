<?php
namespace Tests\Console;

use Bamboo\Console\Command\DevWatch;
use Bamboo\Console\Command\DevWatch\DevWatchSupervisor;
use Bamboo\Console\Command\DevWatch\FinderFileWatcher;
use Bamboo\Console\Command\DevWatch\FileWatcher;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\RouterTestApplication;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class DevWatchTest extends TestCase
{
    public function testWatcherRestartsServerOnFileChange(): void
    {
        $baseDir = sys_get_temp_dir() . '/bamboo-devwatch-' . bin2hex(random_bytes(6));
        mkdir($baseDir, 0777, true);
        $file = $baseDir . '/sample.php';
        file_put_contents($file, "<?php echo 'initial';\n");

        $watcher = new FinderFileWatcher([$baseDir]);
        $handler = new TestHandler();
        $logger = new Logger('devwatch-test');
        $logger->pushHandler($handler);

        $fixture = realpath(__DIR__ . '/../Fixtures/fake-server.php');
        self::assertNotFalse($fixture, 'Fixture script not found');

        $factory = static function () use ($fixture): Process {
            return Process::fromShellCommandline(
                sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($fixture)),
                getcwd(),
                ['XDEBUG_MODE' => 'off']
            );
        };

        $supervisor = new DevWatchSupervisor(
            $watcher,
            $factory,
            $logger,
            0.0,
            static function (int $microseconds): void {
                // Skip sleeping during tests to keep them fast.
            },
            0
        );

        $iterations = 0;

        try {
            $supervisor->run(function (DevWatchSupervisor $loop) use (&$iterations, $file): bool {
                $iterations++;
                if ($iterations === 2) {
                    file_put_contents($file, "<?php echo 'modified';\n");
                    clearstatcache(true, $file);
                }
                if ($loop->getRestartCount() > 0) {
                    return false;
                }
                return $iterations < 12;
            });
        } finally {
            $this->cleanupDirectory($baseDir);
        }

        self::assertGreaterThan(0, $supervisor->getRestartCount(), 'Expected the supervisor to restart the process.');
        self::assertTrue($handler->hasInfoThatContains('Restarting HTTP server'));
    }

    public function testCommandOverridesAfterDoubleDash(): void
    {
        $reflector = new \ReflectionClass(DevWatch::class);
        $command = $reflector->newInstanceWithoutConstructor();

        $method = $reflector->getMethod('parseOptions');
        $method->setAccessible(true);

        $defaults = $method->invoke($command, []);
        self::assertIsArray($defaults);

        $options = $method->invoke($command, ['--', 'php', 'bin/bamboo', 'http.serve', '--debug']);

        self::assertSame('php bin/bamboo http.serve --debug', $options['command']);
        self::assertNotSame($defaults['command'], $options['command']);
    }

    public function testHandleCoordinatesWatcherAndSupervisor(): void
    {
        $watchPath = sys_get_temp_dir() . '/bamboo-devwatch-cmd-' . bin2hex(random_bytes(4));
        mkdir($watchPath, 0777, true);

        $logger = new ArrayLogger();
        $app = new RouterTestApplication();
        $app->singleton('log', fn () => $logger);

        $watcher = new StubWatcher();
        $factoryInvoked = false;
        $factory = function () use (&$factoryInvoked) {
            $factoryInvoked = true;
            throw new \RuntimeException('Factory should not be invoked during the stubbed run');
        };

        $command = new TestableDevWatch($app, $logger, $watcher, $factory);

        $previousMode = getenv('XDEBUG_MODE');
        $previousConfig = getenv('XDEBUG_CONFIG');
        putenv('XDEBUG_MODE=coverage');
        $_ENV['XDEBUG_MODE'] = 'coverage';
        putenv('XDEBUG_CONFIG=client_host=127.0.0.1');
        $_ENV['XDEBUG_CONFIG'] = 'client_host=127.0.0.1';

        try {
            $exitCode = $command->handle([
                '--watch=' . $watchPath,
                '--debounce=250',
                '--command=php -v',
            ]);
        } finally {
            $this->cleanupDirectory($watchPath);
            if ($previousMode === false) {
                putenv('XDEBUG_MODE');
                unset($_ENV['XDEBUG_MODE']);
            } else {
                putenv('XDEBUG_MODE=' . $previousMode);
                $_ENV['XDEBUG_MODE'] = $previousMode;
            }
            if ($previousConfig === false) {
                putenv('XDEBUG_CONFIG');
                unset($_ENV['XDEBUG_CONFIG']);
            } else {
                putenv('XDEBUG_CONFIG=' . $previousConfig);
                $_ENV['XDEBUG_CONFIG'] = $previousConfig;
            }
        }

        self::assertSame(0, $exitCode);
        self::assertNotNull($command->createdSupervisor);
        self::assertTrue($command->createdSupervisor->runCalled);
        self::assertFalse($factoryInvoked);

        self::assertSame([[$watchPath]], $command->calls['resolveWatchPaths'] ?? []);
        self::assertSame([[$watchPath]], $command->calls['createWatcher'] ?? []);
        self::assertSame(['php -v'], $command->calls['createProcessFactory'] ?? []);
        self::assertSame([0.25], $command->calls['createSupervisor'] ?? []);
        self::assertArrayHasKey('registerSignalHandlers', $command->calls);

        self::assertNotEmpty($command->processEnvironment);
        $env = $command->processEnvironment[0];
        self::assertSame('off', $env['XDEBUG_MODE'] ?? null);
        self::assertArrayHasKey('XDEBUG_CONFIG', $env);
        self::assertFalse($env['XDEBUG_CONFIG']);

        self::assertNotEmpty($logger->records);
        $record = $logger->records[0];
        self::assertSame('info', $record['level']);
        self::assertSame('Starting development watcher', $record['message']);
        self::assertSame([$watchPath], $record['context']['paths']);
        self::assertSame('php -v', $record['context']['command']);
        self::assertSame('stub', $record['context']['driver']);
        self::assertSame(250, $record['context']['debounce_ms']);
    }

    private function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }
            @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}

class ArrayLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}

class StubWatcher implements FileWatcher
{
    public array $paths = [];

    public function poll(): bool
    {
        return false;
    }

    public function label(): string
    {
        return 'stub';
    }
}

class SupervisorDouble extends DevWatchSupervisor
{
    public bool $runCalled = false;

    public function __construct(FileWatcher $watcher, callable $factory, LoggerInterface $logger)
    {
        parent::__construct($watcher, $factory, $logger, 0.0, static function (): void {
        }, 0);
    }

    public function run(?callable $afterTick = null): void
    {
        $this->runCalled = true;
    }
}

class TestableDevWatch extends DevWatch
{
    public array $calls = [];
    public ?SupervisorDouble $createdSupervisor = null;
    public array $processEnvironment = [];

    public function __construct(
        RouterTestApplication $app,
        private ArrayLogger $logger,
        private StubWatcher $watcher,
        private $factory
    ) {
        parent::__construct($app);
    }

    protected function resolveLogger(): LoggerInterface
    {
        $this->calls['resolveLogger'] = true;
        return $this->logger;
    }

    protected function resolveWatchPaths(array $paths, LoggerInterface $logger): array
    {
        $this->calls['resolveWatchPaths'][] = $paths;
        return $paths;
    }

    protected function createWatcher(array $paths, LoggerInterface $logger): FileWatcher
    {
        $this->calls['createWatcher'][] = $paths;
        $this->watcher->paths = $paths;
        return $this->watcher;
    }

    protected function createProcessFactory(string $command): callable
    {
        $this->calls['createProcessFactory'][] = $command;
        $this->processEnvironment[] = $this->createProcessEnvironment();
        return $this->factory;
    }

    protected function createProcessEnvironment(): array
    {
        $env = parent::createProcessEnvironment();
        $this->calls['createProcessEnvironment'][] = $env;

        return $env;
    }

    protected function registerSignalHandlers(DevWatchSupervisor $supervisor, LoggerInterface $logger): void
    {
        $this->calls['registerSignalHandlers'] = true;
    }

    protected function createSupervisor(
        FileWatcher $watcher,
        callable $factory,
        LoggerInterface $logger,
        float $debounceSeconds
    ): DevWatchSupervisor {
        $this->calls['createSupervisor'][] = $debounceSeconds;
        $this->createdSupervisor = new SupervisorDouble($watcher, $factory, $logger);
        return $this->createdSupervisor;
    }
}
