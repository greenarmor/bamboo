<?php

declare(strict_types=1);

namespace Bamboo\Auth\Jwt;

final class JwtTokenService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private string $secret,
        private int $ttlSeconds,
        private string $issuer,
        private string $audience
    ) {
        if ($this->ttlSeconds <= 0) {
            throw new JwtException('JWT TTL must be a positive integer.');
        }

        if ($this->secret === '') {
            throw new JwtException('JWT secret must be non-empty.');
        }
    }

    /**
     * @param array<string, mixed> $additionalClaims
     */
    public function issueToken(string $subject, array $additionalClaims = []): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            throw new JwtException('JWT subject must be a non-empty string.');
        }

        $issuedAt = time();
        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + $this->ttlSeconds,
            'sub' => $subject,
            'jti' => bin2hex(random_bytes(16)),
        ];

        foreach ($additionalClaims as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (array_key_exists($key, $payload)) {
                continue;
            }

            $payload[$key] = $value;
        }

        $header = [
            'alg' => self::ALGORITHM,
            'typ' => 'JWT',
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JwtException('Malformed JWT: expected three segments.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $decodedHeader = $this->decodeJson($this->base64UrlDecode($encodedHeader));
        $decodedPayload = $this->decodeJson($this->base64UrlDecode($encodedPayload));
        $signature = $this->base64UrlDecode($encodedSignature);

        if (($decodedHeader['alg'] ?? null) !== self::ALGORITHM) {
            throw new JwtException('Unsupported JWT algorithm.');
        }

        $expectedSignature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->secret, true);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new JwtException('Invalid JWT signature.');
        }

        $now = time();

        if (isset($decodedPayload['nbf']) && is_numeric($decodedPayload['nbf']) && $now < (int) $decodedPayload['nbf']) {
            throw new JwtException('JWT cannot be used before the not-before time.');
        }

        if (isset($decodedPayload['exp']) && is_numeric($decodedPayload['exp']) && $now >= (int) $decodedPayload['exp']) {
            throw new JwtException('JWT has expired.');
        }

        if (isset($decodedPayload['iss']) && $decodedPayload['iss'] !== $this->issuer) {
            throw new JwtException('JWT issuer mismatch.');
        }

        if (isset($decodedPayload['aud']) && $decodedPayload['aud'] !== $this->audience) {
            throw new JwtException('JWT audience mismatch.');
        }

        return $decodedPayload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new JwtException('Unable to decode JWT segment.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new JwtException('Invalid JWT JSON structure.');
        }

        return $decoded;
    }
}
