<?php
namespace Bamboo\Console\Command;

use Bamboo\Web\View\LandingPageContent;
use JsonException;

class LandingMeta extends Command {
  public function name(): string { return 'landing.meta'; }

  public function description(): string {
    return 'Generate landing metadata for a given content descriptor';
  }

  public function usage(): string {
    return 'php bin/bamboo landing.meta [type] [key=value ...]';
  }

  public function handle(array $args): int {
    $descriptor = $this->resolveDescriptor($args);
    $builder = new LandingPageContent($this->app);

    $payload = $builder->payload($descriptor);
    $meta = $payload['meta'] ?? [];

    if (!is_array($meta) || $meta === []) {
      echo "No metadata available.\n";
      return 0;
    }

    try {
      $encoded = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
      fwrite(STDERR, 'Failed to encode metadata: ' . $exception->getMessage() . "\n");
      return 1;
    }

    if ($encoded === false) {
      fwrite(STDERR, "Failed to encode metadata.\n");
      return 1;
    }

    echo $encoded . "\n";

    return 0;
  }

  /**
   * @param list<string> $args
   *
   * @return array<string, string>
   */
  private function resolveDescriptor(array $args): array {
    $descriptor = [];

    if ($args !== [] && !str_contains($args[0], '=')) {
      $type = trim($args[0]);
      if ($type !== '') {
        $descriptor['type'] = strtolower($type);
      }
      $args = array_slice($args, 1);
    }

    foreach ($args as $argument) {
      if (!str_contains($argument, '=')) {
        continue;
      }

      [$key, $value] = explode('=', $argument, 2);
      $key = trim($key);
      $value = trim($value);

      if ($key === '' || $value === '') {
        continue;
      }

      if ($key === 'type') {
        $descriptor['type'] = strtolower($value);
        continue;
      }

      $descriptor[$key] = $value;
    }

    if ($descriptor !== []) {
      return $descriptor;
    }

    $configured = $this->app->config('landing.content');
    if (!is_array($configured)) {
      return [];
    }

    $clean = [];
    foreach ($configured as $key => $value) {
      if (!is_scalar($value) || $value === '') {
        continue;
      }

      $clean[(string) $key] = (string) $value;
    }

    if (isset($clean['type'])) {
      $clean['type'] = strtolower($clean['type']);
    }

    return $clean;
  }
}
