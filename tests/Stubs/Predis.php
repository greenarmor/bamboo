<?php
namespace Tests\Stubs;

use Predis\Command\CommandInterface;
use Predis\Connection\AbstractConnection;
use Predis\Connection\Parameters;
use Predis\Connection\ParametersInterface;

final class PredisFakeServer
{
    private static array $queues = [];

    public static function reset(): void
    {
        self::$queues = [];
    }

    public static function push(string $queue, string $payload): void
    {
        self::$queues[$queue][] = $payload;
    }

    public static function pop(string $queue): ?string
    {
        if (empty(self::$queues[$queue])) {
            return null;
        }

        return array_shift(self::$queues[$queue]);
    }

    public static function dumpQueue(string $queue): array
    {
        return self::$queues[$queue] ?? [];
    }
}

final class PredisMemoryConnection extends AbstractConnection
{
    private $lastResponse = null;

    protected function assertParameters(ParametersInterface $parameters)
    {
        return $parameters;
    }

    protected function createResource()
    {
        return true;
    }

    public function getResource()
    {
        return null;
    }

    public function writeRequest(CommandInterface $command): void
    {
        $id = strtoupper($command->getId());
        $arguments = $command->getArguments();

        if ($id === 'RPUSH') {
            $queue = array_shift($arguments);
            foreach ($arguments as $value) {
                PredisFakeServer::push($queue, (string) $value);
            }
            $this->lastResponse = count(PredisFakeServer::dumpQueue($queue));
            return;
        }

        if ($id === 'BLPOP') {
            $keys = $arguments;
            if ($keys) {
                array_pop($keys); // discard timeout
            }

            foreach ($keys as $queue) {
                $item = PredisFakeServer::pop($queue);
                if ($item !== null) {
                    $this->lastResponse = [$queue, $item];
                    return;
                }
            }

            $this->lastResponse = null;
            return;
        }

        $this->lastResponse = null;
    }

    public function read()
    {
        return $this->lastResponse;
    }

    public function __toString(): string
    {
        return 'memory';
    }

    public static function factory(): callable
    {
        return static function ($parameters): self {
            if (!$parameters instanceof ParametersInterface) {
                $parameters = new Parameters($parameters);
            }

            return new self($parameters);
        };
    }
}
