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

        if (isset($config['auth'])) {
            if (!is_array($config['auth'])) {
                $errors[] = 'auth configuration must be an array.';
            } else {
                $appConfig = is_array($config['app'] ?? null) ? $config['app'] : [];
                $errors = [...$errors, ...$this->validateAuth($config['auth'], $appConfig)];
            }
        }

        if (isset($config['view'])) {
            if (!is_array($config['view'])) {
                $errors[] = 'view configuration must be an array.';
            } else {
                $viewErrors = $this->validateView($config['view']);
                $errors = [...$errors, ...$viewErrors];
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
     * @param array<string, mixed> $view
     * @return array<int, string>
     */
    private function validateView(array $view): array
    {
        $errors = [];

        $default = $view['default'] ?? null;
        if (!is_string($default) || trim($default) === '') {
            $errors[] = 'view.default must be a non-empty string.';
        }

        $engines = $view['engines'] ?? null;
        if (!is_array($engines) || $engines === []) {
            $errors[] = 'view.engines must be an associative array of engine definitions.';
            $engines = [];
        } else {
            foreach ($engines as $name => $engineConfig) {
                if (!is_string($name) || trim($name) === '') {
                    $errors[] = 'view.engines keys must be non-empty strings.';
                    continue;
                }

                if (!is_array($engineConfig)) {
                    $errors[] = sprintf('view.engines.%s must be an array.', $name);
                    continue;
                }

                if (!array_key_exists('driver', $engineConfig) || !is_string($engineConfig['driver']) || trim($engineConfig['driver']) === '') {
                    $errors[] = sprintf('view.engines.%s.driver must be a non-empty string.', $name);
                }
            }
        }

        if (is_string($default) && trim($default) !== '' && $engines !== [] && !array_key_exists($default, $engines)) {
            $errors[] = sprintf('view.default references unknown engine "%s".', $default);
        }

        if (isset($view['pages'])) {
            if (!is_array($view['pages'])) {
                $errors[] = 'view.pages must be an array.';
            } else {
                foreach ($view['pages'] as $page => $engineName) {
                    if (!is_string($page) || trim($page) === '') {
                        $errors[] = 'view.pages keys must be non-empty strings.';
                        continue;
                    }

                    if ($engineName === null) {
                        continue;
                    }

                    if (!is_string($engineName) || trim($engineName) === '') {
                        $errors[] = sprintf('view.pages.%s must be null or a non-empty string.', $page);
                        continue;
                    }

                    if ($engines !== [] && !array_key_exists($engineName, $engines)) {
                        $errors[] = sprintf('view.pages.%s references unknown engine "%s".', $page, $engineName);
                    }
                }
            }
        }

        return $errors;
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

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $app
     * @return array<int, string>
     */
    private function validateAuth(array $auth, array $app): array
    {
        $errors = [];

        $jwt = $auth['jwt'] ?? null;
        if (!is_array($jwt)) {
            $errors[] = 'auth.jwt must be an array.';

            return $errors;
        }

        $secret = $jwt['secret'] ?? '';
        if (!is_string($secret)) {
            $errors[] = 'auth.jwt.secret must be a string.';
        } else {
            $secret = trim($secret);
            $debug = $app['debug'] ?? null;
            if ($secret === '' && $debug === false) {
                $errors[] = 'auth.jwt.secret must be set when app.debug is disabled.';
            }
        }

        $ttl = $jwt['ttl'] ?? 3600;
        if (!is_numeric($ttl) || (int) $ttl <= 0) {
            $errors[] = 'auth.jwt.ttl must be a positive integer.';
        }

        if (!isset($jwt['issuer']) || !is_string($jwt['issuer']) || trim($jwt['issuer']) === '') {
            $errors[] = 'auth.jwt.issuer must be a non-empty string.';
        }

        if (!isset($jwt['audience']) || !is_string($jwt['audience']) || trim($jwt['audience']) === '') {
            $errors[] = 'auth.jwt.audience must be a non-empty string.';
        }

        if (!isset($jwt['storage']) || !is_array($jwt['storage'])) {
            $errors[] = 'auth.jwt.storage must be an array.';
        } else {
            $driver = $jwt['storage']['driver'] ?? null;
            if ($driver !== null && !is_string($driver)) {
                $errors[] = 'auth.jwt.storage.driver must be a string when provided.';
            }

            $path = $jwt['storage']['path'] ?? null;
            if (!is_string($path) || trim($path) === '') {
                $errors[] = 'auth.jwt.storage.path must be a non-empty string path.';
            }
        }

        if (isset($jwt['registration'])) {
            if (!is_array($jwt['registration'])) {
                $errors[] = 'auth.jwt.registration must be an array when provided.';
            } else {
                if (isset($jwt['registration']['enabled']) && !is_bool($jwt['registration']['enabled'])) {
                    $errors[] = 'auth.jwt.registration.enabled must be a boolean value when provided.';
                }

                if (isset($jwt['registration']['default_roles'])) {
                    if (!is_array($jwt['registration']['default_roles'])) {
                        $errors[] = 'auth.jwt.registration.default_roles must be an array of strings.';
                    } else {
                        foreach ($jwt['registration']['default_roles'] as $role) {
                            if (!is_string($role) || trim($role) === '') {
                                $errors[] = 'auth.jwt.registration.default_roles must contain only non-empty strings.';
                                break;
                            }
                        }
                    }
                }
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
