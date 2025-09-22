<?php
namespace Bamboo\Console\Command\DevWatch;

use Symfony\Component\Finder\Finder;

class FinderFileWatcher implements FileWatcher
{
    private array $paths;
    private ?string $lastFingerprint = null;

    public function __construct(array $paths)
    {
        $this->paths = array_values(array_unique($paths));
        $this->lastFingerprint = $this->fingerprint();
    }

    public function poll(): bool
    {
        $current = $this->fingerprint();
        if ($this->lastFingerprint === $current) {
            return false;
        }

        $this->lastFingerprint = $current;
        return true;
    }

    public function label(): string
    {
        return 'finder';
    }

    private function fingerprint(): string
    {
        $parts = [];

        foreach ($this->paths as $path) {
            if (!file_exists($path)) {
                $parts[] = 'missing:' . $path;
                continue;
            }

            if (is_dir($path)) {
                $parts[] = 'dir:' . $path . ':' . filemtime($path);
                $finder = new Finder();
                try {
                    $finder->files()->ignoreUnreadableDirs()->in($path);
                } catch (\Throwable $e) {
                    $parts[] = 'error:' . $path . ':' . $e->getMessage();
                    continue;
                }

                foreach ($finder as $file) {
                    $real = $file->getRealPath() ?: $file->getPathname();
                    $parts[] = $real . ':' . $file->getMTime() . ':' . $file->getSize();
                }

                continue;
            }

            $parts[] = 'file:' . $path . ':' . filemtime($path) . ':' . filesize($path);
        }

        sort($parts);
        return sha1(implode('|', $parts));
    }
}
