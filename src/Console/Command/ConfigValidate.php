<?php

declare(strict_types=1);

namespace Bamboo\Console\Command;

use Bamboo\Core\Config;
use Bamboo\Core\ConfigValidator;
use Bamboo\Core\ConfigurationException;

final class ConfigValidate extends Command
{
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

            fwrite(STDERR, $message);
            echo $message;

            return 1;
        }

        echo "Configuration looks good.\n";

        return 0;
    }
}
