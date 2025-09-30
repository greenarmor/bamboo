<?php
namespace Bamboo\Console\Command;

class AppKeyMake extends Command {
  public function name(): string { return 'app.key.make'; }
  public function description(): string { return 'Generate APP_KEY in .env'; }
  public function usage(): string { return 'php bin/bamboo app.key.make [--if-missing]'; }

  public function handle(array $args): int {
    $root = dirname(__DIR__, 3);
    $envPath = $root . '/.env';
    $envExample = $root . '/.env.example';

    $ifMissing = in_array('--if-missing', $args, true);

    if (!file_exists($envPath) && file_exists($envExample)) {
      @copy($envExample, $envPath);
    }

    $key = 'base64:' . base64_encode(random_bytes(32));
    $env = file_exists($envPath) ? file_get_contents($envPath) : "";

    if ($ifMissing && preg_match('/^APP_KEY\s*=\s*(\S+)/m', $env, $m) && trim($m[1]) !== '') {
      echo "APP_KEY already present; skipping (--if-missing).\n";
      return 0;
    }

    if (preg_match('/^APP_KEY\s*=.*$/m', $env)) {
      $env = preg_replace('/^APP_KEY\s*=.*$/m', 'APP_KEY='.$key, $env);
    } else {
      $env = rtrim($env, "\r\n") . "\nAPP_KEY={$key}\n";
    }

    if (!is_dir(dirname($envPath))) {
      @mkdir(dirname($envPath), 0775, true);
    }

    file_put_contents($envPath, $env);
    echo "APP_KEY set in {$envPath}.\n";
    return 0;
  }
}
