<?php
namespace Bamboo\Console\Command\DevWatch;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class DevWatchSupervisor
{
    private const DEFAULT_POLL_INTERVAL_US = 100_000;

    private ?Process $process = null;
    private bool $pendingRestart = false;
    private float $lastChangeAt = 0.0;
    private bool $stopRequested = false;
    private int $restartCount = 0;
    /** @var callable */
    private $processFactory;
    /** @var callable */
    private $sleeper;
    private int $pollIntervalMicroseconds;

    public function __construct(
        private FileWatcher $watcher,
        callable $processFactory,
        private LoggerInterface $logger,
        private float $debounceSeconds,
        ?callable $sleeper = null,
        ?int $pollIntervalMicroseconds = null
    ) {
        $this->processFactory = $processFactory;
        $this->sleeper = $sleeper ?? static function (int $microseconds): void {
            if ($microseconds > 0) {
                usleep($microseconds);
            }
        };
        $this->pollIntervalMicroseconds = $pollIntervalMicroseconds ?? self::DEFAULT_POLL_INTERVAL_US;
    }

    public function run(?callable $afterTick = null): void
    {
        $this->startProcess(false);

        while (!$this->stopRequested) {
            $this->drainProcessOutput();

            if ($this->watcher->poll()) {
                $this->pendingRestart = true;
                $this->lastChangeAt = microtime(true);
            }

            if ($this->pendingRestart && (microtime(true) - $this->lastChangeAt) >= $this->debounceSeconds) {
                $this->pendingRestart = false;
                $this->restartProcess('file-change');
            }

            if ($this->process && !$this->process->isRunning()) {
                $this->logger->warning('HTTP server exited unexpectedly; restarting');
                $this->restartProcess('process-exit');
            }

            if ($afterTick && $afterTick($this) === false) {
                break;
            }

            ($this->sleeper)($this->pollIntervalMicroseconds);
        }

        $this->shutdown();
    }

    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    public function getRestartCount(): int
    {
        return $this->restartCount;
    }

    private function restartProcess(string $reason): void
    {
        $this->logger->info('Restarting HTTP server', ['reason' => $reason]);
        $this->stopProcess();
        $this->startProcess(true);
        $this->restartCount++;
    }

    private function startProcess(bool $isRestart): void
    {
        try {
            $process = ($this->processFactory)();
            if (!$process instanceof Process) {
                throw new \RuntimeException('Process factory must return an instance of Symfony\\Component\\Process\\Process.');
            }

            $process->setTimeout(null);
            $process->setIdleTimeout(null);
            $process->start();
            $this->process = $process;
            $this->logger->info($isRestart ? 'HTTP server relaunched' : 'HTTP server started', [
                'pid' => $process->getPid(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to start HTTP server process', ['exception' => $e]);
            $this->stopRequested = true;
        }
    }

    private function stopProcess(): void
    {
        if (!$this->process) {
            return;
        }

        try {
            if ($this->process->isRunning()) {
                $pid = $this->process->getPid();
                $this->logger->info('Stopping HTTP server', ['pid' => $pid]);
                try {
                    $this->process->signal(SIGTERM);
                } catch (\Throwable $signalException) {
                    $this->logger->warning('Unable to send SIGTERM to HTTP server', ['exception' => $signalException]);
                }
                try {
                    $this->process->wait();
                } catch (\Throwable $waitException) {
                    $this->logger->warning('Error while waiting for HTTP server to exit', ['exception' => $waitException]);
                }
            }
        } finally {
            $this->drainProcessOutput();
            $this->process = null;
        }
    }

    private function drainProcessOutput(): void
    {
        if (!$this->process) {
            return;
        }

        $out = $this->process->getIncrementalOutput();
        if ($out !== '') {
            fwrite(STDOUT, $out);
        }

        $err = $this->process->getIncrementalErrorOutput();
        if ($err !== '') {
            fwrite(STDERR, $err);
        }
    }

    private function shutdown(): void
    {
        $this->stopProcess();
        $this->logger->info('Development watcher stopped');
    }
}
