<?php
return [
  'default' => [
    'timeout' => 5.0,
    'headers' => [
      'User-Agent' => 'Bamboo-HTTP/1.0'
    ],
    'retries' => [
      'max' => 2,
      'base_delay_ms' => 150,
      'status_codes' => [429, 500, 502, 503, 504],
    ],
  ],
  'services' => [
    'httpbin' => [
      'base_uri' => 'https://httpbin.org',
      'timeout'  => 5.0,
    ],
  ],
];
