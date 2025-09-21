<?php
namespace Bamboo\Console\Command;
class AppKeyMake extends Command {
  public function name(): string { return 'app.key.make'; }
  public function description(): string { return 'Generate APP_KEY in .env'; }
  public function handle(array $args): int { 
$envPath = dirname(__DIR__, 4) . '/.env';
if (!file_exists($envPath) && file_exists(dirname(__DIR__,4).'/.env.example')) {
  copy(dirname(__DIR__,4).'/.env.example', $envPath);
}
$key = bin2hex(random_bytes(32));
$env = file_exists($envPath) ? file_get_contents($envPath) : "";
if (str_contains($env, 'APP_KEY=')) {
  $env = preg_replace('/^APP_KEY=.*/m', 'APP_KEY='.$key, $env);
} else { $env .= "\nAPP_KEY={$key}\n"; }
file_put_contents($envPath, $env);
echo "APP_KEY set.\n"; return 0; }
}
