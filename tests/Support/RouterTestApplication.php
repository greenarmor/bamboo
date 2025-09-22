<?php
namespace Tests\Support;

use Bamboo\Core\Application;
use Bamboo\Core\Router;

final class RouterTestApplication extends Application
{
    private Router $routerInstance;

    /** @var array<int, array{0:string,1:string,2:callable|array}> */
    private array $definitions;

    public function __construct(array $definitions = [], array $configOverrides = [])
    {
        $this->routerInstance = new Router();
        $this->definitions = $definitions;

        $config = array_replace_recursive(self::baseConfiguration(), $configOverrides);

        parent::__construct(new ArrayConfig($config));
    }

    public static function baseConfiguration(): array
    {
        return [
            'app' => [
                'name' => 'TestApp',
                'env' => 'testing',
                'debug' => true,
                'key' => 'test-key',
                'log_file' => 'php://temp',
            ],
            'server' => [
                'host' => '127.0.0.1',
                'port' => 9501,
                'static_enabled' => false,
                'workers' => 1,
                'task_workers' => 1,
                'max_requests' => 1,
            ],
            'cache' => [
                'routes' => null,
            ],
            'redis' => [
                'url' => 'redis://memory',
                'queue' => 'jobs',
            ],
            'database' => [
                'connections' => [],
                'default' => null,
            ],
            'ws' => [],
            'http' => [],
        ];
    }

    protected function bootRoutes(): void
    {
        $this->singleton('router', fn () => $this->routerInstance);
        $router = $this->routerInstance;

        foreach ($this->definitions as $definition) {
            [$method, $path, $handler] = $definition;
            $router->{strtolower($method)}($path, $handler);
        }
    }

    public function router(): Router
    {
        return $this->routerInstance;
    }
}
