<?php

declare(strict_types=1);

namespace Tests\Console;

use Bamboo\Console\Command\ConfigValidate;
use Bamboo\Core\Application;
use Bamboo\Core\Config;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArrayConfig;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;

final class ConfigValidateCommandTest extends TestCase
{
    public function testValidConfigurationPasses(): void
    {
        $app = new Application(new Config(dirname(__DIR__, 2) . '/etc'));

        $stdout = fopen('php://temp', 'w+');
        $stderr = fopen('php://temp', 'w+');

        $this->assertIsResource($stdout);
        $this->assertIsResource($stderr);

        try {
            $command = new ConfigValidate($app, $stdout, $stderr);

            $exitCode = $command->handle([]);

            rewind($stdout);
            rewind($stderr);

            $output = stream_get_contents($stdout) ?: '';
            $errorOutput = stream_get_contents($stderr) ?: '';
        } finally {
            fclose($stdout);
            fclose($stderr);
        }

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Configuration looks good.', $output);
        $this->assertSame('', $errorOutput);
    }

    public function testInvalidConfigurationReportsErrors(): void
    {
        $config = new Config(dirname(__DIR__, 2) . '/etc');
        $overrides = $config->all();
        $overrides['server']['host'] = '';

        $app = new Application(new ArrayConfig($overrides));

        $stdout = fopen('php://temp', 'w+');
        $stderr = fopen('php://temp', 'w+');

        $this->assertIsResource($stdout);
        $this->assertIsResource($stderr);

        try {
            $command = new ConfigValidate($app, $stdout, $stderr);

            $exitCode = $command->handle([]);

            rewind($stdout);
            rewind($stderr);

            $output = stream_get_contents($stdout) ?: '';
            $errorOutput = stream_get_contents($stderr) ?: '';
        } finally {
            fclose($stdout);
            fclose($stderr);
        }

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('server.host must be a non-empty string.', $output);
        $this->assertStringContainsString('server.host must be a non-empty string.', $errorOutput);
    }
}
