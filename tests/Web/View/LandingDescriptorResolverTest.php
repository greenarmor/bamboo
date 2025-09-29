<?php

namespace Tests\Web\View;

use Bamboo\Core\Application;
use Bamboo\Web\View\LandingDescriptorResolver;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class LandingDescriptorResolverTest extends TestCase {
  public function testResolvesDescriptorFromQueryParameters(): void {
    $app = $this->createMock(Application::class);
    $app->method('config')->willReturnCallback(function(?string $key = null, $default = null) {
      if ($key === 'landing.content') {
        $this->fail('Landing content configuration should not be read when query parameters exist.');
      }

      return $default;
    });

    $resolver = new LandingDescriptorResolver($app);
    $request = (new ServerRequest('GET', '/'))
      ->withQueryParams([
        'type' => 'Docs',
        'theme' => 'Dark',
        'count' => 5,
        'empty' => '',
        'array' => ['ignored'],
        0 => 'skip',
      ]);

    $descriptor = $resolver->resolve($request);

    $this->assertSame([
      'type' => 'docs',
      'theme' => 'Dark',
      'count' => '5',
    ], $descriptor);
  }

  public function testFallsBackToConfiguredDescriptor(): void {
    $app = $this->createMock(Application::class);
    $app->method('config')->willReturnCallback(static function(?string $key = null, $default = null) {
      if ($key === 'landing.content') {
        return [
          'type' => 'Article',
          'audience' => 'Leads',
          'count' => 5,
          'empty' => '',
        ];
      }

      return $default;
    });

    $resolver = new LandingDescriptorResolver($app);
    $request = (new ServerRequest('GET', '/'))->withQueryParams(['foo' => '']);

    $descriptor = $resolver->resolve($request);

    $this->assertSame([
      'type' => 'article',
      'audience' => 'Leads',
      'count' => '5',
    ], $descriptor);
  }
}
