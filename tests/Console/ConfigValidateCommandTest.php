<?php

declare(strict_types=1);

namespace Tests\Console;

use Bamboo\Console\Command\ConfigValidate;
use Bamboo\Core\Application;
use Bamboo\Core\Config;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArrayConfig;

final class ConfigValidateCommandTest extends TestCase
{
    public function testValidConfigurationPasses(): void
    {
        $app = new Application(new Config(dirname(__DIR__, 2) . '/etc'));

        $command = new ConfigValidate($app);

        ob_start();
        $exitCode = $command->handle([]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Configuration looks good.', $output);
    }

    public function testInvalidConfigurationReportsErrors(): void
    {
        $config = new Config(dirname(__DIR__, 2) . '/etc');
        $overrides = $config->all();
        $overrides['server']['host'] = '';

        $app = new Application(new ArrayConfig($overrides));

        $command = new ConfigValidate($app);

        ob_start();
        $exitCode = $command->handle([]);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('server.host must be a non-empty string.', $output);
    }
}
