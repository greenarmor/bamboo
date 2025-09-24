<?php

declare(strict_types=1);

namespace Tests\Auth\Jwt;

use Bamboo\Auth\Jwt\JwtException;
use Bamboo\Auth\Jwt\JwtTokenService;
use PHPUnit\Framework\TestCase;

final class JwtTokenServiceTest extends TestCase
{
    public function testIssueAndParseToken(): void
    {
        $service = new JwtTokenService('super-secret', 3600, 'issuer', 'audience');
        $token = $service->issueToken('alice', ['roles' => ['admin']]);

        $claims = $service->parseToken($token);

        $this->assertSame('alice', $claims['sub']);
        $this->assertSame('issuer', $claims['iss']);
        $this->assertSame('audience', $claims['aud']);
        $this->assertSame(['admin'], $claims['roles']);
    }

    public function testExpiredTokenThrows(): void
    {
        $service = new JwtTokenService('secret', 1, 'issuer', 'audience');
        $token = $service->issueToken('bob');

        sleep(2);

        $this->expectException(JwtException::class);
        $this->expectExceptionMessage('JWT has expired');
        $service->parseToken($token);
    }

    public function testInvalidSignatureThrows(): void
    {
        $service = new JwtTokenService('secret', 3600, 'issuer', 'audience');
        $token = $service->issueToken('carol');

        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        $parts[2] = str_repeat('A', strlen($parts[2]));
        $tampered = implode('.', $parts);

        $this->expectException(JwtException::class);
        $this->expectExceptionMessage('Invalid JWT signature');
        $service->parseToken($tampered);
    }
}
