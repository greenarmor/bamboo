<?php
namespace Bamboo\Console\Command;
abstract class Command {
  abstract public function name(): string;
  abstract public function description(): string;
  public function __construct(protected \Bamboo\Core\Application $app) {}
  public function matches(string $input): bool { return $input === $this->name(); }
  public function usage(): string { return sprintf('php bin/bamboo %s', $this->name()); }
  /**
   * @param list<string> $args
   */
  abstract public function handle(array $args): int;
}
