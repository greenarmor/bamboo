<?php
namespace Tests\Console;

use Bamboo\Console\Command\DevWatch;
use Bamboo\Console\Command\DevWatch\DevWatchSupervisor;
use Bamboo\Console\Command\DevWatch\FinderFileWatcher;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

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
                getcwd()
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
