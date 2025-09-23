<?php

declare(strict_types=1);

namespace Bamboo\Web\Controller;

use Bamboo\Core\Application;
use Bamboo\Web\Health\HealthState;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HealthController
{
    private HealthState $state;

    public function __construct(Application $app)
    {
        $this->state = $app->get(HealthState::class);
    }

    public function healthz(Request $request): ResponseInterface
    {
        $payload = [
            'status' => $this->state->isLive() ? 'live' : 'stopped',
            'live' => $this->state->isLive(),
            'ready' => $this->state->isReady(),
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            (string) json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    public function readyz(Request $request): ResponseInterface
    {
        $ready = $this->state->isReady();
        $statusCode = $ready ? 200 : 503;

        $payload = [
            'status' => $ready ? 'ready' : 'not_ready',
            'dependencies' => $this->state->dependencies(),
        ];

        return new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            (string) json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }
}
