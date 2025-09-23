<?php

declare(strict_types=1);

namespace Bamboo\Web\Health;

final class HealthState
{
    private static ?self $global = null;

    private bool $live = true;

    private bool $ready = true;

    /**
     * @var array<string, array{healthy: bool, message: ?string, checked_at: string}>
     */
    private array $dependencies = [];

    public function __construct()
    {
        self::$global = $this;
    }

    public static function global(): ?self
    {
        return self::$global;
    }

    public static function resetGlobal(): void
    {
        self::$global = null;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    public function isReady(): bool
    {
        if ($this->ready === false) {
            return false;
        }

        foreach ($this->dependencies as $dependency) {
            if ($dependency['healthy'] === false) {
                return false;
            }
        }

        return true;
    }

    public function markReady(): void
    {
        $this->ready = true;
    }

    public function markUnready(): void
    {
        $this->ready = false;
    }

    public function markShuttingDown(): void
    {
        $this->ready = false;
        $this->live = true;
    }

    public function markTerminated(): void
    {
        $this->live = false;
        $this->ready = false;
    }

    public function setDependency(string $name, bool $healthy, ?string $message = null): void
    {
        $this->dependencies[$name] = [
            'healthy' => $healthy,
            'message' => $message,
            'checked_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, array{healthy: bool, message: ?string, checked_at: string}>
     */
    public function dependencies(): array
    {
        ksort($this->dependencies);

        return $this->dependencies;
    }
}
