<?php
namespace Bamboo\Console\Command\DevWatch;

class InotifyFileWatcher implements FileWatcher
{
    private $resource;

    /** @var array<int, string> */
    private array $watchMap = [];

    public function __construct(array $paths)
    {
        if (!extension_loaded('inotify')) {
            throw new \RuntimeException('inotify extension is not available');
        }

        $resource = @inotify_init();
        if (!is_resource($resource)) {
            throw new \RuntimeException('Failed to initialize inotify resource');
        }

        stream_set_blocking($resource, false);
        $this->resource = $resource;

        foreach ($paths as $path) {
            $this->registerPath($path);
        }
    }

    public function __destruct()
    {
        foreach (array_keys($this->watchMap) as $wd) {
            @inotify_rm_watch($this->resource, $wd);
        }

        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    public function poll(): bool
    {
        $events = @inotify_read($this->resource);
        if (empty($events)) {
            return false;
        }

        foreach ($events as $event) {
            $mask = $event['mask'] ?? 0;
            $wd = $event['wd'] ?? null;

            if ($wd !== null && ($mask & (IN_DELETE_SELF | IN_MOVE_SELF))) {
                @inotify_rm_watch($this->resource, $wd);
                unset($this->watchMap[$wd]);
                continue;
            }

            if (($mask & IN_ISDIR) && ($mask & (IN_CREATE | IN_MOVED_TO))) {
                $parent = $this->watchMap[$wd] ?? null;
                if ($parent) {
                    $name = $event['name'] ?? '';
                    $child = $parent . DIRECTORY_SEPARATOR . $name;
                    if (is_dir($child)) {
                        $this->registerPath($child);
                    }
                }
            }
        }

        return true;
    }

    public function label(): string
    {
        return 'inotify';
    }

    private function registerPath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_dir($path)) {
            $this->addWatch($path);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $this->addWatch($item->getPathname());
                }
            }

            return;
        }

        $this->addWatch($path);
    }

    private function addWatch(string $path): void
    {
        $mask = IN_ATTRIB | IN_CREATE | IN_DELETE | IN_DELETE_SELF | IN_MODIFY | IN_MOVE_SELF | IN_MOVED_FROM | IN_MOVED_TO | IN_CLOSE_WRITE;
        $wd = @inotify_add_watch($this->resource, $path, $mask);
        if ($wd !== false) {
            $this->watchMap[$wd] = $path;
        }
    }
}
