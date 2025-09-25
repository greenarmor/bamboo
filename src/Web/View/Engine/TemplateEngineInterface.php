<?php
namespace Bamboo\Web\View\Engine;

interface TemplateEngineInterface {
  /**
   * @param array<string,mixed> $template
   * @param array<string,mixed> $context
   */
  public function render(array $template, array $context = []): string;
}
