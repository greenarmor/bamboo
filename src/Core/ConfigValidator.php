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

        if (isset($config['metrics']) && is_array($config['metrics'])) {
            $metrics = $config['metrics'];

            if (!array_key_exists('namespace', $metrics) || !is_string($metrics['namespace']) || trim($metrics['namespace']) === '') {
                $errors[] = 'metrics.namespace must be a non-empty string.';
            }

            if (!array_key_exists('storage', $metrics) || !is_array($metrics['storage'])) {
                $errors[] = 'metrics.storage must be an array.';
            } else {
                $storage = $metrics['storage'];
                if (!array_key_exists('driver', $storage) || !is_string($storage['driver']) || trim($storage['driver']) === '') {
                    $errors[] = 'metrics.storage.driver must be a non-empty string.';
                }
            }

            if (!array_key_exists('histogram_buckets', $metrics) || !is_array($metrics['histogram_buckets'])) {
                $errors[] = 'metrics.histogram_buckets must be an array.';
            } else {
                foreach ($metrics['histogram_buckets'] as $metricName => $buckets) {
                    if (!is_array($buckets)) {
                        $errors[] = sprintf('metrics.histogram_buckets.%s must be an array of numeric bucket upper bounds.', (string) $metricName);
                        continue;
                    }

                    foreach ($buckets as $bucket) {
                        if (!$this->isNumeric($bucket) || (float) $bucket < 0.0) {
                            $errors[] = sprintf('metrics.histogram_buckets.%s contains an invalid bucket definition.', (string) $metricName);
                            break;
                        }
                    }
                }
            }
        }

        if (isset($config['resilience'])) {
            if (!is_array($config['resilience'])) {
                $errors[] = 'resilience configuration must be an array.';
            } else {
                $errors = [...$errors, ...$this->validateResilience($config['resilience'])];
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

    /**
     * @param array<string, mixed> $resilience
     * @return array<int, string>
     */
    private function validateResilience(array $resilience): array
    {
        $errors = [];

        $timeouts = $resilience['timeouts'] ?? [];
        if (!is_array($timeouts)) {
            $errors[] = 'resilience.timeouts must be an array.';
        } else {
            $defaultTimeout = $timeouts['default'] ?? null;
            if (!$this->isNumeric($defaultTimeout) || (float) $defaultTimeout <= 0.0) {
                $errors[] = 'resilience.timeouts.default must be a positive number.';
            }

            if (isset($timeouts['per_route'])) {
                if (!is_array($timeouts['per_route'])) {
                    $errors[] = 'resilience.timeouts.per_route must be an array.';
                } else {
                    foreach ($timeouts['per_route'] as $route => $value) {
                        $timeout = $this->extractTimeoutValue($value);
                        if ($timeout === null || $timeout <= 0.0) {
                            $errors[] = sprintf('resilience.timeouts.per_route.%s must be a positive number.', (string) $route);
                        }
                    }
                }
            }
        }

        $breaker = $resilience['circuit_breaker'] ?? [];
        if (!is_array($breaker)) {
            $errors[] = 'resilience.circuit_breaker must be an array.';
        } else {
            $failureThreshold = $breaker['failure_threshold'] ?? null;
            if (!is_int($failureThreshold) || $failureThreshold <= 0) {
                $errors[] = 'resilience.circuit_breaker.failure_threshold must be a positive integer.';
            }

            $successThreshold = $breaker['success_threshold'] ?? null;
            if (!is_int($successThreshold) || $successThreshold <= 0) {
                $errors[] = 'resilience.circuit_breaker.success_threshold must be a positive integer.';
            }

            $cooldown = $breaker['cooldown_seconds'] ?? null;
            if (!$this->isNumeric($cooldown) || (float) $cooldown < 0.0) {
                $errors[] = 'resilience.circuit_breaker.cooldown_seconds must be zero or greater.';
            }

            if (isset($breaker['enabled']) && !is_bool($breaker['enabled'])) {
                $errors[] = 'resilience.circuit_breaker.enabled must be a boolean value.';
            }

            if (isset($breaker['per_route'])) {
                if (!is_array($breaker['per_route'])) {
                    $errors[] = 'resilience.circuit_breaker.per_route must be an array.';
                } else {
                    foreach ($breaker['per_route'] as $route => $settings) {
                        if (is_array($settings)) {
                            if (isset($settings['enabled']) && !is_bool($settings['enabled'])) {
                                $errors[] = sprintf('resilience.circuit_breaker.per_route.%s.enabled must be a boolean value.', (string) $route);
                            }
                            if (isset($settings['failure_threshold']) && (!is_int($settings['failure_threshold']) || $settings['failure_threshold'] <= 0)) {
                                $errors[] = sprintf('resilience.circuit_breaker.per_route.%s.failure_threshold must be a positive integer.', (string) $route);
                            }
                            if (isset($settings['success_threshold']) && (!is_int($settings['success_threshold']) || $settings['success_threshold'] <= 0)) {
                                $errors[] = sprintf('resilience.circuit_breaker.per_route.%s.success_threshold must be a positive integer.', (string) $route);
                            }
                            if (isset($settings['cooldown_seconds']) && (!$this->isNumeric($settings['cooldown_seconds']) || (float) $settings['cooldown_seconds'] < 0.0)) {
                                $errors[] = sprintf('resilience.circuit_breaker.per_route.%s.cooldown_seconds must be zero or greater.', (string) $route);
                            }
                        } elseif (!is_int($settings) || $settings <= 0) {
                            $errors[] = sprintf('resilience.circuit_breaker.per_route.%s must be a positive integer when specified as a scalar.', (string) $route);
                        }
                    }
                }
            }
        }

        if (isset($resilience['health'])) {
            if (!is_array($resilience['health'])) {
                $errors[] = 'resilience.health must be an array.';
            } elseif (isset($resilience['health']['dependencies']) && !is_array($resilience['health']['dependencies'])) {
                $errors[] = 'resilience.health.dependencies must be an array.';
            }
        }

        return $errors;
    }

    private function extractTimeoutValue(mixed $value): ?float
    {
        if ($this->isNumeric($value)) {
            return (float) $value;
        }

        if (is_array($value) && isset($value['timeout'])) {
            return $this->isNumeric($value['timeout']) ? (float) $value['timeout'] : null;
        }

        return null;
    }
}
