<?php

namespace Tests\Web\Controller;

use Bamboo\Core\Application;
use Bamboo\Web\Controller\LandingContentController;
use Bamboo\Web\View\LandingDescriptorResolver;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Tests\Web\Controller\Fixtures\RecordingLandingPageContent;

final class LandingContentControllerTest extends TestCase {
  public function testShowUsesResolverDescriptor(): void {
    $app = $this->createMock(Application::class);
    $app->method('config')->willReturnCallback(static function(?string $key = null, $default = null) {
      if ($key === 'landing.content') {
        return [
          'type' => 'Article',
          'audience' => 'Teams',
          'count' => 5,
          'empty' => '',
        ];
      }

      return $default;
    });

    $resolver = new LandingDescriptorResolver($app);
    $payload = [
      'html' => '<div>content</div>',
      'template' => [
        'version' => 1,
        'component' => 'page',
        'children' => [],
      ],
      'meta' => [
        'title' => 'Custom Title',
      ],
    ];
    $builder = new RecordingLandingPageContent($app, $payload);
    $controller = new LandingContentController($app, $resolver, $builder);

    $request = (new ServerRequest('GET', '/'))->withQueryParams([
      'type' => '',
      'audience' => '',
    ]);

    $response = $controller->show($request);

    $this->assertSame([
      'type' => 'article',
      'audience' => 'Teams',
      'count' => '5',
    ], $builder->lastDescriptor);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertJson((string) $response->getBody());
  }
}
