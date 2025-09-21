<?php
namespace Bamboo\Core;
use Nyholm\Psr7\ServerRequest;

class Helpers {
  public static function toPsrRequest(\OpenSwoole\HTTP\Request $req): ServerRequest {
    $uri = ($req->server['request_uri'] ?? '/') . ((isset($req->server['query_string']) && $req->server['query_string']!=='') ? '?' . $req->server['query_string'] : '');
    $psr = new ServerRequest($req->server['request_method'] ?? 'GET', $uri);
    foreach (($req->header ?? []) as $k=>$v) { $psr = $psr->withHeader($k, $v); }
    $psr = $psr->withQueryParams($req->get ?? []);
    if (($req->server['request_method'] ?? 'GET') !== 'GET') { $psr->getBody()->write($req->rawContent() ?: ''); }
    return $psr;
  }
}
