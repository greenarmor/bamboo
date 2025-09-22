<?php

namespace Tests\Http\Client;

use Bamboo\Http\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as Psr18;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\RouterTestApplication;

class StubPsr18Client implements Psr18 {
  public array $requests = [];

  public function sendRequest(RequestInterface $request): ResponseInterface {
    $this->requests[] = $request;
    $psr17 = new Psr17Factory();
    return $psr17->createResponse(204)->withBody($psr17->createStream('done'));
  }
}

class ClientFacadeTest extends TestCase {
  public function testFacadeAppliesServiceConfiguration(): void {
    $app = new RouterTestApplication([], [
      'http' => [
        'default' => [
          'timeout' => 1.0,
          'headers' => ['User-Agent' => 'Default-UA'],
        ],
        'services' => [
          'search' => [
            'base_uri' => 'https://api.example.com',
            'timeout' => 3.5,
            'headers' => ['X-Service' => 'search'],
          ],
        ],
      ],
    ]);

    $capturedConfig = null;
    $stub = new StubPsr18Client();
    $factory = function (array $config) use (&$capturedConfig, $stub): Psr18 {
      $capturedConfig = $config;
      return $stub;
    };

    $client = new Client($app, $factory);

    $facade = $client->for('search');
    $request = new ServerRequest('GET', 'https://api.example.com/resources');
    $response = $facade->send($request);

    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame([
      'timeout' => 3.5,
      'base_uri' => 'https://api.example.com',
      'http_errors' => false,
    ], $capturedConfig);

    $this->assertCount(1, $stub->requests);
    $sent = $stub->requests[0];
    $this->assertSame('Default-UA', $sent->getHeaderLine('User-Agent'));
    $this->assertSame('search', $sent->getHeaderLine('X-Service'));
  }
}
