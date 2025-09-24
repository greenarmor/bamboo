<?php

declare(strict_types=1);

namespace Bamboo\Auth\Jwt;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class JwtAuthenticationMiddleware
{
    public function __construct(
        private JwtTokenService $tokens,
        private JsonUserRepository $users
    ) {
    }

    public function handle(Request $request, callable $next): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (!is_string($header) || stripos($header, 'bearer ') !== 0) {
            return $this->unauthorized('Missing bearer token.');
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            return $this->unauthorized('Missing bearer token.');
        }

        try {
            $claims = $this->tokens->parseToken($token);
        } catch (JwtException $exception) {
            return $this->unauthorized($exception->getMessage());
        }

        $subject = (string) ($claims['sub'] ?? '');
        if ($subject === '') {
            return $this->unauthorized('Token missing subject claim.');
        }

        $user = $this->users->find($subject);
        if ($user === null) {
            return $this->unauthorized('User not found for token subject.');
        }

        $request = $request
            ->withAttribute('auth.user', $user)
            ->withAttribute('auth.claims', $claims);

        /** @var ResponseInterface $response */
        $response = $next($request);

        return $response;
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $payload = [
            'error' => 'unauthorized',
            'message' => $message,
        ];

        return new Response(401, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
