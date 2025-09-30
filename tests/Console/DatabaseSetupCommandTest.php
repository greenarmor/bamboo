<?php

declare(strict_types=1);

namespace Tests\Console;

use Bamboo\Console\Command\DatabaseSetup;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Tests\Support\RouterTestApplication;

final class DatabaseSetupCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/bamboo-db-cli-' . bin2hex(random_bytes(6));
        @mkdir($this->root, 0775, true);
        @mkdir($this->root . '/etc', 0775, true);
        @mkdir($this->root . '/var/database', 0775, true);
        file_put_contents($this->root . '/.env', "APP_ENV=testing\n");
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function testWizardConfiguresDatabaseAndSeedsData(): void
    {
        $app = new RouterTestApplication();
        $input = $this->inputStream([
            '',
            'var/database/blog.sqlite',
            'y',
            'posts',
            'id',
            'increments',
            'title',
            'string',
            'n',
            'n',
            'published',
            'boolean',
            'n',
            'n',
            '',
            'y',
            'First Post',
            'yes',
            'n',
            'n',
        ]);
        $output = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        $command = new DatabaseSetup($app, $this->root, $input, $output, $stderr);

        $exitCode = $command->handle([]);
        $this->assertSame(0, $exitCode);

        $env = file_get_contents($this->root . '/.env');
        $this->assertIsString($env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertStringContainsString('DB_DATABASE=var/database/blog.sqlite', $env);

        $configPath = $this->root . '/etc/database.php';
        $this->assertFileExists($configPath);
        $config = require $configPath;
        $this->assertIsArray($config);
        $this->assertSame('sqlite', $config['default']);
        $this->assertArrayHasKey('sqlite', $config['connections']);

        $connection = $config['connections']['sqlite'];
        $capsule = new Capsule();
        $capsule->addConnection($connection);
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $this->assertTrue($schema->hasTable('posts'));

        $rows = $capsule->getConnection()->table('posts')->get()->all();
        $rows = array_map(static fn ($row) => (array) $row, $rows);
        $this->assertCount(1, $rows);
        $this->assertSame('First Post', $rows[0]['title']);
        $this->assertTrue((bool) $rows[0]['published']);

        $configBeforeSecondRun = $config;
        $inputSecond = $this->inputStream([
            '',
            '',
            'y',
            'posts',
            'n',
        ]);
        $outputSecond = fopen('php://memory', 'w+');
        $commandSecond = new DatabaseSetup($app, $this->root, $inputSecond, $outputSecond, $stderr);

        $secondExitCode = $commandSecond->handle([]);
        $this->assertSame(0, $secondExitCode);
        $configAfterSecondRun = require $configPath;
        $this->assertSame($configBeforeSecondRun, $configAfterSecondRun);

        $rowCount = (int) $capsule->getConnection()->table('posts')->count();
        $this->assertSame(1, $rowCount);

        rewind($outputSecond);
        $secondOutput = stream_get_contents($outputSecond) ?: '';
        $this->assertStringContainsString('Table "posts" already exists; skipping.', $secondOutput);
    }

    /**
     * @param list<string> $lines
     * @return resource
     */
    private function inputStream(array $lines)
    {
        $stream = fopen('php://memory', 'w+');
        foreach ($lines as $line) {
            fwrite($stream, $line . "\n");
        }
        rewind($stream);

        return $stream;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->deleteDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
