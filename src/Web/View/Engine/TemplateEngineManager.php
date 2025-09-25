<?php
namespace Bamboo\Web\View\Engine;

use Bamboo\Core\Application;
use Bamboo\Web\View\Engine\Engines\ComponentTemplateEngine;
use InvalidArgumentException;

class TemplateEngineManager {
  /** @var array<string, TemplateEngineInterface> */
  private array $engines = [];

  /**
   * @var array<string, callable(Application,array<string,mixed>):TemplateEngineInterface>
   */
  private array $factories = [];

  public function __construct(private Application $app) {}

  public function extend(string $driver, callable $factory): void {
    $this->factories[$driver] = $factory;
  }

  /**
   * @param array<string,mixed> $template
   * @param array<string,mixed> $context
   */
  public function render(array $template, array $context = [], ?string $engine = null): string {
    return $this->engine($engine)->render($template, $context);
  }

  public function engine(?string $name = null): TemplateEngineInterface {
    $engineName = $name;
    if (!is_string($engineName) || $engineName === '') {
      $engineName = $this->defaultEngine();
    }

    if (!isset($this->engines[$engineName])) {
      $configuration = $this->configuration($engineName);
      $driver = $configuration['driver'] ?? $engineName;
      if (!is_string($driver) || $driver === '') {
        throw new InvalidArgumentException(sprintf('View engine "%s" must define a driver name.', $engineName));
      }
      $this->engines[$engineName] = $this->createEngine($driver, $configuration);
    }

    return $this->engines[$engineName];
  }

  /**
   * @param array<string,mixed> $config
   */
  private function createEngine(string $driver, array $config): TemplateEngineInterface {
    $factory = $this->factories[$driver] ?? null;

    if ($factory === null) {
      $method = 'create' . ucfirst($driver) . 'Driver';
      if (!method_exists($this, $method)) {
        throw new InvalidArgumentException(sprintf('View driver "%s" is not supported.', $driver));
      }
      /** @var callable(array<string,mixed>):TemplateEngineInterface $factory */
      $factory = [$this, $method];
      $engine = $factory($config);
    } else {
      $engine = $factory($this->app, $config);
    }

    if (!$engine instanceof TemplateEngineInterface) {
      throw new InvalidArgumentException(sprintf('View driver "%s" must return an instance of %s.', $driver, TemplateEngineInterface::class));
    }

    return $engine;
  }

  private function defaultEngine(): string {
    $default = $this->app->config('view.default', 'components');
    if (!is_string($default) || $default === '') {
      return 'components';
    }

    return $default;
  }

  /**
   * @return array<string,mixed>
   */
  private function configuration(string $name): array {
    $config = $this->app->config("view.engines.{$name}");
    if (!is_array($config)) {
      throw new InvalidArgumentException(sprintf('View engine configuration "%s" must be an array.', $name));
    }

    return $config;
  }

  /**
   * @param array<string,mixed> $config
   */
  private function createComponentsDriver(array $config): TemplateEngineInterface {
    return new ComponentTemplateEngine();
  }
}
