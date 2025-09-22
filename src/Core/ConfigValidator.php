<?php
namespace Bamboo\Core;

class ConfigValidator
{
    /**
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException
     */
    public function validate(array $config): void
    {
        $errors = [];

        if (!isset($config['server']) || !is_array($config['server'])) {
            $errors[] = 'server configuration must be an array.';
        } else {
            $server = $config['server'];

            if (!array_key_exists('host', $server) || !is_string($server['host']) || trim($server['host']) === '') {
                $errors[] = 'server.host must be a non-empty string.';
            }

            if (!array_key_exists('port', $server) || !is_int($server['port']) || $server['port'] <= 0 || $server['port'] > 65535) {
                $errors[] = 'server.port must be an integer between 1 and 65535.';
            }
        }

        if (!isset($config['cache']) || !is_array($config['cache'])) {
            $errors[] = 'cache configuration must be an array.';
        } else {
            $cache = $config['cache'];
            if (!array_key_exists('routes', $cache) || !is_string($cache['routes']) || trim($cache['routes']) === '') {
                $errors[] = 'cache.routes must be a non-empty string path.';
            }
        }

        if (!isset($config['redis']) || !is_array($config['redis'])) {
            $errors[] = 'redis configuration must be an array.';
        } else {
            $redis = $config['redis'];
            if (!array_key_exists('url', $redis) || !is_string($redis['url']) || trim($redis['url']) === '') {
                $errors[] = 'redis.url must be a non-empty string.';
            }
        }

        if (!isset($config['http']) || !is_array($config['http'])) {
            $errors[] = 'http configuration must be an array.';
        } else {
            $http = $config['http'];
            if (!array_key_exists('default', $http) || !is_array($http['default'])) {
                $errors[] = 'http.default must be an array.';
            } else {
                $default = $http['default'];
                if (!array_key_exists('timeout', $default) || !$this->isNumeric($default['timeout']) || (float) $default['timeout'] <= 0) {
                    $errors[] = 'http.default.timeout must be a positive number.';
                }
            }
        }

        if (!isset($config['app']) || !is_array($config['app'])) {
            $errors[] = 'app configuration must be an array.';
        } else {
            $app = $config['app'];

            if (!array_key_exists('debug', $app) || !is_bool($app['debug'])) {
                $errors[] = 'app.debug must be a boolean value.';
            }

            if (($app['debug'] ?? true) === false) {
                if (!array_key_exists('key', $app) || !is_string($app['key']) || trim($app['key']) === '') {
                    $errors[] = 'app.key must be set when app.debug is disabled.';
                }
            }
        }

        if ($errors !== []) {
            throw new ConfigurationException($errors);
        }
    }

    private function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }
}
