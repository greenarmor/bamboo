<?php
namespace Bamboo\Http\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface {
  public function send(RequestInterface $request): ResponseInterface;
  /** @param RequestInterface[] $requests */
  public function sendConcurrent(array $requests): array;
}
