<?php
use Dotenv\Dotenv;
use Bamboo\Core\{Application, Config};

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$config = new Config(__DIR__.'/../etc');
$app = new Application($config);
$app->register(new Bamboo\Provider\AppProvider());
$app->bootEloquent();

return $app;
