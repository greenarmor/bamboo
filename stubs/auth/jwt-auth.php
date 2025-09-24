<?php

declare(strict_types=1);

$secret = getenv('AUTH_JWT_SECRET');
if ($secret === false) {
    $secret = $_ENV['AUTH_JWT_SECRET'] ?? '';
}

$ttl = getenv('AUTH_JWT_TTL');
if ($ttl === false) {
    $ttl = $_ENV['AUTH_JWT_TTL'] ?? 3600;
}

$issuer = getenv('AUTH_JWT_ISSUER');
if ($issuer === false) {
    $issuer = $_ENV['AUTH_JWT_ISSUER'] ?? 'Bamboo';
}

$audience = getenv('AUTH_JWT_AUDIENCE');
if ($audience === false) {
    $audience = $_ENV['AUTH_JWT_AUDIENCE'] ?? 'BambooUsers';
}

$store = getenv('AUTH_JWT_USER_STORE');
if ($store === false) {
    $store = $_ENV['AUTH_JWT_USER_STORE'] ?? 'var/auth/users.json';
}

$allowRegistration = getenv('AUTH_JWT_ALLOW_REGISTRATION');
if ($allowRegistration === false) {
    $allowRegistration = $_ENV['AUTH_JWT_ALLOW_REGISTRATION'] ?? 'true';
}

return [
    'jwt' => [
        'secret' => is_string($secret) ? $secret : '',
        'ttl' => is_numeric($ttl) ? (int) $ttl : 3600,
        'issuer' => is_string($issuer) && $issuer !== '' ? $issuer : 'Bamboo',
        'audience' => is_string($audience) && $audience !== '' ? $audience : 'BambooUsers',
        'storage' => [
            'driver' => 'json',
            'path' => is_string($store) && $store !== '' ? $store : 'var/auth/users.json',
        ],
        'registration' => [
            'enabled' => filter_var($allowRegistration, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'default_roles' => [],
        ],
    ],
];
