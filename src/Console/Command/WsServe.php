<?php
namespace Bamboo\Console\Command;
class WsServe extends Command {
  public function name(): string { return 'ws.serve'; }
  public function description(): string { return 'Start a WebSocket echo server'; }
  public function handle(array $args): int { 
$host = $this->app->config('ws.host'); $port = (int)$this->app->config('ws.port');
$ws = new \OpenSwoole\WebSocket\Server($host, $port);
$ws->on('open', fn($sv,$req)=> printf("WS %d open\n", $req->fd));
$ws->on('message', fn($sv,$frame)=> $sv->push($frame->fd, $frame->data));
$ws->on('close', fn($sv,$fd)=> printf("WS %d closed\n", $fd));
echo "WS on ws://{$host}:{$port}\n"; $ws->start(); return 0; }
}
