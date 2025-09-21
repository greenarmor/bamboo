<?php
return [
  'host' => $_ENV['WS_HOST'] ?? '127.0.0.1',
  'port' => (int)($_ENV['WS_PORT'] ?? 9502),
];
