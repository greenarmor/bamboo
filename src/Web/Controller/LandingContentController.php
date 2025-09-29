<?php
namespace Bamboo\Web\Controller;

use Bamboo\Core\Application;
use Bamboo\Web\View\LandingDescriptorResolver;
use Bamboo\Web\View\LandingPageContent;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LandingContentController {
  private LandingDescriptorResolver $descriptorResolver;
  private LandingPageContent $contentBuilder;

  public function __construct(
    private Application $app,
    ?LandingDescriptorResolver $descriptorResolver = null,
    ?LandingPageContent $contentBuilder = null,
  ) {
    $this->descriptorResolver = $descriptorResolver ?? new LandingDescriptorResolver($this->app);
    $this->contentBuilder = $contentBuilder ?? new LandingPageContent($this->app);
  }

  public function show(Request $request): Response {
    $payload = $this->contentBuilder->payload($this->descriptorResolver->resolve($request));

    return new Response(
      200,
      ['Content-Type' => 'application/json'],
      json_encode($payload, JSON_THROW_ON_ERROR)
    );
  }
}
