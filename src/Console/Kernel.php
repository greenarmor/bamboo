<?php
namespace Bamboo\Console;
use Bamboo\Console\Command\{
  HttpServe, RoutesShow, RoutesCache, CachePurge, AppKeyMake,
  QueueWork, WsServe, DevWatch, ScheduleRun, PkgInfo, ClientCall,
  ConfigValidate
};

class Kernel {
  protected array $commands = [
    HttpServe::class, RoutesShow::class, RoutesCache::class, CachePurge::class,
    AppKeyMake::class, QueueWork::class, WsServe::class, DevWatch::class, ScheduleRun::class,
    PkgInfo::class, ClientCall::class, ConfigValidate::class
  ];
  public function __construct(protected \Bamboo\Core\Application $app) {}
  public function run(array $argv): int {
    $name = $argv[1] ?? 'help';
    if ($name === 'help') {
      echo "Bamboo CLI\nCommands:\n";
      foreach ($this->commands as $c) { $i = new $c($this->app); echo "  - ".$i->name()."  ".$i->description()."\n"; }
      return 0;
    }
    foreach ($this->commands as $c) {
      $i = new $c($this->app);
      if ($i->matches($name)) return $i->handle(array_slice($argv,2));
    }
    echo "Unknown command: {$name}\n";
    return 1;
  }
}
