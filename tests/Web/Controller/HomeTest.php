<?php

namespace Tests\Web\Controller;

use Bamboo\Core\Application;
use Bamboo\Web\Controller\Home;
use Bamboo\Web\View\LandingDescriptorResolver;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Tests\Web\Controller\Fixtures\RecordingLandingPageContent;

final class HomeTest extends TestCase {
  public function testIndexUsesResolverDescriptor(): void {
    $app = $this->createMock(Application::class);
    $app->method('config')->willReturnCallback(function(?string $key = null, $default = null) {
      if ($key === 'app.name') {
        return 'Bamboo';
      }

      if ($key === 'landing.content') {
        $this->fail('Landing content configuration should not be read when query parameters exist.');
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
        'description' => 'Custom Description',
      ],
    ];
    $builder = new RecordingLandingPageContent($app, $payload);
    $controller = new Home($app, $resolver, $builder);

    $request = (new ServerRequest('GET', '/'))->withQueryParams([
      'type' => 'Article',
      'author' => 'Jordan',
      'views' => 500,
      'empty' => '',
    ]);

    $response = $controller->index($request);

    $this->assertSame([
      'type' => 'article',
      'author' => 'Jordan',
      'views' => '500',
    ], $builder->lastDescriptor);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('Custom Title', (string) $response->getBody());
  }
}
