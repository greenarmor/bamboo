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

$driver = getenv('AUTH_JWT_STORAGE_DRIVER');
if ($driver === false) {
    $driver = $_ENV['AUTH_JWT_STORAGE_DRIVER'] ?? 'json';
}

$driver = is_string($driver) && $driver !== '' ? strtolower($driver) : 'json';
$supportedDrivers = ['json', 'mysql', 'pgsql', 'firebase', 'nosql'];
if (!in_array($driver, $supportedDrivers, true)) {
    $driver = 'json';
}

$allowRegistration = getenv('AUTH_JWT_ALLOW_REGISTRATION');
if ($allowRegistration === false) {
    $allowRegistration = $_ENV['AUTH_JWT_ALLOW_REGISTRATION'] ?? 'true';
}

$mysqlDsn = getenv('AUTH_JWT_MYSQL_DSN');
if ($mysqlDsn === false) {
    $mysqlDsn = $_ENV['AUTH_JWT_MYSQL_DSN'] ?? 'mysql:host=127.0.0.1;dbname=bamboo';
}

$mysqlUsername = getenv('AUTH_JWT_MYSQL_USERNAME');
if ($mysqlUsername === false) {
    $mysqlUsername = $_ENV['AUTH_JWT_MYSQL_USERNAME'] ?? 'root';
}

$mysqlPassword = getenv('AUTH_JWT_MYSQL_PASSWORD');
if ($mysqlPassword === false) {
    $mysqlPassword = $_ENV['AUTH_JWT_MYSQL_PASSWORD'] ?? '';
}

$mysqlTable = getenv('AUTH_JWT_MYSQL_TABLE');
if ($mysqlTable === false) {
    $mysqlTable = $_ENV['AUTH_JWT_MYSQL_TABLE'] ?? 'auth_users';
}

$mysqlSchema = <<<'SQL'
CREATE TABLE `auth_users` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `roles` JSON NOT NULL,
    `email` VARCHAR(255) NULL,
    `meta` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

$pgsqlDsn = getenv('AUTH_JWT_PGSQL_DSN');
if ($pgsqlDsn === false) {
    $pgsqlDsn = $_ENV['AUTH_JWT_PGSQL_DSN'] ?? 'pgsql:host=127.0.0.1;port=5432;dbname=bamboo';
}

$pgsqlUsername = getenv('AUTH_JWT_PGSQL_USERNAME');
if ($pgsqlUsername === false) {
    $pgsqlUsername = $_ENV['AUTH_JWT_PGSQL_USERNAME'] ?? 'postgres';
}

$pgsqlPassword = getenv('AUTH_JWT_PGSQL_PASSWORD');
if ($pgsqlPassword === false) {
    $pgsqlPassword = $_ENV['AUTH_JWT_PGSQL_PASSWORD'] ?? '';
}

$pgsqlTable = getenv('AUTH_JWT_PGSQL_TABLE');
if ($pgsqlTable === false) {
    $pgsqlTable = $_ENV['AUTH_JWT_PGSQL_TABLE'] ?? 'auth_users';
}

