<?php
namespace Tests\Web\Controller\Fixtures;

use Bamboo\Core\Application;
use Bamboo\Web\View\LandingPageContent;

final class RecordingLandingPageContent extends LandingPageContent {
  /**
   * @var array{
   *   html: string,
   *   template: array{
   *     version: int,
   *     component: string,
   *     children: list<array<string, mixed>>
   *   },
   *   meta: array<string, string>
   * }
   */
  private array $payload;

  /**
   * @var array<string, scalar>
   */
  public array $lastDescriptor = [];

  /**
   * @param array{
   *   html: string,
   *   template: array{
   *     version: int,
   *     component: string,
   *     children: list<array<string, mixed>>
   *   },
   *   meta: array<string, string>
   * } $payload
   */
  public function __construct(Application $app, array $payload) {
    parent::__construct($app);
    $this->payload = $payload;
  }

  /**
   * @param array<string, scalar> $descriptor
   *
   * @return array{
   *   html: string,
   *   template: array{
   *     version: int,
   *     component: string,
   *     children: list<array<string, mixed>>
   *   },
   *   meta: array<string, string>
   * }
   */
  public function payload(array $descriptor = []): array {
    $this->lastDescriptor = $descriptor;

    return $this->payload;
  }
}
