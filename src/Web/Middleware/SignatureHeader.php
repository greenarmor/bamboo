<?php
namespace Bamboo\Web\Middleware;
use Psr\Http\Message\ServerRequestInterface as Request;

class SignatureHeader {
  public function handle(Request $request, \Closure $next) {
    $resp = $next($request);
    return $resp->withHeader('X-Bamboo', 'fast');
  }
}
