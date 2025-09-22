<?php
namespace Bamboo\Web;
class Kernel {
  public array $middleware = [
    \Bamboo\Web\Middleware\RequestId::class,
    \Bamboo\Web\Middleware\SignatureHeader::class,
  ];
}
