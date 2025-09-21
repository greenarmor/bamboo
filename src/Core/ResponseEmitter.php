<?php
namespace Bamboo\Core;
class ResponseEmitter {
  public static function emit(\OpenSwoole\HTTP\Response $res, $psr): void {
    $res->status($psr->getStatusCode());
    foreach ($psr->getHeaders() as $name=>$values){ foreach ($values as $v){ $res->header($name, $v); } }
    $res->end((string)$psr->getBody());
  }
  public static function emitCli($psr): void {
    http_response_code($psr->getStatusCode());
    foreach ($psr->getHeaders() as $name=>$values){ foreach ($values as $v){ header($name.': '.$v); } }
    echo (string)$psr->getBody();
  }
}