$pgsqlSchema = <<<'SQL'
CREATE TABLE auth_users (
    id UUID PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    roles JSONB NOT NULL,
    email VARCHAR(255) NULL,
    meta JSONB NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
SQL;

$firebaseCredentials = getenv('AUTH_JWT_FIREBASE_CREDENTIALS');
if ($firebaseCredentials === false) {
    $firebaseCredentials = $_ENV['AUTH_JWT_FIREBASE_CREDENTIALS'] ?? 'var/firebase/service-account.json';
}

$firebaseDatabaseUrl = getenv('AUTH_JWT_FIREBASE_DATABASE_URL');
if ($firebaseDatabaseUrl === false) {
    $firebaseDatabaseUrl = $_ENV['AUTH_JWT_FIREBASE_DATABASE_URL'] ?? 'https://project-id.firebaseio.com';
}

$firebaseCollection = getenv('AUTH_JWT_FIREBASE_COLLECTION');
if ($firebaseCollection === false) {
    $firebaseCollection = $_ENV['AUTH_JWT_FIREBASE_COLLECTION'] ?? 'auth_users';
}

$firebaseSchema = <<<'JSON'
{
    "id": "string",
    "username": "string",
    "password_hash": "string",
    "roles": ["string"],
    "email": "string",
    "meta": {
        "display_name": "string"
    },
    "created_at": "ISO8601 timestamp"
}
JSON;

$nosqlConnection = getenv('AUTH_JWT_NOSQL_CONNECTION');
if ($nosqlConnection === false) {
    $nosqlConnection = $_ENV['AUTH_JWT_NOSQL_CONNECTION'] ?? 'mongodb://127.0.0.1:27017';
}

$nosqlDatabase = getenv('AUTH_JWT_NOSQL_DATABASE');
if ($nosqlDatabase === false) {
    $nosqlDatabase = $_ENV['AUTH_JWT_NOSQL_DATABASE'] ?? 'bamboo';
}

$nosqlCollection = getenv('AUTH_JWT_NOSQL_COLLECTION');
if ($nosqlCollection === false) {
    $nosqlCollection = $_ENV['AUTH_JWT_NOSQL_COLLECTION'] ?? 'auth_users';
}

$nosqlSchema = <<<'JSON'
{
    "_id": "UUID",
    "username": "string",
    "password_hash": "string",
    "roles": ["string"],
    "email": "string",
    "meta": {
        "display_name": "string"
    },
    "created_at": "ISO8601 timestamp"
}
JSON;

return [
    'jwt' => [
        'secret' => is_string($secret) ? $secret : '',
        'ttl' => is_numeric($ttl) ? (int) $ttl : 3600,
        'issuer' => is_string($issuer) && $issuer !== '' ? $issuer : 'Bamboo',
        'audience' => is_string($audience) && $audience !== '' ? $audience : 'BambooUsers',
        'storage' => [
            'driver' => $driver,
            'path' => is_string($store) && $store !== '' ? $store : 'var/auth/users.json',
            'drivers' => [
                'json' => [
                    'path' => is_string($store) && $store !== '' ? $store : 'var/auth/users.json',
                    'schema' => 'File-based JSON document store seeded by the auth.jwt.setup command.',
                ],
                'mysql' => [
                    'connection' => [
                        'dsn' => is_string($mysqlDsn) && $mysqlDsn !== '' ? $mysqlDsn : 'mysql:host=127.0.0.1;dbname=bamboo',
                        'username' => is_string($mysqlUsername) ? $mysqlUsername : 'root',
                        'password' => is_string($mysqlPassword) ? $mysqlPassword : '',
                    ],
                    'table' => is_string($mysqlTable) && $mysqlTable !== '' ? $mysqlTable : 'auth_users',
                    'schema' => $mysqlSchema,
                ],
                'pgsql' => [
                    'connection' => [
                        'dsn' => is_string($pgsqlDsn) && $pgsqlDsn !== '' ? $pgsqlDsn : 'pgsql:host=127.0.0.1;port=5432;dbname=bamboo',
                        'username' => is_string($pgsqlUsername) ? $pgsqlUsername : 'postgres',
                        'password' => is_string($pgsqlPassword) ? $pgsqlPassword : '',
                    ],
                    'table' => is_string($pgsqlTable) && $pgsqlTable !== '' ? $pgsqlTable : 'auth_users',
                    'schema' => $pgsqlSchema,
                ],
                'firebase' => [
                    'credentials' => is_string($firebaseCredentials) && $firebaseCredentials !== '' ? $firebaseCredentials : 'var/firebase/service-account.json',
                    'database_url' => is_string($firebaseDatabaseUrl) && $firebaseDatabaseUrl !== '' ? $firebaseDatabaseUrl : 'https://project-id.firebaseio.com',
                    'collection' => is_string($firebaseCollection) && $firebaseCollection !== '' ? $firebaseCollection : 'auth_users',
                    'schema' => $firebaseSchema,
                ],
                'nosql' => [
                    'connection' => is_string($nosqlConnection) && $nosqlConnection !== '' ? $nosqlConnection : 'mongodb://127.0.0.1:27017',
                    'database' => is_string($nosqlDatabase) && $nosqlDatabase !== '' ? $nosqlDatabase : 'bamboo',
                    'collection' => is_string($nosqlCollection) && $nosqlCollection !== '' ? $nosqlCollection : 'auth_users',
                    'schema' => $nosqlSchema,
                ],
            ],
        ],
        'registration' => [
            'enabled' => filter_var($allowRegistration, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'default_roles' => [],
        ],
    ],
];
