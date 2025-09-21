<?php
namespace Bamboo\Console\Command;
abstract class Command {
  abstract public function name(): string;
  abstract public function description(): string;
  public function __construct(protected \Bamboo\Core\Application $app) {}
  public function matches(string $input): bool { return $input === $this->name(); }
  abstract public function handle(array $args): int;
}
