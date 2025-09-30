<?php
namespace Bamboo\Console;
use Bamboo\Console\Command\{
  HttpServe, RoutesShow, RoutesCache, CachePurge, AppKeyMake,
  QueueWork, WsServe, DevWatch, ScheduleRun, PkgInfo, ClientCall,
  ConfigValidate, AuthJwtSetup, LandingMeta, DatabaseSetup
};

use Bamboo\Console\Command\Command;

class Kernel {
  /**
   * @var list<class-string<Command>>
   */
  protected array $commands = [
    HttpServe::class, RoutesShow::class, RoutesCache::class, CachePurge::class,
    AppKeyMake::class, QueueWork::class, WsServe::class, DevWatch::class, ScheduleRun::class,
    PkgInfo::class, ClientCall::class, ConfigValidate::class, AuthJwtSetup::class,
    LandingMeta::class, DatabaseSetup::class
  ];

  /**
   * @var list<Command>|null
   */
  private ?array $resolvedCommands = null;

  public function __construct(protected \Bamboo\Core\Application $app) {}

  /**
   * @param list<string> $argv
   */
  public function run(array $argv): int {
    $args = array_slice($argv, 1);
    if ($args === []) {
      $this->printGeneralHelp();

      return 0;
    }

    $requested = array_shift($args);
    if ($requested !== null && $this->isHelpRequest($requested)) {
      $target = $args[0] ?? null;
      if ($target !== null) {
        return $this->printCommandHelp($target);
      }

      $this->printGeneralHelp();

      return 0;
    }

    $command = $requested !== null ? $this->findCommand($requested) : null;
    if ($command !== null) {
      return $command->handle($args);
    }

    echo "Unknown command: {$requested}\n";
    echo "Run 'php bin/bamboo --help' to see available commands.\n";

    return 1;
  }

  private function isHelpRequest(string $value): bool {
    return in_array($value, ['help', '--help', '-h'], true);
  }

  private function printGeneralHelp(): void {
    echo "Bamboo CLI\n";
    echo "Usage: php bin/bamboo <command> [options]\n\n";
    echo "Available commands:\n";
    foreach ($this->instances() as $command) {
      printf("  %-20s %s\n", $command->name(), $command->description());
      printf("      Usage: %s\n", $command->usage());
      echo "\n";
    }
  }

  private function printCommandHelp(string $name): int {
    $command = $this->findCommand($name);
    if ($command === null) {
      echo "Unknown command: {$name}\n";
      echo "Run 'php bin/bamboo --help' to see available commands.\n";

      return 1;
    }

    echo sprintf("Command: %s\n", $command->name());
    echo sprintf("Description: %s\n", $command->description());
    echo sprintf("Usage: %s\n", $command->usage());

    return 0;
  }

  /**
   * @return list<Command>
   */
  private function instances(): array {
    if ($this->resolvedCommands === null) {
      $this->resolvedCommands = array_map(
        fn(string $class): Command => new $class($this->app),
        $this->commands,
      );
    }

    return $this->resolvedCommands;
  }

  private function findCommand(string $name): ?Command {
    foreach ($this->instances() as $command) {
      if ($command->matches($name)) {
        return $command;
      }
    }

    return null;
  }
}
