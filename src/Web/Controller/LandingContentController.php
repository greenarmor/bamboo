<?php
namespace Bamboo\Web\Controller;

use Bamboo\Core\Application;
use Bamboo\Web\View\LandingPageContent;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LandingContentController {
  public function __construct(private Application $app) {}

  public function show(Request $request): Response {
    $builder = new LandingPageContent($this->app);
    $payload = $builder->payload();

    return new Response(
      200,
      ['Content-Type' => 'application/json'],
      json_encode($payload, JSON_THROW_ON_ERROR)
    );
  }
}
