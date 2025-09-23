<?php
use Dotenv\Dotenv;
use Bamboo\Core\{Application, Config, ConfigValidator, ConfigurationException};

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$config = new Config(__DIR__.'/../etc');
$validator = new ConfigValidator();

try {
    $validator->validate($config->all());
} catch (ConfigurationException $e) {
    foreach ($e->errors() as $error) {
        error_log('[config] ' . $error);
    }

    throw $e;
}

$app = new Application($config);
$app->register(new Bamboo\Provider\AppProvider());
$app->register(new Bamboo\Provider\MetricsProvider());
$app->register(new Bamboo\Provider\ResilienceProvider());
$modules = require __DIR__ . '/../etc/modules.php';
if (!is_array($modules)) {
    throw new \InvalidArgumentException('Module configuration must return an array of class names.');
}
$app->bootModules($modules);
$app->bootEloquent();

return $app;
