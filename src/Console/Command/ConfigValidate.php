<?php

declare(strict_types=1);

namespace Bamboo\Console\Command;

use Bamboo\Core\Application;
use Bamboo\Core\Config;
use Bamboo\Core\ConfigValidator;
use Bamboo\Core\ConfigurationException;
use InvalidArgumentException;

final class ConfigValidate extends Command
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(
        Application $app,
        $stdout = null,
        $stderr = null
    ) {
        parent::__construct($app);

        if ($stdout !== null && !is_resource($stdout)) {
            throw new InvalidArgumentException('STDOUT stream must be a resource.');
        }

        if ($stderr !== null && !is_resource($stderr)) {
            throw new InvalidArgumentException('STDERR stream must be a resource.');
        }

        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    public function name(): string
    {
        return 'config.validate';
    }

    public function description(): string
    {
        return 'Validate etc/ configuration against the v1.0 contract';
    }

    /**
     * @param list<string> $args
     */
    public function handle(array $args): int
    {
        $config = $this->app->get(Config::class);
        $validator = $this->app->has(ConfigValidator::class)
            ? $this->app->get(ConfigValidator::class)
            : new ConfigValidator();

        try {
            $validator->validate($config->all());
        } catch (ConfigurationException $exception) {
            $lines = ["Configuration validation failed:"];
            foreach ($exception->errors() as $error) {
                $lines[] = sprintf("  - %s", $error);
            }
            $message = implode("\n", $lines) . "\n";

            $this->writeToStream($this->stderr, $message);
            $this->writeToStream($this->stdout, $message);

            return 1;
        }

        $this->writeToStream($this->stdout, "Configuration looks good.\n");

        return 0;
    }

    /**
     * @param resource $stream
     */
    private function writeToStream($stream, string $message): void
    {
        fwrite($stream, $message);
    }
}
