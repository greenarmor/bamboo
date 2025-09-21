<?php
namespace Bamboo\Console\Command;
class PkgInfo extends Command {
  public function name(): string { return 'pkg.info'; }
  public function description(): string { return 'Show installed Composer packages (from vendor/composer/installed.json)'; }
  public function handle(array $args): int { 
$path = dirname(__DIR__, 4).'/vendor/composer/installed.json';
if (!file_exists($path)) { echo "No vendor packages installed yet.\n"; return 0; }
$data = json_decode(file_get_contents($path), true);
$packages = $data['packages'] ?? $data;
foreach ($packages as $p) { printf("%-40s %s\n", $p['name'] ?? 'unknown', $p['version'] ?? ''); }
return 0; }
}
