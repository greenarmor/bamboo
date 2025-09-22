<?php
namespace Bamboo\Console\Command\DevWatch;

interface FileWatcher
{
    public function poll(): bool;

    public function label(): string;
}
