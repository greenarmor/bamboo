<?php
namespace Bamboo\Web\View;

use RuntimeException;

class LandingShellRenderer {
  /**
   * @param array{title?:string, metaTags?:string, loadingMessage?:string, encodedErrorHtml?:string} $data
   */
  public function render(array $data): string {
    $templatePath = __DIR__ . '/templates/landing-shell.php';
    if (!is_file($templatePath)) {
      throw new RuntimeException('Landing shell template not found.');
    }

    $title = (string) ($data['title'] ?? '');
    $metaTags = (string) ($data['metaTags'] ?? '');
    $loadingMessage = (string) ($data['loadingMessage'] ?? '');
    $encodedErrorHtml = (string) ($data['encodedErrorHtml'] ?? '');

    ob_start();
    try {
      require $templatePath;
    } finally {
      $output = ob_get_clean();
    }

    if ($output === false) {
      throw new RuntimeException('Unable to render landing shell template.');
    }

    return $output;
  }
}
