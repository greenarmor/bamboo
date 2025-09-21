<?php
return [
  'url' => $_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379',
  'queue' => $_ENV['REDIS_QUEUE'] ?? 'jobs',
];
