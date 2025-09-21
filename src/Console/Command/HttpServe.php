<?php
namespace Bamboo\Console\Command;
class HttpServe extends Command {
  public function name(): string { return 'http.serve'; }
  public function description(): string { return 'Start the Bamboo OpenSwoole HTTP server'; }
  public function handle(array $args): int { require __DIR__ . '/../../../bootstrap/server.php'; return 0; }
}
