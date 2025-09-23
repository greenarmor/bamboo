<?php
$utilClass = 'OpenSwoole\\Util';
$cpuNum =
    (class_exists($utilClass) && is_callable([$utilClass, 'getCPUNum']))
        ? (int) call_user_func([$utilClass, 'getCPUNum'])
        : (function_exists('swoole_cpu_num')
            ? swoole_cpu_num()
            : (int) (trim(@shell_exec('nproc 2>/dev/null')) ?: 1));

return [
  'host' => $_ENV['HTTP_HOST'] ?? '127.0.0.1',
  'port' => (int)($_ENV['HTTP_PORT'] ?? 9501),
  'workers' => ($_ENV['HTTP_WORKERS'] ?? 'auto') === 'auto'
      ? $cpuNum
      : (int) $_ENV['HTTP_WORKERS'],
  'task_workers' => (int)($_ENV['TASK_WORKERS'] ?? 0),
  'max_requests' => (int)($_ENV['MAX_REQUESTS'] ?? 10000),
  'static_enabled' => filter_var($_ENV['STATIC_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
];
