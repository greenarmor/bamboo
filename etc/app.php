<?php
return [
  'name' => $_ENV['APP_NAME'] ?? 'Bamboo',
  'env' => $_ENV['APP_ENV'] ?? 'local',
  'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
  'key' => $_ENV['APP_KEY'] ?? '',
  'log_file' => $_ENV['LOG_FILE'] ?? __DIR__.'/../var/log/app.log',
];
