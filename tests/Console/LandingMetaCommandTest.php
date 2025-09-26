<?php

declare(strict_types=1);

namespace Tests\Console;

use Bamboo\Console\Command\LandingMeta;
use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Provider\AppProvider;
use Bamboo\Provider\MetricsProvider;
use PHPUnit\Framework\TestCase;

final class LandingMetaCommandTest extends TestCase
{
    public function testOutputsDefaultMetadataAsJson(): void
    {
        $app = $this->createApplication();
        $command = new LandingMeta($app);

        ob_start();
        $exitCode = $command->handle([]);
        $output = ob_get_clean();

        if ($output === false) {
            $output = '';
        }

        $this->assertSame(0, $exitCode);

        $meta = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($meta);
        $this->assertSame(
            sprintf('%s | Modern PHP Microframework', $app->config('app.name', 'Bamboo')),
            $meta['title'] ?? null
        );
        $this->assertSame('Bamboo makes high-performance PHP approachable.', $meta['description'] ?? null);
        $this->assertArrayHasKey('generated_at', $meta);
    }

    public function testAllowsTypeAndOverrideArguments(): void
    {
        $app = $this->createApplication();
        $command = new LandingMeta($app);

        ob_start();
        $exitCode = $command->handle(['article', 'author=Jane Doe']);
        $output = ob_get_clean();

        if ($output === false) {
            $output = '';
        }

        $this->assertSame(0, $exitCode);

        $meta = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($meta);
        $this->assertSame('Jane Doe', $meta['author'] ?? null);
        $this->assertSame('Green Armor Engineering', $meta['publication'] ?? null);
        $this->assertStringContainsString('Async PHP in Production', $meta['title'] ?? '');
    }

    public function testAboutTypeOutputsDefaultsAndOverrides(): void
    {
        $app = $this->createApplication();
        $command = new LandingMeta($app);

        ob_start();
        $exitCode = $command->handle(['about', 'mission=Grow async PHP adoption.']);
        $output = ob_get_clean();

        if ($output === false) {
            $output = '';
        }

        $this->assertSame(0, $exitCode);

        $meta = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($meta);
        $this->assertSame('Grow async PHP adoption.', $meta['mission'] ?? null);
        $this->assertSame('Jordan Queue', $meta['team_lead'] ?? null);
        $this->assertStringContainsString('About', $meta['title'] ?? '');
    }

    private function createApplication(): Application
    {
        $config = new Config(dirname(__DIR__, 2) . '/etc');
        $app = new Application($config);
        $app->register(new AppProvider());
        $app->register(new MetricsProvider());
        $app->register(new \Bamboo\Provider\ResilienceProvider());

        return $app;
    }
}
