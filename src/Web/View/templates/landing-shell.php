<?php
/**
 * @var string $title
 * @var string $metaTags
 * @var string $loadingMessage
 * @var string $encodedErrorHtml
 */
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?= $title ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?= $metaTags ?>
    <link rel="stylesheet" href="/assets/bamboo-ui.css">
  </head>
  <body>
    <div id="landing-root" class="bamboo-page">
      <div class="bamboo-loading" role="status" aria-live="polite">
        <span class="bamboo-spinner" aria-hidden="true"></span>
        <span class="label"><?= $loadingMessage ?></span>
      </div>
    </div>
    <noscript>
      <div class="bamboo-page">
        <div class="bamboo-error" role="alert">Enable JavaScript to view the Bamboo landing experience.</div>
      </div>
    </noscript>
    <script type="module">
      import { renderTemplate } from '/assets/bamboo-ui.js';
      const root = document.getElementById('landing-root');

      async function renderLanding() {
        try {
          const response = await fetch('/api/landing', { headers: { 'Accept': 'application/json' } });
          if (!response.ok) {
            throw new Error('Failed with status ' + response.status);
          }

          const payload = await response.json();

          if (payload && payload.template) {
            renderTemplate(root, payload.template);
          } else if (payload && payload.html) {
            root.innerHTML = payload.html;
          }

          if (payload && payload.meta) {
            if (payload.meta.title) {
              document.title = payload.meta.title;
            }

            for (const [name, value] of Object.entries(payload.meta)) {
              if (!value || name === 'title') {
                continue;
              }

              const selector = 'meta[name="' + String(name).replace(/"/g, '\\"') + '"]';
              let tag = document.head.querySelector(selector);
              if (!tag) {
                tag = document.createElement('meta');
                tag.setAttribute('name', name);
                document.head.appendChild(tag);
              }

              tag.setAttribute('content', String(value));
            }
          }
        } catch (error) {
          root.innerHTML = <?= $encodedErrorHtml ?>;
          console.error('Failed to load landing page payload', error);
        }
      }

      renderLanding();
    </script>
  </body>
</html>
